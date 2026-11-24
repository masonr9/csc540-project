<?php
require_once __DIR__ . '/admin_common.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminFinish('manage_currencies.php', function () {
        $name = $_POST['name'] ?? '';
        if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 30) {
            throw new InvalidArgumentException('Enter a name between 1 and 30 characters.');
        }
        $name = trim($name);
        if (($_POST['action'] ?? '') === 'add') {
            adminQuery('INSERT INTO currencies (currency) VALUES (?)', 's', [$name]);
            return 'Currency added.';
        }
        if (($_POST['action'] ?? '') !== 'edit') {
            throw new InvalidArgumentException('Unknown action.');
        }
        $id = adminId('id');
        $record = adminQuery('SELECT currency_id FROM currencies WHERE currency_id = ? FOR UPDATE', 'i', [$id])->get_result()->fetch_assoc();
        if (!$record) {
            throw new InvalidArgumentException('This item no longer exists.');
        }
        adminQuery('UPDATE currencies SET currency = ? WHERE currency_id = ?', 'si', [$name, $id]);
        return 'Currency saved.';
    });
}
$items = adminQuery('SELECT currency_id, currency FROM currencies ORDER BY currency, currency_id')->get_result()->fetch_all(MYSQLI_ASSOC);
adminStart('Manage Currencies');
?>
<section class="card card-body my-4">
    <h2 class="h5">Add Currency</h2>
    <form method="post" class="d-flex gap-2">
        <?php adminToken(); ?>
        <input type="hidden" name="action" value="add">
        <input name="name" class="form-control" aria-label="Currency name" maxlength="30" required>
        <button class="btn btn-primary">Add</button>
    </form>
</section>
<table class="table table-striped">
    <thead><tr><th>ID</th><th>Currency</th></tr></thead>
    <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= (int)$item['currency_id'] ?></td>
                <td>
                    <form method="post" class="d-flex gap-2">
                        <?php adminToken(); ?>
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?= (int)$item['currency_id'] ?>">
                        <input name="name" class="form-control" aria-label="Currency name" value="<?= adminEscape($item['currency']) ?>" maxlength="30" required>
                        <button class="btn btn-outline-primary">Save</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="2">No items have been added yet.</td></tr><?php endif; ?>
    </tbody>
</table>
<?php adminEnd(); ?>
