<?php

/**
 * Authentication Script
 *
 * @author    Joshua Connor <connorj4@southernct.edu>
 * @copyright 2026 
 * @date      2026-02-24
 * @version   1.0
 */

// Include necessary configuration and path files
include_once (realpath(dirname(__FILE__).'/path.php'));
include_once (realpath(dirname(__FILE__).'/config.php'));

//-----------------------------------------------------
// Authenticate
//-----------------------------------------------------
if (isset($_POST['submit'])) {
  // Validate input
  if (empty($_POST['username']) || empty($_POST['password'])) {
    // Handle empty input
    $error = "Username or Password is empty!";
    return $error;
  }
} else {
/* Check the Username and Password */
  if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Define variables
    $username = $_POST['username'];
    $pass = crypt($_POST["password"], SALT );
    $user_roll = 0;
    // Protect against MYSQL injection
    $username = stripslashes($username);
    $pass = stripslashes($pass);
    $username = mysqli_real_escape_string($db_connection, $username);
    $pass = mysqli_real_escape_string($db_connection, $pass);
    // fetch the stored password hash instead of matching a legacy crypt() value in SQL.
    // This allows the same login query to work with both old and newly updated passwords.
    // SQL query to fetch information and find match user
    $select_users = $db_connection->prepare(
        // SQL query to fetch user details
        "SELECT u.user_id, u.role_id, cr.username, cr.password_salted
         FROM Users u
         JOIN Credentials cr ON u.user_id = cr.user_id
         WHERE cr.username = ? LIMIT 1");
    $select_users->bind_param("s", $username);
    $select_users->execute();
    // retrieve the stored hash so PHP can verify either password format.
    $select_users->bind_result($user_id, $user_role, $username, $stored_hash);
    $select_users->store_result();

    //Checking the result of the query for a match and results only found 1 match
    if($select_users->num_rows == 1) {
      // Fetch the results
      // password_verify() checks hashes made by password_hash() on Change Credentials.
      // the crypt() fallback accepts existing legacy passwords until they are changed.
      if($select_users->fetch() && (password_verify($_POST["password"], $stored_hash) || hash_equals($stored_hash, crypt($_POST["password"], SALT)))) {
        // Successful login
        session_start(); 
        # This area needs to send users and admins to there own directory
        $_SESSION['user_id'] = $user_id;
        $_SESSION['login_user']=$username;
        $_SESSION['user_role'] = $user_role;
        // Redirect based on user role
        if ($_SESSION['user_role'] == 1) {
          header("location: " . BASE_URL . "/admin");
        } elseif ($_SESSION['user_role'] == 2) {
          header("location:" . BASE_URL . "/user");
        } elseif ($_SESSION['user_role'] == 3) {
          header("location:" . BASE_URL . "/guest");
        } else {
          $_SESSION['error'] = "Login Failed";
          
        }
      }
  } else {
    // Invalid login
    $_SESSION['message'] = "Username or Password did not match!";
    // Redirect to logout
    header("location: " . SRC_PATH . "/logout.php"); 
    exit();
  }
  // close the mysql connection
  $select_users->close();
} else {
  // Direct access without POST data
  header("location: " . SRC_PATH . "/logout.php"); 
}
}