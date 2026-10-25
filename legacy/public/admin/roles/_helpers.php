<?php

function permission_groups(): array
{
    return [
        'Operasional' => [
            'icon' => 'bi-shop',
            'description' => 'Akses menu operasional toko.',
            'items' => [
                'dashboard.view' => ['Dashboard', 'Melihat ringkasan toko'],
                'pos.access' => ['POS Kasir', 'Mengakses halaman kasir'],
                'products.view' => ['Produk', 'Melihat data produk'],
                'products.manage' => ['Kelola Produk', 'Tambah, edit, hapus produk'],
                'categories.view' => ['Kategori', 'Melihat kategori produk'],
                'categories.manage' => ['Kelola Kategori', 'Tambah, edit, hapus kategori'],
                'customers.view' => ['Pelanggan', 'Melihat data pelanggan/member'],
                'customers.manage' => ['Kelola Pelanggan', 'Tambah, edit, hapus pelanggan'],
                'suppliers.view' => ['Supplier', 'Melihat data supplier'],
                'suppliers.manage' => ['Kelola Supplier', 'Tambah, edit, hapus supplier'],
                'stock.view' => ['Stok Barang', 'Melihat stok barang'],
                'stock.manage' => ['Kelola Stok', 'Stok masuk, stok keluar, adjustment'],
                'import_products.view' => ['Import Produk', 'Upload produk dari CSV'],
            ],
        ],
        'Transaksi' => [
            'icon' => 'bi-receipt-cutoff',
            'description' => 'Akses transaksi, retur, shift, dan pembelian.',
            'items' => [
                'transactions.view' => ['Transaksi', 'Melihat riwayat transaksi'],
                'transactions.manage' => ['Kelola Transaksi', 'Detail, cetak, tindakan transaksi'],
                'returns.view' => ['Retur / Refund', 'Melihat retur dan refund'],
                'returns.manage' => ['Kelola Retur', 'Buat, batalkan, cetak retur'],
                'shifts.view' => ['Shift Kasir', 'Melihat shift kasir'],
                'shifts.manage' => ['Kelola Shift', 'Buka dan tutup shift kasir'],
                'purchases.view' => ['Pembelian Supplier', 'Melihat pembelian supplier'],
                'purchases.manage' => ['Kelola Pembelian', 'Buat dan batalkan pembelian'],
            ],
        ],
        'Keuangan & Laporan' => [
            'icon' => 'bi-wallet2',
            'description' => 'Akses keuangan, laporan, backup, dan export.',
            'items' => [
                'finance.view' => ['Keuangan', 'Melihat modul keuangan'],
                'finance.manage' => ['Kelola Keuangan', 'Tambah pemasukan/pengeluaran'],
                'finance.operational' => ['Input Operasional', 'Kasir mencatat beban operasional tanpa buka menu Keuangan'],
                'reports.view' => ['Laporan', 'Melihat semua laporan'],
                'backup.view' => ['Backup & Export', 'Melihat halaman backup'],
                'backup.manage' => ['Kelola Backup', 'Buat, download, hapus backup'],
            ],
        ],
        'Marketplace & Kurir' => [
            'icon' => 'bi-bicycle',
            'description' => 'Akses laporan dan pengaturan kurir toko (ongkir).',
            'items' => [
                'courier_income.view' => ['Laporan Ongkir Toko', 'Melihat laporan ongkir kurir toko'],
                'courier_income.export' => ['Export Laporan Ongkir', 'Mengunduh laporan ongkir (CSV)'],
            ],
        ],
        'Sistem' => [
            'icon' => 'bi-gear',
            'description' => 'Akses sistem, user, role, log, dan pengaturan.',
            'items' => [
                'notifications.view' => ['Notifikasi', 'Melihat notifikasi sistem'],
                'logs.view' => ['Activity Log', 'Melihat log aktivitas'],
                'users.view' => ['User', 'Melihat daftar user'],
                'users.manage' => ['Kelola User', 'Tambah, edit, hapus, reset password'],
                'roles.view' => ['Role & Akses', 'Melihat role dan akses'],
                'roles.manage' => ['Kelola Role', 'Mengubah hak akses role'],
                'settings.view' => ['Pengaturan', 'Melihat pengaturan toko'],
                'settings.manage' => ['Kelola Pengaturan', 'Menyimpan pengaturan toko'],
            ],
        ],
    ];
}

function all_permission_keys(): array
{
    $keys = [];
    foreach (permission_groups() as $group) {
        foreach ($group['items'] as $key => $meta) {
            $keys[] = $key;
        }
    }
    return $keys;
}

function ensure_role_permissions_table(PDO $db): void
{
    $db->exec("
        CREATE TABLE IF NOT EXISTS role_permissions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            role_id INT NOT NULL,
            permission_key VARCHAR(120) NOT NULL,
            is_allowed TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY role_permission_unique (role_id, permission_key),
            INDEX idx_role_permissions_role (role_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    // FK dipasang TERPISAH & best-effort: tipe kolom induk di dump lama bisa beda signedness,
    // dan dulu itu membuat SELURUH CREATE TABLE gagal sehingga tabel modul ini tidak pernah dibuat.
    // Sekarang tabel + index pasti terbentuk; FK hanya ditambahkan bila tipe kolomnya cocok.
    foreach ([
        ['fk_role_permissions_role', 'role_id', 'roles', 'id', 'CASCADE'],
    ] as [$fkName, $fkCol, $refTable, $refCol, $onDelete]) {
        try {
            $ada = (int)$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='role_permissions' AND CONSTRAINT_NAME='{$fkName}'")->fetchColumn();
            if ($ada === 0) {
                $db->exec("ALTER TABLE `role_permissions` ADD CONSTRAINT `{$fkName}` FOREIGN KEY (`{$fkCol}`) REFERENCES `{$refTable}`(`{$refCol}`) ON DELETE {$onDelete}");
            }
        } catch (Throwable $e) { /* tipe tidak cocok: tabel & index tetap ada */ }
    }
}

function seed_default_role_permissions(PDO $db): void
{
    ensure_role_permissions_table($db);

    $count = (int) $db->query("SELECT COUNT(*) FROM role_permissions")->fetchColumn();
    if ($count > 0) {
        // V17.0.25: permission baru tetap masuk ke Super Admin & Owner walau tabel sudah terisi.
        owner_permission_topup($db);
        return;
    }

    $roles = $db->query("SELECT id, slug FROM roles")->fetchAll();
    $all = all_permission_keys();

    $ownerPermissions = array_values(array_diff($all, [
        'roles.manage'
    ]));

    $cashierPermissions = [
        'dashboard.view',
        'pos.access',
        'transactions.view',
        'customers.view',
        'customers.manage',
        'shifts.view',
        'shifts.manage',
        'returns.view',
        'finance.operational',
    ];

    $insert = $db->prepare("INSERT INTO role_permissions (role_id, permission_key, is_allowed) VALUES (?, ?, 1)");

    foreach ($roles as $role) {
        $slug = $role['slug'];
        $roleId = (int) $role['id'];

        if ($slug === 'super_admin') {
            $permissions = $all;
        } elseif ($slug === 'owner') {
            $permissions = $ownerPermissions;
        } elseif ($slug === 'cashier') {
            $permissions = $cashierPermissions;
        } else {
            $permissions = ['dashboard.view'];
        }

        foreach ($permissions as $permission) {
            $insert->execute([$roleId, $permission]);
        }
    }

    owner_permission_topup($db);
}

/**
 * V17.0.25: hak akses baru otomatis ikut untuk Super Admin & Owner.
 * Dulu seed berhenti bila tabel sudah terisi, sehingga permission BARU tidak pernah masuk
 * dan halaman yang tadinya owner-only jadi tidak bisa dibuka role mana pun.
 */
function owner_permission_topup(PDO $db): void
{
    try {
        $all = all_permission_keys();
        $roles = $db->query("SELECT id, slug FROM roles WHERE slug IN ('super_admin','owner')")->fetchAll() ?: [];
        if (!$roles) return;
        $ins = $db->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_key, is_allowed) VALUES (?, ?, 1)");
        foreach ($roles as $role) {
            foreach ($all as $key) {
                if ($role['slug'] === 'owner' && $key === 'roles.manage') continue;
                $ins->execute([(int)$role['id'], $key]);
            }
        }
        // V17.0.48: kasir mendapat 'finance.operational' (input beban) tanpa hak buka menu Keuangan.
        try {
            $cas = $db->query("SELECT id, slug FROM roles WHERE slug='cashier'")->fetchAll() ?: [];
            foreach ($cas as $role) {
                $ins->execute([(int)$role['id'], 'finance.operational']);
            }
        } catch (Throwable $e) {}
        if (class_exists('Auth') && in_array(Auth::role(), ['super_admin', 'owner'], true)) {
            Auth::refreshPermissions();
        }
    } catch (Throwable $e) {
        error_log('[roles][topup] ' . $e->getMessage());
    }
}

function role_permission_keys(PDO $db, int $roleId): array
{
    ensure_role_permissions_table($db);

    $stmt = $db->prepare("SELECT permission_key FROM role_permissions WHERE role_id = ? AND is_allowed = 1");
    $stmt->execute([$roleId]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}
