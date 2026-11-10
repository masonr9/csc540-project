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

<?php if (isset($_SESSION['user_name']) && $_SESSION['user_role'] == 2) { ?>

    <!-- Expense Tracker Sidebar -->
    <aside class="expense-sidebar">

        <h2 class="sidebar-title">Expense<br>Tracker</h2>

        <nav class="sidebar-nav">

            <a href="<?php echo BASE_URL; ?>/user/">
                <i class="bi bi-house-door-fill"></i>
                Home
            </a>

            <a href="<?php echo BASE_URL; ?>/user/expenses.php">
                <i class="bi bi-receipt"></i>
                Expenses
            </a>

            <a href="<?php echo BASE_URL; ?>/user/budget.php">
                <i class="bi bi-pie-chart-fill"></i>
                Budgets
            </a>

            <a href="<?php echo BASE_URL; ?>/user/reports.php">
                <i class="bi bi-bar-chart-fill"></i>
                Reports
            </a>

            <a href="<?php echo BASE_URL; ?>/change_credentials.php">
                <i class="bi bi-key-fill"></i>
                Change Credentials
            </a>

            <a href="<?php echo BASE_URL; ?>/user/settings.php">
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

            <a href="<?php echo BASE_URL; ?>/php/logout.php">Logout</a>
        </div>
    </header>

<?php } else { ?>

    <!-- Navigation for Admin, Guest and Login pages -->
    <header class="mb-5">
        <nav class="navbar navbar-expand-lg bg-body-tertiary">
            <div class="container-fluid">

                <a class="navbar-brand" href="<?php echo BASE_URL; ?>/">
                    Expense Tracker
                </a>

                <div class="navbar-nav me-auto">

                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 1) { ?>

                        <a class="nav-link" href="<?php echo BASE_URL; ?>/admin/">
                            Admin Dashboard
                        </a>

                        <a class="nav-link" href="<?php echo BASE_URL; ?>/change_credentials.php">
                            Change Credentials
                        </a>

                        <a class="nav-link" href="<?php echo BASE_URL; ?>/user/expenses.php">
                            Expenses
                        </a>

                        <a class="nav-link" href="<?php echo BASE_URL; ?>/user/budget.php">
                            Budgets
                        </a>

                    <?php } ?>

                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 3) { ?>

                        <a class="nav-link" href="<?php echo BASE_URL; ?>/guest/">
                            Guest Dashboard
                        </a>

                    <?php } ?>

                </div>

                <div class="navbar-text">
                    <?php if (isset($_SESSION['user_first'])) { ?>

                        Welcome <?php echo htmlspecialchars($_SESSION['user_first']); ?>

                        | <a href="<?php echo BASE_URL; ?>/php/logout.php">
                            Logout
                        </a>

                    <?php } else { ?>

                        Not Logged In

                    <?php } ?>
                </div>

            </div>
        </nav>
    </header>

<?php } ?>
