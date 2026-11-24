<?php
if (!isset($_SESSION)) {
    // Only include session if not already started
    include_once (realpath(dirname(__FILE__).'/path.php'));
    // Include session file
    include_once (ROOT_PATH . '/php/session.php');
}
?>
<?php
?>
<header class="mb-5">
<?php
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
          if (!isset($_SESSION['user_name'])) {
            echo '<li class="nav-item">';
            echo '<a class="nav-link active" aria-current="page" href="../">Home</a>';
            echo '</li>';
          }
        ?>
        <?php
          /* User Information */
          if (isset($_SESSION['user_name']) && $_SESSION['user_role'] == 1) { // Only display when user is logged in
            ?>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/">Dashboard</a></li>
            <?php
            $adminMenus = [
                'Users' => [
                    'Search Users' => 'search_users.php',
                    'User Roles' => 'manage_roles.php',
                    'Transfer Records' => 'transfer_accounts.php',
                ],
                'Expense Settings' => [
                    'Categories' => 'manage_categories.php',
                    'Merchants' => 'manage_merchants.php',
                    'Payment Methods' => 'manage_payment_methods.php',
                    'Currencies' => 'manage_currencies.php',
                ],
            ];
            foreach ($adminMenus as $label => $links): ?>
                <li class="nav-item dropdown">
                    <button class="nav-link dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><?= $label ?></button>
                    <ul class="dropdown-menu">
                        <?php foreach ($links as $title => $file): ?>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/<?= $file ?>"><?= $title ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endforeach; ?>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/admin/manage_recommendations.php">Budget Recommendations</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/change_credentials.php">Account Settings</a></li>
            <?php
          }
        ?>
        <?php
          /* User Information */
          if (isset($_SESSION['user_name']) && $_SESSION['user_role'] == 2) { // Only display when user is logged in
            echo '<li class="nav-item"><a class="nav-link" href="' . BASE_URL . '/user/">User Dashboard</a></li>';
            echo '<li class="nav-item"><a class="nav-link" href="' . BASE_URL . '/user/expenses.php">Expenses</a></li>';
            echo '<li class="nav-item"><a class="nav-link" href="' . BASE_URL . '/user/recurring_expenses.php">Recurring Expenses</a></li>';
            echo '<li class="nav-item"><a class="nav-link" href="' . BASE_URL . '/user/budget.php">Budgets</a></li>';
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
