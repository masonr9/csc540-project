<?php
require_once __DIR__ . '/admin_common.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminFinish('manage_roles.php', function () {
        $target = adminId('user_id');
        $role = adminId('role_id');
        // Lock accounts in the same order before changing administrator access.
        $accounts = adminQuery('SELECT user_id, role_id FROM Users ORDER BY user_id FOR UPDATE')->get_result()->fetch_all(MYSQLI_ASSOC);
        $found = false;
        $admins = 0;
        $oldRole = 0;
        foreach ($accounts as $account) {
            $admins += (int)$account['role_id'] === 1 ? 1 : 0;
            if ((int)$account['user_id'] === $target) {
                $found = true;
                $oldRole = (int)$account['role_id'];
            }
        }
        if (!$found || !adminQuery('SELECT role_id FROM Roles WHERE role_id = ?', 'i', [$role])->get_result()->fetch_assoc()) {
            throw new InvalidArgumentException('Choose an existing user and role.');
        }
        if ($role !== 1 && ($target === (int)$_SESSION['user_id'] || ($oldRole === 1 && $admins <= 1))) {
            throw new InvalidArgumentException('Keep your own administrator role and at least one administrator.');
        }
        adminQuery('UPDATE Users SET role_id = ? WHERE user_id = ?', 'ii', [$role, $target]);
        return 'User role saved. The new role applies on the account’s next page request.';
    });
}
$roles = adminQuery('SELECT role_id, role_type FROM Roles ORDER BY role_id')->get_result()->fetch_all(MYSQLI_ASSOC);
$users = adminQuery('SELECT u.user_id, u.role_id, cr.username FROM Users u JOIN Credentials cr ON cr.user_id = u.user_id ORDER BY cr.username')->get_result()->fetch_all(MYSQLI_ASSOC);
adminStart('Set User Roles');
?>
<table class="table table-striped mt-4">
    <thead><tr><th>Username</th><th>Role</th></tr></thead>
    <tbody>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?= adminEscape($user['username']) ?></td>
                <td>
                    <form method="post" class="d-flex gap-2">
                        <?php adminToken(); ?>
                        <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
                        <select name="role_id" class="form-select" aria-label="Role for <?= adminEscape($user['username']) ?>" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= (int)$role['role_id'] ?>" <?= (int)$user['role_id'] === (int)$role['role_id'] ? 'selected' : '' ?>><?= adminEscape($role['role_type']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline-primary">Save</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php adminEnd(); ?>
