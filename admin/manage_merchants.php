<?php
require_once __DIR__ . '/admin_common.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminFinish('manage_merchants.php', function () {
        $name = $_POST['name'] ?? '';
        if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 50) {
            throw new InvalidArgumentException('Enter a name between 1 and 50 characters.');
        }
        $name = trim($name);
        if (($_POST['action'] ?? '') === 'add') {
            adminQuery('INSERT INTO merchant (merchant_name) VALUES (?)', 's', [$name]);
            return 'Merchant added.';
        }
        if (($_POST['action'] ?? '') !== 'edit') {
            throw new InvalidArgumentException('Unknown action.');
        }
        $id = adminId('id');
        $record = adminQuery('SELECT merchant_id FROM merchant WHERE merchant_id = ? FOR UPDATE', 'i', [$id])->get_result()->fetch_assoc();
        if (!$record) {
            throw new InvalidArgumentException('This item no longer exists.');
        }
        adminQuery('UPDATE merchant SET merchant_name = ? WHERE merchant_id = ?', 'si', [$name, $id]);
        return 'Merchant saved.';
    });
}
$items = adminQuery('SELECT merchant_id, merchant_name FROM merchant ORDER BY merchant_name, merchant_id')->get_result()->fetch_all(MYSQLI_ASSOC);
adminStart('Manage Merchants');
?>
<section class="card card-body my-4">
    <h2 class="h5">Add Merchant</h2>
    <form method="post" class="d-flex gap-2">
        <?php adminToken(); ?>
        <input type="hidden" name="action" value="add">
        <input name="name" class="form-control" aria-label="Merchant name" maxlength="50" required>
        <button class="btn btn-primary">Add</button>
    </form>
</section>
<table class="table table-striped">
    <thead><tr><th>ID</th><th>Merchant</th></tr></thead>
    <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= (int)$item['merchant_id'] ?></td>
                <td>
                    <form method="post" class="d-flex gap-2">
                        <?php adminToken(); ?>
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?= (int)$item['merchant_id'] ?>">
                        <input name="name" class="form-control" aria-label="Merchant name" value="<?= adminEscape($item['merchant_name']) ?>" maxlength="50" required>
                        <button class="btn btn-outline-primary">Save</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="2">No items have been added yet.</td></tr><?php endif; ?>
    </tbody>
</table>
<?php adminEnd(); ?>
