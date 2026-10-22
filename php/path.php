<?php
// old code
//=====================================================================
// DATABASE CONNECTION
//======================================================================

// $directory = "/csc235_login"; // Change to your project folder

/* Define base url path */
// DEFINE("BASE_URL", $directory);
// DEFINE("ROOT_SRC_PATH", $_SERVER["DOCUMENT_ROOT"] . $directory . "/php");
// DEFINE("SRC_PATH", $directory . "/php");
// Project directory
$directory = "/csc540-project";

// Define base URL path
define("BASE_URL", $directory);

// Define root filesystem path
define("ROOT_PATH", dirname(__DIR__));

// Define PHP source directory
define("ROOT_SRC_PATH", ROOT_PATH . "/php");

// Define PHP URL path
define("SRC_PATH", $directory . "/php");
?>