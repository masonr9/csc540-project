<?php
require_once __DIR__ . '/php/path.php'; // load project dependencies
require_once __DIR__ . '/php/config.php'; 
// start a PHP session only when one has not already been started.
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
// reject visitors without a user ID or without one of the allowed roles (1=admin, 2=user).
if (empty($_SESSION['user_id']) || !in_array((int)($_SESSION['user_role'] ?? 0), [1,2], true)) {
    // send unauthenticated or unauthorized visitors back to the login page.
    header('Location: ' . BASE_URL . '/index.php'); exit;
}
// convert the logged-in user's session ID to an integer for database queries.
$userId = (int) $_SESSION['user_id'];
// choose the Cancel destination based on the role: admin dashboard or user dashboard.
$dashboard = (int)$_SESSION['user_role'] === 1 ? '/admin/' : '/user/';
// start with an empty list of validation errors.
$errors = [];
// start with no success message because nothing has been saved yet.
$success = '';
// prepare a query to read this user's username, stored password hash, and email.
// users links to Credentials by user_id and to Contacts by contact_id.
// the ? placeholder will be bound to $userId instead of concatenated into SQL.
$stmt = $db_connection->prepare(
    'SELECT cr.username, cr.password_salted, c.email 
    FROM Users u 
    JOIN Credentials cr ON cr.user_id=u.user_id 
    JOIN Contacts c ON c.contact_id=u.contact_id 
    WHERE u.user_id=?'
);
// bind the user ID as an integer (i) to the query's single ? placeholder.
$stmt->bind_param('i', $userId); 
$stmt->execute();
// fetch the matching row as an associative array with username, password_salted, and email keys.
$account = $stmt->get_result()->fetch_assoc(); 
// release the prepared statement after its result.
$stmt->close();
// stop if the logged-in session points to an account that cannot be found in the database.
if (!$account) { 
    // mark the HTTP response as forbidden.
    http_response_code(403); 
    exit('Account not found.'); 
}
// generate a random one-time-per-session form token if this session does not have one.
if (empty($_SESSION['credentials_csrf']))
    // random_bytes(32) makes 32 unpredictable bytes; bin2hex turns them into a printable string. 
    $_SESSION['credentials_csrf'] = bin2hex(random_bytes(32));
// run the update logic only after the user submits the form using HTTP POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // read the submitted username, default to empty, and remove whitespace from its ends.
    $username = trim($_POST['username'] ?? '');
    // read and trim the submitted email address.
    $email = trim($_POST['email'] ?? '');
    // read the current password exactly as typed; do not trim passwords.
    $current = $_POST['current_password'] ?? '';
    // read the optional replacement password; an empty value means keep the existing password.
    $new = $_POST['new_password'] ?? '';
    // read the repeated new password so the two entries can be compared.
    $confirm = $_POST['confirm_password'] ?? '';
    // compare the hidden form token with the session token to reject forged form submissions.
    if (!hash_equals($_SESSION['credentials_csrf'], $_POST['csrf_token'] ?? '')) 
        $errors[] = 'Invalid form token. Reload and try again.';
    // require a nonempty username and limit it to 255 bytes, matching the original rule.
    if ($username === '' || strlen($username) > 255) 
        $errors[] = 'Username must be between 1 and 255 characters.';
    // ensure the email has valid syntax and fits within 255 bytes.
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) 
        $errors[] = 'Enter a valid email address.';
    // require the current password and check it against either supported storage format.
    // password_verify checks modern password_hash values; crypt supports legacy passwords.
    if ($current === '' || !(
        password_verify($current, $account['password_salted']) || 
        hash_equals($account['password_salted'], crypt($current, SALT))
    )) 
        $errors[] = 'Current password is incorrect.';
    // If a replacement password was entered, require at least six characters/bytes.
    if ($new !== '' && strlen($new) < 6) 
        $errors[] = 'New password must be at least 6 characters.';
    // make sure the confirmation matches the replacement password exactly.
    if ($new !== $confirm) 
        $errors[] = 'New passwords do not match.';
    // only check for duplicate usernames when the user is actually changing their username.
    if ($username !== $account['username']) {
        // find any other account with this username; <> excludes the logged-in user.
        $check = $db_connection->prepare('SELECT user_id FROM Credentials WHERE username=? AND user_id<>?');
        // bind the proposed username as a string (s) and current user ID as an integer (i).
        $check->bind_param('si', $username, $userId); 
        // run the duplicate-username query.
        $check->execute();
        // a nonzero row count means another account already uses the requested username.
        if ($check->get_result()->num_rows) 
            $errors[] = 'Username already exists.';
        // close the duplicate-check statement.
        $check->close();
    }
    // write to the database only when all validation checks passed.
    if (!$errors) {
        try {
            // start a transaction so username/password and email updates succeed together.
            $db_connection->begin_transaction();
            // hash the new password if provided; otherwise reuse the existing stored hash.
            $hashed = $new !== '' ? password_hash($new, PASSWORD_DEFAULT) : $account['password_salted'];
            // prepare an update of the username and password hash for this account's user ID.
            $update = $db_connection->prepare('UPDATE Credentials SET username=?, password_salted=? WHERE user_id=?');
            // bind username, password hash, and user ID (string, string, integer).
            $update->bind_param('ssi', $username, $hashed, $userId); 
            // apply the update to credentials.
            $update->execute(); 
            // close the prepared UPDATE statement.
            $update->close();
            // prepare an email update in Contacts, finding the correct contact through Users.
            $update = $db_connection->prepare('UPDATE Contacts c JOIN Users u ON u.contact_id=c.contact_id SET c.email=? WHERE u.user_id=?');
            // bind the new email as a string and the logged-in user ID as an integer.
            $update->bind_param('si', $email, $userId); 
            // apply the email update to Contacts.
            $update->execute(); 
            // close the second UPDATE statement.
            $update->close();
            // make both changes permanent now that neither UPDATE failed.
            $db_connection->commit();
            // update session values so the new username appears without requiring a new login.
            $_SESSION['login_user'] = $username;
            $_SESSION['user_name'] = $username;
            // replace the form token so the old token cannot be reused after a successful save.
            $_SESSION['credentials_csrf'] = bin2hex(random_bytes(32));
            // refresh the account values shown in the form and used for password checks.
            $account['username'] = $username; 
            $account['email'] = $email; 
            $account['password_salted'] = $hashed;
            // set the confirmation message that will appear above the form.
            $success = 'Your credentials have been updated successfully.';
        } catch (mysqli_sql_exception $e) {
            // undo all updates from this transaction if MySQL reports an error.
            $db_connection->rollback();
            // write technical details to the server log instead of exposing them to visitors.
            error_log('Credentials update failed: ' . $e->getMessage());            
            // show the visitor a general failure message.
            $errors[] = 'Unable to update credentials. Please try again.';
        }
    }
}
// escape special HTML characters before displaying any variable in the page.
// this prevents values such as <script> from being interpreted as HTML or JavaScript.
function esc($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
    <?php include __DIR__ . '/include/head.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/include/header.php'; ?>
    <main class="container" style="max-width:700px">
        <h1 class="mb-3">Change Credentials</h1>
        <p class="text-muted">Update your username, email, or password. Your current password is required to save.</p>
        <?php if ($success): ?>
            <div class="alert alert-success" role="status"><?= esc($success) ?></div>
        <?php endif; ?>
        <!-- only will show the error list if at least one validation or database error occurred. -->
        <?php if ($errors): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    <!-- render each collected error as its own list item. -->
                    <?php foreach ($errors as $error): ?>
                        <li><?= esc($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="post" autocomplete="on">
            <!-- send the session's CSRF token invisibly so PHP can validate the submission. -->
            <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['credentials_csrf']) ?>">
            <!-- show the saved username and require a replacement value of at most 255 characters. -->
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <input class="form-control" id="username" name="username" maxlength="255" required value="<?= esc($account['username']) ?>" autocomplete="username">
            </div>
            <!-- show the saved email and let the browser check basic email syntax. -->
            <div class="mb-3">
                <label class="form-label" for="email">Email address</label>
                <input class="form-control" type="email" id="email" name="email" maxlength="255" required value="<?= esc($account['email']) ?>" autocomplete="email">
            </div>
            <!-- separate identity fields from password fields visually. -->
            <hr>
            <div class="mb-3">
                <label class="form-label" for="new_password">New password (optional)</label>
                <input class="form-control" type="password" id="new_password" name="new_password" minlength="6" autocomplete="new-password">
                <div class="form-text">Leave blank to keep your password. At least 6 characters if changing.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="confirm_password">Confirm new password</label>
                <input class="form-control" type="password" id="confirm_password" name="confirm_password" autocomplete="new-password">
            </div>
            <hr>
            <div class="mb-3">
                <label class="form-label" for="current_password">Current password (required)</label>
                <input class="form-control" type="password" id="current_password" name="current_password" required autocomplete="current-password">
            </div>
            <button class="btn btn-primary" type="submit">Save Changes</button>
            <!-- return to the correct dashboard without submitting the form. -->
            <a class="btn btn-outline-secondary" href="<?= esc(BASE_URL . $dashboard) ?>">Cancel</a>
        </form>
    </main>
    <?php include __DIR__ . '/include/footer.php'; ?>
</body>
</html>