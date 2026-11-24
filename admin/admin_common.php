<?php
require_once dirname(__DIR__) . '/php/session.php';

if (empty($_SESSION['user_id']) || (int)($_SESSION['user_role'] ?? 0) !== 1) {
    http_response_code(403);
    exit('Access denied. Administrators only.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$page_name = 'admin';
$_SESSION['admin_token'] ??= bin2hex(random_bytes(32));

function adminEscape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function adminQuery(string $sql, string $types = '', array $values = []): mysqli_stmt
{
    global $db_connection;
    $statement = $db_connection->prepare($sql);
    if ($types !== '') {
        $statement->bind_param($types, ...$values);
    }
    $statement->execute();
    return $statement;
}

function adminId(string $name): int
{
    $id = filter_var($_POST[$name] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) {
        throw new InvalidArgumentException('Choose a valid account or item.');
    }
    return $id;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['admin_token'], $token)) {
        http_response_code(403);
        exit('Invalid request token.');
    }
}

function adminFinish(string $page, callable $action): void
{
    global $db_connection;
    try {
        $db_connection->begin_transaction();
        $notice = $action();
        $db_connection->commit();
        $_SESSION['admin_notice'] = $notice;
    } catch (Throwable $error) {
        $db_connection->rollback();
        error_log($error->getMessage());
        $_SESSION['admin_error'] = $error instanceof InvalidArgumentException
            ? $error->getMessage() : 'Unable to save changes. Please try again.';
    }
    header('Location: ' . BASE_URL . '/admin/' . $page);
    exit;
}

function adminToken(): void
{
    echo '<input type="hidden" name="token" value="' . adminEscape($_SESSION['admin_token']) . '">';
}

function adminStart(string $title): void
{
    global $page_name;
    ?>
    <!doctype html>
    <html lang="en">
    <head><?php include ROOT_PATH . '/include/head.php'; ?></head>
    <body class="admin">
    <?php include ROOT_PATH . '/include/header.php'; ?>
    <main class="container" style="padding-bottom: 120px;">
        <h1><?= adminEscape($title) ?></h1>
        <?php foreach (['admin_notice' => 'success', 'admin_error' => 'danger'] as $key => $style): ?>
            <?php if (isset($_SESSION[$key])): ?>
                <div class="alert alert-<?= $style ?>" role="status"><?= adminEscape($_SESSION[$key]) ?></div>
                <?php unset($_SESSION[$key]); ?>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php
}

function adminEnd(): void
{
    echo '</main>';
    include ROOT_PATH . '/include/footer.php';
    echo '</body></html>';
}
