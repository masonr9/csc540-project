
<?php
/**
 * User Budget Management Page
 *
 * @copyright 2026
 * @version   1.0
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

/*Quick paths*/
include_once (realpath(dirname(__FILE__, 2).'/php/session.php'));
include_once (realpath(dirname(__FILE__, 2).'/php/path.php'));

/*Check role*/
include_once (ROOT_SRC_PATH .'/check_user.php');

/*Page name*/
$page_name = "user";

/*Database connection*/
include_once (ROOT_PATH . '/php/config.php');

/*Get user ID*/
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

/* Get categories*/
$categories = $db_connection->query(
    "SELECT category_id, category_name
     FROM categories
     ORDER BY category_name"
);


/* Add Monthly Budget */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    /* Get selected month and year*/
    $selected_month = $_POST['month'] ?? '';
    $selected_year = $_POST['year'] ?? '';

    $month = $selected_year . "-" . $selected_month;
    $limit_amount = $_POST['limit_amount'] ?? '';

    if (
        $category_id !== false &&
        $category_id !== null &&
        preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) &&
        is_numeric($limit_amount) &&
        $limit_amount > 0
    ) {

        $insert_budget = $db_connection->prepare(
            "INSERT INTO budget (limit_amount, month, user_id, category_id)
             VALUES (?, ?, ?, ?)"
        );

        $insert_budget->bind_param(
            "dsii",
            $limit_amount,
            $month,
            $user_id,
            $category_id
        );

        if ($insert_budget->execute()) {
            header("location: budget.php");
            exit();
        } else {
            $message = "Unable to add budget.";
        }

        $insert_budget->close();

    } else {
    $message = "Please check your category, month, and budget amount";
    }
}


/*Get user budgets*/
$stmt = $db_connection->prepare(
    "SELECT b.month, b.limit_amount, c.category_name
     FROM budget b
     INNER JOIN categories c
     ON b.category_id = c.category_id
     WHERE b.user_id = ?"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($month, $limit_amount, $category_name);

$budgets = array();

while ($stmt->fetch()) {
    $budgets[] = array(
        "month" => $month,
        "limit_amount" => $limit_amount,
        "category_name" => $category_name
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

        <h1>My Monthly Budgets</h1>
        <p class="lead">
            View and manage your monthly spending limits.
        </p>

        <hr class="mb-4">

        <!-- Add Monthly Budget Form -->
        <section class="mb-4">

            <h2>Add Monthly Budget</h2>
            <?php
            if (isset($message)) {
                echo "<p class='text-danger'>" . htmlspecialchars($message) . "</p>";
            }?>
            <form action="budget.php" method="post">

                <div class="mb-3">
                    <label for="category" class="form-label">Category</label>

                    <select class="form-control" name="category_id" id="category" required>
                        <option value="">Select Category</option>

                        <?php
                        while($row = $categories->fetch_assoc()) {
                            echo "<option value='" . $row["category_id"] . "'>"
                                . htmlspecialchars($row["category_name"])
                                . "</option>";
                        }?>
                    </select>
                </div>


                
                <!-- Select month and year-->
                <div class="row mb-3">

                    <div class="col-md-6">
                        <label for="month" class="form-label">Month</label>
                        <select class="form-control" name="month" id="month" required>
                            <option value="">Select Month</option>

                            <?php
                            for ($m = 1; $m <= 12; $m++) {
                                echo "<option value='" . sprintf("%02d", $m) . "'>"
                                    . date("F", mktime(0, 0, 0, $m, 1))
                                    . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="year" class="form-label">Year</label>
                        <select class="form-control" name="year" id="year" required>
                            <option value="">Select Year</option>

                            <?php
                            $current_year = date("Y");

                            for ($y = $current_year; $y <= $current_year + 5; $y++) {
                                echo "<option value='$y'>$y</option>";
                            }
                            ?>
                        </select>
                    </div>

                </div>


                <div class="mb-3">
                    <label for="limit_amount" class="form-label">Budget Limit</label>
                    <input type="number" class="form-control" name="limit_amount"
                        id="limit_amount" min="0.01" step="0.01" required>
                </div>

                <button type="submit" class="btn btn-primary">Add Budget</button>

            </form>
        </section>

        <hr class="mb-4">

        <section class="mb-4">

            <h2>Budget Information</h2>

            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Month</th>
                        <th scope="col">Category</th>
                        <th scope="col">Budget Limit</th>
                    </tr>
                </thead>

                <tbody>
                <?php
                if (count($budgets) > 0) {
                    foreach ($budgets as $row) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row["month"]) . "</td>";
                        echo "<td>" . htmlspecialchars($row["category_name"]) . "</td>";
                        echo "<td>$" . number_format($row["limit_amount"], 2) . "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='3'>No budgets found</td></tr>";
                }
                ?>
                </tbody>
            </table>

        </section>

    </div>

</main>

<?php include_once (ROOT_PATH . '/include/footer.php'); ?>

</body>
</html>
