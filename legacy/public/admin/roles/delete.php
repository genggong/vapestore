<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_helpers.php';

Auth::requirePermission('roles.manage');

if (!is_post() || !CSRF::verify()) {
    flash('error', 'Permintaan hapus role tidak valid.');
    legacy_redirect('admin/roles/index.php');
}

$db = Database::connect();
$id = (int)($_POST['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM roles WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$role = $stmt->fetch();

if (!$role) {
    flash('error', 'Role tidak ditemukan.');
    legacy_redirect('admin/roles/index.php');
}
if (in_array($role['slug'], ['super_admin', 'owner'], true)) {
    flash('error', 'Role sistem (' . $role['name'] . ') tidak boleh dihapus.');
    legacy_redirect('admin/roles/index.php');
}

try {
    $u = $db->prepare('SELECT COUNT(*) FROM users WHERE role_id = ?');
    $u->execute([$id]);
    $users = (int)$u->fetchColumn();
    if ($users > 0) {
        flash('error', 'Role masih dipakai ' . $users . ' user. Pindahkan user ke role lain dulu.');
        legacy_redirect('admin/roles/index.php');
    }

    $db->beginTransaction();
    $db->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM roles WHERE id = ?')->execute([$id]);
    $db->commit();

    Auth::refreshPermissions();
    Auth::log('delete_role', 'Menghapus role ' . $role['name'] . ' (' . $role['slug'] . ')');
    flash('success', 'Role "' . $role['name'] . '" dihapus.');
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    flash('error', 'Gagal menghapus role: ' . $e->getMessage());
}
legacy_redirect('admin/roles/index.php');
