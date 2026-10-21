<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_helpers.php';

Auth::requirePermission('roles.manage');

$pageTitle = 'Tambah Role';
$db = Database::connect();
seed_default_role_permissions($db);

$reserved = ['super_admin', 'owner'];

if (is_post()) {
    if (!CSRF::verify()) {
        flash('error', 'Token keamanan tidak valid.');
        redirect('admin/roles/create.php');
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $slugInput = trim((string)($_POST['slug'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $copyFrom = (int)($_POST['copy_from'] ?? 0);

    $slug = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', $slugInput !== '' ? $slugInput : $name));
    $slug = trim($slug, '_');

    if ($name === '' || $slug === '') {
        flash('error', 'Nama role wajib diisi.');
        redirect('admin/roles/create.php');
    }
    if (in_array($slug, $reserved, true)) {
        flash('error', 'Slug "' . $slug . '" dipakai sistem. Pilih slug lain.');
        redirect('admin/roles/create.php');
    }

    try {
        $chk = $db->prepare('SELECT id FROM roles WHERE slug = ? LIMIT 1');
        $chk->execute([$slug]);
        if ($chk->fetchColumn()) {
            flash('error', 'Slug role sudah dipakai. Gunakan slug lain.');
            redirect('admin/roles/create.php');
        }

        $db->beginTransaction();
        $db->prepare('INSERT INTO roles (name, slug, description) VALUES (?,?,?)')
           ->execute([$name, $slug, $description !== '' ? $description : null]);
        $roleId = (int)$db->lastInsertId();

        // salin akses dari role lain bila dipilih, kalau tidak: dashboard saja
        $permissions = ['dashboard.view'];
        if ($copyFrom > 0) {
            $stmt = $db->prepare('SELECT permission_key FROM role_permissions WHERE role_id = ? AND is_allowed = 1');
            $stmt->execute([$copyFrom]);
            $copied = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            if ($copied) $permissions = $copied;
        }
        $permissions = array_values(array_intersect($permissions, all_permission_keys()));
        $ins = $db->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_key, is_allowed) VALUES (?,?,1)');
        foreach ($permissions as $key) $ins->execute([$roleId, $key]);

        $db->commit();
        Auth::refreshPermissions();
        Auth::log('create_role', 'Menambah role ' . $name . ' (' . $slug . ')');
        flash('success', 'Role "' . $name . '" dibuat. Atur aksesnya lewat tombol Atur Akses.');
        redirect('admin/roles/permissions.php?id=' . $roleId);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        flash('error', 'Gagal membuat role: ' . $e->getMessage());
    }
}

$roles = $db->query('SELECT id, name, slug FROM roles ORDER BY id ASC')->fetchAll() ?: [];
require_once __DIR__ . '/../../layouts/admin-header.php';
require_once __DIR__ . '/../../layouts/admin-sidebar.php';
?>
<main class="admin-main roles-wrap">
    <div class="roles-head">
        <div>
            <h1>Tambah Role</h1>
            <p>Buat role baru (mis. Master Kurir) lalu tentukan menu yang boleh dibuka.</p>
        </div>
        <a class="roles-btn" href="<?= base_url('admin/roles/index.php'); ?>"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>

    <section class="roles-panel">
        <form method="POST" class="premium-form">
            <?= CSRF::input(); ?>
            <div class="form-grid">
                <div class="form-group"><label>Nama Role</label><input name="name" required placeholder="Contoh: Master Kurir"></div>
                <div class="form-group"><label>Slug</label><input name="slug" placeholder="otomatis dari nama (huruf kecil, garis bawah)"></div>
                <div class="form-group"><label>Deskripsi</label><input name="description" placeholder="Tugas role ini"></div>
                <div class="form-group"><label>Salin akses dari role</label>
                    <select name="copy_from">
                        <option value="0">Tidak menyalin (hanya Dashboard)</option>
                        <?php foreach ($roles as $r): ?>
                            <?php if (in_array($r['slug'], ['super_admin'], true)) continue; ?>
                            <option value="<?= (int)$r['id']; ?>"><?= e($r['name']); ?> (<?= e($r['slug']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <small>Praktis: buat role baru dengan akses mirip role yang sudah ada.</small>
                </div>
            </div>
            <div class="form-actions">
                <a class="btn-soft" href="<?= base_url('admin/roles/index.php'); ?>">Batal</a>
                <button class="btn-premium"><i class="bi bi-save"></i> Simpan Role</button>
            </div>
        </form>
    </section>
</main>
<?php require_once __DIR__ . '/../../layouts/admin-footer.php'; ?>
