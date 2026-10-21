<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/_helpers.php';
Auth::requireRole(['super_admin','owner']);
$db = Database::connect();
categories_ensure_image_column($db);
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM categories WHERE id=?"); $stmt->execute([$id]); $cat = $stmt->fetch();
if (!$cat) { flash('error','Kategori tidak ditemukan.'); redirect('admin/categories/index.php'); }
$pageTitle = 'Edit Kategori';

if (is_post()) {
    if (!CSRF::verify()) { flash('error','Token tidak valid.'); redirect('admin/categories/edit.php?id='.$id); }
    $name = trim($_POST['name'] ?? ''); $slug = slugify($_POST['slug'] ?: $name);
    $icon = trim($_POST['icon'] ?? 'bi-box-seam'); $color = trim($_POST['color'] ?? '#111111');
    $sort = (int)($_POST['sort_order'] ?? 0); $active = isset($_POST['is_active']) ? 1 : 0;
    if ($name === '') { flash('error','Nama kategori wajib diisi.'); redirect('admin/categories/edit.php?id='.$id); }
    try {
        $image = trim((string)($cat['image'] ?? ''));
        if (!empty($_POST['remove_image'])) $image = '';
        $uploaded = upload_category_image($_FILES['image'] ?? []);
        if ($uploaded !== null) $image = $uploaded;
        $up = $db->prepare("UPDATE categories SET name=?, slug=?, icon=?, color=?, image=?, sort_order=?, is_active=? WHERE id=?");
        $up->execute([$name,$slug,$icon,$color,($image !== '' ? $image : null),$sort,$active,$id]);
        Auth::log('update_category','Mengubah kategori '.$name . ($uploaded ? ' (foto baru)' : ($image === '' ? ' (foto dihapus)' : '')));
        flash('success','Kategori berhasil diperbarui.');
        redirect('admin/categories/index.php');
    } catch(Throwable $e){ flash('error','Gagal update: '.$e->getMessage()); }
}
require_once __DIR__ . '/../../layouts/admin-header.php'; require_once __DIR__ . '/../../layouts/admin-sidebar.php';
$preview = category_image_url($cat['image'] ?? '');
?>
<main class="admin-main"><header class="admin-topbar"><button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button><div class="topbar-title"><h1>Edit Kategori</h1><p>Perbarui kategori produk.</p></div></header>
<section class="premium-card mt-4"><form method="POST" enctype="multipart/form-data" class="premium-form"><?= CSRF::input(); ?>
<div class="form-grid">
  <div class="form-group"><label>Nama Kategori</label><input name="name" value="<?= e($cat['name']); ?>" required></div>
  <div class="form-group"><label>Slug</label><input name="slug" value="<?= e($cat['slug']); ?>"></div>
  <div class="form-group"><label>Foto Kategori</label>
    <?php if ($preview !== ''): ?><div style="margin:.35rem 0 .5rem"><img src="<?= e($preview); ?>" alt="<?= e($cat['name']); ?>" style="width:84px;height:84px;object-fit:cover;border-radius:14px;border:1px solid #e5e5e5"></div>
    <label class="switch-line" style="font-weight:400"><input type="checkbox" name="remove_image" value="1"> Hapus foto, kembali pakai icon</label><?php endif; ?>
    <input type="file" name="image" accept="image/jpeg,image/png,image/webp"><small>Pilih file baru untuk mengganti foto (JPG/PNG/WEBP maks 4MB).</small>
  </div>
  <div class="form-group"><label>Icon Bootstrap (cadangan)</label><input name="icon" value="<?= e($cat['icon']); ?>"><small>Dipakai hanya bila foto kosong.</small></div>
  <div class="form-group"><label>Warna</label><input type="color" name="color" value="<?= e($cat['color']); ?>"></div>
  <div class="form-group"><label>Urutan</label><input type="number" name="sort_order" value="<?= e($cat['sort_order']); ?>"></div>
  <div class="form-group switch-line"><label><input type="checkbox" name="is_active" <?= $cat['is_active'] ? 'checked' : ''; ?>> Kategori aktif</label></div>
</div>
<div class="form-actions"><a class="btn-soft" href="<?= base_url('admin/categories/index.php'); ?>">Batal</a><button class="btn-premium"><i class="bi bi-save"></i> Update</button></div>
</form></section></main><?php require_once __DIR__ . '/../../layouts/admin-footer.php'; ?>
