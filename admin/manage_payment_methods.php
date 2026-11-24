<?php
// Load the existing login session and MySQL database connection.
require_once dirname(__DIR__) . '/php/session.php';
// Reject visitors and non-admin accounts before displaying or changing any data.
if (empty($_SESSION['user_id']) || (int)($_SESSION['user_role'] ?? 0) !== 1) {
    // Return the HTTP status code for forbidden access.
    http_response_code(403);
    // Stop the script so no payment methods are exposed.
    exit('Access denied. Administrators only.');
}
// Identify this page as an administrator page for the shared layout.
$page_name = 'admin';
// Generate a unique security token once per login session.
if (empty($_SESSION['payment_methods_csrf'])) {
    // Use secure random bytes to make forged form submissions difficult.
    $_SESSION['payment_methods_csrf'] = bin2hex(random_bytes(32));
}
// Define a helper to safely display database values inside HTML.
function paymentEscape($value): string {
    // Convert HTML-special characters to entities to prevent XSS.
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
// Handle additions and edits only when the administrator submits a form.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve the token submitted with the form.
    $submittedToken = $_POST['csrf_token'] ?? '';
    // Compare tokens securely and reject missing or invalid tokens.
    if (!is_string($submittedToken) || !hash_equals($_SESSION['payment_methods_csrf'], $submittedToken)) {
        // Signal that the submitted request is forbidden.
        http_response_code(403);
        // End processing before any database modification.
        exit('Invalid request token.');
    }
    // Read which form operation was requested.
    $action = $_POST['action'] ?? '';
    // Trim whitespace from the submitted payment method name.
    $name = trim((string)($_POST['method_name'] ?? ''));
    // Validate the ID supplied by an edit form as a positive integer.
    $id = filter_var($_POST['payment_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    // Catch validation and database errors without showing SQL details to visitors.
    try {
        // Enforce the length of the method_name VARCHAR(50) database column.
        if ($name === '' || mb_strlen($name, 'UTF-8') > 50) {
            // Reject empty names and names that would exceed the column limit.
            throw new RuntimeException('Payment method name must be between 1 and 50 characters.');
        }
        // Create a new payment method when the Add form was submitted.
        if ($action === 'add') {
            // Prepare an INSERT statement using a parameter instead of SQL concatenation.
            $stmt = $db_connection->prepare('INSERT INTO payment_method (method_name) VALUES (?)');
            // Bind the new payment method name as a string.
            $stmt->bind_param('s', $name);
            // Insert the new payment method into MySQL.
            $stmt->execute();
            // Release the prepared statement.
            $stmt->close();
            // Store a success message to display after redirecting.
            $_SESSION['payment_methods_notice'] = 'Payment method added successfully.';
        // Update a method only when the Edit form was submitted.
        } elseif ($action === 'edit') {
            // Ensure an existing payment method ID was provided.
            if (!$id) {
                // Reject malformed or missing IDs.
                throw new RuntimeException('Invalid payment method ID.');
            }
            // Prepare an UPDATE statement targeting only the requested method ID.
            $stmt = $db_connection->prepare('UPDATE payment_method SET method_name = ? WHERE payment_id = ?');
            // Bind the new name and selected numeric ID to the query.
            $stmt->bind_param('si', $name, $id);
            // Apply the edit without changing the method's ID or expense references.
            $stmt->execute();
            // Check whether the ID still exists (an unchanged name may affect zero rows).
            $stmt->close();
            // Store a confirmation for the administrator.
            $_SESSION['payment_methods_notice'] = 'Payment method saved.';
        // Reject any operation that this page does not support.
        } else {
            // Do not allow an unexpected action to run arbitrary SQL.
            throw new RuntimeException('Unknown payment method action.');
        }
    // Handle invalid input and database errors in one place.
    } catch (Throwable $error) {
        // Show a helpful validation message or a generic database error.
        $_SESSION['payment_methods_error'] = $error instanceof RuntimeException ? $error->getMessage() : 'Unable to save payment method. Check your database configuration.';
    }
    // Use Post/Redirect/Get to avoid duplicate inserts when the page is refreshed.
    header('Location: ' . BASE_URL . '/admin/manage_payment_methods.php');
    // Stop executing the POST request after sending the redirect.
    exit;
}
// Read any success message saved by the previous request.
$notice = $_SESSION['payment_methods_notice'] ?? '';
// Read any error message saved by the previous request.
$error = $_SESSION['payment_methods_error'] ?? '';
// Remove the success message so it appears only once.
unset($_SESSION['payment_methods_notice']);
// Remove the error message so it appears only once.
unset($_SESSION['payment_methods_error']);
// Start with an empty list for the payment methods table.
$methods = [];
// Retrieve payment methods sorted alphabetically for easy viewing.
try {
    // Query the two columns used on this page.
    $result = $db_connection->query('SELECT payment_id, method_name FROM payment_method ORDER BY method_name, payment_id');
    // Read each returned database row into the methods array.
    while ($row = $result->fetch_assoc()) {
        // Add the current row to the list displayed below.
        $methods[] = $row;
    }
    // Release the query result's resources.
    $result->free();
// Avoid exposing internal database errors to the browser.
} catch (Throwable $exception) {
    // Display a generic error if the table cannot be loaded.
    $error = 'Unable to load payment methods. Confirm that the payment_method table exists.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include_once ROOT_PATH . '/include/head.php'; ?>
</head>
<body class="<?= paymentEscape($page_name) ?>">
    <?php include_once ROOT_PATH . '/include/header.php'; ?>
    <main class="container mb-5" style="max-width: 1000px;">
        <h1 class="mb-3">Manage Payment Methods</h1>
        <p class="text-muted">Add, edit, and view payment methods used by the expense tracker.</p>
        <!-- Show the success notification only if one exists. -->
        <?php if ($notice !== ''): ?>
            <!-- Escape the message before rendering it. -->
            <div class="alert alert-success" role="status"><?= paymentEscape($notice) ?></div>
        <?php endif; ?>
        <!-- Show the error notification only if one exists. -->
        <?php if ($error !== ''): ?>
            <!-- Escape the error to prevent HTML injection. -->
            <div class="alert alert-danger" role="alert"><?= paymentEscape($error) ?></div>
        <?php endif; ?>
        <section class="card mb-4">
            <div class="card-body">
                <h2 class="h5">Add Payment Method</h2>
                <form method="post" class="row g-3 align-items-end">
                    <!-- Attach the session security token to the request. -->
                    <input type="hidden" name="csrf_token" value="<?= paymentEscape($_SESSION['payment_methods_csrf']) ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="col-md-8">
                        <label for="new_method_name" class="form-label">Payment method name</label>
                        <input id="new_method_name" name="method_name" type="text" class="form-control" maxlength="50" placeholder="e.g., Credit Card" required>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary" type="submit">Add Payment Method</button>
                    </div>
                </form>
            </div>
        </section>
        <section>
            <!-- Show the total number of payment methods retrieved. -->
            <h2 class="h5 mb-3">Existing Payment Methods (<?= count($methods) ?>)</h2>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead><tr><th scope="col">ID</th><th scope="col">Payment Method Name / Edit</th></tr></thead>
                    <tbody>
                        <?php foreach ($methods as $method): ?>
                            <tr>
                                <td><?= (int)$method['payment_id'] ?></td>
                                <td>
                                    <!-- Submit this method's edit with POST. -->
                                    <form method="post" class="d-flex flex-wrap gap-2">
                                        <!-- Prevent forged edits using the CSRF token. -->
                                        <input type="hidden" name="csrf_token" value="<?= paymentEscape($_SESSION['payment_methods_csrf']) ?>">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="payment_id" value="<?= (int)$method['payment_id'] ?>">
                                        <input type="text" name="method_name" class="form-control w-auto flex-grow-1" aria-label="Payment method name" value="<?= paymentEscape($method['method_name']) ?>" maxlength="50" required>
                                        <button class="btn btn-outline-primary" type="submit">Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$methods): ?>
                            <!-- Span both columns with the empty-state message. -->
                            <tr><td colspan="2">No payment methods found. Add one above.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <?php include_once ROOT_PATH . '/include/footer.php'; ?>
</body>
</html>
