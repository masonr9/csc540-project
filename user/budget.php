
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
