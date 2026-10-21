<?php
/**
 * V17.0.33 — Ganti cabang aktif (admin/owner). Semua halaman yang memakai
 * branch_current_id() otomatis mengikuti: POS, transaksi, stok, gudang, laporan.
 *
 * Dipanggil dari sidebar (select cabang) atau ?branch_id=2.
 */
require_once __DIR__ . '/../../app/bootstrap.php';
Auth::requireLogin();
require_once __DIR__ . '/branches/_helpers.php';

$db = Database::connect();
branch_install($db);

if (!branch_is_admin()) {
    flash('error', 'Hanya admin/owner yang bisa mengganti cabang.');
    legacy_redirect('admin/dashboard.php');
}

$bid = (int) ($_POST['branch_id'] ?? $_GET['branch_id'] ?? 0);
$back = (string) ($_POST['redirect'] ?? $_GET['redirect'] ?? '');

// hanya izinkan path relatif di host yang sama
$back = trim($back);
if ($back === '' || $back[0] !== '/' || strpos($back, '//') === 0) {
    $back = legacy_base_url('admin/dashboard.php');
}
// jangan balik ke halaman switcher itu sendiri
if (strpos($back, 'switch-branch.php') !== false) {
    $back = legacy_base_url('admin/dashboard.php');
}

if ($bid <= 0) {
    flash('error', 'Cabang tidak dipilih.');
    header('Location: ' . $back);
    exit;
}

$st = $db->prepare("SELECT id, name FROM branches WHERE id = ? AND is_active = 1 LIMIT 1");
$st->execute([$bid]);
$branch = $st->fetch();
if (!$branch) {
    flash('error', 'Cabang tidak ditemukan atau nonaktif.');
    header('Location: ' . $back);
    exit;
}

$_SESSION['active_branch_id'] = (int) $branch['id'];
if (method_exists('Auth', 'log')) {
    try { Auth::log('switch_branch', 'Ganti cabang aktif ke ' . $branch['name']); } catch (Throwable $e) {}
}
flash('success', 'Cabang aktif: ' . $branch['name']);

header('Location: ' . $back);
exit;
