<?php

// Recurring expenses use a start date and frequency.
require_once dirname(__DIR__) . '/php/path.php';
session_start();

if (empty($_SESSION['login_user'])) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

require_once ROOT_PATH . '/php/config.php';

$auth = $db_connection->prepare(
    'SELECT u.user_id, u.role_id, c.first_name
     FROM Users u
     JOIN Credentials cr ON cr.user_id=u.user_id
     JOIN Contacts c ON c.contact_id=u.contact_id
     WHERE cr.username=?'
);

$auth->bind_param('s', $_SESSION['login_user']);
$auth->execute();
$identity = $auth->get_result()->fetch_assoc();

if (!$identity || (int)$identity['role_id'] !== 2) {
    http_response_code(403);
    exit('This page is available to standard users only.');
}

$user_id = (int)$identity['user_id'];

$_SESSION['user_id'] = $user_id;
$_SESSION['user_name'] = $_SESSION['login_user'];
$_SESSION['user_role'] = 2;
$_SESSION['user_first'] = $identity['first_name'];
$_SESSION['user_type'] = 'user';
$_SESSION['recurring_token'] ??= bin2hex(random_bytes(32));

function esc($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function query_recurring($db, $sql, $types, $values) {
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $stmt->execute();

    return $stmt;
}

$lookups = [
    'category_id' => ['categories', 'category_name', 'Category'],
    'payment_id' => ['payment_method', 'method_name', 'Payment method'],
    'merchant_id' => ['merchant', 'merchant_name', 'Merchant'],
    'currency_id' => ['currencies', 'currency', 'Currency'],
];

$choices = [];

foreach ($lookups as $key => [$table, $label, $title]) {
    $choices[$key] = $db_connection
        ->query("SELECT $key, $label FROM $table ORDER BY $label")
        ->fetch_all(MYSQLI_ASSOC);
}

$frequencies = ['Daily', 'Weekly', 'Monthly', 'Yearly'];
$error = '';

$form = [
    'recurring_id' => '',
    'amount' => '',
    'start_date' => date('Y-m-d'),
    'frequency' => 'Monthly',
];

foreach ($lookups as $key => $unused) {
    $form[$key] = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $value) {
        $form[$key] = is_string($_POST[$key] ?? null)
            ? trim($_POST[$key])
            : '';
    }

    $action = $_POST['action'] ?? '';

    $id = filter_var(
        $form['recurring_id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (
        !is_string($_POST['token'] ?? null)
        || !hash_equals($_SESSION['recurring_token'], $_POST['token'])
    ) {
        http_response_code(403);
        $error = 'Your form expired. Reload the page and try again.';
    }
    elseif (!in_array($action, ['add', 'edit', 'delete'], true)) {
        $error = 'Invalid action.';
    }
    elseif (
        $action !== 'add'
        && (
            !$id
            || !query_recurring(
                $db_connection,
                'SELECT recurring_id
                 FROM recurring_expense
                 WHERE recurring_id=? AND user_id=?',
                'ii',
                [$id, $user_id]
            )->get_result()->fetch_assoc()
        )
    ) {
        http_response_code(404);
        $error = 'Recurring expense not found.';
    }
    else {
        if ($action !== 'delete') {
            if (!in_array($form['frequency'], $frequencies, true)) {
                $error = 'Select a valid frequency.';
            }

            foreach ($lookups as $key => $unused) {
                if (
                    !in_array(
                        $form[$key],
                        array_map('strval', array_column($choices[$key], $key)),
                        true
                    )
                ) {
                    $error = 'Select a valid category, payment method, merchant and currency.';
                }
            }

            if (
                !preg_match('/^\d{1,8}(\.\d{1,2})?$/D', $form['amount'])
                || (float)$form['amount'] <= 0
            ) {
                $error = 'Enter a positive amount, at most 99,999,999.99, with up to two decimal places.';
            }

            $date = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $form['start_date']
            );

            if (
                !$date
                || $date->format('Y-m-d') !== $form['start_date']
                || substr($form['start_date'], 0, 4) < '1000'
            ) {
                $error = 'Enter a valid start date.';
            }
        }

        if ($error === '') {
            if ($action === 'delete') {
                query_recurring(
                    $db_connection,
                    'DELETE FROM recurring_expense
                     WHERE recurring_id=? AND user_id=?',
                    'ii',
                    [$id, $user_id]
                );
            }
            else {
                $values = [
                    $form['category_id'],
                    $form['payment_id'],
                    $form['merchant_id'],
                    $form['currency_id'],
                    $form['amount'],
                    $form['start_date'],
                    $form['frequency'],
                ];

                if ($action === 'add') {
                    $values[] = $user_id;

                    query_recurring(
                        $db_connection,
                        'INSERT INTO recurring_expense
                         (
                             category_id,
                             payment_id,
                             merchant_id,
                             currency_id,
                             amount,
                             start_date,
                             frequency,
                             user_id
                         )
                         VALUES (?,?,?,?,?,?,?,?)',
                        'iiiisssi',
                        $values
                    );
                }
                else {
                    $values[] = $id;
                    $values[] = $user_id;

                    query_recurring(
                        $db_connection,
                        'UPDATE recurring_expense
                         SET category_id=?,
                             payment_id=?,
                             merchant_id=?,
                             currency_id=?,
                             amount=?,
                             start_date=?,
                             frequency=?
                         WHERE recurring_id=? AND user_id=?',
                        'iiiisssii',
                        $values
                    );
                }
            }

            $_SESSION['recurring_notice'] = 'Recurring expense '
                . ['add' => 'added', 'edit' => 'updated', 'delete' => 'deleted'][$action]
                . '.';

            header('Location: ' . BASE_URL . '/user/recurring.php');
            exit;
        }
    }
}
elseif (isset($_GET['edit'])) {
    $id = filter_var(
        $_GET['edit'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $record = $id
        ? query_recurring(
            $db_connection,
            'SELECT *
             FROM recurring_expense
             WHERE recurring_id=? AND user_id=?',
            'ii',
            [$id, $user_id]
        )->get_result()->fetch_assoc()
        : null;

    if (!$record) {
        http_response_code(404);
        $error = 'Recurring expense not found.';
    }
    else {
        $form = $record;
    }
}

$frequency = is_string($_GET['frequency'] ?? null)
    ? $_GET['frequency']
    : '';

if (!in_array($frequency, $frequencies, true)) {
    $frequency = '';
}

$category = filter_var(
    $_GET['category'] ?? '',
    FILTER_VALIDATE_INT
) ?: 0;

$search = is_string($_GET['search'] ?? null)
    ? substr(trim($_GET['search']), 0, 100)
    : '';

$where = 'e.user_id=?';
$values = [$user_id];
$types = 'i';

if ($frequency !== '') {
    $where .= ' AND e.frequency=?';
    $values[] = $frequency;
    $types .= 's';
}

if ($category > 0) {
    $where .= ' AND e.category_id=?';
    $values[] = $category;
    $types .= 'i';
}

if ($search !== '') {
    $where .= ' AND (m.merchant_name LIKE ? OR c.category_name LIKE ?)';
    $values[] = '%' . $search . '%';
    $values[] = '%' . $search . '%';
    $types .= 'ss';
}

$rows = query_recurring(
    $db_connection,
    "SELECT e.*,
            c.category_name,
            m.merchant_name,
            p.method_name,
            cu.currency
     FROM recurring_expense e
     JOIN categories c ON c.category_id=e.category_id
     JOIN merchant m ON m.merchant_id=e.merchant_id
     JOIN payment_method p ON p.payment_id=e.payment_id
     JOIN currencies cu ON cu.currency_id=e.currency_id
     WHERE $where
     ORDER BY e.start_date DESC, e.recurring_id DESC",
    $types,
    $values
)->get_result()->fetch_all(MYSQLI_ASSOC);

$notice = $_SESSION['recurring_notice'] ?? '';
unset($_SESSION['recurring_notice']);

?>

<!doctype html>
<html lang="en">
    <head>
        <?php include ROOT_PATH . '/include/head.php'; ?>

        <style>
            main.recurring {
                padding-bottom: 100px;
            }

            .recurring-form {
                max-width: 900px;
            }

            td, th {
                vertical-align: middle;
            }
        </style>
    </head>

    <body class="user">
        <?php include ROOT_PATH . '/include/header.php'; ?>

        <main class="container recurring">
            <h1>My Recurring Expenses</h1>

            <p>
                Manage regular payments, their start dates and how often they repeat.
            </p>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <?= esc($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($notice): ?>
                <div class="alert alert-success" role="status">
                    <?= esc($notice) ?>
                </div>
            <?php endif; ?>

            <section class="card card-body mb-4 recurring-form">
                <h2>
                    <?= $form['recurring_id'] ? 'Edit recurring expense' : 'Add recurring expense' ?>
                </h2>

                <form method="post" action="<?= esc(BASE_URL) ?>/user/recurring.php">
                    <input
                        type="hidden"
                        name="token"
                        value="<?= esc($_SESSION['recurring_token']) ?>"
                    >

                    <input
                        type="hidden"
                        name="recurring_id"
                        value="<?= esc($form['recurring_id']) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="<?= $form['recurring_id'] ? 'edit' : 'add' ?>"
                    >

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="frequency">
                                Frequency
                            </label>

                            <select
                                class="form-select"
                                name="frequency"
                                id="frequency"
                                required
                            >
                                <?php foreach ($frequencies as $option): ?>
                                    <option
                                        value="<?= esc($option) ?>"
                                        <?= $form['frequency'] === $option ? 'selected' : '' ?>
                                    >
                                        <?= esc($option) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php foreach ($lookups as $key => [$table, $label, $title]): ?>
                            <div class="col-md-6">
                                <label class="form-label" for="<?= $key ?>">
                                    <?= esc($title) ?>
                                </label>

                                <select
                                    class="form-select"
                                    name="<?= $key ?>"
                                    id="<?= $key ?>"
                                    required
                                >
                                    <option value="">
                                        Select <?= esc(strtolower($title)) ?>
                                    </option>

                                    <?php foreach ($choices[$key] as $choice): ?>
                                        <option
                                            value="<?= (int)$choice[$key] ?>"
                                            <?= (string)$form[$key] === (string)$choice[$key] ? 'selected' : '' ?>
                                        >
                                            <?= esc($choice[$label]) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>

                        <div class="col-md-6">
                            <label class="form-label" for="amount">
                                Amount
                            </label>

                            <input
                                class="form-control"
                                type="number"
                                name="amount"
                                id="amount"
                                min="0.01"
                                max="99999999.99"
                                step="0.01"
                                value="<?= esc($form['amount']) ?>"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="start_date">
                                Start date
                            </label>

                            <input
                                class="form-control"
                                type="date"
                                name="start_date"
                                id="start_date"
                                value="<?= esc($form['start_date']) ?>"
                                required
                            >
                        </div>
                    </div>

                    <button class="btn btn-primary mt-3" type="submit">
                        <?= $form['recurring_id'] ? 'Save changes' : 'Add recurring expense' ?>
                    </button>

                    <a
                        class="btn btn-outline-secondary mt-3"
                        href="<?= esc(BASE_URL) ?>/user/recurring.php"
                    >
                        Clear / Cancel
                    </a>
                </form>
            </section>

            <section class="mb-4">
                <h2>Recurring expenses</h2>

                <form method="get" class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label" for="filter_frequency">
                            Frequency
                        </label>

                        <select
                            class="form-select"
                            name="frequency"
                            id="filter_frequency"
                        >
                            <option value="">All frequencies</option>

                            <?php foreach ($frequencies as $option): ?>
                                <option
                                    value="<?= esc($option) ?>"
                                    <?= $frequency === $option ? 'selected' : '' ?>
                                >
                                    <?= esc($option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="filter_category">
                            Category
                        </label>

                        <select
                            class="form-select"
                            id="filter_category"
                            name="category"
                        >
                            <option value="">All categories</option>

                            <?php foreach ($choices['category_id'] as $choice): ?>
                                <option
                                    value="<?= (int)$choice['category_id'] ?>"
                                    <?= $category === (int)$choice['category_id'] ? 'selected' : '' ?>
                                >
                                    <?= esc($choice['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="search">
                            Search merchant or category
                        </label>

                        <input
                            class="form-control"
                            id="search"
                            name="search"
                            maxlength="100"
                            value="<?= esc($search) ?>"
                        >
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-outline-primary">
                            Filter
                        </button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Start date</th>
                                <th>Category</th>
                                <th>Merchant</th>
                                <th>Payment</th>
                                <th>Amount</th>
                                <th>Frequency</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td>
                                        <?= esc($row['start_date']) ?>
                                    </td>

                                    <td>
                                        <?= esc($row['category_name']) ?>
                                    </td>

                                    <td>
                                        <?= esc($row['merchant_name']) ?>
                                    </td>

                                    <td>
                                        <?= esc($row['method_name']) ?>
                                    </td>

                                    <td>
                                        <?= esc($row['currency']) ?>
                                        <?= esc(number_format((float)$row['amount'], 2)) ?>
                                    </td>

                                    <td>
                                        <?= esc($row['frequency']) ?>
                                    </td>

                                    <td>
                                        <a
                                            class="btn btn-outline-primary btn-sm"
                                            href="?edit=<?= (int)$row['recurring_id'] ?>"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="post"
                                            class="d-inline"
                                            action="<?= esc(BASE_URL) ?>/user/recurring.php"
                                            onsubmit="return confirm('Delete this recurring expense?');"
                                        >
                                            <input
                                                type="hidden"
                                                name="token"
                                                value="<?= esc($_SESSION['recurring_token']) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >

                                            <input
                                                type="hidden"
                                                name="recurring_id"
                                                value="<?= (int)$row['recurring_id'] ?>"
                                            >

                                            <button class="btn btn-outline-danger btn-sm">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$rows): ?>
                                <tr>
                                    <td colspan="7">
                                        No recurring expenses match these filters.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>

        <?php include ROOT_PATH . '/include/footer.php'; ?>
    </body>
</html>