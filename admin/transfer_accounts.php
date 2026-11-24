<?php
require_once __DIR__ . '/admin_common.php';

$preview = null;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'transfer') {
        adminFinish('transfer_accounts.php', function () {
            $source = adminId('source_id');
            $destination = adminId('destination_id');
            $review = $_SESSION['transfer_review'] ?? [];
            $confirmation = $_POST['confirmation'] ?? '';
            if ($source === $destination || ($review['source'] ?? 0) !== $source
                || ($review['destination'] ?? 0) !== $destination
                || !is_string($confirmation) || !hash_equals($review['token'] ?? '', $confirmation)) {
                throw new InvalidArgumentException('Review the transfer again before confirming.');
            }
            $accounts = adminQuery('SELECT user_id, role_id FROM Users WHERE user_id IN (?, ?) ORDER BY user_id FOR UPDATE', 'ii', [$source, $destination])->get_result()->fetch_all(MYSQLI_ASSOC);
            if (count($accounts) !== 2 || (int)$accounts[0]['role_id'] !== 2 || (int)$accounts[1]['role_id'] !== 2) {
                throw new InvalidArgumentException('Choose two existing standard user accounts.');
            }
            $counts = [];
            foreach (['expenses', 'recurring_expense', 'budget', 'savings_goals'] as $table) {
                $counts[$table] = adminQuery("SELECT user_id FROM $table WHERE user_id = ? FOR UPDATE", 'i', [$source])->get_result()->num_rows;
            }
            if ($counts !== $review['counts']) {
                throw new InvalidArgumentException('The source records changed. Review the transfer again.');
            }
            foreach (['expenses', 'recurring_expense', 'savings_goals'] as $table) {
                adminQuery("UPDATE $table SET user_id = ? WHERE user_id = ?", 'ii', [$destination, $source]);
            }
            $budgets = adminQuery('SELECT budget_id, category_id, month FROM budget WHERE user_id = ? ORDER BY budget_id FOR UPDATE', 'i', [$source])->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($budgets as $budget) {
                $matches = adminQuery('SELECT budget_id FROM budget WHERE user_id = ? AND category_id = ? AND month <=> ? ORDER BY budget_id FOR UPDATE', 'iis', [$destination, $budget['category_id'], $budget['month']])->get_result()->fetch_all(MYSQLI_ASSOC);
                if (!$matches) {
                    adminQuery('UPDATE budget SET user_id = ? WHERE budget_id = ?', 'ii', [$destination, $budget['budget_id']]);
                    continue;
                }
                // Sum money in SQL to keep decimal precision during the transfer.
                $sum = adminQuery('SELECT SUM(COALESCE(limit_amount, 0)) AS total FROM budget WHERE budget_id = ? OR (user_id = ? AND category_id = ? AND month <=> ?)', 'iiis', [$budget['budget_id'], $destination, $budget['category_id'], $budget['month']])->get_result()->fetch_assoc()['total'];
                if ((float)$sum > 99999999.99 || (float)$sum < 0) {
                    throw new InvalidArgumentException('A combined budget exceeds the supported amount. Adjust the budgets first.');
                }
                $keep = (int)$matches[0]['budget_id'];
                adminQuery('UPDATE budget SET limit_amount = ? WHERE budget_id = ?', 'si', [$sum, $keep]);
                adminQuery('DELETE FROM budget WHERE budget_id = ? OR (user_id = ? AND category_id = ? AND month <=> ? AND budget_id <> ?)', 'iiisi', [$budget['budget_id'], $destination, $budget['category_id'], $budget['month'], $keep]);
            }
            unset($_SESSION['transfer_review']);
            return 'Financial records transferred. Both profiles and logins have been kept.';
        });
    }
    try {
        if (($_POST['action'] ?? '') !== 'review') {
            throw new InvalidArgumentException('Choose Review Transfer first.');
        }
        $source = adminId('source_id');
        $destination = adminId('destination_id');
        if ($source === $destination) {
            throw new InvalidArgumentException('Choose different source and destination accounts.');
        }
        $accounts = adminQuery('SELECT u.user_id, MIN(c.username) AS username FROM Users u JOIN Credentials c ON c.user_id = u.user_id WHERE u.role_id = 2 AND u.user_id IN (?, ?) GROUP BY u.user_id', 'ii', [$source, $destination])->get_result()->fetch_all(MYSQLI_ASSOC);
        if (count($accounts) !== 2) {
            throw new InvalidArgumentException('Choose two standard user accounts.');
        }
        $names = array_column($accounts, 'username', 'user_id');
        $counts = [];
        foreach (['expenses', 'recurring_expense', 'budget', 'savings_goals'] as $table) {
            $counts[$table] = (int)adminQuery("SELECT COUNT(*) AS total FROM $table WHERE user_id = ?", 'i', [$source])->get_result()->fetch_assoc()['total'];
        }
        if (array_sum($counts) === 0) {
            throw new InvalidArgumentException('The source account has no financial records to transfer.');
        }
        $preview = ['source' => $source, 'destination' => $destination, 'counts' => $counts, 'token' => bin2hex(random_bytes(32))];
        $_SESSION['transfer_review'] = $preview;
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $error = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Unable to review the transfer. Check the database connection.';
    }
}
$users = adminQuery('SELECT u.user_id, MIN(c.username) AS username FROM Users u JOIN Credentials c ON c.user_id = u.user_id WHERE u.role_id = 2 GROUP BY u.user_id ORDER BY username')->get_result()->fetch_all(MYSQLI_ASSOC);
adminStart('Transfer Account Records');
?>
<p>Move expenses, recurring expenses, budgets, and savings goals between two user accounts. Both profiles and logins stay active.</p>
<?php if ($error): ?><div class="alert alert-danger"><?= adminEscape($error) ?></div><?php endif; ?>
<form method="post" class="card card-body my-4">
    <?php adminToken(); ?>
    <input type="hidden" name="action" value="review">
    <?php foreach (['source_id' => 'Transfer from', 'destination_id' => 'Transfer to'] as $field => $label): ?>
        <label for="<?= $field ?>" class="form-label"><?= $label ?></label>
        <select id="<?= $field ?>" name="<?= $field ?>" class="form-select mb-3" required>
            <option value="">Choose an account</option>
            <?php foreach ($users as $user): ?>
                <option value="<?= (int)$user['user_id'] ?>"><?= adminEscape($user['username']) ?> (#<?= (int)$user['user_id'] ?>)</option>
            <?php endforeach; ?>
        </select>
    <?php endforeach; ?>
    <button class="btn btn-primary">Review Transfer</button>
</form>
<?php if ($preview): ?>
    <section class="card card-body mb-4">
        <h2>Confirm Transfer</h2>
        <p>Move records from <strong><?= adminEscape($names[$source]) ?></strong> to <strong><?= adminEscape($names[$destination]) ?></strong>.</p>
        <ul>
            <?php foreach (['expenses' => 'Expenses', 'recurring_expense' => 'Recurring expenses', 'budget' => 'Budgets', 'savings_goals' => 'Savings goals'] as $table => $label): ?>
                <li><?= $label ?>: <?= $preview['counts'][$table] ?></li>
            <?php endforeach; ?>
        </ul>
        <p>Budgets with matching categories and months will have their limits added together. Expense currencies and amounts stay attached to their records. Both accounts keep their profile details, roles, and passwords. The destination user will be able to access the transferred records.</p>
        <form method="post">
            <?php adminToken(); ?>
            <input type="hidden" name="action" value="transfer">
            <input type="hidden" name="source_id" value="<?= $source ?>">
            <input type="hidden" name="destination_id" value="<?= $destination ?>">
            <input type="hidden" name="confirmation" value="<?= adminEscape($preview['token']) ?>">
            <button class="btn btn-warning">Confirm and Transfer</button>
        </form>
    </section>
<?php endif; ?>
<?php adminEnd(); ?>
