<?php
/**
 * User Savings Goals Management Page
 *
 * @copyright 2026
 * @version 1.0
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

/* Quick paths */
include_once (realpath(dirname(__FILE__, 2).'/php/session.php'));
include_once (realpath(dirname(__FILE__, 2).'/php/path.php'));

/* Check role */
include_once (ROOT_SRC_PATH .'/check_user.php');

/* Page name */
$page_name = "user";

/* Database connection */
include_once (ROOT_PATH . '/php/config.php');

/* Get user ID */
$stmt = $db_connection->prepare(
    "SELECT user_id FROM Credentials WHERE username = ?"
);

$stmt->bind_param("s", $user_check);
$stmt->execute();
$stmt->bind_result($user_id);

if (!$stmt->fetch()) {
    $stmt->close();
    exit("User not found.");
}

$stmt->close();

/* CSRF protection */
if (empty($_SESSION['savings_csrf'])) {
    $_SESSION['savings_csrf'] = bin2hex(random_bytes(32));
}

/* Add Savings Goal */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_goal"])) {

    if (!hash_equals($_SESSION['savings_csrf'], $_POST['csrf_token'] ?? '')) {
        exit("Invalid request.");
    }

    $goal_name = trim($_POST["goal_name"] ?? '');
    $amount = $_POST["amount"] ?? '';
    $due_date = $_POST["due_date"] ?? '';
    $saved_amount = 0.00;

    $valid_date = DateTime::createFromFormat("!Y-m-d", $due_date);

    if (
        !empty($goal_name) &&
        strlen($goal_name) <= 100 &&
        is_numeric($amount) &&
        $amount > 0 &&
        $amount <= 99999999.99 &&
        $valid_date &&
        $valid_date->format("Y-m-d") === $due_date &&
        $due_date >= date("Y-m-d")
    ) {

        $insert_goal = $db_connection->prepare(
            "INSERT INTO savings_goals
            (user_id, goal_name, amount, saved_amount, due_date)
            VALUES (?, ?, ?, ?, ?)"
        );

        $insert_goal->bind_param(
            "isdds",
            $user_id,
            $goal_name,
            $amount,
            $saved_amount,
            $due_date
        );

        if ($insert_goal->execute()) {
            header("location: savings_goals.php");
            exit();
        } else {
            $message = "Unable to add savings goal.";
        }

        $insert_goal->close();

    } else {
        $message = "Please enter valid savings goal information.";
    }
}

/* Add or Withdraw Savings */
if ($_SERVER["REQUEST_METHOD"] == "POST" &&
    isset($_POST["update_savings"])) {

    /* Verify CSRF token */
    if (!hash_equals($_SESSION['savings_csrf'],
        $_POST['csrf_token'] ?? '')) {
        exit("Invalid request.");
    }

    $savings_id = filter_input(
        INPUT_POST, "savings_id", FILTER_VALIDATE_INT
    );

    $transaction_amount = $_POST["transaction_amount"] ?? '';
    $action = $_POST["action"] ?? '';

    /* Validate information */
    if (
        $savings_id > 0 &&
        is_numeric($transaction_amount) &&
        $transaction_amount >= 0.01 &&
        $transaction_amount <= 99999999.99 &&
        in_array($action, ["add", "withdraw"], true)
    ) {

        /* Add Savings */
        if ($action === "add") {

            $update_goal = $db_connection->prepare(
                "UPDATE savings_goals
                 SET saved_amount = saved_amount + ?
                 WHERE savings_id = ? AND user_id = ?
                 AND saved_amount + ? <= amount"
            );

        } else {

            /* Withdraw Savings */
            $update_goal = $db_connection->prepare(
                "UPDATE savings_goals
                 SET saved_amount = saved_amount - ?
                 WHERE savings_id = ? AND user_id = ?
                 AND saved_amount >= ?"
            );
        }

        $update_goal->bind_param(
            "diid",
            $transaction_amount,
            $savings_id,
            $user_id,
            $transaction_amount
        );

        if ($update_goal->execute() &&
            $update_goal->affected_rows > 0) {

            $update_goal->close();
            header("location: savings_goals.php");
            exit();

        } else {
            $message = "Unable to update savings. Check your amount.";
        }

        $update_goal->close();

    } else {
        $message = "Please enter a valid savings amount.";
    }
}

/* Get user savings goals */
$stmt = $db_connection->prepare(
    "SELECT savings_id, goal_name, amount, saved_amount, due_date
     FROM savings_goals
     WHERE user_id = ?
     ORDER BY due_date"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$stmt->bind_result(
    $savings_id,
    $goal_name,
    $amount,
    $saved_amount,
    $due_date
);

$goals = array();

while ($stmt->fetch()) {

    $goals[] = array(
        "savings_id" => $savings_id,
        "goal_name" => $goal_name,
        "amount" => $amount,
        "saved_amount" => $saved_amount,
        "due_date" => $due_date
    );
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php include_once (ROOT_PATH . '/include/head.php'); ?>
</head>

<body class="<?php echo $page_name; ?>">

<?php include_once (ROOT_PATH . '/include/header.php'); ?>

<main role="main" class="container">

    <div class="container text-center">

        <h1>My Savings Goals</h1>

        <p class="lead">
            Create, track, and manage your personal savings goals.
        </p>

        <hr class="mb-4">

        <!-- Add Savings Goal Form -->
        <section class="mb-4">

            <h2>Add Savings Goal</h2>

            <?php if (isset($message)) { ?>
                <p class="text-danger">
                    <?php echo htmlspecialchars($message); ?>
                </p>
            <?php } ?>

            <form action="savings_goals.php" method="post">

                <input type="hidden" name="csrf_token"
                    value="<?php echo htmlspecialchars($_SESSION['savings_csrf']); ?>">

                <div class="mb-3">
                    <label for="goal_name" class="form-label">
                        Goal Name
                    </label>

                    <input type="text" class="form-control"
                        name="goal_name" id="goal_name"
                        maxlength="100"
                        placeholder="e.g. Vacation, New Car"
                        required>
                </div>

                <div class="row mb-3">

                    <div class="col-md-6">
                        <label for="amount" class="form-label">
                            Target Amount
                        </label>

                        <input type="number" class="form-control"
                            name="amount" id="amount"
                            min="0.01" max="99999999.99"
                            step="0.01" required>
                    </div>

                    <div class="col-md-6">
                        <label for="due_date" class="form-label">
                            Due Date
                        </label>

                        <input type="date" class="form-control"
                            name="due_date" id="due_date"
                            min="<?php echo date('Y-m-d'); ?>"
                            required>
                    </div>

                </div>

                <button type="submit" name="add_goal"
                    class="btn btn-primary">
                    Add Savings Goal
                </button>

            </form>

        </section>

        <hr class="mb-4">

        <!-- Display Savings Goals -->
        <section class="mb-4">

            <h2>My Savings Goals</h2>

            <div class="row">

                <?php if (count($goals) > 0) { ?>

                    <?php foreach ($goals as $goal) { ?>

                        <?php
                        $progress = $goal['amount'] > 0
                            ? min(100, ($goal['saved_amount'] / $goal['amount']) * 100)
                            : 0;
                        ?>

                        <div class="col-md-6 mb-4">

                            <div class="card shadow-sm h-100">

                                <div class="card-body">

                                    <h3 class="card-title">
                                        <?php echo htmlspecialchars($goal['goal_name'] ?? 'Savings Goal'); ?>
                                    </h3>

                                    <p>
                                        Target: $<?php echo number_format($goal['amount'], 2); ?>
                                    </p>

                                    <p>
                                        Saved: $<?php echo number_format($goal['saved_amount'], 2); ?>
                                    </p>

                                    <div class="progress mb-3">

                                        <div class="progress-bar"
                                            role="progressbar"
                                            style="width: <?php echo $progress; ?>%;"
                                            aria-valuenow="<?php echo $progress; ?>"
                                            aria-valuemin="0"
                                            aria-valuemax="100">
                                        </div>

                                    </div>

                                    <p>
                                        <?php echo number_format($progress, 1); ?>% completed
                                    </p>

                                    <p class="text-muted">
                                        Due: <?php echo htmlspecialchars($goal['due_date']); ?>
                                    </p>

                                    <p>
                                        Remaining: $
                                        <?php echo number_format(
                                            max(0, $goal['amount'] - $goal['saved_amount']), 2
                                        ); ?>
                                    </p>

                                    <?php if ($progress >= 100) { ?>
                                        <p class="text-success fw-bold">
                                            Goal Completed!
                                        </p>
                                    <?php } ?>

                                    <hr>

                                        <form action="savings_goals.php" method="post">

                                            <input type="hidden" name="csrf_token"
                                                value="<?php echo htmlspecialchars($_SESSION['savings_csrf']); ?>">

                                            <input type="hidden" name="savings_id"
                                                value="<?php echo (int)$goal['savings_id']; ?>">

                                            <input type="hidden" name="update_savings" value="1">

                                            <div class="mb-3">

                                                <label class="form-label">
                                                    Savings Amount
                                                </label>

                                                <input type="number"
                                                    class="form-control"
                                                    name="transaction_amount"
                                                    placeholder="Enter amount"
                                                    min="0.01"
                                                    max="99999999.99"
                                                    step="0.01"
                                                    required>

                                            </div>

                                            <button type="submit"
                                                name="action"
                                                value="add"
                                                class="btn btn-primary btn-sm">
                                                Add Savings
                                            </button>

                                            <button type="submit"
                                                name="action"
                                                value="withdraw"
                                                class="btn btn-outline-danger btn-sm">
                                                Withdraw
                                            </button>

                                        </form>

                                </div>

                            </div>

                        </div>

                    <?php } ?>

                <?php } else { ?>

                    <p>No savings goals found.</p>

                <?php } ?>

            </div>

        </section>

    </div>

</main>

<?php include_once (ROOT_PATH . '/include/footer.php'); ?>

</body>
</html>