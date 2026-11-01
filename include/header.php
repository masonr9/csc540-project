<?php
/**
 * Header Include File
 *
 * @author    Joshua Connor <connorj4@southernct.edu>
 * @copyright 2026 
 * @date      2026-02-24
 * @version   1.0
 */
// 
if (!isset($_SESSION)) {
    // Only include session if not already started
    include_once (realpath(dirname(__FILE__).'/path.php'));
    // Include session file
    include_once (ROOT_PATH . '/php/session.php');
}
?>
<?php
// Header Section
?>
<<<<<<< HEAD

<?php if (isset($_SESSION['user_name']) && $_SESSION['user_role'] == 2) { ?>

    <!-- Expense Tracker Sidebar -->
    <aside class="expense-sidebar">

        <h2 class="sidebar-title">Expense<br>Tracker</h2>

        <nav class="sidebar-nav">

            <a href="index.php">
                <i class="bi bi-house-door-fill"></i>
                Home
            </a>

            <a href="expenses.php">
                <i class="bi bi-receipt"></i>
                Expenses
            </a>

            <a href="budget.php">
                <i class="bi bi-pie-chart-fill"></i>
                Budgets
            </a>

            <a href="reports.php">
                <i class="bi bi-bar-chart-fill"></i>
                Reports
            </a>

            <a href="settings.php">
                <i class="bi bi-gear-fill"></i>
                Settings
            </a>

        </nav>

    </aside>

    <!-- Top User Bar -->
    <header class="expense-topbar">

        <div class="user-info">
            <i class="bi bi-person-circle"></i>

            <?php echo htmlspecialchars($_SESSION['user_first'] ?? 'User'); ?>

            <a href="../php/logout.php">Logout</a>
        </div>

    </header>

<?php } else { ?>

    <!-- Keep the existing navigation for other roles -->
    <header class="mb-5">
        <nav class="navbar navbar-expand-lg bg-body-tertiary">
            <div class="container-fluid">

                <a class="navbar-brand" href="../">Basic Login</a>

                <div class="ms-auto">
                    <?php
                    if (isset($_SESSION['user_first'])) {
                        echo 'Welcome ' . htmlspecialchars($_SESSION['user_first']);
                        echo ' | <a href="../php/logout.php">Logout</a>';
                    } else {
                        echo '<a href="../">Home</a>';
                    }
                    ?>
                </div>

            </div>
        </nav>
    </header>

<?php } ?>
=======
<header class="mb-5">
<?php
// navigation bar
?>
<nav class="navbar navbar-expand-lg bg-body-tertiary">
  <div class="container-fluid">
    <a class="navbar-brand" href="../">   
    <i class="bi bi-claude"></i>  Basic Login</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarText" aria-controls="navbarText" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarText">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php
          /* Show Home link only when user is NOT logged in */
          if (!isset($_SESSION['user_name'])) {
            echo '<li class="nav-item">';
            echo '<a class="nav-link active" aria-current="page" href="../">Home</a>';
            echo '</li>';
          }
        ?>
        <?php
          /* User Information */
          if (isset($_SESSION['user_name']) && $_SESSION['user_role'] == 1) { // Only display when user is logged in
            echo '<li class="nav-item"><a class="nav-link" href="' . BASE_URL . '/admin/">Admin Dashboard</a></li>';
            echo '<li class="nav-item"><a class="nav-link" href="' . BASE_URL . '/change_credentials.php">Change Credentials</a></li>';
          }
        ?>
        <?php
          /* User Information */
          if (isset($_SESSION['user_name']) && $_SESSION['user_role'] == 2) { // Only display when user is logged in
            echo '<li class="nav-item"><a class="nav-link" href="' . BASE_URL . '/user/">User Dashboard</a></li>';
            echo '<li class="nav-item"><a class="nav-link" href="' . BASE_URL . '/change_credentials.php">Change Credentials</a></li>';
          }
        ?>
        <?php
          /* User Information */
          if (isset($_SESSION['user_name']) && $_SESSION['user_role'] == 3) { // Only display when user is logged in
            echo '<li class="nav-item"><a class="nav-link" href="index.php">Guest Dashboard</a></li>';
            echo '<li class="nav-item"><a class="nav-link" href="#">New Page</a></li>';
          }
        ?>
      </ul>
      <span class="navbar-text">
        <?php
            /* User Information */
            if (isset($_SESSION['user_first']) && isset($_SESSION['user_type'])) {
                echo 'Welcome ' . $_SESSION['user_first'] . ' (' . $_SESSION['user_type'] . ')';
                echo ' | <a href="../php/logout.php">Logout</a>';
            } else {
                echo 'Not Logged In';
            }
            ?>
      </span>
    </div>
  </div>
</nav>
</header>
>>>>>>> origin/main
