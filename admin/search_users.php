<?php
/** Admin-only user search and account deletion. */
// Load session setup, database connection, and application constants from the parent php folder.
require_once dirname(__DIR__) . '/php/session.php';
// Load URL and filesystem path helpers used throughout the application.
require_once ROOT_PATH . '/php/path.php';

// Enforce authorization on the server, not just in the navigation menu.
// Reject anyone who is not logged in or whose session role is not administrator (role ID 1).
if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_role'] !== 1) {
    // Return HTTP 403 Forbidden to the browser.
    http_response_code(403);
    // Stop executing the page so unauthorized visitors cannot see user data.
    exit('Access denied. Administrators only.');
}

// Identify this as an admin page for the shared page layout.
$page_name = 'admin';
// Create a per-session anti-CSRF token if this session does not already have one.
if (empty($_SESSION['search_users_csrf'])) {
    // Generate 32 cryptographically secure random bytes and encode them as hexadecimal.
    $_SESSION['search_users_csrf'] = bin2hex(random_bytes(32));
}

// Deleting a user is a POST action protected by a CSRF token.
// Process account deletion only for HTTP POST requests, never ordinary page visits.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read the submitted anti-CSRF token, default to an empty string if missing.
    $token = $_POST['csrf_token'] ?? '';
    // Ensure the token is text and compare it securely against the session token.
    if (!is_string($token) || !hash_equals($_SESSION['search_users_csrf'], $token)) {
        // Return HTTP 403 Forbidden to the browser.
        http_response_code(403);
        // Abort the delete request if its CSRF token is invalid.
        exit('Invalid request token.');
    }

    // Validate the submitted account ID as a positive integer.
    $targetId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    // Handle a missing or invalid user ID without running a database delete.
    if (!$targetId) {
        // Store an error message to display after redirecting.
        $_SESSION['search_users_notice'] = 'Invalid user ID.';
    // Prevent the logged-in administrator from deleting their own account.
    } elseif ((int)$targetId === (int)$_SESSION['user_id']) {
        // Tell the administrator why self-deletion was blocked.
        $_SESSION['search_users_notice'] = 'You cannot delete your own administrator account.';
    } else {
        try {
            // Start a transaction so all database deletions succeed together or none are saved.
            $db_connection->begin_transaction();
            // Lock and read the target user record, including its contact and role IDs.
            $lookup = $db_connection->prepare('SELECT contact_id, role_id FROM Users WHERE user_id = ? FOR UPDATE');
            // Bind the validated integer user ID into the prepared query.
            $lookup->bind_param('i', $targetId);
            // Run the user lookup against the database.
            $lookup->execute();
            // Store the selected contact ID and role ID in PHP variables.
            $lookup->bind_result($contactId, $targetRole);
            // Fetch the matching user, if one exists.
            $found = $lookup->fetch();
            // Release the prepared lookup statement.
            $lookup->close();
            // Reject deletion when the requested account does not exist.
            if (!$found) {
                // Raise an error that will roll back the transaction.
                throw new RuntimeException('User not found.');
            }
            // Preserve administrator accounts. Only standard users/guests can be removed here.
            // Block deletion of any administrator account, not only the current administrator.
            if ((int)$targetRole === 1) {
                // Stop the transaction with a specific administrator-protection message.
                throw new RuntimeException('Administrator accounts cannot be deleted from this page.');
            }

            // Only delete from tables that actually exist in this installation.
            // List the known tables that may contain records owned by the user.
            $dependentTables = ['expenses', 'budget', 'recurring_expense', 'savings_goals', 'Credentials'];
            // Check each allowed dependent table before trying to delete its records.
            foreach ($dependentTables as $table) {
                // Ask the database metadata whether the current dependent table exists.
                $tableCheck = $db_connection->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
                // Bind the table name safely as a string.
                $tableCheck->bind_param('s', $table);
                // Execute the table-existence check.
                $tableCheck->execute();
                // Read the number of matching tables into $exists.
                $tableCheck->bind_result($exists);
                // Fetch the existence-check result.
                $tableCheck->fetch();
                // Close the metadata query.
                $tableCheck->close();
                // Only delete related rows when the dependent table exists.
                if ((int)$exists > 0) {
                    // Table names come exclusively from the hard-coded allowlist above.
                    // Prepare deletion of records belonging to this user from an allowlisted table.
                    $deleteRelated = $db_connection->prepare("DELETE FROM `$table` WHERE user_id = ?");
                    // Bind the target user ID so the query cannot delete another user’s records.
                    $deleteRelated->bind_param('i', $targetId);
                    // Remove the target user’s rows from this dependent table.
                    $deleteRelated->execute();
                    // Close this related-record deletion statement.
                    $deleteRelated->close();
                }
            }

            // Prepare deletion of the user’s main account record.
            $deleteUser = $db_connection->prepare('DELETE FROM Users WHERE user_id = ?');
            // Bind the ID of the account being removed.
            $deleteUser->bind_param('i', $targetId);
            // Delete the target row from Users.
            $deleteUser->execute();
            // Close the user-deletion statement.
            $deleteUser->close();

            // A contact belongs to the deleted user only if no other account uses it.
            // Prepare contact deletion only if no remaining user references that contact.
            $deleteContact = $db_connection->prepare('DELETE FROM Contacts WHERE contact_id = ? AND NOT EXISTS (SELECT 1 FROM Users WHERE contact_id = ?)');
            // Bind the same contact ID to both placeholders in the query.
            $deleteContact->bind_param('ii', $contactId, $contactId);
            // Remove the now-unreferenced contact information, if eligible.
            $deleteContact->execute();
            // Close the contact-deletion statement.
            $deleteContact->close();

            // Commit all changes permanently after every statement succeeds.
            $db_connection->commit();
            // Store a success message for the next page load.
            $_SESSION['search_users_notice'] = 'User and associated records deleted successfully.';
        // Catch database or application errors raised during deletion.
        } catch (Throwable $e) {
            // Undo the entire transaction so partial deletions are not saved.
            $db_connection->rollback();
            // Record technical error details in the server log for troubleshooting.
            error_log('Search Users delete failed: ' . $e->getMessage());
            // Show a safe error message to the administrator without exposing raw SQL errors.
            $_SESSION['search_users_notice'] = $e instanceof RuntimeException ? $e->getMessage() : 'Could not delete user. No changes were saved. Check server logs.';
        }
    }
    // Redirect back to the Search Users page after handling the POST request.
    header('Location: ' . BASE_URL . '/admin/search_users.php');
    exit;
}

// Read and trim the optional search term from the URL query string.
$search = trim((string)($_GET['search'] ?? ''));
// Limit the search text to 150 bytes to keep queries manageable.
$search = substr($search, 0, 150);
// Use a filtered query when the administrator entered search text.
if ($search !== '') {
    // Wrap the search term in SQL LIKE wildcards to match partial text.
    $like = '%' . $search . '%';
    // Prepare a query joining users, contacts, credentials, and roles; limit output to 200 accounts.
    $stmt = $db_connection->prepare('SELECT u.user_id, c.first_name, c.last_name, c.email, cr.username, r.role_type, u.role_id FROM Users u JOIN Contacts c ON u.contact_id = c.contact_id JOIN Credentials cr ON u.user_id = cr.user_id JOIN Roles r ON u.role_id = r.role_id WHERE c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR cr.username LIKE ? ORDER BY u.user_id ASC LIMIT 200');
    // Bind the same search pattern to first name, last name, email, and username.
    $stmt->bind_param('ssss', $like, $like, $like, $like);
} else {
    // Prepare a query joining users, contacts, credentials, and roles; limit output to 200 accounts.
    $stmt = $db_connection->prepare('SELECT u.user_id, c.first_name, c.last_name, c.email, cr.username, r.role_type, u.role_id FROM Users u JOIN Contacts c ON u.contact_id = c.contact_id JOIN Credentials cr ON u.user_id = cr.user_id JOIN Roles r ON u.role_id = r.role_id ORDER BY u.user_id ASC LIMIT 200');
}
// Execute the prepared user-list query.
$stmt->execute();
// Fetch all matching rows as an array keyed by column names.
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
// Close the user-list statement.
$stmt->close();
// Retrieve any success or error notice saved during the previous request.
$notice = $_SESSION['search_users_notice'] ?? null;
// Clear the one-time notice so it does not reappear after refresh.
unset($_SESSION['search_users_notice']);
?>
<!doctype html>
<html lang="en">
<head>
<?php include ROOT_PATH . '/include/head.php'; ?>
<title>Search Users - Expense Tracker</title>
</head>
<body class="<?php echo htmlspecialchars($page_name); ?>">
<?php include ROOT_PATH . '/include/header.php'; ?>
<main class="container pb-5">
  <h1 class="mb-3">Search Users</h1>
  <p class="text-muted">Search accounts and manage users. Deletion permanently removes the user's related financial records.</p>
  <!-- Only show the notice area when a message exists. -->
  <?php if ($notice): ?>
    <!-- Display the notice with HTML escaping to prevent injected markup. -->
    <div class="alert alert-info" role="alert"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>
  <form method="get" class="row g-2 mb-4" action="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/admin/search_users.php">
    <div class="col-md-8"><label for="search" class="form-label">Name, email, or username</label><input class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" maxlength="150" placeholder="Search users..."></div>
    <div class="col-md-4 d-flex align-items-end gap-2"><button type="submit" class="btn btn-primary">Search</button><a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>/admin/search_users.php">Clear</a></div>
  </form>
  <div class="table-responsive">
    <!-- Start the striped, hoverable Bootstrap results table. -->
    <table class="table table-striped table-hover align-middle">
      <!-- Define column headers for the results. -->
      <thead><tr><th>ID</th><th>First Name</th><th>Last Name</th><th>Email</th><th>Username</th><th>Role</th><th>Action</th></tr></thead>
      <tbody>
      <!-- Repeat the following table row for every matching user. -->
      <?php foreach ($users as $user): ?>
        <!-- Start a user row. -->
        <tr>
          <td><?php echo (int)$user['user_id']; ?></td>
          <!-- Loop through the five user details shown as text columns. -->
          <?php foreach (['first_name', 'last_name', 'email', 'username', 'role_type'] as $field): ?>
          <!-- Escape user-provided text to prevent cross-site scripting. -->
          <td><?php echo htmlspecialchars((string)$user[$field], ENT_QUOTES, 'UTF-8'); ?></td>
          <?php endforeach; ?>
          <!-- Start the Actions column. -->
          <td>
            <!-- Show a protected label for administrators instead of a Delete button. -->
            <?php if ((int)$user['role_id'] === 1): ?>
              <!-- Indicate that this administrator account cannot be deleted here. -->
              <span class="text-muted">Protected admin</span>
            <!-- For non-admin accounts, display a deletion form. -->
            <?php else: ?>
              <!-- Submit deletion by POST and ask for browser confirmation first. -->
              <form method="post" onsubmit="return confirm('Permanently delete this user and all associated expenses, budgets, and other financial records? This cannot be undone.');">
                <!-- Send the session CSRF token with the deletion request. -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['search_users_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                <!-- Send the ID of the specific account to delete. -->
                <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">
                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?><tr><td colspan="7" class="text-center py-4">No users found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if (count($users) === 200): ?><p class="text-muted">Showing the first 200 matching users. Refine your search to narrow the results.</p><?php endif; ?>
</main>
<?php include ROOT_PATH . '/include/footer.php'; ?>
</body>
</html>
