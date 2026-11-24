<?php
require_once __DIR__ . '/admin_common.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminFinish('manage_recommendations.php', function () {
        $category = adminId('category_id');
        $amount = $_POST['amount'] ?? '';
        if (!is_string($amount) || !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $amount) || (float)$amount <= 0) {
            throw new InvalidArgumentException('Enter an amount from 0.01 to 99999999.99.');
        }
        if (!adminQuery('SELECT category_id FROM categories WHERE category_id = ? FOR UPDATE', 'i', [$category])->get_result()->fetch_assoc()) {
            throw new InvalidArgumentException('Choose an existing category.');
        }
        adminQuery('INSERT INTO budget_recommendations (category_id, amount) VALUES (?, ?) ON DUPLICATE KEY UPDATE amount = VALUES(amount)', 'is', [$category, $amount]);
        return 'Budget recommendation saved.';
    });
}
$categories = adminQuery('SELECT category_id, category_name FROM categories ORDER BY category_name')->get_result()->fetch_all(MYSQLI_ASSOC);
$recommendations = adminQuery('SELECT r.recommendation_id, c.category_name, r.amount FROM budget_recommendations r JOIN categories c ON c.category_id = r.category_id ORDER BY r.recommendation_id DESC')->get_result()->fetch_all(MYSQLI_ASSOC);
adminStart('Budget Recommendations');
?>
<form method="post" class="card card-body my-4">
    <?php adminToken(); ?>
    <label for="category" class="form-label">Category</label>
    <select id="category" name="category_id" class="form-select mb-3" required>
        <option value="">Choose a category</option>
        <?php foreach ($categories as $category): ?>
            <option value="<?= (int)$category['category_id'] ?>"><?= adminEscape($category['category_name']) ?></option>
        <?php endforeach; ?>
    </select>
    <label for="amount" class="form-label">Recommended monthly budget</label>
    <input id="amount" name="amount" type="number" min="0.01" max="99999999.99" step="0.01" class="form-control mb-3" required>
    <button class="btn btn-primary">Save Recommendation</button>
</form>
<table class="table table-striped">
    <thead><tr><th>Category</th><th>Recommended amount</th></tr></thead>
    <tbody>
        <?php foreach ($recommendations as $recommendation): ?>
            <tr><td><?= adminEscape($recommendation['category_name']) ?></td><td><?= number_format((float)$recommendation['amount'], 2) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$recommendations): ?><tr><td colspan="2">No recommendations yet.</td></tr><?php endif; ?>
    </tbody>
</table>
<?php adminEnd(); ?>
