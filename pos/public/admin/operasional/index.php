<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
Auth::guard_finance_operational();
$pageTitle = 'Input Operasional';
$db = Database::connect();

function op_table_exists(PDO $db, string $table): bool
{
    if (!in_array($table, ['finance_categories','finance_transactions'], true)) return false;
    try { $db->query("SELECT 1 FROM `{$table}` LIMIT 1"); return true; } catch (Throwable $e) { return false; }
}

// Tabel finance wajib ada (akan dibuat saat menu Keuangan dibuka). Dipastikan di sini bila user kasir
if (!op_table_exists($db, 'finance_categories') || !op_table_exists($db, 'finance_transactions')) {
    // coba install lewat file install keuangan
    if (is_file(__DIR__ . '/../finance/install.php')) { require_once __DIR__ . '/../finance/install.php'; }
    if (!op_table_exists($db, 'finance_transactions')) {
        flash('error', 'Modul keuangan belum siap. Buka menu Keuangan sekali oleh admin.');
        redirect('dashboard.php');
    }
}

// pastikan kategori Operasional ada (type=expense)
$catId = (int) $db->query("SELECT id FROM finance_categories WHERE name='Operasional' AND type='expense' AND is_active=1 LIMIT 1")->fetchColumn();
if ($catId <= 0) {
    try {
        $db->prepare("INSERT INTO finance_categories (name,type,is_active,created_at) VALUES ('Operasional','expense',1,NOW())")->execute();
        $catId = (int) $db->lastInsertId();
    } catch (Throwable $e) {}
}

if (is_post()) {
    try {
        if (!CSRF::verify()) throw new RuntimeException('Token keamanan tidak valid.');
        $title = trim((string) ($_POST['title'] ?? ''));
        $amount = (float) ($_POST['amount'] ?? 0);
        $description = trim((string) ($_POST['description'] ?? ''));
        $date = trim((string) ($_POST['transaction_date'] ?? date('Y-m-d')));
        $branchId = max(0, (int) ($_POST['branch_id'] ?? 0));
        if ($amount <= 0 || $title === '') throw new RuntimeException('Judul dan nominal wajib diisi.');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

        $cols = 'category_id,user_id,type,amount,title,description,transaction_date';
        $vals = [(int) ($catId ?: $catId), (int) Auth::id(), 'expense', $amount, $title, $description !== '' ? $description : null, $date];
        // simpan branch_id bila kolom ada
        $hasBranch = false;
        try { $hasBranch = (bool) $db->query("SHOW COLUMNS FROM finance_transactions LIKE 'branch_id'")->fetchColumn(); } catch (Throwable $e) {}
        if ($hasBranch && $branchId > 0) { $cols .= ',branch_id'; $vals[] = $branchId; }
        // simpan reference_type agar masuk akuntansi sebagai beban operasional
        $hasRef = false;
        try { $hasRef = (bool) $db->query("SHOW COLUMNS FROM finance_transactions LIKE 'reference_type'")->fetchColumn(); } catch (Throwable $e) {}
        if ($hasRef) { $cols .= ',reference_type'; $vals[] = 'operational'; }

        $ph = implode(',', array_fill(0, count($vals), '?'));
        $db->prepare("INSERT INTO finance_transactions ($cols) VALUES ($ph)")->execute($vals);

        Auth::log('operational_create', 'Input operasional: ' . $title . ' Rp ' . number_format($amount, 0, ',', '.'));
        flash('success', 'Beban operasional tercatat dan masuk ke menu Keuangan & Akuntansi.');
    } catch (Throwable $e) {
        flash('error', 'Gagal menyimpan: ' . $e->getMessage());
    }
    redirect('admin/operasional/index.php');
}

// riwayat bulan berjalan
$month = date('Y-m');
$items = [];
try {
    $st = $db->prepare("SELECT ft.*, u.name user_name FROM finance_transactions ft LEFT JOIN users u ON u.id=ft.user_id
        WHERE ft.type='expense' AND DATE_FORMAT(COALESCE(ft.transaction_date,ft.created_at),'%Y-%m')=? ORDER BY COALESCE(ft.transaction_date,ft.created_at) DESC LIMIT 30");
    $st->execute([$month]);
    $items = $st->fetchAll() ?: [];
} catch (Throwable $e) {}
$total = 0.0;
foreach ($items as $it) $total += (float) $it['amount'];

require_once __DIR__ . '/../../layouts/admin-header.php';
require_once __DIR__ . '/../../layouts/admin-sidebar.php';
?>
<link rel="stylesheet" href="<?= base_url('assets/css/finance.css?v=' . APP_ASSET_VERSION); ?>">
<main class="admin-main finance-wrap">
    <header class="admin-topbar page-topbar">
        <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
        <div class="topbar-title">
            <h1>Input Operasional</h1>
            <p>Catat beban operasional (listrik, gaji, galon, perlengkapan). Otomatis masuk menu Keuangan & Akuntansi. Ini bukan pembelian stok.</p>
        </div>
    </header>

    <?php if ($f = flash('success')): ?><div class="alert alert-success mt-3"><?= e($f); ?></div><?php endif; ?>
    <?php if ($f = flash('error')): ?><div class="alert alert-danger mt-3"><?= e($f); ?></div><?php endif; ?>

    <div class="row g-4 mt-1">
        <div class="col-lg-5">
            <section class="premium-card">
                <div class="card-head"><h3>Catat Beban</h3><p>Total bulan ini: <b><?= rupiah($total); ?></b></p></div>
                <form method="post" class="premium-form">
                    <?= CSRF::input(); ?>
                    <div class="form-group">
                        <label>Jenis Beban</label>
                        <select name="title" required>
                            <option value="">— Pilih —</option>
                            <?php foreach (['Listrik','Air / Galon','Gaji Karyawan','Internet / Wifi','Sewa Tempat','Perlengkapan Toko','Kebersihan','Transportasi','Lainnya'] as $opt): ?>
                                <option value="<?= e($opt); ?>"><?= e($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nominal (Rp)</label>
                        <input type="number" name="amount" min="1" step="1000" required placeholder="cth: 150000">
                    </div>
                    <div class="form-group">
                        <label>Keterangan</label>
                        <input type="text" name="description" maxlength="180" placeholder="Opsional — cth: token listrik 100rb">
                    </div>
                    <div class="form-group">
                        <label>Tanggal</label>
                        <input type="date" name="transaction_date" value="<?= e(date('Y-m-d')); ?>" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-premium"><i class="bi bi-check2-circle"></i> Simpan</button>
                    </div>
                    <p style="margin-top:10px;color:#64748b;font-size:12px">Beban <b>tidak</b> memakai kategori pembelian stok, jadi tidak menambah HPP sekaligus tidak digandakan sebagai beban akuntansi.</p>
                </form>
            </section>
        </div>

        <div class="col-lg-7">
            <section class="premium-card">
                <div class="card-head"><h3>Beban Bulan Ini</h3><p><?= count($items); ?> catatan</p></div>
                <div class="table-responsive">
                    <table class="premium-table">
                        <thead><tr><th>Tanggal</th><th>Beban</th><th>Keterangan</th><th>Oleh</th><th style="text-align:right">Nominal</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td><?= e(date('d/m/Y', strtotime((string) ($it['transaction_date'] ?: $it['created_at'])))); ?></td>
                                <td><?= e($it['title']); ?></td>
                                <td><?= e((string) ($it['description'] ?? '')); ?></td>
                                <td><?= e((string) ($it['user_name'] ?? '-')); ?></td>
                                <td style="text-align:right"><?= rupiah($it['amount']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$items): ?><tr><td colspan="5" class="text-muted">Belum ada beban operasional bulan ini.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../../layouts/admin-footer.php'; ?>
