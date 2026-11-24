<?php

/**
 * Authentication Script
 *
 * @author Joshua Connor <connorj4@southernct.edu>
 * @copyright 2026
 */

require_once __DIR__ . '/path.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/');
    exit;
}

unset(
    $_SESSION['login_user'],
    $_SESSION['user_id'],
    $_SESSION['user_role'],
    $_SESSION['user_name'],
    $_SESSION['user_first'],
    $_SESSION['user_type'],
    $_SESSION['error'],
    $_SESSION['message']
);

$username = is_string($_POST['username'] ?? null) ? $_POST['username'] : '';
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

if ($username === '' || $password === '') {
    $_SESSION['error'] = 'Enter your username and password.';
    header('Location: ' . BASE_URL . '/');
    exit;
}

require_once __DIR__ . '/config.php';

$select_users = $db_connection->prepare(
    'SELECT u.user_id, u.role_id, cr.username, cr.password_salted,
            c.first_name, r.role_type
     FROM Users u
     JOIN Credentials cr ON cr.user_id = u.user_id
     JOIN Contacts c ON c.contact_id = u.contact_id
     JOIN Roles r ON r.role_id = u.role_id
     WHERE cr.username = ? LIMIT 1'
);

$select_users->bind_param('s', $username);
$select_users->execute();
$identity = $select_users->get_result()->fetch_assoc();
$select_users->close();

$valid_password = false;

if ($identity) {
    $stored_hash = $identity['password_salted'];
    $valid_password = password_verify($password, $stored_hash);

    // Older accounts still use the original crypt password format.
    if (!$valid_password && strlen($stored_hash) === 13) {
        $legacy_hash = crypt($password, SALT);
        $valid_password = strlen($legacy_hash) === 13
            && hash_equals($stored_hash, $legacy_hash);
    }
}

$destinations = [1 => '/admin/', 2 => '/user/', 3 => '/guest/'];
$role = $identity ? (int)$identity['role_id'] : 0;

if (!$valid_password || !isset($destinations[$role])) {
    $_SESSION['error'] = 'Username or password did not match.';
    header('Location: ' . BASE_URL . '/');
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int)$identity['user_id'];
$_SESSION['login_user'] = $identity['username'];
$_SESSION['user_name'] = $identity['username'];
$_SESSION['user_role'] = $role;
$_SESSION['user_first'] = $identity['first_name'];
$_SESSION['user_type'] = $identity['role_type'];

header('Location: ' . BASE_URL . $destinations[$role]);
exit;
