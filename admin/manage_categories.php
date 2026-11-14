<?php
// Load the application's session, database connection, and filesystem/URL constants.
require_once dirname(__DIR__) . '/php/session.php';
// Stop execution unless the logged-in account has the administrator role (ID 1).
if (empty($_SESSION['user_id']) || (int)($_SESSION['user_role'] ?? 0) !== 1) {
    // Tell the browser that this account is forbidden from viewing this resource.
    http_response_code(403);
    // Display a brief denial and prevent any database operations below.
    exit('Access denied. Administrators only.');
}
// Set the page type for the shared site layout.
$page_name = 'admin';
// Create a unique CSRF token for this session if one does not already exist.
if (empty($_SESSION['manage_categories_csrf'])) {
    // Generate a cryptographically secure random token to protect form submissions.
    $_SESSION['manage_categories_csrf'] = bin2hex(random_bytes(32));
}
// Escape dynamic text before inserting it into HTML to prevent cross-site scripting.
function categoryEscape($value): string {
    // Convert special characters such as <, >, and quotes into safe HTML entities.
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
// Handle category creation, editing, or deletion only when a form is submitted.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read the token included in the submitted form.
    $token = $_POST['csrf_token'] ?? '';
    // Reject missing or incorrect tokens to prevent cross-site request forgery.
    if (!is_string($token) || !hash_equals($_SESSION['manage_categories_csrf'], $token)) {
        // Mark the request as forbidden.
        http_response_code(403);
        // Stop processing the untrusted request.
        exit('Invalid request token.');
    }
    // Keep the current search term when returning to the page after a change.
    $returnSearch = trim((string)($_POST['return_search'] ?? ''));
    // Limit the search term length before including it in the redirect URL.
    $returnSearch = mb_substr($returnSearch, 0, 100, 'UTF-8');
    // Determine which operation the administrator requested.
    $action = $_POST['action'] ?? '';
    // Read the category name and remove extra whitespace at its ends.
    $name = trim((string)($_POST['category_name'] ?? ''));
    // Validate the category ID when editing or deleting an existing row.
    $id = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    // Begin handling errors without exposing database internals to the browser.
    try {
        if (in_array($action, ['add', 'edit'], true) && ($name === '' || mb_strlen($name, 'UTF-8') > 30)) {
            // Explain the validation requirement to the administrator.
            throw new RuntimeException('Category name must contain 1-30 characters.');
        }
        // Require a valid category ID for modifications of existing records.
        if (in_array($action, ['edit', 'delete'], true) && !$id) {
            // Prevent invalid IDs from reaching SQL statements.
            throw new RuntimeException('Invalid category ID.');
        }
        if ($action === 'add') {
            // Prepare SQL with a placeholder so user input is not interpreted as SQL.
            $stmt = $db_connection->prepare('INSERT INTO categories (category_name) VALUES (?)');
            // Supply the category name as a string parameter.
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $stmt->close();
            // Save a success message for the redirected page.
            $_SESSION['manage_categories_notice'] = 'Category added successfully.';
        // Update an existing category when the Edit form was submitted.
        } elseif ($action === 'edit') {
            // Prepare a query that updates only the selected category.
            $stmt = $db_connection->prepare('UPDATE categories SET category_name = ? WHERE category_id = ?');
            // Bind the new name and the validated category ID.
            $stmt->bind_param('si', $name, $id);
            $stmt->execute();
            // Close the statement after execution.
            $stmt->close();
            // Save a confirmation for the next page load.
            $_SESSION['manage_categories_notice'] = 'Category updated successfully.';
        // Delete a category only if no other table references it.
        } elseif ($action === 'delete') {
            // Start a transaction so the reference checks and deletion are coordinated.
            $db_connection->begin_transaction();
            // Lock the selected category row while checking whether it can be deleted.
            $stmt = $db_connection->prepare('SELECT category_id FROM categories WHERE category_id = ? FOR UPDATE');
            // Bind the requested category ID.
            $stmt->bind_param('i', $id);
            $stmt->execute();
            // Fetch the matching category, if present.
            $found = $stmt->get_result()->fetch_assoc();
            // Close the lookup statement.
            $stmt->close();
            // Refuse deletion when the category no longer exists.
            if (!$found) {
                // Raise an error that will roll back the transaction.
                throw new RuntimeException('Category not found.');
            }
            // Enumerate every database foreign key referencing categories.category_id.
            $references = $db_connection->query("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'categories' AND REFERENCED_COLUMN_NAME = 'category_id'");
            // Inspect each referencing table so related financial data is never deleted accidentally.
            while ($reference = $references->fetch_assoc()) {
                // Quote identifier backticks safely; metadata supplies these names, not form input.
                $table = str_replace('`', '``', $reference['TABLE_NAME']);
                // Quote the foreign-key column name safely for the same reason.
                $column = str_replace('`', '``', $reference['COLUMN_NAME']);
                // Check whether this category is used by any row in the referencing table.
                $check = $db_connection->prepare("SELECT 1 FROM `$table` WHERE `$column` = ? LIMIT 1");
                // Bind the category ID to the check query.
                $check->bind_param('i', $id);
                // Execute the reference check.
                $check->execute();
                // Retrieve a single matching row, if one exists.
                $inUse = $check->get_result()->fetch_assoc();
                // Release the check statement.
                $check->close();
                // Protect expenses, budgets, and other records that depend on this category.
                if ($inUse) {
                    // Explain why the administrator must reassign dependent records first.
                    throw new RuntimeException('Cannot delete this category because it is used in ' . $reference['TABLE_NAME'] . '. Reassign those records first.');
                }
            }
            // Free the metadata query's result resources.
            $references->free();
            // Prepare deletion of only the selected unused category.
            $stmt = $db_connection->prepare('DELETE FROM categories WHERE category_id = ?');
            // Bind the category ID safely.
            $stmt->bind_param('i', $id);
            // Execute the deletion; foreign keys still protect against concurrent references.
            $stmt->execute();
            // Close the deletion statement.
            $stmt->close();
            // Make the transaction permanent.
            $db_connection->commit();
            // Save the deletion success message.
            $_SESSION['manage_categories_notice'] = 'Category deleted successfully.';
        // Reject unknown action values instead of silently processing them.
        } else {
            // Report an invalid form action.
            throw new RuntimeException('Invalid category action.');
        }
    // Catch validation errors and SQL failures.
    } catch (Throwable $error) {
        // Roll back any open transaction so a failed deletion cannot partially succeed.
        try { $db_connection->rollback(); } catch (Throwable $ignored) { /* No active transaction may exist. */ }
        // Log the full error on the server for troubleshooting.
        error_log('Manage Categories error: ' . $error->getMessage());
        // Show a readable validation message, or a generic message for SQL errors.
        $_SESSION['manage_categories_notice'] = $error instanceof RuntimeException ? $error->getMessage() : 'Database operation failed. Check server logs.';
    }
    // Redirect after POST so refreshing the page does not repeat a database operation.
    header('Location: ' . BASE_URL . '/admin/manage_categories.php' . ($returnSearch !== '' ? '?search=' . rawurlencode($returnSearch) : ''));
    // Stop executing after the redirect.
    exit;
}
// Read the optional search term from the URL; GET searches never change database records.
$search = trim((string)($_GET['search'] ?? ''));
// Limit search input length to avoid excessively large queries.
$search = mb_substr($search, 0, 100, 'UTF-8');
// If a search term was provided, filter the category list by category name or ID.
if ($search !== '') {
    // Use a prepared statement to prevent SQL injection through the search field.
    $stmt = $db_connection->prepare('SELECT category_id, category_name FROM categories WHERE category_name LIKE ? OR CAST(category_id AS CHAR) LIKE ? ORDER BY category_name, category_id');
    // Add SQL LIKE wildcards to match the search text anywhere in the name or ID.
    $pattern = '%' . $search . '%';
    // Bind the search pattern as strings for both comparison conditions.
    $stmt->bind_param('ss', $pattern, $pattern);
    // Run the filtered category lookup.
    $stmt->execute();
    // Fetch all matching categories into an array for the HTML table.
    $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    // Release the prepared statement after reading the results.
    $stmt->close();
} else {
    // Without a search term, display every category in alphabetical order.
    $categories = $db_connection->query('SELECT category_id, category_name FROM categories ORDER BY category_name, category_id')->fetch_all(MYSQLI_ASSOC);
}
// Retrieve a one-time success or error message from the session.
$notice = $_SESSION['manage_categories_notice'] ?? null;
// Remove the message so it appears only once.
unset($_SESSION['manage_categories_notice']);
?>
<!doctype html>
<html lang="en">
<head>
    <!-- Load the application's existing Bootstrap styles and head metadata. -->
    <?php include_once ROOT_PATH . '/include/head.php'; ?>
    <title>Manage Categories</title>
</head>
<!-- Apply the application's administrator page class. -->
<body class="<?= categoryEscape($page_name) ?>">
    <!-- Render the shared header, including the admin-only navigation link. -->
    <?php include_once ROOT_PATH . '/include/header.php'; ?>
    <main class="container mb-5">
        <h1 class="mb-3">Manage Categories</h1>
        <p class="text-muted">Add, rename, or remove categories. Categories in use cannot be deleted until their related records are reassigned.</p>
        <?php if ($notice): ?>
            <!-- Escape the message before displaying it in the alert. -->
            <div class="alert alert-info" role="alert"><?= categoryEscape($notice) ?></div>
        <?php endif; ?>
        <section class="card mb-4">
            <div class="card-body">
                <h2 class="h5">Add Category</h2>
                <form method="post" class="row g-2 align-items-end">
                    <!-- Include the CSRF token to authenticate this form submission. -->
                    <input type="hidden" name="csrf_token" value="<?= categoryEscape($_SESSION['manage_categories_csrf']) ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="col-md-8">
                        <label class="form-label" for="newCategory">Category name</label>
                        <input class="form-control" type="text" id="newCategory" name="category_name" maxlength="30" required>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary" type="submit">Add Category</button>
                    </div>
                </form>
            </div>
        </section>
        <section class="card mb-4">
            <div class="card-body">
                <h2 class="h5">Search Categories</h2>
                <!-- Use GET so searches appear in the URL and can be bookmarked. -->
                <form method="get" action="<?= categoryEscape(BASE_URL) ?>/admin/manage_categories.php" class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label" for="categorySearch">Category name or ID</label>
                        <!-- Retain the current search text after submitting the form. -->
                        <input class="form-control" id="categorySearch" type="search" name="search" maxlength="100" placeholder="Search categories..." value="<?= categoryEscape($search) ?>">
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Search</button>
                        <a class="btn btn-outline-secondary" href="<?= categoryEscape(BASE_URL) ?>/admin/manage_categories.php">Clear</a>
                    </div>
                </form>
            </div>
        </section>
        <!-- Show existing categories with editing and deletion controls. -->
        <section>
            <h2 class="h5 mb-3"><?= $search !== '' ? 'Search Results' : 'Existing Categories' ?></h2>
            <!-- Show the number of matching categories when filtering the list. -->
            <?php if ($search !== ''): ?>
                <p class="text-muted"><?= count($categories) ?> categor<?= count($categories) === 1 ? 'y' : 'ies' ?> found for "<?= categoryEscape($search) ?>".</p>
            <?php endif; ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead><tr><th scope="col">ID</th><th scope="col">Category name / Edit</th><th scope="col">Delete</th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?= (int)$category['category_id'] ?></td>
                            <td>
                                <form method="post" class="d-flex flex-wrap gap-2">
                                    <!-- Protect this form from forged submissions. -->
                                    <input type="hidden" name="csrf_token" value="<?= categoryEscape($_SESSION['manage_categories_csrf']) ?>">
                                    <!-- Preserve the active search after saving an edit. -->
                                    <input type="hidden" name="return_search" value="<?= categoryEscape($search) ?>">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="category_id" value="<?= (int)$category['category_id'] ?>">
                                    <input class="form-control w-auto flex-grow-1" aria-label="Category name" type="text" name="category_name" value="<?= categoryEscape($category['category_name']) ?>" maxlength="30" required>
                                    <button class="btn btn-outline-primary" type="submit">Save</button>
                                </form>
                            </td>
                            <td>
                                <form method="post" onsubmit="return confirm('Delete this category? This cannot be undone.');">
                                    <!-- Protect the delete action with the same session CSRF token. -->
                                    <input type="hidden" name="csrf_token" value="<?= categoryEscape($_SESSION['manage_categories_csrf']) ?>">
                                    <!-- Preserve the active search after deleting a category. -->
                                    <input type="hidden" name="return_search" value="<?= categoryEscape($search) ?>">
                                    <!-- Indicate that the request is a deletion. -->
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="category_id" value="<?= (int)$category['category_id'] ?>">
                                    <button class="btn btn-outline-danger" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <!-- Display an empty-state message when no categories exist. -->
                    <?php if (!$categories): ?>
                        <!-- Explain whether the empty table is caused by a search or no stored categories. -->
                        <tr><td colspan="3"><?= $search !== '' ? 'No categories match your search.' : 'No categories found.' ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <?php include_once ROOT_PATH . '/include/footer.php'; ?>
</body>
</html>
