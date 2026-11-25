<?php
/**
 * User Index Page
 *
 * @author    Joshua Connor <connorj4@southernct.edu>
 * @copyright 2026 
 * @date      2026-02-24
 * @version   1.0
 * new update: 2026-06-01
 */
error_reporting(E_ALL); // Report all errors
ini_set('display_errors', 0); // set to 1 to display errors, 0 to hide them

  /* Quick Paths */
  /* note the 2 after __FILE__, because it's 2 directories deep */
  include_once (realpath(dirname(__FILE__, 2).'/php/session.php')); // Session will be included in header.php
  include_once (realpath(dirname(__FILE__, 2).'/php/path.php')); // Path will be included in header.php
  // Session will be included in header.php

  /* Page Name */
  $page_name = "user";

?>
<?php
//======================================================================
// USER DASHBOARD PAGE
//======================================================================

error_reporting(E_ALL);
ini_set('display_errors', 0); // set to 1 to display errors, 0 to hide them

  /* Quick Paths */
  /* note the 2 after __FILE__, because it's 2 directories deep */
  include_once (realpath(dirname(__FILE__, 2).'/php/session.php')); // Session will be included in header.php
  include_once (realpath(dirname(__FILE__, 2).'/php/path.php')); // Path will be included in header.php
  // Session will be included in header.php
  
  /* Check Role */
  include_once (ROOT_SRC_PATH .'/check_user.php'); // Check user role and redirect if not authorized

  $user_check = $_SESSION['login_user'];
  // Check user and get roll session from database

  /* Page Name */
  $page_name = "user"; // Set page name for active link in header
    /* Database Connection */
  include_once (ROOT_PATH . '/php/config.php');

    /* Get User Information */
  $stmt = $db_connection->prepare(
    "SELECT 
        u.user_id,
        c.first_name,
        c.last_name,
        c.email,
        cr.username,
        r.role_type
    FROM Users u
    INNER JOIN Contacts c ON u.contact_id = c.contact_id
    INNER JOIN Credentials cr ON u.user_id = cr.user_id
    INNER JOIN Roles r ON u.role_id = r.role_id
    WHERE cr.username = ?"
  );

  $stmt->bind_param("s", $user_check);
  $stmt->execute();

  $user = $stmt->get_result()->fetch_assoc();

  $stmt->close();

  if (!$user) {
    exit("User not found.");
  }

  $user_id = $user['user_id'];

    /* Current Month */
  $current_month = date("Y-m");

  /* Get Monthly Expenses */
  $stmt = $db_connection->prepare(
    "SELECT c.currency, SUM(e.amount) AS total
     FROM expenses e
     INNER JOIN currencies c
     ON e.currency_id = c.currency_id
     WHERE e.user_id = ?
     AND DATE_FORMAT(e.expense_date, '%Y-%m') = ?
     GROUP BY c.currency"
  );

  $stmt->bind_param("is", $user_id, $current_month);
  $stmt->execute();

  $monthly_expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

  $stmt->close();

    /* Get Monthly Budget */
  $stmt = $db_connection->prepare(
    "SELECT COALESCE(SUM(limit_amount), 0) AS total
     FROM budget
     WHERE user_id = ? AND month = ?"
  );

  $stmt->bind_param("is", $user_id, $current_month);
  $stmt->execute();

  $monthly_budget = $stmt->get_result()->fetch_assoc()['total'];

  $stmt->close();

    /* Calculate Remaining Budget */
  $usd_expenses = 0;

  foreach ($monthly_expenses as $expense) {

    if ($expense['currency'] === 'USD') {
      $usd_expenses = $expense['total'];
      break;
    }

  }

  $remaining_budget = $monthly_budget - $usd_expenses;

  /* Get Total Savings */
  $stmt = $db_connection->prepare(
    "SELECT COALESCE(SUM(saved_amount), 0) AS total
     FROM savings_goals
     WHERE user_id = ?"
  );

  $stmt->bind_param("i", $user_id);
  $stmt->execute();

  $total_savings = $stmt->get_result()->fetch_assoc()['total'];

  $stmt->close();
?>
<!doctype html>
<html lang="en">
  <head>
  <?php include_once (ROOT_PATH . '/include/head.php'); // Include head.php ?>
  </head>
  <body class="<?php echo $page_name; ?>">

  <?php include_once (ROOT_PATH . '/include/header.php'); // Include header.php ?>
    <main role="main" class="container">

    <div class="container text-center">
  <div class="row align-items-center">
    
    <div class="col">
      <div class="mb-6">
      <h1>Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</h1>

      <p class="lead">
        Here's your financial overview for <?php echo date("F Y"); ?>.
      </p>
      </div>
      <hr class="mb-4">
      <!-- Financial Summary -->
       <div class="row mb-4">

        <!-- Monthly Expenses -->
        <div class="col-md-3 col-sm-6 mb-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">

              <h6 class="text-muted">Monthly Expenses</h6>

              <?php if (count($monthly_expenses) > 0) { ?>

                <?php foreach ($monthly_expenses as $expense) { ?>

                  <h4 class="text-danger">
                    <?php echo htmlspecialchars($expense['currency']); ?>
                    <?php echo number_format($expense['total'], 2); ?>
                  </h4>

                <?php } ?>

              <?php } else { ?>

                <h4 class="text-danger">0.00</h4>

              <?php } ?>

            </div>
          </div>
        </div>

        <!-- Monthly Budget -->
        <div class="col-md-3 col-sm-6 mb-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">

              <h6 class="text-muted">Monthly Budget</h6>

              <h4 class="text-primary">
                $<?php echo number_format($monthly_budget, 2); ?>
              </h4>

            </div>
          </div>
        </div>

        <!-- Remaining Budget -->
        <div class="col-md-3 col-sm-6 mb-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">

              <h6 class="text-muted">Remaining Budget</h6>

              <h4 class="<?php echo $remaining_budget < 0
                  ? 'text-danger' : 'text-success'; ?>">

                $<?php echo number_format($remaining_budget, 2); ?>

              </h4>
              <small class="text-muted">USD expenses only</small>

            </div>
          </div>
        </div>

        <!-- Total Savings -->
        <div class="col-md-3 col-sm-6 mb-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">

              <h6 class="text-muted">Total Savings</h6>

              <h4 class="text-success">
                $<?php echo number_format($total_savings, 2); ?>
              </h4>

            </div>
          </div>
        </div>

      </div>


      <!-- Savings Goals -->


      <!-- Recent Expenses -->


      <!-- My Profile -->
      <section class="card shadow-sm mb-4">
        <div class="card-body text-start">

          <h4>My Profile</h4>
          <hr>

          <h5>
            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
          </h5>

          <p class="text-muted">
            <?php echo htmlspecialchars($user['email']); ?>
          </p>

          <p>
            <strong>Username:</strong>
            <?php echo htmlspecialchars($user['username']); ?>
          </p>

          <a href="<?php echo BASE_URL; ?>/change_credentials.php"
            class="btn btn-outline-primary btn-sm">
            Manage Account Settings
          </a>

        </div>
      </section>
      
    </div>
    
  </div>
</div>  
       
    </main>
    <?php include_once (ROOT_PATH . '/include/footer.php'); ?>
  </body>
</html>
