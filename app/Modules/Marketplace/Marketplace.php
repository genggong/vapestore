<?php
require_once __DIR__ . '/MarketplaceV15.php';
require_once __DIR__ . '/MarketplaceV16.php';
require_once __DIR__ . '/SchemaDoctor.php';
require_once __DIR__ . '/StoreCourier.php';
require_once __DIR__ . '/StorePickup.php';
require_once __DIR__ . '/CasaKuPayment.php';

/**
 * Marketplace core bridge for the legacy POS.
 * - RajaOngkir Shipping Cost API (Komerce /api/v1)
 * - Pakasir API v2 (marketplace credential separated from POS)
 * - RajaOngkir Shipping Delivery / Komship (optional)
 * - Online orders tied to the existing products/branch stock tables
 */

function marketplace_table_exists(PDO $db, string $table): bool
{
    // Cache INFORMATION_SCHEMA checks for the lifetime of one request. Several
    // pages call this helper dozens/hundreds of times while rendering catalogs.
    static $cache = [];
    $key = spl_object_id($db) . ':' . $table;
    if (!empty($cache[$key])) return true;

    try {
        $stmt = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
        $stmt->execute([$table]);
        if ((bool)$stmt->fetchColumn()) return $cache[$key] = true;
    } catch (Throwable $e) {
        // Continue to a direct, read-only fallback below.
    }
    try {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) return false;
        $db->query("SELECT 1 FROM `{$table}` LIMIT 1");
        return $cache[$key] = true;
    } catch (Throwable $e) {
        return false;
    }
}

function marketplace_column_exists(PDO $db, string $table, string $column): bool
{
    static $cache = [];
    $key = spl_object_id($db) . ':' . $table . ':' . $column;
    if (!empty($cache[$key])) return true;

    try {
        $stmt = $db->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
        $stmt->execute([$table, $column]);
        if ((bool)$stmt->fetchColumn()) return $cache[$key] = true;
    } catch (Throwable $e) {
        // Fallback below.
    }
    try {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) return false;
        $db->query("SELECT `{$column}` FROM `{$table}` LIMIT 0");
        return $cache[$key] = true;
    } catch (Throwable $e) {
        return false;
    }
}

function marketplace_setting_set(PDO $db, string $key, string $value): void
{
    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$key, $value]);
}


/**
 * Marketplace theme system V15.8.
 * One palette is used by the storefront, courier portal and (optionally)
 * the internal POS/admin shell so the interface does not end up with
 * mixed purple/red/green accents after the merchant changes the color.
 */
function marketplace_theme_presets(): array
{
    return [
        'purple' => ['name' => 'Ungu Modern', 'primary' => '#7C3AED', 'secondary' => '#5B21B6', 'accent' => '#A855F7'],
        'green' => ['name' => 'Hijau Emerald', 'primary' => '#16A34A', 'secondary' => '#166534', 'accent' => '#22C55E'],
        'teal' => ['name' => 'Teal', 'primary' => '#0D9488', 'secondary' => '#115E59', 'accent' => '#14B8A6'],
        'blue' => ['name' => 'Biru Ocean', 'primary' => '#2563EB', 'secondary' => '#1D4ED8', 'accent' => '#0EA5E9'],
        'red' => ['name' => 'Merah', 'primary' => '#DC2626', 'secondary' => '#991B1B', 'accent' => '#EF4444'],
        'orange' => ['name' => 'Oranye', 'primary' => '#EA580C', 'secondary' => '#9A3412', 'accent' => '#F97316'],
        'pink' => ['name' => 'Pink', 'primary' => '#DB2777', 'secondary' => '#9D174D', 'accent' => '#EC4899'],
        'gold' => ['name' => 'Gold', 'primary' => '#CA8A04', 'secondary' => '#854D0E', 'accent' => '#EAB308'],
        'graphite' => ['name' => 'Graphite', 'primary' => '#334155', 'secondary' => '#0F172A', 'accent' => '#475569'],
        'monochrome' => ['name' => 'Hitam Putih (Monokrom)', 'primary' => '#111111', 'secondary' => '#000000', 'accent' => '#333333'],
        'custom' => ['name' => 'Custom', 'primary' => '#7C3AED', 'secondary' => '#5B21B6', 'accent' => '#A855F7'],
    ];
}

function marketplace_theme_templates(): array
{
    return [
        'modern' => 'Modern Gradient',
        'minimal' => 'Minimal Clean',
        'soft' => 'Soft Pastel',
        'bold' => 'Bold Commerce',
        'dark' => 'Dark Premium',
    ];
}

function marketplace_theme_hex(string $value, string $fallback = '#7C3AED'): string
{
    $value = strtoupper(trim($value));
    if (preg_match('/^#[0-9A-F]{6}$/', $value)) return $value;
    return strtoupper($fallback);
}

function marketplace_theme_rgb(string $hex): array
{
    $hex = ltrim(marketplace_theme_hex($hex), '#');
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

function marketplace_theme_mix(string $a, string $b, float $weight = 0.5): string
{
    $weight = max(0, min(1, $weight));
    [$ar,$ag,$ab] = marketplace_theme_rgb($a);
    [$br,$bg,$bb] = marketplace_theme_rgb($b);
    $r = (int) round($ar * (1 - $weight) + $br * $weight);
    $g = (int) round($ag * (1 - $weight) + $bg * $weight);
    $bl = (int) round($ab * (1 - $weight) + $bb * $weight);
    return sprintf('#%02X%02X%02X', $r, $g, $bl);
}

function marketplace_theme_config(): array
{
    $presets = marketplace_theme_presets();
    $templates = marketplace_theme_templates();
    $preset = strtolower(trim((string) setting('marketplace_theme_preset', 'purple')));
    if (!isset($presets[$preset])) $preset = 'purple';
    $template = strtolower(trim((string) setting('marketplace_theme_template', 'modern')));
    if (!isset($templates[$template])) $template = 'modern';

    $base = $presets[$preset];
    $primary = marketplace_theme_hex((string) setting('marketplace_theme_primary', $base['primary']), $base['primary']);
    $secondary = marketplace_theme_hex((string) setting('marketplace_theme_secondary', $base['secondary']), $base['secondary']);
    $accent = marketplace_theme_hex((string) setting('marketplace_theme_accent', $base['accent']), $base['accent']);

    // Non-custom presets always remain visually consistent even if an older
    // installation contains stale manual colors.
    if ($preset !== 'custom') {
        $primary = $base['primary'];
        $secondary = $base['secondary'];
        $accent = $base['accent'];
    }

    $soft = marketplace_theme_mix($primary, '#FFFFFF', 0.92);
    $soft2 = marketplace_theme_mix($accent, '#FFFFFF', 0.95);
    $line = marketplace_theme_mix($primary, '#FFFFFF', 0.78);
    $lineStrong = marketplace_theme_mix($primary, '#FFFFFF', 0.62);
    [$r,$g,$b] = marketplace_theme_rgb($primary);

    $darkMode = $template === 'dark';
    return [
        'preset' => $preset,
        'template' => $template,
        'primary' => $primary,
        'secondary' => $secondary,
        'accent' => $accent,
        'soft' => $soft,
        'soft2' => $soft2,
        'line' => $line,
        'line_strong' => $lineStrong,
        'rgb' => $r.','.$g.','.$b,
        'background' => $darkMode ? '#0B1220' : '#FFFFFF',
        'surface' => $darkMode ? '#111827' : '#FFFFFF',
        'ink' => $darkMode ? '#F8FAFC' : '#101827',
        'muted' => $darkMode ? '#AAB4C3' : '#667085',
        'sync_internal' => strtolower((string) setting('marketplace_theme_sync_internal', 'on')) === 'on',
    ];
}

function marketplace_theme_style_vars(?array $theme = null): string
{
    $t = $theme ?: marketplace_theme_config();
    $vars = [
        '--mp-primary' => $t['primary'],
        '--mp-primary-dark' => $t['secondary'],
        '--mp-secondary' => $t['secondary'],
        '--mp-accent' => $t['accent'],
        '--mp-soft' => $t['soft'],
        '--mp-soft-2' => $t['soft2'],
        '--mp-line' => $t['line'],
        '--mp-line-strong' => $t['line_strong'],
        '--mp-rgb' => $t['rgb'],
        '--mp-bg' => $t['background'],
        '--mp-surface' => $t['surface'],
        '--mp-ink' => $t['ink'],
        '--mp-muted' => $t['muted'],
    ];
    $out = [];
    foreach ($vars as $k => $v) $out[] = $k.':'.$v;
    return implode(';', $out).';';
}

function marketplace_install(PDO $db): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    // V15.7: prefer a marketplace metadata table instead of forcing ALTER TABLE
    // on legacy/shared-hosting POS databases. Existing weight_gram columns continue
    // to work; when absent, marketplace_product_meta stores the shipping weight.
    $metaStorageReady = function_exists('marketplace_v15_product_meta_install')
        ? marketplace_v15_product_meta_install($db)
        : false;
    if (!$metaStorageReady) {
        try {
            if (marketplace_table_exists($db, 'products') && !marketplace_column_exists($db, 'products', 'weight_gram')) {
                $db->exec("ALTER TABLE products ADD COLUMN weight_gram INT NOT NULL DEFAULT 100 AFTER unit");
            }
        } catch (Throwable $ignore) {}
    }

    try {
        $db->exec("CREATE TABLE IF NOT EXISTS online_orders (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(80) NOT NULL,
            public_token VARCHAR(96) NOT NULL,
            idempotency_key VARCHAR(96) NULL,
            branch_id BIGINT NOT NULL,
            customer_id BIGINT NULL,
            recipient_name VARCHAR(160) NOT NULL,
            recipient_phone VARCHAR(50) NOT NULL,
            recipient_email VARCHAR(190) NULL,
            address TEXT NOT NULL,
            destination_id BIGINT NOT NULL,
            destination_label VARCHAR(255) NOT NULL,
            province VARCHAR(120) NULL,
            city VARCHAR(120) NULL,
            district VARCHAR(120) NULL,
            subdistrict VARCHAR(120) NULL,
            postal_code VARCHAR(20) NULL,
            customer_note TEXT NULL,
            total_weight_gram INT NOT NULL DEFAULT 0,
            subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
            discount DECIMAL(15,2) NOT NULL DEFAULT 0,
            shipping_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
            grand_total DECIMAL(15,2) NOT NULL DEFAULT 0,
            courier_code VARCHAR(50) NOT NULL,
            courier_name VARCHAR(120) NULL,
            courier_service VARCHAR(100) NOT NULL,
            courier_description VARCHAR(255) NULL,
            shipping_etd VARCHAR(100) NULL,
            awb_number VARCHAR(120) NULL,
            payment_provider VARCHAR(40) NOT NULL DEFAULT 'pakasir',
            payment_method VARCHAR(60) NOT NULL,
            payment_txn_id VARCHAR(120) NULL,
            payment_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            payment_fee DECIMAL(15,2) NOT NULL DEFAULT 0,
            payment_total DECIMAL(15,2) NOT NULL DEFAULT 0,
            payment_qr_string MEDIUMTEXT NULL,
            payment_va_number VARCHAR(120) NULL,
            payment_link TEXT NULL,
            payment_status VARCHAR(40) NOT NULL DEFAULT 'pending',
            payment_expired_at DATETIME NULL,
            paid_at DATETIME NULL,
            order_status VARCHAR(40) NOT NULL DEFAULT 'pending_payment',
            stock_released_at DATETIME NULL,
            shipped_at DATETIME NULL,
            completed_at DATETIME NULL,
            cancelled_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_online_order_number (order_number),
            UNIQUE KEY uniq_online_order_token (public_token),
            UNIQUE KEY uniq_online_order_idempotency (idempotency_key),
            INDEX idx_online_orders_branch (branch_id),
            INDEX idx_online_orders_payment (payment_status),
            INDEX idx_online_orders_status (order_status),
            INDEX idx_online_orders_awb (awb_number),
            INDEX idx_online_orders_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $ignore) {}

    // V12: optional Shipping Delivery / Komship metadata. Kept additive for existing installations.
    $v12Columns = [
        'delivery_order_id' => "BIGINT NULL",
        'delivery_order_no' => "VARCHAR(120) NULL",
        'delivery_status' => "VARCHAR(120) NULL",
        'delivery_live_tracking_url' => "TEXT NULL",
        'delivery_origin_id' => "BIGINT NULL",
        'delivery_destination_id' => "BIGINT NULL",
        'delivery_label_file' => "VARCHAR(255) NULL",
        'delivery_pickup_date' => "DATE NULL",
        'delivery_pickup_time' => "TIME NULL",
        'delivery_pickup_vehicle' => "VARCHAR(30) NULL",
        'delivery_last_sync_at' => "DATETIME NULL",
        'delivery_created_at' => "DATETIME NULL",
        'delivery_pickup_at' => "DATETIME NULL"
    ];
    foreach ($v12Columns as $column => $definition) {
        try {
            if (!marketplace_column_exists($db, 'online_orders', $column)) {
                $db->exec("ALTER TABLE online_orders ADD COLUMN `{$column}` {$definition}");
            }
        } catch (Throwable $ignore) {}
    }

    try {
        $db->exec("CREATE TABLE IF NOT EXISTS online_order_items (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT NOT NULL,
            product_id BIGINT NOT NULL,
            product_code VARCHAR(100) NULL,
            product_name VARCHAR(190) NOT NULL,
            unit VARCHAR(50) NULL,
            qty INT NOT NULL DEFAULT 1,
            unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
            purchase_price DECIMAL(15,2) NOT NULL DEFAULT 0,
            weight_gram INT NOT NULL DEFAULT 0,
            line_total DECIMAL(15,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_online_order_items_order (order_id),
            INDEX idx_online_order_items_product (product_id),
            CONSTRAINT fk_online_order_items_order FOREIGN KEY (order_id) REFERENCES online_orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $ignore) {}

    try {
        $db->exec("CREATE TABLE IF NOT EXISTS online_order_events (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT NOT NULL,
            status VARCHAR(60) NOT NULL,
            title VARCHAR(160) NOT NULL,
            description TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_online_order_events_order (order_id),
            CONSTRAINT fk_online_order_events_order FOREIGN KEY (order_id) REFERENCES online_orders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $ignore) {}


    // V13: customer marketplace account, saved addresses, wishlist, vouchers, reviews, notifications.
    try {
        if (marketplace_table_exists($db, 'customers')) {
            if (!marketplace_column_exists($db, 'customers', 'marketplace_password_hash')) $db->exec("ALTER TABLE customers ADD COLUMN marketplace_password_hash VARCHAR(255) NULL");
            if (!marketplace_column_exists($db, 'customers', 'marketplace_registered_at')) $db->exec("ALTER TABLE customers ADD COLUMN marketplace_registered_at DATETIME NULL");
            if (!marketplace_column_exists($db, 'customers', 'marketplace_last_login_at')) $db->exec("ALTER TABLE customers ADD COLUMN marketplace_last_login_at DATETIME NULL");
        }
        if (!marketplace_column_exists($db, 'online_orders', 'voucher_code')) $db->exec("ALTER TABLE online_orders ADD COLUMN voucher_code VARCHAR(80) NULL AFTER discount");
        if (!marketplace_column_exists($db, 'online_orders', 'voucher_released_at')) $db->exec("ALTER TABLE online_orders ADD COLUMN voucher_released_at DATETIME NULL");
        if (!marketplace_column_exists($db, 'online_orders', 'points_earned')) $db->exec("ALTER TABLE online_orders ADD COLUMN points_earned INT NOT NULL DEFAULT 0");
        if (!marketplace_column_exists($db, 'online_orders', 'points_awarded_at')) $db->exec("ALTER TABLE online_orders ADD COLUMN points_awarded_at DATETIME NULL");
        // V17.0.11: penanda order online sudah dicatat ke transaksi POS (idempotent)
        if (!marketplace_column_exists($db, 'online_orders', 'pos_recorded_at')) $db->exec("ALTER TABLE online_orders ADD COLUMN pos_recorded_at DATETIME NULL");
        if (!marketplace_column_exists($db, 'online_orders', 'pos_invoice_number')) $db->exec("ALTER TABLE online_orders ADD COLUMN pos_invoice_number VARCHAR(80) NULL");
        // V17.0.11: penanda relasi transaksi POS ke order online + ongkir (dipisah di akuntansi)
        if (marketplace_table_exists($db, 'transactions')) {
            if (!marketplace_column_exists($db, 'transactions', 'reference_type')) $db->exec("ALTER TABLE transactions ADD COLUMN reference_type VARCHAR(40) NULL");
            if (!marketplace_column_exists($db, 'transactions', 'reference_id')) $db->exec("ALTER TABLE transactions ADD COLUMN reference_id BIGINT NULL");
            if (!marketplace_column_exists($db, 'transactions', 'shipping_cost')) $db->exec("ALTER TABLE transactions ADD COLUMN shipping_cost DECIMAL(15,2) NOT NULL DEFAULT 0");
        }
    } catch (Throwable $ignore) {}

    try { $db->exec("CREATE TABLE IF NOT EXISTS customer_addresses (
        id BIGINT AUTO_INCREMENT PRIMARY KEY, customer_id BIGINT NOT NULL, label VARCHAR(80) NOT NULL DEFAULT 'Alamat', recipient_name VARCHAR(160) NOT NULL,
        recipient_phone VARCHAR(50) NOT NULL, address TEXT NOT NULL, destination_id BIGINT NOT NULL, destination_label VARCHAR(255) NOT NULL,
        province VARCHAR(120) NULL, city VARCHAR(120) NULL, district VARCHAR(120) NULL, subdistrict VARCHAR(120) NULL, postal_code VARCHAR(20) NULL,
        is_default TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_customer_addresses_customer(customer_id), CONSTRAINT fk_customer_addresses_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $ignore) {}

    // products.id on the legacy POS is INT, not BIGINT. Keep the FK column type identical
    // so MySQL/MariaDB can actually create the table.
    try { $db->exec("CREATE TABLE IF NOT EXISTS marketplace_wishlists (
        id BIGINT AUTO_INCREMENT PRIMARY KEY, customer_id BIGINT NOT NULL, product_id INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_marketplace_wishlist(customer_id,product_id), INDEX idx_marketplace_wishlist_customer(customer_id),
        CONSTRAINT fk_marketplace_wishlist_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
        CONSTRAINT fk_marketplace_wishlist_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $ignore) {}

    try { $db->exec("CREATE TABLE IF NOT EXISTS marketplace_vouchers (
        id BIGINT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(80) NOT NULL, name VARCHAR(160) NOT NULL, discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'fixed',
        discount_value DECIMAL(15,2) NOT NULL DEFAULT 0, min_purchase DECIMAL(15,2) NOT NULL DEFAULT 0, max_discount DECIMAL(15,2) NOT NULL DEFAULT 0,
        usage_limit INT NOT NULL DEFAULT 0, used_count INT NOT NULL DEFAULT 0, starts_at DATETIME NULL, ends_at DATETIME NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_marketplace_voucher_code(code), INDEX idx_marketplace_voucher_active(is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $ignore) {}

    // Legacy products.id is INT. A BIGINT product_id causes errno 150 / incompatible
    // foreign-key errors on MySQL/MariaDB and left this table missing on some upgrades.
    try { $db->exec("CREATE TABLE IF NOT EXISTS marketplace_reviews (
        id BIGINT AUTO_INCREMENT PRIMARY KEY, customer_id BIGINT NOT NULL, product_id INT NOT NULL, order_id BIGINT NULL, rating TINYINT NOT NULL,
        title VARCHAR(160) NULL, review TEXT NOT NULL, status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uniq_marketplace_review_order_product(customer_id,product_id,order_id),
        INDEX idx_marketplace_reviews_product(product_id,status), CONSTRAINT fk_marketplace_review_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
        CONSTRAINT fk_marketplace_review_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
        CONSTRAINT fk_marketplace_review_order FOREIGN KEY(order_id) REFERENCES online_orders(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $ignore) {}

    try { $db->exec("CREATE TABLE IF NOT EXISTS customer_notifications (
        id BIGINT AUTO_INCREMENT PRIMARY KEY, customer_id BIGINT NOT NULL, order_id BIGINT NULL, type VARCHAR(60) NOT NULL DEFAULT 'order', title VARCHAR(160) NOT NULL,
        message TEXT NULL, is_read TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_customer_notifications(customer_id,is_read,created_at), CONSTRAINT fk_customer_notifications_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
        CONSTRAINT fk_customer_notifications_order FOREIGN KEY(order_id) REFERENCES online_orders(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $ignore) {}


    // V14 add-ons are installed only after core marketplace/customer tables exist.
    if (function_exists('marketplace_wa_install')) marketplace_wa_install($db);
    if (function_exists('marketplace_service_install')) marketplace_service_install($db);
    if (function_exists('marketplace_v15_install')) marketplace_v15_install($db);
    if (function_exists('marketplace_v16_install')) marketplace_v16_install($db);
    if (function_exists('marketplace_store_courier_install')) marketplace_store_courier_install($db);
    if (function_exists('marketplace_store_pickup_install')) marketplace_store_pickup_install($db);
}

function marketplace_ensure_secret(PDO $db, string $key): string
{
    $current = trim((string) setting($key, ''));
    if ($current !== '') return $current;
    $current = bin2hex(random_bytes(20));
    marketplace_setting_set($db, $key, $current);
    return $current;
}

/**
 * V17.0.29 — cabang pengirim untuk pesanan online (bukan pickup).
 * Utamakan cabang marketplace (setting), lalu cabang aktif lain yang mampu
 * memenuhi SELURUH isi keranjang. Kalau tidak ada yang mampu, kembalikan cabang utama
 * supaya validasi stok tetap memberi pesan error yang jelas.
 */
function marketplace_delivery_branch(PDO $db, array $rawItems = [], int $preferred = 0): int
{
    $preferred = $preferred > 0 ? $preferred : marketplace_branch_id($db);
    if (!$rawItems) return $preferred;
    $ids = [$preferred];
    try {
        $rows = $db->query("SELECT id FROM branches WHERE is_active = 1 ORDER BY is_main DESC, id ASC")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows ?: [] as $id) {
            $id = (int) $id;
            if ($id > 0 && !in_array($id, $ids, true)) $ids[] = $id;
        }
    } catch (Throwable $e) {}
    foreach ($ids as $id) {
        try {
            marketplace_cart($db, $rawItems, $id);
            return (int) $id;
        } catch (Throwable $e) {
            continue;
        }
    }
    return $preferred;
}

function marketplace_branch_id(PDO $db): int
{
    marketplace_install($db);
    $configured = (int) setting('marketplace_branch_id', '0');
    if ($configured > 0) {
        $stmt = $db->prepare("SELECT id FROM branches WHERE id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$configured]);
        if ((int) $stmt->fetchColumn() > 0) return $configured;
    }
    try {
        $id = (int) $db->query("SELECT id FROM branches WHERE is_active = 1 ORDER BY is_main DESC, id ASC LIMIT 1")->fetchColumn();
        return $id > 0 ? $id : 1;
    } catch (Throwable $e) {
        return 1;
    }
}

function marketplace_default_weight(): int
{
    return max(1, (int) setting('marketplace_default_weight_gram', '100'));
}

function marketplace_rajaongkir_courier_options(): array
{
    return [
        'jne' => 'JNE',
        'sicepat' => 'SiCepat',
        'ide' => 'ID Express',
        'sap' => 'SAP Express',
        'jnt' => 'J&T Express',
        'ninja' => 'Ninja Xpress',
        'tiki' => 'TIKI',
        'lion' => 'Lion Parcel',
        'anteraja' => 'AnterAja',
        'pos' => 'Pos Indonesia',
        'ncs' => 'NCS',
        'rex' => 'REX',
        'rpx' => 'RPX',
        'sentral' => 'Sentral Cargo',
        'star' => 'Star Cargo',
        'wahana' => 'Wahana',
        'dse' => 'DSE',
    ];
}

function marketplace_rajaongkir_config(): array
{
    $defaultCouriers = 'jne:sicepat:jnt:ninja:tiki:lion:anteraja:pos';
    return [
        'api_key' => trim((string) setting('rajaongkir_api_key', '')),
        'base_url' => 'https://rajaongkir.komerce.id/api/v1',
        'origin_id' => (int) setting('rajaongkir_origin_id', '0'),
        'origin_label' => trim((string) setting('rajaongkir_origin_label', '')),
        'origin_province_id' => (int) setting('rajaongkir_origin_province_id', '0'),
        'origin_province_name' => trim((string) setting('rajaongkir_origin_province_name', '')),
        'origin_city_id' => (int) setting('rajaongkir_origin_city_id', '0'),
        'origin_city_name' => trim((string) setting('rajaongkir_origin_city_name', '')),
        'origin_district_id' => (int) setting('rajaongkir_origin_district_id', '0'),
        'origin_district_name' => trim((string) setting('rajaongkir_origin_district_name', '')),
        'origin_subdistrict_id' => (int) setting('rajaongkir_origin_subdistrict_id', '0'),
        'origin_subdistrict_name' => trim((string) setting('rajaongkir_origin_subdistrict_name', '')),
        'origin_zip_code' => trim((string) setting('rajaongkir_origin_zip_code', '')),
        'couriers' => trim((string) setting('rajaongkir_couriers', $defaultCouriers)),
    ];
}

function marketplace_pakasir_config(PDO $db = null): array
{
    // Marketplace dan POS memakai project/credential Pakasir terpisah.
    // v17.0.9: webhook v2 menggunakan X-Secret dari halaman detail project Pakasir.
    $project = trim((string) setting('marketplace_pakasir_project', ''));
    $apiKey = trim((string) setting('marketplace_pakasir_api_key', ''));
    $webhookSecret = trim((string) setting('marketplace_pakasir_webhook_secret', ''));
    $legacyToken = trim((string) setting('pakasir_webhook_token', ''));
    if ($db && $legacyToken === '') $legacyToken = marketplace_ensure_secret($db, 'pakasir_webhook_token');
    return [
        'project' => $project,
        'api_key' => $apiKey,
        'webhook_secret' => $webhookSecret,
        'webhook_token' => $legacyToken, // fallback URL lama selama transisi.
        'base_url' => 'https://app.pakasir.com',
        'api_version' => 'v2',
    ];
}

function marketplace_rajaongkir_cache_get(string $key, int $ttl = 604800): ?array
{
    $dir = dirname(__DIR__, 3) . '/storage/cache/rajaongkir';
    $file = $dir . '/' . preg_replace('/[^a-z0-9_.-]+/i', '-', $key) . '.json';
    if (!is_file($file) || (time() - (int) @filemtime($file)) > $ttl) return null;
    $json = json_decode((string) @file_get_contents($file), true);
    return is_array($json) ? $json : null;
}

function marketplace_rajaongkir_cache_set(string $key, array $data): void
{
    $dir = dirname(__DIR__, 3) . '/storage/cache/rajaongkir';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (is_dir($dir)) @file_put_contents($dir . '/' . preg_replace('/[^a-z0-9_.-]+/i', '-', $key) . '.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function marketplace_rajaongkir_regions(string $level, int $parentId = 0, string $apiKeyOverride = ''): array
{
    $config = marketplace_rajaongkir_config();
    $apiKeyOverride = trim($apiKeyOverride);
    if ($apiKeyOverride !== '') $config['api_key'] = $apiKeyOverride;
    if ($config['api_key'] === '') throw new RuntimeException('API Key RajaOngkir belum diatur. Masukkan API Key lalu klik Muat Wilayah.');
    $level = strtolower(trim($level));
    $path = '';
    if ($level === 'province') $path = '/destination/province';
    elseif ($level === 'city' && $parentId > 0) $path = '/destination/city/' . $parentId;
    elseif ($level === 'district' && $parentId > 0) $path = '/destination/district/' . $parentId;
    elseif (in_array($level, ['subdistrict','sub-district'], true) && $parentId > 0) $path = '/destination/sub-district/' . $parentId;
    else throw new InvalidArgumentException('Level wilayah RajaOngkir tidak valid.');

    $cacheKey = $level . '-' . $parentId;
    $cached = marketplace_rajaongkir_cache_get($cacheKey);
    if ($cached !== null) return $cached;

    $json = marketplace_http_json($config['base_url'] . $path, 'GET', [
        'Accept: application/json',
        'key: ' . $config['api_key'],
    ]);
    $rows = is_array($json['data'] ?? null) ? $json['data'] : [];
    $out = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        $name = trim((string) ($row['name'] ?? $row['label'] ?? ''));
        if ($id <= 0 || $name === '') continue;
        $out[] = ['id' => $id, 'name' => $name, 'zip_code' => trim((string) ($row['zip_code'] ?? ''))];
    }
    marketplace_rajaongkir_cache_set($cacheKey, $out);
    return $out;
}

function marketplace_delivery_config(): array
{
    $environment = strtolower(trim((string) setting('marketplace_delivery_environment', 'sandbox')));
    if (!in_array($environment, ['sandbox','production'], true)) $environment = 'sandbox';
    return [
        'enabled' => setting('marketplace_delivery_enabled', 'off') === 'on',
        'environment' => $environment,
        'api_key' => trim((string) setting('marketplace_delivery_api_key', '')),
        'base_url' => $environment === 'production' ? 'https://api.collaborator.komerce.id' : 'https://api-sandbox.collaborator.komerce.id',
        'shipper_name' => trim((string) setting('marketplace_delivery_shipper_name', setting('store_name', 'Toko'))),
        'shipper_phone' => trim((string) setting('marketplace_delivery_shipper_phone', setting('store_phone', ''))),
        'shipper_email' => trim((string) setting('marketplace_delivery_shipper_email', setting('store_email', ''))),
        'shipper_address' => trim((string) setting('marketplace_delivery_shipper_address', setting('store_address', ''))),
        'default_vehicle' => trim((string) setting('marketplace_delivery_pickup_vehicle', 'Motor')) ?: 'Motor',
        'label_page' => trim((string) setting('marketplace_delivery_label_page', 'page_5')) ?: 'page_5',
        'default_length_cm' => max(1, (int) setting('marketplace_default_length_cm', '10')),
        'default_width_cm' => max(1, (int) setting('marketplace_default_width_cm', '10')),
        'default_height_cm' => max(1, (int) setting('marketplace_default_height_cm', '10')),
    ];
}

function marketplace_http_json(string $url, string $method = 'GET', array $headers = [], $body = null, bool $formEncoded = false): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Ekstensi cURL belum aktif di server.');
    }

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
    ];

    if ($body !== null) {
        if ($formEncoded) {
            $opts[CURLOPT_POSTFIELDS] = http_build_query($body, '', '&', PHP_QUERY_RFC3986);
        } else {
            $opts[CURLOPT_POSTFIELDS] = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }

    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $provider = str_contains($url,'rajaongkir') || str_contains($url,'komerce') ? 'rajaongkir' : (str_contains($url,'pakasir') ? 'pakasir' : (str_contains($url,'gateway.teknorilis.com') ? 'teknorilis' : 'external_api'));
    $event = strtoupper($method).' '.((string)(parse_url($url,PHP_URL_PATH) ?: '/'));
    if ($raw === false || $error !== '') {
        try { marketplace_v15_integration_log(Database::connect(),$provider,$event,false,$status,'','',$error ?: 'response kosong'); } catch(Throwable $ignore) {}
        throw new RuntimeException('Koneksi API gagal: ' . ($error ?: 'response kosong'));
    }

    $json = json_decode((string) $raw, true);
    if (!is_array($json)) {
        try { marketplace_v15_integration_log(Database::connect(),$provider,$event,false,$status,'','','Response bukan JSON'); } catch(Throwable $ignore) {}
        throw new RuntimeException('Response API bukan JSON yang valid.');
    }

    if ($status < 200 || $status >= 300) {
        $message = (string) ($json['message'] ?? $json['meta']['message'] ?? $json['error'] ?? ('HTTP ' . $status));
        try { marketplace_v15_integration_log(Database::connect(),$provider,$event,false,$status,'','',$message); } catch(Throwable $ignore) {}
        throw new RuntimeException($message);
    }
    try { marketplace_v15_integration_log(Database::connect(),$provider,$event,true,$status); } catch(Throwable $ignore) {}
    return $json;
}

function marketplace_rajaongkir_search(string $search, int $limit = 20, string $apiKeyOverride = ''): array
{
    $config = marketplace_rajaongkir_config();
    $apiKeyOverride = trim($apiKeyOverride);
    if ($apiKeyOverride !== '') $config['api_key'] = $apiKeyOverride;
    if ($config['api_key'] === '') throw new RuntimeException('API Key RajaOngkir belum diatur. Masukkan API Key terlebih dahulu.');
    $search = trim($search);
    if (mb_strlen($search) < 3) return [];

    $url = $config['base_url'] . '/destination/domestic-destination?search=' . rawurlencode($search) . '&limit=' . max(1, min(50, $limit)) . '&offset=0';
    $json = marketplace_http_json($url, 'GET', [
        'Accept: application/json',
        'key: ' . $config['api_key'],
    ]);

    $rows = is_array($json['data'] ?? null) ? $json['data'] : [];
    $out = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) continue;
        $out[] = [
            'id' => $id,
            'label' => trim((string) ($row['label'] ?? '')),
            'province' => trim((string) ($row['province'] ?? '')),
            'city' => trim((string) ($row['city'] ?? '')),
            'district' => trim((string) ($row['district'] ?? '')),
            'subdistrict' => trim((string) ($row['subdistrict'] ?? '')),
            'zip_code' => trim((string) ($row['zip_code'] ?? '')),
        ];
    }
    return $out;
}

function marketplace_rajaongkir_resolve_direct_destination(string $subdistrict, string $district = '', string $city = '', string $zipCode = '', string $apiKeyOverride = ''): array
{
    $query = trim(implode(' ', array_filter([$subdistrict, $district, $city])));
    if ($query === '') throw new RuntimeException('Wilayah asal belum lengkap.');
    $rows = marketplace_rajaongkir_search($query, 50, $apiKeyOverride);
    if (!$rows && trim($zipCode) !== '') $rows = marketplace_rajaongkir_search(trim($zipCode), 50, $apiKeyOverride);
    $norm = static function (string $v): string {
        $v = strtoupper(trim($v));
        $v = preg_replace('/[^A-Z0-9]+/', ' ', $v) ?: '';
        return trim(preg_replace('/\s+/', ' ', $v) ?: '');
    };
    $wanted = ['subdistrict'=>$norm($subdistrict),'district'=>$norm($district),'city'=>$norm($city),'zip_code'=>trim($zipCode)];
    $best = null; $bestScore = -1;
    foreach ($rows as $row) {
        $score = 0;
        if ($wanted['zip_code'] !== '' && (string)($row['zip_code'] ?? '') === $wanted['zip_code']) $score += 100;
        if ($wanted['subdistrict'] !== '' && $norm((string)($row['subdistrict'] ?? '')) === $wanted['subdistrict']) $score += 60;
        if ($wanted['district'] !== '' && $norm((string)($row['district'] ?? '')) === $wanted['district']) $score += 35;
        if ($wanted['city'] !== '' && $norm((string)($row['city'] ?? '')) === $wanted['city']) $score += 20;
        if ($score > $bestScore) { $bestScore = $score; $best = $row; }
    }
    if (!$best || $bestScore < 55) throw new RuntimeException('Wilayah terpilih belum dapat dipetakan ke ID ongkir. Coba pilih kelurahan/desa yang lebih spesifik.');
    return $best;
}

function marketplace_normalize_couriers(string $value): string
{
    $parts = preg_split('/[,:;\s]+/', strtolower($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $allowed = [];
    foreach ($parts as $part) {
        $part = preg_replace('/[^a-z0-9_-]/', '', $part);
        if ($part !== '' && !in_array($part, $allowed, true)) $allowed[] = $part;
    }
    return implode(':', $allowed);
}

function marketplace_rajaongkir_cost(int $destinationId, int $weightGram): array
{
    $config = marketplace_rajaongkir_config();
    if ($config['api_key'] === '') throw new RuntimeException('API Key RajaOngkir belum diatur.');
    if ($config['origin_id'] <= 0) throw new RuntimeException('Lokasi asal RajaOngkir belum dipilih di Pengaturan.');
    if ($destinationId <= 0) throw new InvalidArgumentException('Tujuan pengiriman belum valid.');
    $couriers = marketplace_normalize_couriers($config['couriers']);
    if ($couriers === '') throw new RuntimeException('Kurir RajaOngkir belum diatur.');

    $json = marketplace_http_json($config['base_url'] . '/calculate/domestic-cost', 'POST', [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
        'key: ' . $config['api_key'],
    ], [
        'origin' => $config['origin_id'],
        'destination' => $destinationId,
        'weight' => max(1, $weightGram),
        'courier' => $couriers,
        'price' => 'lowest',
    ], true);

    $rows = is_array($json['data'] ?? null) ? $json['data'] : [];
    $out = [];
    foreach ($rows as $row) {
        $cost = (float) ($row['cost'] ?? 0);
        $code = strtolower(trim((string) ($row['code'] ?? '')));
        $service = trim((string) ($row['service'] ?? ''));
        if ($cost < 0 || $code === '' || $service === '') continue;
        $out[] = [
            'code' => $code,
            'name' => trim((string) ($row['name'] ?? strtoupper($code))),
            'service' => $service,
            'description' => trim((string) ($row['description'] ?? '')),
            'cost' => $cost,
            'etd' => trim((string) ($row['etd'] ?? '')),
        ];
    }

    usort($out, static fn(array $a, array $b): int => ($a['cost'] <=> $b['cost']) ?: strcmp($a['code'], $b['code']));
    return $out;
}

function marketplace_cart(PDO $db, array $rawItems, ?int $branchId = null): array
{
    marketplace_install($db);
    $branchId = $branchId ?: marketplace_branch_id($db);
    $requested = [];
    foreach ($rawItems as $item) {
        if (!is_array($item)) continue;
        $id = (int) ($item['id'] ?? $item['product_id'] ?? 0);
        $variantId = max(0, (int) ($item['variant_id'] ?? 0));
        $qty = (int) ($item['qty'] ?? 0);
        if ($id <= 0 || $qty <= 0) continue;
        $key = $id . ':' . $variantId;
        if (!isset($requested[$key])) $requested[$key] = ['product_id'=>$id,'variant_id'=>$variantId,'qty'=>0];
        $requested[$key]['qty'] = min(999, $requested[$key]['qty'] + $qty);
    }
    if (!$requested) throw new InvalidArgumentException('Keranjang masih kosong.');

    $isMainStmt = $db->prepare("SELECT is_main FROM branches WHERE id=? LIMIT 1");
    $isMainStmt->execute([$branchId]);
    $isMain = (int)$isMainStmt->fetchColumn() === 1;
    $weightExpr = marketplace_column_exists($db, 'products', 'weight_gram') ? 'p.weight_gram' : (string) marketplace_default_weight();
    $items=[];$subtotal=0.0;$weight=0;

    foreach($requested as $req){
        $stmt=$db->prepare("SELECT p.id,p.product_code,p.name,p.unit,p.purchase_price,p.selling_price,p.is_active,{$weightExpr} AS weight_gram,COALESCE(pbs.stock,CASE WHEN ?=1 THEN p.stock ELSE 0 END,0) branch_stock FROM products p LEFT JOIN product_branch_stocks pbs ON pbs.product_id=p.id AND pbs.branch_id=? WHERE p.id=? LIMIT 1");
        $stmt->execute([$isMain?1:0,$branchId,$req['product_id']]);
        $row=$stmt->fetch();
        if(!$row || (int)$row['is_active']!==1) throw new RuntimeException('Ada produk di keranjang yang sudah tidak tersedia.');
        // V15.7: never require products.weight_gram. On older/subdomain schemas the
        // weight is read from marketplace_product_meta (or the default setting).
        $row['weight_gram'] = marketplace_v15_product_weight($db, (int)$row['id'], (int)($row['weight_gram'] ?? marketplace_default_weight()));
        $variantId=(int)$req['variant_id'];$variantName='';$variantSku='';
        $price=(float)$row['selling_price'];$purchase=(float)$row['purchase_price'];$unitWeight=max(1,(int)($row['weight_gram']?:marketplace_default_weight()));$stock=(int)$row['branch_stock'];
        if($variantId>0){
            $v=marketplace_v15_variant($db,$variantId,$branchId,false);
            if(!$v || (int)$v['product_id']!==(int)$row['id'] || (int)$v['is_active']!==1) throw new RuntimeException('Varian produk sudah tidak tersedia.');
            $variantName=(string)($v['display_name']??$v['name']);$variantSku=(string)($v['sku']?:'');$price=(float)$v['selling_price'];$purchase=(float)$v['purchase_price'];$unitWeight=max(1,(int)($v['weight_gram']?:$unitWeight));$stock=max(0,(int)($v['stock']??0));
        }
        $eff=marketplace_v15_effective_price($db,(int)$row['id'],$price,$variantId>0?$variantId:null);$price=(float)$eff['price'];
        $qty=(int)$req['qty'];if($stock<$qty)throw new RuntimeException('Stok '.$row['name'].($variantName?' - '.$variantName:'').' tidak cukup. Tersedia '.$stock.'.');
        $line=$price*$qty;
        $items[]=['product_id'=>(int)$row['id'],'variant_id'=>$variantId?:null,'variant_name'=>$variantName,'variant_sku'=>$variantSku,'product_code'=>(string)$row['product_code'],'name'=>(string)$row['name'],'unit'=>(string)($row['unit']?:'pcs'),'purchase_price'=>max(0,$purchase),'price'=>max(0,$price),'normal_price'=>(float)$eff['normal_price'],'flash_sale'=>$eff['flash'],'qty'=>$qty,'stock'=>$stock,'weight_gram'=>$unitWeight,'line_total'=>$line];
        $subtotal+=$line;$weight+=$unitWeight*$qty;
    }
    return ['branch_id'=>$branchId,'items'=>$items,'subtotal'=>$subtotal,'weight_gram'=>max(1,$weight)];
}

function marketplace_find_shipping_option(array $options, string $code, string $service, ?float $expectedCost = null): ?array
{
    $code = strtolower(trim($code));
    $service = trim($service);
    foreach ($options as $option) {
        if (strtolower((string) ($option['code'] ?? '')) !== $code) continue;
        if (strcasecmp((string) ($option['service'] ?? ''), $service) !== 0) continue;
        if ($expectedCost !== null && abs((float) ($option['cost'] ?? 0) - $expectedCost) > 0.01) continue;
        return $option;
    }
    return null;
}

function marketplace_pakasir_methods(): array
{
    // Daftar metode resmi Pakasir API v2 (13 Okt 2026).
    return [
        'qris' => 'QRIS',
        'bri_va' => 'BRI Virtual Account',
        'bni_va' => 'BNI Virtual Account',
        'cimb_niaga_va' => 'CIMB Niaga Virtual Account',
        'danamon_va' => 'Danamon Virtual Account',
        'maybank_va' => 'Maybank Virtual Account',
        'bnc_va' => 'Bank Neo Commerce Virtual Account',
        'bsi_va' => 'BSI Virtual Account',
        'muamalat_va' => 'Bank Muamalat Virtual Account',
        'ocbc_va' => 'OCBC Virtual Account',
    ];
}

function marketplace_pakasir_create(PDO $db, string $orderId, int $amount, string $method): array
{
    $methods = marketplace_pakasir_methods();
    if (!isset($methods[$method])) throw new InvalidArgumentException('Metode pembayaran tidak didukung.');
    $config = marketplace_pakasir_config($db);
    if ($config['project'] === '' || $config['api_key'] === '') {
        throw new RuntimeException('Slug Project / API Key pembayaran belum diatur.');
    }
    if ($amount <= 0) throw new InvalidArgumentException('Nominal pembayaran tidak valid.');
    if ($method === 'qris' && ($amount < 500 || $amount > 10000000)) {
        throw new InvalidArgumentException('Nominal QRIS Pakasir v2 harus Rp500 s.d. Rp10.000.000.');
    }
    if ($method !== 'qris' && ($amount < 10000 || $amount > 50000000)) {
        throw new InvalidArgumentException('Nominal Virtual Account Pakasir v2 harus Rp10.000 s.d. Rp50.000.000.');
    }

    $url = $config['base_url'] . '/api/v2/create-transaction/' . rawurlencode($config['project']) . '/' . rawurlencode($orderId);
    $json = marketplace_http_json($url, 'POST', [
        'Accept: application/json',
        'Content-Type: application/json',
        'X-Api-Key: ' . $config['api_key'],
    ], [
        'method' => $method,
        'amount' => $amount,
    ]);

    if (empty($json['txn_id'])) {
        throw new RuntimeException((string) ($json['message'] ?? 'Pembayaran gagal dibuat.'));
    }

    return [
        'txn_id' => (string) ($json['txn_id'] ?? ''),
        'project' => (string) ($json['project'] ?? $config['project']),
        'order_id' => (string) ($json['order_id'] ?? $orderId),
        'amount' => (int) ($json['amount'] ?? $amount),
        'fee' => (int) ($json['fee'] ?? 0),
        'total_payment' => (int) ($json['total_payment'] ?? ($json['amount'] ?? $amount)),
        'payment_method' => (string) ($json['payment_method'] ?? $method),
        'qr_string' => (string) ($json['qr_string'] ?? ''),
        'va_number' => (string) ($json['va_number'] ?? ''),
        'payment_link' => (string) ($json['payment_link'] ?? ''),
        'expired_at' => (string) ($json['expired_at'] ?? ''),
        'status' => strtolower((string) ($json['status'] ?? 'pending')),
        'is_sandbox' => (bool) ($json['is_sandbox'] ?? false),
        'raw' => $json,
    ];
}

function marketplace_datetime_from_iso(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') return null;
    try {
        $dt = new DateTimeImmutable($value);
        $dt = $dt->setTimezone(new DateTimeZone((string) setting('timezone', 'Asia/Jakarta')));
        return $dt->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}

function marketplace_generate_order_number(): string
{
    return 'WEB-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
}

function marketplace_add_event(PDO $db, int $orderId, string $status, string $title, string $description = ''): void
{
    $eventId = null;
    try {
        $stmt = $db->prepare("INSERT INTO online_order_events (order_id,status,title,description,created_at) VALUES (?,?,?,?,NOW())");
        $stmt->execute([$orderId, $status, $title, $description]);
        $eventId = (int)$db->lastInsertId();
    } catch (Throwable $ignore) {}
    try {
        $stmt = $db->prepare("SELECT customer_id FROM online_orders WHERE id=? LIMIT 1");
        $stmt->execute([$orderId]);
        $customerId = (int) $stmt->fetchColumn();
        if ($customerId > 0 && marketplace_table_exists($db, 'customer_notifications')) {
            $n = $db->prepare("INSERT INTO customer_notifications(customer_id,order_id,type,title,message,is_read,created_at) VALUES(?,?,'order',?,?,0,NOW())");
            $n->execute([$customerId,$orderId,$title,$description]);
        }
    } catch (Throwable $ignore) {}
    try {
        if (function_exists('marketplace_wa_enqueue_order_event')) {
            marketplace_wa_enqueue_order_event($db,$orderId,$status,$title,$description,$eventId);
        }
    } catch (Throwable $ignore) {}
}

function marketplace_get_order(PDO $db, string $orderNumber, ?string $token = null): ?array
{
    marketplace_install($db);
    $sql = "SELECT o.*,b.name AS branch_name,b.address AS branch_address,b.phone AS branch_phone FROM online_orders o LEFT JOIN branches b ON b.id=o.branch_id WHERE o.order_number=?";
    $params = [$orderNumber];
    if ($token !== null) {
        $sql .= " AND o.public_token=?";
        $params[] = $token;
    }
    $sql .= " LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

function marketplace_order_items(PDO $db, int $orderId): array
{
    $stmt = $db->prepare("SELECT * FROM online_order_items WHERE order_id=? ORDER BY id ASC");
    $stmt->execute([$orderId]);
    return $stmt->fetchAll() ?: [];
}

function marketplace_order_events(PDO $db, int $orderId): array
{
    $stmt = $db->prepare("SELECT * FROM online_order_events WHERE order_id=? ORDER BY id DESC");
    $stmt->execute([$orderId]);
    return $stmt->fetchAll() ?: [];
}

/**
 * V17.0.37 — jendela pembatalan mandiri oleh pembeli (default 10 menit, semua metode bayar).
 */
function marketplace_cancel_window_minutes(): int
{
    $m = (int) setting('marketplace_cancel_window_minutes', '10');
    return max(0, min(180, $m));
}

/** Sisa detik untuk membatalkan; 0 = tidak bisa dibatalkan sendiri. */
function marketplace_order_cancel_remaining(array $order, ?PDO $db = null): int
{
    if (empty($order)) return 0;
    $status = (string) ($order['order_status'] ?? '');
    if (!in_array($status, ['pending_payment', 'pending', 'confirmed', 'processing'], true)) return 0;
    if (!in_array((string) ($order['payment_status'] ?? ''), ['pending', 'unpaid', 'error'], true)) return 0;
    // Umur dihitung dari MySQL supaya tidak terpengaruh beda timezone PHP vs DB.
    $age = null;
    if ($db instanceof PDO && !empty($order['id'])) {
        try {
            $st = $db->prepare('SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) FROM online_orders WHERE id=?');
            $st->execute([(int) $order['id']]);
            $age = (int) $st->fetchColumn();
        } catch (Throwable $e) {
            $age = null;
        }
    }
    if ($age === null) {
        $created = strtotime((string) ($order['created_at'] ?? ''));
        if (!$created) return 0;
        $age = time() - $created;
    }
    $left = (marketplace_cancel_window_minutes() * 60) - max(0, (int) $age);
    return $left > 0 ? $left : 0;
}

function marketplace_order_cancel_label(array $order, ?PDO $db = null): string
{
    $left = marketplace_order_cancel_remaining($order, $db);
    if ($left <= 0) return '';
    return sprintf('%02d:%02d', intdiv($left, 60), $left % 60);
}

/**
 * Batalkan pesanan atas permintaan pembeli (guest maupun member) dalam jendela waktu.
 * @return array{ok:bool,message:string,order:?array}
 */
function marketplace_customer_cancel_order(PDO $db, string $orderNumber, string $token): array
{
    marketplace_install($db);
    $stmt = $db->prepare("SELECT * FROM online_orders WHERE order_number=? AND public_token=? LIMIT 1");
    $stmt->execute([$orderNumber, $token]);
    $order = $stmt->fetch();
    if (!$order) return ['ok' => false, 'message' => 'Pesanan tidak ditemukan.', 'order' => null];

    if (marketplace_order_cancel_remaining($order, $db) <= 0) {
        $menit = marketplace_cancel_window_minutes();
        if ((string) ($order['payment_status'] ?? '') === 'paid') {
            return ['ok' => false, 'message' => 'Pesanan sudah dibayar. Ajukan pembatalan lewat menu Bantuan Pesanan.', 'order' => $order];
        }
        return ['ok' => false, 'message' => "Batas pembatalan {$menit} menit sudah lewat. Hubungi toko lewat menu Bantuan Pesanan.", 'order' => $order];
    }

    $started = !$db->inTransaction();
    if ($started) $db->beginTransaction();
    try {
        $up = $db->prepare("UPDATE online_orders SET order_status='cancelled', payment_status=CASE WHEN payment_status IN ('pending','unpaid','error') THEN 'cancelled' ELSE payment_status END, cancelled_at=COALESCE(cancelled_at,NOW()), updated_at=NOW() WHERE id=? AND order_status=?");
        $up->execute([(int) $order['id'], (string) $order['order_status']]);
        if ($up->rowCount() === 0) {
            if ($started) $db->rollBack();
            return ['ok' => false, 'message' => 'Pesanan sudah diproses dan tidak bisa dibatalkan.', 'order' => $order];
        }
        if ($started) $db->commit();
    } catch (Throwable $e) {
        if ($started && $db->inTransaction()) $db->rollBack();
        throw $e;
    }

    if (function_exists('marketplace_release_stock')) {
        marketplace_release_stock($db, (int) $order['id'], 'Dibatalkan pembeli dalam ' . marketplace_cancel_window_minutes() . ' menit.');
    }
    marketplace_add_event($db, (int) $order['id'], 'cancelled', 'Dibatalkan pembeli', 'Pembatalan mandiri dalam ' . marketplace_cancel_window_minutes() . ' menit setelah checkout.');
    return ['ok' => true, 'message' => 'Pesanan dibatalkan. Stok sudah dikembalikan.', 'order' => $order];
}

function marketplace_release_stock(PDO $db, int $orderId, string $reason = 'Pesanan dibatalkan/expired'): bool
{
    marketplace_install($db);
    $started = !$db->inTransaction();
    if ($started) $db->beginTransaction();
    try {
        $stmt = $db->prepare("SELECT * FROM online_orders WHERE id=? FOR UPDATE");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order || !empty($order['stock_released_at'])) {
            if ($started) $db->commit();
            return false;
        }

        $items = marketplace_order_items($db, $orderId);
        $isMainStmt = $db->prepare("SELECT is_main FROM branches WHERE id=? LIMIT 1");
        $isMainStmt->execute([(int) $order['branch_id']]);
        $isMain = (int) $isMainStmt->fetchColumn() === 1;

        foreach ($items as $item) {
            $pid = (int) $item['product_id'];
            $qty = (int) $item['qty'];
            if(!empty($item['variant_id'])) {
                $db->prepare("UPDATE marketplace_product_variant_stocks SET stock=stock+?,updated_at=NOW() WHERE variant_id=? AND branch_id=?")->execute([$qty,(int)$item['variant_id'],(int)$order['branch_id']]);
                marketplace_v16_sync_parent_stock($db,$pid,(int)$order['branch_id']);
            } else {
                $up = $db->prepare("UPDATE product_branch_stocks SET stock=stock+?, updated_at=NOW() WHERE branch_id=? AND product_id=?");
                $up->execute([$qty, (int) $order['branch_id'], $pid]);
                if ($isMain) $db->prepare("UPDATE products SET stock=stock+? WHERE id=?")->execute([$qty, $pid]);
            }
            if (!empty($item['flash_sale_item_id'])) {
                $db->prepare("UPDATE marketplace_flash_sale_items SET sold_count=GREATEST(sold_count-?,0) WHERE id=?")->execute([$qty,(int)$item['flash_sale_item_id']]);
            }
        }

        if (!empty($order['voucher_code']) && empty($order['voucher_released_at']) && ($order['payment_status'] ?? '') !== 'paid') {
            $db->prepare("UPDATE marketplace_vouchers SET used_count=GREATEST(used_count-1,0),updated_at=NOW() WHERE UPPER(code)=UPPER(?)")->execute([(string)$order['voucher_code']]);
            $db->prepare("UPDATE online_orders SET voucher_released_at=NOW() WHERE id=?")->execute([$orderId]);
        }
        if (($order['payment_status'] ?? '') !== 'paid' && function_exists('marketplace_v15_refund_points')) marketplace_v15_refund_points($db,$orderId);
        $db->prepare("UPDATE online_orders SET stock_released_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$orderId]);
        marketplace_add_event($db, $orderId, 'stock_released', 'Stok dikembalikan', $reason);
        if ($started) $db->commit();
        return true;
    } catch (Throwable $e) {
        if ($started && $db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

/**
 * V17.0.40 — batas waktu pembayaran QRIS/VA (menit). Default 30.
 */
function marketplace_payment_timeout_minutes(): int
{
    $m = (int) setting('marketplace_payment_timeout_minutes', '30');
    return max(1, min(1440, $m));
}

function marketplace_expire_if_needed(PDO $db, array $order): array
{
    if (!in_array((string) ($order['payment_status'] ?? ''), ['pending', 'error'], true)) return $order;

    // V17.0.40 — dibandingkan di MySQL: bebas beda timezone PHP/DB, dan tetap jalan
    // walau payment_expired_at kosong (pakai created_at + batas menit).
    try {
        $chk = $db->prepare("SELECT (COALESCE(payment_expired_at, created_at + INTERVAL ? MINUTE) <= NOW()) FROM online_orders WHERE id=? AND payment_status IN ('pending','error')");
        $chk->execute([marketplace_payment_timeout_minutes(), (int) $order['id']]);
        if ((int) $chk->fetchColumn() !== 1) return $order;
    } catch (Throwable $e) {
        return $order;
    }

    $stmt = $db->prepare("UPDATE online_orders SET payment_status='expired',order_status='expired',cancelled_at=COALESCE(cancelled_at,NOW()),updated_at=NOW() WHERE id=? AND payment_status IN ('pending','error')");
    $stmt->execute([(int) $order['id']]);
    if ($stmt->rowCount() > 0) {
        marketplace_release_stock($db, (int) $order['id'], 'Pembayaran melewati batas waktu.');
        marketplace_add_event($db, (int) $order['id'], 'expired', 'Pembayaran kedaluwarsa', 'Pesanan otomatis dibatalkan dan stok dikembalikan.');
    }
    $fresh = marketplace_get_order($db, (string) $order['order_number']);
    return $fresh ?: $order;
}

/**
 * V17.0.38 — Ambil di Toko lewat batas: batalkan otomatis (belum dibayar) & kembalikan stok.
 */
function marketplace_pickup_expire_if_needed(PDO $db, array $order): array
{
    if (empty($order) || strtolower((string) ($order['courier_code'] ?? '')) !== 'pickup') return $order;
    if (empty($order['pickup_deadline_at']) || !empty($order['stock_released_at'])) return $order;
    if (!in_array((string) ($order['payment_status'] ?? ''), ['pending', 'unpaid', 'error'], true)) return $order;
    if (!in_array((string) ($order['order_status'] ?? ''), ['pending_payment', 'pending', 'confirmed', 'processing'], true)) return $order;

    // Bandingkan di MySQL supaya tidak terpengaruh beda timezone PHP vs DB.
    try {
        $chk = $db->prepare('SELECT (pickup_deadline_at IS NOT NULL AND pickup_deadline_at < NOW()) FROM online_orders WHERE id=?');
        $chk->execute([(int) $order['id']]);
        if ((int) $chk->fetchColumn() !== 1) return $order;
    } catch (Throwable $e) {
        $deadline = strtotime((string) $order['pickup_deadline_at']);
        if (!$deadline || $deadline >= time()) return $order;
    }
    $deadline = strtotime((string) $order['pickup_deadline_at']);

    try {
        $up = $db->prepare("UPDATE online_orders SET order_status='expired', payment_status=CASE WHEN payment_status IN ('pending','unpaid','error') THEN 'expired' ELSE payment_status END, cancelled_at=COALESCE(cancelled_at,NOW()), updated_at=NOW() WHERE id=? AND order_status=?");
        $up->execute([(int) $order['id'], (string) $order['order_status']]);
        if ($up->rowCount() > 0) {
            marketplace_release_stock($db, (int) $order['id'], 'Batas pengambilan di toko terlewat.');
            marketplace_add_event($db, (int) $order['id'], 'expired', 'Batas ambil terlewat', 'Pesanan dibatalkan otomatis dan stok dikembalikan karena tidak diambil sampai ' . date('d/m/Y H:i', $deadline) . '.');
        }
    } catch (Throwable $e) {
        return $order;
    }
    $fresh = marketplace_get_order($db, (string) $order['order_number'], (string) ($order['public_token'] ?? ''));
    return $fresh ?: $order;
}

function marketplace_customer_id(): int
{
    return max(0, (int) ($_SESSION['marketplace_customer_id'] ?? 0));
}

function marketplace_customer(PDO $db = null): ?array
{
    $id = marketplace_customer_id();
    if ($id <= 0) return null;
    try {
        $db = $db ?: Database::connect();
        marketplace_install($db);
        $stmt = $db->prepare("SELECT * FROM customers WHERE id=? AND status='active' LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) unset($_SESSION['marketplace_customer_id']);
        return $row ?: null;
    } catch (Throwable $e) { return null; }
}

function marketplace_default_member_password(): string
{
    // V17.0.32 — password default member baru (bisa diubah lewat setting member_default_password).
    $def = '';
    if (function_exists('setting')) { try { $def = trim((string) setting('member_default_password', 'vapekong123')); } catch (Throwable $e) { $def = ''; } }
    return $def !== '' ? $def : 'vapekong123';
}

function marketplace_customer_login(PDO $db, string $identity, string $password): bool
{
    marketplace_install($db);
    $identity = trim($identity);
    if ($identity === '' || $password === '') return false;
    $stmt = $db->prepare("SELECT * FROM customers WHERE status='active' AND (LOWER(email)=LOWER(?) OR phone=?) LIMIT 1");
    $stmt->execute([$identity,$identity]);
    $row = $stmt->fetch() ?: null;

    // V17.0.32 — nomor HP boleh ditulis 08…, +62…, 62…, atau pakai spasi/tanda hubung.
    if (!$row) {
        $digits = preg_replace('/\D+/', '', $identity);
        if (is_string($digits) && strlen($digits) >= 8) {
            $tail = substr($digits, -8);
            $c = $db->prepare("SELECT * FROM customers WHERE status='active' AND phone LIKE ? LIMIT 25");
            $c->execute(['%' . $tail]);
            foreach (($c->fetchAll() ?: []) as $cand) {
                $cd = preg_replace('/\D+/', '', (string) ($cand['phone'] ?? ''));
                if (is_string($cd) && $cd !== '' && substr($cd, -8) === $tail) { $row = $cand; break; }
            }
        }
    }
    if (!$row) return false;

    // V17.0.32 — member yang didaftarkan tanpa password boleh masuk dengan password default
    // (sekali masuk, hash-nya langsung disimpan supaya aman & tidak dihitung ulang).
    if (empty($row['marketplace_password_hash'])) {
        if (!hash_equals(marketplace_default_member_password(), $password)) return false;
        if (marketplace_column_exists($db, 'customers', 'marketplace_password_hash')) {
            $db->prepare("UPDATE customers SET marketplace_password_hash=? WHERE id=?")
               ->execute([password_hash($password, PASSWORD_DEFAULT), (int) $row['id']]);
        }
    } elseif (!password_verify($password, (string) $row['marketplace_password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['marketplace_customer_id'] = (int) $row['id'];
    $db->prepare("UPDATE customers SET marketplace_last_login_at=NOW() WHERE id=?")->execute([(int)$row['id']]);
    return true;
}

function marketplace_customer_logout(): void
{
    unset($_SESSION['marketplace_customer_id']);
    session_regenerate_id(true);
}

function marketplace_customer_addresses(PDO $db, int $customerId): array
{
    $stmt = $db->prepare("SELECT * FROM customer_addresses WHERE customer_id=? ORDER BY is_default DESC,id DESC");
    $stmt->execute([$customerId]);
    return $stmt->fetchAll() ?: [];
}

function marketplace_wishlist_ids(PDO $db, int $customerId): array
{
    if ($customerId <= 0 || !marketplace_table_exists($db,'marketplace_wishlists')) return [];
    try {
        $stmt=$db->prepare("SELECT product_id FROM marketplace_wishlists WHERE customer_id=?"); $stmt->execute([$customerId]);
        return array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    } catch (Throwable $e) {
        return [];
    }
}

function marketplace_voucher_calculate(PDO $db, string $code, float $subtotal, bool $lock=false): array
{
    $code = strtoupper(trim($code));
    if ($code === '') return ['code'=>'','discount'=>0.0,'voucher'=>null];
    $sql="SELECT * FROM marketplace_vouchers WHERE UPPER(code)=UPPER(?) AND is_active=1 LIMIT 1" . ($lock ? " FOR UPDATE" : "");
    $stmt=$db->prepare($sql); $stmt->execute([$code]); $v=$stmt->fetch();
    if (!$v) throw new InvalidArgumentException('Voucher tidak ditemukan atau tidak aktif.');
    $now=time();
    if (!empty($v['starts_at']) && strtotime((string)$v['starts_at'])>$now) throw new InvalidArgumentException('Voucher belum dapat digunakan.');
    if (!empty($v['ends_at']) && strtotime((string)$v['ends_at'])<$now) throw new InvalidArgumentException('Voucher sudah berakhir.');
    if ((int)$v['usage_limit']>0 && (int)$v['used_count'] >= (int)$v['usage_limit']) throw new InvalidArgumentException('Kuota voucher sudah habis.');
    if ($subtotal < (float)$v['min_purchase']) throw new InvalidArgumentException('Minimum belanja voucher adalah ' . rupiah((float)$v['min_purchase']) . '.');
    $discount = 0.0;
    if (($v['benefit_type'] ?? 'order') !== 'shipping') {
        $discount = $v['discount_type']==='percent' ? $subtotal*((float)$v['discount_value']/100) : (float)$v['discount_value'];
        if ((float)$v['max_discount']>0) $discount=min($discount,(float)$v['max_discount']);
        $discount=max(0,min($subtotal,$discount));
    }
    return ['code'=>(string)$v['code'],'discount'=>$discount,'voucher'=>$v];
}

function marketplace_product_reviews(PDO $db, int $productId, int $limit=30): array
{
    if (!marketplace_table_exists($db,'marketplace_reviews')) return [];
    try {
        $stmt=$db->prepare("SELECT r.*,c.name customer_name FROM marketplace_reviews r JOIN customers c ON c.id=r.customer_id WHERE r.product_id=? AND r.status='approved' ORDER BY r.id DESC LIMIT " . max(1,min(100,$limit)));
        $stmt->execute([$productId]); return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function marketplace_review_summary(PDO $db, int $productId): array
{
    if (!marketplace_table_exists($db,'marketplace_reviews')) return ['total'=>0,'average'=>0.0];
    try {
        $stmt=$db->prepare("SELECT COUNT(*) total,COALESCE(AVG(rating),0) average FROM marketplace_reviews WHERE product_id=? AND status='approved'");
        $stmt->execute([$productId]); $r=$stmt->fetch() ?: ['total'=>0,'average'=>0];
        return ['total'=>(int)$r['total'],'average'=>(float)$r['average']];
    } catch (Throwable $e) {
        return ['total'=>0,'average'=>0.0];
    }
}

function marketplace_customer_can_review(PDO $db,int $customerId,int $productId): ?int
{
    if (!marketplace_table_exists($db,'marketplace_reviews')) return null;
    try {
        $stmt=$db->prepare("SELECT o.id FROM online_orders o JOIN online_order_items i ON i.order_id=o.id WHERE o.customer_id=? AND i.product_id=? AND o.order_status='completed' AND NOT EXISTS(SELECT 1 FROM marketplace_reviews r WHERE r.customer_id=o.customer_id AND r.product_id=i.product_id AND r.order_id=o.id) ORDER BY o.id DESC LIMIT 1");
        $stmt->execute([$customerId,$productId]); $id=(int)$stmt->fetchColumn(); return $id>0?$id:null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * V17.0.30 — notifikasi WhatsApp pesanan marketplace ke nomor WA cabang pengirim.
 * Antrean disimpan lebih dulu (cepat, tidak menahan checkout), pengiriman dilakukan
 * setelah respons terkirim ke pembeli.
 */
function marketplace_order_notify_install(PDO $db): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS marketplace_order_notifications (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT NOT NULL,
            branch_id BIGINT NOT NULL DEFAULT 0,
            target VARCHAR(120) NOT NULL,
            message MEDIUMTEXT NULL,
            status ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
            attempts INT NOT NULL DEFAULT 0,
            provider VARCHAR(40) NOT NULL DEFAULT 'fonnte',
            response MEDIUMTEXT NULL,
            last_error TEXT NULL,
            sent_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_mp_notif_status(status,created_at),
            INDEX idx_mp_notif_order(order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if (marketplace_table_exists($db, 'settings')) {
            foreach ([['marketplace_order_wa_enabled', '1'], ['marketplace_order_wa_target', '']] as $pair) {
                $s = $db->prepare("INSERT IGNORE INTO settings(setting_key,setting_value) VALUES(?,?)");
                $s->execute($pair);
            }
        }
    } catch (Throwable $e) {}
}

/** Nomor tujuan: setting khusus, kalau kosong pakai nomor WA cabang pengirim. */
function marketplace_order_notify_target(PDO $db, array $order): string
{
    $override = trim((string) setting('marketplace_order_wa_target', ''));
    if ($override !== '') return $override;
    $branchId = (int) ($order['branch_id'] ?? 0);
    if ($branchId > 0) {
        try {
            $s = $db->prepare("SELECT phone FROM branches WHERE id=? LIMIT 1");
            $s->execute([$branchId]);
            $phone = trim((string) ($s->fetchColumn() ?: ''));
            if ($phone !== '') return $phone;
        } catch (Throwable $e) {}
    }
    if (function_exists('marketplace_wa_config')) {
        try {
            $admin = (string) (marketplace_wa_config()['admin_phone'] ?? '');
            if ($admin !== '') return $admin;
        } catch (Throwable $e) {}
    }
    return trim((string) setting('store_phone', ''));
}

function marketplace_order_notify_message(PDO $db, array $order): string
{
    $orderId = (int) ($order['id'] ?? 0);
    $lines = [];
    $lines[] = '*PESANAN ONLINE BARU*';
    $lines[] = 'No: ' . (string) ($order['order_number'] ?? '-');
    $branch = '';
    try {
        $s = $db->prepare("SELECT name FROM branches WHERE id=? LIMIT 1");
        $s->execute([(int) ($order['branch_id'] ?? 0)]);
        $branch = (string) ($s->fetchColumn() ?: '');
    } catch (Throwable $e) {}
    if ($branch !== '') $lines[] = 'Cabang: ' . $branch;
    $lines[] = 'Penerima: ' . (string) ($order['recipient_name'] ?? '-') . ' (' . (string) ($order['recipient_phone'] ?? '-') . ')';
    $address = trim(preg_replace('/\s+/', ' ', (string) ($order['address'] ?? '')));
    if ($address !== '') $lines[] = 'Alamat: ' . (mb_substr($address, 0, 110));
    $method = (string) ($order['courier_code'] ?? '');
    $lines[] = 'Pengiriman: ' . ($method === 'pickup' ? 'Ambil di toko' : ($method === 'store' ? 'Kurir Toko' : strtoupper($method) . ' ' . (string) ($order['courier_service'] ?? '')));

    $items = [];
    try {
        $s = $db->prepare("SELECT product_name,variant_name,qty,line_total FROM online_order_items WHERE order_id=? ORDER BY id ASC");
        $s->execute([$orderId]);
        $items = $s->fetchAll() ?: [];
    } catch (Throwable $e) {}
    if ($items) {
        $lines[] = '';
        $lines[] = 'Item:';
        foreach ($items as $it) {
            $nm = (string) $it['product_name'] . (!empty($it['variant_name']) ? ' - ' . $it['variant_name'] : '');
            $lines[] = '- ' . $nm . ' x' . (int) $it['qty'] . ' = Rp ' . number_format((float) $it['line_total'], 0, ',', '.');
        }
    }
    $lines[] = '';
    $lines[] = 'Total: Rp ' . number_format((float) ($order['grand_total'] ?? 0), 0, ',', '.');
    if ((float) ($order['shipping_cost'] ?? 0) > 0) {
        $lines[] = 'Ongkir: Rp ' . number_format((float) $order['shipping_cost'], 0, ',', '.');
    }
    $pay = (string) ($order['payment_status'] ?? 'pending');
    $methodLabel = strtoupper((string) ($order['payment_method'] ?? ''));
    if ($pay === 'paid') {
        $lines[] = 'Pembayaran: LUNAS' . ($methodLabel !== '' ? ' (' . $methodLabel . ')' : '');
    } elseif ($methodLabel === 'COD' && $pay === 'unpaid') {
        $lines[] = 'Pembayaran: BAYAR DI TEMPAT (COD) — tunai ' . (strtolower((string) ($order['courier_code'] ?? '')) === 'pickup' ? 'saat ambil di toko' : 'saat pesanan diterima');
        $lines[] = 'Tagihan: Rp ' . number_format((float) ($order['grand_total'] ?? 0), 0, ',', '.');
    } else {
        $lines[] = 'Pembayaran: ' . strtoupper($pay) . ' (' . $methodLabel . ')';
    }
    $lines[] = 'Waktu: ';
    try {
        // V17.0.45 — PHP date() = UTC, DB = +08; pakai jam MySQL biar notif WA tidak telat 8 jam.
        $w = $db->query('SELECT NOW()')->fetchColumn();
        $lines[count($lines) - 1] = 'Waktu: ' . date('d/m/Y H:i', strtotime((string) $w));
    } catch (Throwable $e) {
        $lines[count($lines) - 1] = 'Waktu: ' . date('d/m/Y H:i');
    }
    if (strtolower((string) ($order['courier_code'] ?? '')) === 'pickup' && !empty($order['pickup_deadline_at'])) {
        $dl = strtotime((string) $order['pickup_deadline_at']);
        if ($dl) $lines[] = 'Batas ambil: ' . date('d/m/Y H:i', $dl);
    }
    $lines[] = '';
    $lines[] = 'Segera proses dari menu Marketplace > Pesanan.';
    return implode("\n", $lines);
}

/** Simpan antrean notifikasi (idempoten per pesanan). */
function marketplace_order_notify_queue(PDO $db, int $orderId): bool
{
    if ($orderId <= 0) return false;
    if (trim((string) setting('marketplace_order_wa_enabled', '1')) === '0') return false;
    // Jangan jalankan DDL bila tabel sudah ada: pemanggil bisa sedang di dalam transaksi.
    if (!marketplace_table_exists($db, 'marketplace_order_notifications')) marketplace_order_notify_install($db);
    try {
        $dup = $db->prepare("SELECT id FROM marketplace_order_notifications WHERE order_id=? LIMIT 1");
        $dup->execute([$orderId]);
        if ((int) $dup->fetchColumn() > 0) return false;

        $s = $db->prepare("SELECT * FROM online_orders WHERE id=? LIMIT 1");
        $s->execute([$orderId]);
        $order = $s->fetch();
        if (!$order) return false;

        $target = marketplace_order_notify_target($db, $order);
        if ($target === '') {
            $db->prepare("INSERT INTO marketplace_order_notifications(order_id,branch_id,target,status,last_error,created_at)
                          VALUES(?,?,?,'skipped','Nomor WhatsApp cabang belum diisi.',NOW())")
               ->execute([$orderId, (int) $order['branch_id'], '-']);
            return false;
        }
        $db->prepare("INSERT INTO marketplace_order_notifications(order_id,branch_id,target,message,status,created_at)
                      VALUES(?,?,?,?,'pending',NOW())")
           ->execute([$orderId, (int) $order['branch_id'], $target, marketplace_order_notify_message($db, $order)]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/** Kirim satu pesan WA memakai helper Fonnte yang sudah ada di modul WhatsApp. */
function marketplace_order_send_wa(PDO $db, string $target, string $message, int $orderId = 0): array
{
    // V17.0.31 — notifikasi pesanan memakai Fonnte (WhatsApp POS) saja, bukan gateway marketplace.
    if (!function_exists('wa_send_fonnte')) {
        foreach ([dirname(__FILE__, 4) . '/public/admin/whatsapp/_helpers.php',
                  dirname(__FILE__, 3) . '/public/admin/whatsapp/_helpers.php'] as $file) {
            if (is_file($file)) { require_once $file; break; }
        }
    }
    if (!function_exists('wa_send_fonnte')) {
        return ['status' => 'skipped', 'error' => 'Modul WhatsApp POS (Fonnte) tidak ditemukan di server.'];
    }
    try {
        $res = wa_send_fonnte($db, $target, $message, null);
        return ['status' => 'sent', 'response' => json_encode($res, JSON_UNESCAPED_UNICODE)];
    } catch (Throwable $e) {
        return ['status' => 'failed', 'error' => $e->getMessage()];
    }
}

/** Kirim antrean yang masih pending/failed. Dipakai shutdown hook & cron tool. */
function marketplace_order_notify_dispatch(PDO $db, int $limit = 10, bool $retryFailed = false): array
{
    marketplace_order_notify_install($db);
    $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
    $limit = max(1, min(50, $limit));
    try {
        $statuses = $retryFailed ? "'pending','failed'" : "'pending'";
        $rows = $db->query("SELECT * FROM marketplace_order_notifications WHERE status IN ($statuses) AND attempts < 5 ORDER BY id ASC LIMIT $limit")->fetchAll() ?: [];
    } catch (Throwable $e) {
        return $stats;
    }
    foreach ($rows as $row) {
        $result = marketplace_order_send_wa($db, (string) $row['target'], (string) $row['message'], (int) $row['order_id']);
        $status = (string) $result['status'];
        $stats[$status] = ($stats[$status] ?? 0) + 1;
        try {
            $up = $db->prepare("UPDATE marketplace_order_notifications
                                   SET status=?, attempts=attempts+1, response=?, last_error=?, sent_at=?
                                 WHERE id=?");
            $up->execute([
                $status,
                $result['response'] ?? null,
                $result['error'] ?? null,
                $status === 'sent' ? date('Y-m-d H:i:s') : null,
                (int) $row['id'],
            ]);
        } catch (Throwable $e) {}
    }
    return $stats;
}

function marketplace_create_order(PDO $db, array $payload): array
{
    marketplace_install($db);
    $rawItems = is_array($payload['items'] ?? null) ? $payload['items'] : [];
    $requestedCourier = strtolower(trim((string)($payload['courier_code'] ?? '')));
    $pickupBranch = null;

    if ($requestedCourier === 'pickup') {
        $pickupBranchId = max(0, (int)($payload['pickup_branch_id'] ?? 0));
        $pickupBranch = marketplace_store_pickup_branch($db, $pickupBranchId, $rawItems);
        if (!$pickupBranch) throw new RuntimeException('Toko pengambilan tidak tersedia atau stok keranjang tidak lengkap di cabang tersebut. Pilih toko kembali.');
        $branchId = (int)$pickupBranch['branch_id'];
    } else {
        // V17.0.29 — pilih cabang pengirim yang bisa memenuhi seluruh keranjang.
        $branchId = marketplace_delivery_branch($db, $rawItems);
    }
    $cart = marketplace_cart($db, $rawItems, $branchId);

    $recipientName = trim((string) ($payload['recipient_name'] ?? ''));
    $recipientPhone = preg_replace('/[^0-9+]/', '', (string) ($payload['recipient_phone'] ?? ''));
    $recipientEmail = trim((string) ($payload['recipient_email'] ?? ''));
    $address = trim((string) ($payload['address'] ?? ''));
    $destinationId = (int) ($payload['destination_id'] ?? 0);
    $destinationLabel = trim((string) ($payload['destination_label'] ?? ''));
    $postalCode = trim((string) ($payload['postal_code'] ?? ''));
    $customerNote = trim((string) ($payload['customer_note'] ?? ''));
    $province = trim((string)($payload['province'] ?? ''));
    $city = trim((string)($payload['city'] ?? ''));
    $district = trim((string)($payload['district'] ?? ''));
    $subdistrict = trim((string)($payload['subdistrict'] ?? ''));

    if ($recipientName === '' || mb_strlen($recipientName) < 2) throw new InvalidArgumentException('Nama penerima wajib diisi.');
    if (strlen(preg_replace('/\D+/', '', $recipientPhone)) < 9) throw new InvalidArgumentException('Nomor WhatsApp tidak valid.');
    if ($recipientEmail !== '' && !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Email tidak valid.');

    if ($requestedCourier === 'pickup') {
        $selected = marketplace_store_pickup_option($pickupBranch);
        $postedCost = isset($payload['shipping_cost']) ? (float)$payload['shipping_cost'] : 0.0;
        if (abs($postedCost) > 0.01) throw new RuntimeException('Ambil di Toko harus memiliki ongkir Rp0. Silakan pilih metode pengambilan kembali.');
        $address = trim((string)($pickupBranch['address'] ?? ''));
        if ($address === '') $address = (string)($pickupBranch['name'] ?? 'Toko');
        $destinationId = 0;
        $destinationLabel = 'Ambil di ' . (string)($pickupBranch['name'] ?? 'Toko');
        $province = $city = $district = $subdistrict = '';
        $postalCode = '';
    } else {
        if (mb_strlen($address) < 8) throw new InvalidArgumentException('Alamat lengkap wajib diisi.');
        if ($destinationId <= 0 || $destinationLabel === '') throw new InvalidArgumentException('Pilih tujuan pengiriman dari RajaOngkir.');

        if ($requestedCourier === 'store') {
            if (!marketplace_store_courier_validate_destination($payload, $destinationId, $branchId)) {
                throw new RuntimeException('Alamat tidak termasuk area layanan Kurir Toko atau data wilayah berubah. Silakan pilih wilayah kembali.');
            }
            $selected = marketplace_store_courier_option($province,$city,$district,$subdistrict,$destinationId,$branchId);
            if (!$selected) throw new RuntimeException('Kurir Toko tidak tersedia untuk alamat ini.');
            $postedCost = isset($payload['shipping_cost']) ? (float)$payload['shipping_cost'] : null;
            if ($postedCost !== null && abs($postedCost - (float)$selected['cost']) > 0.01) throw new RuntimeException('Tarif Kurir Toko berubah. Silakan hitung ongkir kembali.');
        } else {
            $shippingOptions = marketplace_rajaongkir_cost($destinationId, (int) $cart['weight_gram']);
            $selected = marketplace_find_shipping_option(
                $shippingOptions,
                (string) ($payload['courier_code'] ?? ''),
                (string) ($payload['courier_service'] ?? ''),
                isset($payload['shipping_cost']) ? (float) $payload['shipping_cost'] : null
            );
            if (!$selected) throw new RuntimeException('Pilihan ongkir berubah/tidak tersedia. Silakan hitung ongkir kembali.');
        }
    }

    $paymentMethod = strtolower(trim((string) ($payload['payment_method'] ?? 'qris')));
    $isCod = $paymentMethod === 'cod';
    if ($isCod) {
        // V17.0.34 — COD hanya boleh untuk pengambilan di toko atau kurir toko.
        if (!marketplace_cod_allowed((string) $requestedCourier)) {
            throw new InvalidArgumentException('Bayar di Tempat (COD) hanya tersedia untuk Ambil di Toko atau Kurir Toko.');
        }
    } elseif (!isset(marketplace_pakasir_methods()[$paymentMethod])) {
        throw new InvalidArgumentException('Metode pembayaran tidak valid.');
    }

    $idempotency = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($payload['checkout_key'] ?? ''));
    if ($idempotency === '' || strlen($idempotency) < 12) $idempotency = bin2hex(random_bytes(16));

    $existing = $db->prepare("SELECT order_number,public_token FROM online_orders WHERE idempotency_key=? LIMIT 1");
    $existing->execute([$idempotency]);
    if ($row = $existing->fetch()) {
        return ['order_number' => $row['order_number'], 'public_token' => $row['public_token'], 'existing' => true];
    }

    $orderNumber = marketplace_generate_order_number();
    $publicToken = bin2hex(random_bytes(24));
    $subtotal = (float) $cart['subtotal'];
    $shippingCost = (float) $selected['cost'];
    $voucherCode = strtoupper(trim((string) ($payload['voucher_code'] ?? '')));
    $voucherPreview = marketplace_voucher_calculate($db, $voucherCode, $subtotal, false);
    $discount = (float) $voucherPreview['discount'];
    $shippingDiscount = !empty($voucherPreview['voucher']) ? marketplace_v15_shipping_discount($voucherPreview['voucher'], $shippingCost, (string)$selected['code']) : 0.0;
    $customerId = marketplace_customer_id();
    if ($customerId > 0 && !marketplace_customer($db)) $customerId = 0;
    // V17.0.54 — GUEST checkout: tautkan nomor HP ke member (jika sudah terdaftar) atau
    // buatkan member otomatis (password default vapekong123) bila nomor belum terdaftar.
    if ($customerId <= 0 && $recipientPhone !== '') {
        $memberStmt = $db->prepare("SELECT id,name,email FROM customers WHERE status='active' AND phone=? AND TRIM(COALESCE(phone,''))<>'' ORDER BY id LIMIT 1");
        $memberStmt->execute([$recipientPhone]);
        $member = $memberStmt->fetch();
        if ($member) {
            $customerId = (int) $member['id']; // tautkan transaksi ke member yang sudah ada
        } else {
            $db->prepare("INSERT INTO customers (name,email,phone,marketplace_password_hash,status,created_at) VALUES (?,?,?,?,'active',NOW())")
                ->execute([$recipientName !== '' ? $recipientName : 'Member Marketplace', $recipientEmail !== '' ? $recipientEmail : null, $recipientPhone, password_hash('vapekong123', PASSWORD_DEFAULT)]);
            $customerId = (int) $db->lastInsertId();
        }
    }
    $pointsRequested = max(0, (int)($payload['points_to_use'] ?? 0));
    $pointsPreview = marketplace_v15_points_preview($db, $customerId, $pointsRequested, max(0,$subtotal-$discount), false);
    $pointsDiscount = (float)$pointsPreview['discount'];
    $grandTotal = max(0, $subtotal - $discount - $pointsDiscount + $shippingCost - $shippingDiscount);
    $grandAmount = (int) round($grandTotal);
    if ($grandAmount <= 0) throw new InvalidArgumentException('Total pembayaran setelah poin/voucher harus lebih dari Rp0. Kurangi poin yang digunakan.');

    // V17.0.39 — siapkan tabel poin sebelum transaksi (DDL memicu implicit commit).
    if ($customerId > 0 && function_exists('marketplace_v15_points_ensure')) marketplace_v15_points_ensure($db);

    $db->beginTransaction();
    try {
        // Lock and deduct stock so POS + marketplace cannot sell the same unit while payment is pending.
        $isMainStmt = $db->prepare("SELECT is_main FROM branches WHERE id=? LIMIT 1");
        $isMainStmt->execute([$branchId]);
        $isMain = (int) $isMainStmt->fetchColumn() === 1;

        foreach ($cart['items'] as $item) {
            $stmt = $db->prepare("SELECT stock FROM product_branch_stocks WHERE branch_id=? AND product_id=? FOR UPDATE");
            $stmt->execute([$branchId, (int) $item['product_id']]);
            $current = $stmt->fetchColumn();
            if ($current === false && $isMain) {
                $current = $db->prepare("SELECT stock FROM products WHERE id=? FOR UPDATE");
                $current->execute([(int) $item['product_id']]);
                $current = $current->fetchColumn();
                $db->prepare("INSERT IGNORE INTO product_branch_stocks (branch_id,product_id,stock,minimum_stock,created_at) SELECT ?,id,stock,minimum_stock,NOW() FROM products WHERE id=?")->execute([$branchId, (int) $item['product_id']]);
            }
            if ((int) $current < (int) $item['qty']) throw new RuntimeException('Stok ' . $item['name'] . ' baru saja berubah. Silakan ulangi checkout.');
            if (!empty($item['flash_sale']['flash_sale_item_id'])) {
                $fsid=(int)$item['flash_sale']['flash_sale_item_id'];
                $fs=$db->prepare("SELECT i.sale_price,i.stock_limit,i.sold_count,f.is_active,f.starts_at,f.ends_at FROM marketplace_flash_sale_items i JOIN marketplace_flash_sales f ON f.id=i.flash_sale_id WHERE i.id=? AND i.product_id=? AND ((i.variant_id IS NULL AND ?=0) OR i.variant_id=?) FOR UPDATE");
                $fs->execute([$fsid,(int)$item['product_id'],(int)($item['variant_id']??0),(int)($item['variant_id']??0)]);
                $fr=$fs->fetch();
                if(!$fr || (int)$fr['is_active']!==1 || strtotime((string)$fr['starts_at'])>time() || strtotime((string)$fr['ends_at'])<time() || abs((float)$fr['sale_price']-(float)$item['price'])>0.01) throw new RuntimeException('Flash sale '.$item['name'].' sudah berubah. Silakan ulangi checkout.');
                if((int)$fr['stock_limit']>0 && (int)$fr['sold_count']+(int)$item['qty']>(int)$fr['stock_limit']) throw new RuntimeException('Kuota flash sale '.$item['name'].' tidak mencukupi.');
                $db->prepare("UPDATE marketplace_flash_sale_items SET sold_count=sold_count+? WHERE id=?")->execute([(int)$item['qty'],$fsid]);
            }
            if(!empty($item['variant_id'])) {
                $variantLock=$db->prepare("SELECT stock FROM marketplace_product_variant_stocks WHERE variant_id=? AND branch_id=? FOR UPDATE");
                $variantLock->execute([(int)$item['variant_id'],$branchId]);
                $variantCurrent=$variantLock->fetchColumn();
                if($variantCurrent===false || (int)$variantCurrent<(int)$item['qty']) throw new RuntimeException('Stok varian '.$item['name'].' - '.$item['variant_name'].' baru saja berubah. Silakan ulangi checkout.');
                $db->prepare("UPDATE marketplace_product_variant_stocks SET stock=stock-?,updated_at=NOW() WHERE variant_id=? AND branch_id=?")->execute([(int)$item['qty'],(int)$item['variant_id'],$branchId]);
                marketplace_v16_sync_parent_stock($db,(int)$item['product_id'],$branchId);
            } else {
                $db->prepare("UPDATE product_branch_stocks SET stock=stock-?,updated_at=NOW() WHERE branch_id=? AND product_id=?")->execute([(int) $item['qty'], $branchId, (int) $item['product_id']]);
                if ($isMain) $db->prepare("UPDATE products SET stock=stock-? WHERE id=?")->execute([(int) $item['qty'], (int) $item['product_id']]);
            }
        }

        // Re-check voucher under lock so usage_limit cannot be oversold concurrently.
        $voucherLocked = marketplace_voucher_calculate($db, $voucherCode, $subtotal, $voucherCode !== '');
        $discount = (float) $voucherLocked['discount'];
        $shippingDiscount = !empty($voucherLocked['voucher']) ? marketplace_v15_shipping_discount($voucherLocked['voucher'], $shippingCost, (string)$selected['code']) : 0.0;
        $pointsLocked = marketplace_v15_redeem_points($db,$customerId,$pointsRequested,max(0,$subtotal-$discount),$orderNumber);
        $pointsDiscount = (float)$pointsLocked['discount'];
        $grandTotal = max(0, $subtotal - $discount - $pointsDiscount + $shippingCost - $shippingDiscount);
        $grandAmount = (int) round($grandTotal);
        if($grandAmount<=0) throw new RuntimeException('Total pembayaran tidak valid setelah penukaran poin.');

        $stmt = $db->prepare("INSERT INTO online_orders
            (order_number,public_token,idempotency_key,branch_id,customer_id,recipient_name,recipient_phone,recipient_email,address,destination_id,destination_label,province,city,district,subdistrict,postal_code,customer_note,total_weight_gram,subtotal,discount,voucher_code,shipping_cost,shipping_discount,points_redeemed,points_discount,points_redeemed_at,grand_total,courier_code,courier_name,courier_service,courier_description,shipping_etd,payment_method,payment_amount,payment_status,order_status,fulfillment_status,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,IF(? > 0,NOW(),NULL),?,?,?,?,?,?,?,?,'pending','pending_payment','waiting_payment',NOW())");
        $stmt->execute([
            $orderNumber,$publicToken,$idempotency,$branchId,$customerId ?: null,$recipientName,$recipientPhone,$recipientEmail ?: null,$address,$destinationId,$destinationLabel,
            $province ?: null,
            $city ?: null,
            $district ?: null,
            $subdistrict ?: null,
            $postalCode ?: null,$customerNote ?: null,(int) $cart['weight_gram'],$subtotal,$discount,$voucherLocked['code'] ?: null,$shippingCost,$shippingDiscount,(int)$pointsLocked['points'],$pointsDiscount,(int)$pointsLocked['points'],$grandTotal,
            $selected['code'],$selected['name'],$selected['service'],$selected['description'],$selected['etd'],$paymentMethod,$grandAmount
        ]);

        // WAJIB ambil id parent langsung sesudah INSERT online_orders. Pada PDO/MySQL,
        // query UPDATE/INSERT lain setelah ini dapat mengubah hasil lastInsertId() pada
        // sebagian driver/shared-hosting dan membuat online_order_items memakai order_id=0.
        $orderId = (int) $db->lastInsertId();
        if ($orderId <= 0) {
            $orderLookup = $db->prepare("SELECT id FROM online_orders WHERE order_number=? LIMIT 1 FOR UPDATE");
            $orderLookup->execute([$orderNumber]);
            $orderId = (int) $orderLookup->fetchColumn();
        }
        if ($orderId <= 0) {
            throw new RuntimeException('ID pesanan tidak terbentuk setelah menyimpan order. Jalankan Upgrade Sistem & Database lalu coba checkout kembali.');
        }

        if ($voucherLocked['code'] !== '') {
            $db->prepare("UPDATE marketplace_vouchers SET used_count=used_count+1,updated_at=NOW() WHERE id=?")->execute([(int)$voucherLocked['voucher']['id']]);
        }
        if ($requestedCourier === 'pickup') {
            $pickupCode = marketplace_store_pickup_generate_code();
            // V17.0.41 — batas ambil hanya untuk COD (bayar tunai). QRIS/VA yang sudah lunas tanpa expired.
            $pickupDeadline = ($isCod && function_exists('marketplace_store_pickup_deadline')) ? marketplace_store_pickup_deadline() : null;
            $db->prepare("UPDATE online_orders SET pickup_code=?,pickup_deadline_at=COALESCE(?,pickup_deadline_at),delivery_status='Menunggu diproses untuk pengambilan di toko' WHERE id=?")->execute([$pickupCode,$pickupDeadline,$orderId]);
        }

        $parentCheck = $db->prepare("SELECT id FROM online_orders WHERE id=? AND order_number=? LIMIT 1");
        $parentCheck->execute([$orderId,$orderNumber]);
        if (!(int)$parentCheck->fetchColumn()) {
            throw new RuntimeException('Parent pesanan tidak ditemukan sebelum menyimpan item checkout. Transaksi dibatalkan aman; jalankan Upgrade Sistem & Database.');
        }

        $itemStmt = $db->prepare("INSERT INTO online_order_items (order_id,product_id,variant_id,product_code,product_name,variant_name,variant_sku,flash_sale_item_id,unit,qty,unit_price,purchase_price,weight_gram,line_total,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
        foreach ($cart['items'] as $item) {
            $itemStmt->execute([$orderId,$item['product_id'],$item['variant_id'] ?: null,$item['product_code'],$item['name'],$item['variant_name'] ?: null,$item['variant_sku'] ?: null,(int)($item['flash_sale']['flash_sale_item_id']??0) ?: null,$item['unit'],$item['qty'],$item['price'],$item['purchase_price'],$item['weight_gram'],$item['line_total']]);
        }
        if ($isCod) {
            $db->prepare("UPDATE online_orders SET payment_status='unpaid', order_status='confirmed', fulfillment_status=?, payment_expired_at=NULL, updated_at=NOW() WHERE id=?")
               ->execute([$requestedCourier === 'pickup' ? 'waiting_pick' : 'waiting', $orderId]);
            marketplace_add_event($db, $orderId, 'cod_confirmed', 'Pesanan diterima (COD)', marketplace_cod_instruction(['courier_code' => $requestedCourier, 'grand_total' => $grandTotal]));
        } else {
            marketplace_add_event($db, $orderId, 'pending_payment', 'Pesanan dibuat', 'Menunggu pembayaran pelanggan.');
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }

    // Create payment after the order is committed. The v2 endpoint is idempotent for the same order/body.
    // V17.0.34 — COD tidak membuat instruksi pembayaran online.
    try {
        if ($isCod) { $payment = null; } else {
        $payment = marketplace_payment_create($db, $orderNumber, $grandAmount, $paymentMethod);
        $expired = marketplace_datetime_from_iso($payment['expired_at'] ?? null);
        // V17.0.40 — batas bayar QRIS/VA maksimal N menit sejak instruksi dibuat.
        $timeoutMin = marketplace_payment_timeout_minutes();
        $stmt = $db->prepare("UPDATE online_orders SET payment_txn_id=?,payment_amount=?,payment_fee=?,payment_total=?,payment_qr_string=?,payment_va_number=?,payment_link=?,payment_status=?,payment_expired_at=LEAST(COALESCE(?, NOW() + INTERVAL ? MINUTE), NOW() + INTERVAL ? MINUTE),updated_at=NOW() WHERE order_number=?");
        $stmt->execute([
            $payment['txn_id'],(float) $payment['amount'],(float) $payment['fee'],(float) $payment['total_payment'],
            $payment['qr_string'] ?: null,$payment['va_number'] ?: null,$payment['payment_link'] ?: null,
            $payment['status'] === 'completed' ? 'paid' : 'pending',$expired,$timeoutMin,$timeoutMin,$orderNumber
        ]);
        marketplace_add_event($db, $orderId, 'payment_created', 'Instruksi pembayaran dibuat', marketplace_pakasir_methods()[$paymentMethod]);
        if ($payment['status'] === 'completed') {
            $db->prepare("UPDATE online_orders SET order_status='paid',fulfillment_status='waiting_pick',paid_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$orderId]);
            marketplace_add_event($db, $orderId, 'paid', 'Pembayaran berhasil', 'Pembayaran terkonfirmasi otomatis.');
            marketplace_award_customer_points($db, $orderId);
        }
        }
    } catch (Throwable $e) {
        if (!$isCod) {
            $db->prepare("UPDATE online_orders SET payment_status='error',updated_at=NOW() WHERE order_number=?")->execute([$orderNumber]);
            marketplace_add_event($db, $orderId, 'payment_error', 'Pembayaran belum berhasil dibuat', $e->getMessage());
        }
    }

    // V17.0.30 — antre notifikasi WA cabang, lalu kirim setelah respons selesai (tidak menahan checkout).
    try {
        if (marketplace_order_notify_queue($db, (int) $orderId)) {
            register_shutdown_function(static function () use ($db) {
                try {
                    if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }
                    elseif (function_exists('litespeed_finish_request')) { @litespeed_finish_request(); }
                } catch (Throwable $e) {}
                try { marketplace_order_notify_dispatch($db, 5); } catch (Throwable $e) {}
            });
        }
    } catch (Throwable $e) {}

    return ['order_number' => $orderNumber, 'public_token' => $publicToken, 'existing' => false];
}

function marketplace_retry_payment(PDO $db, array $order): array
{
    $order = marketplace_expire_if_needed($db, $order);
    if (in_array((string) $order['payment_status'], ['paid', 'expired', 'cancelled'], true)) {
        throw new RuntimeException('Pembayaran pesanan ini tidak dapat dibuat ulang.');
    }
    $payment = marketplace_payment_create($db, (string) $order['order_number'], (int) round((float) $order['grand_total']), (string) $order['payment_method']);
    $expired = marketplace_datetime_from_iso($payment['expired_at'] ?? null);
    $stmt = $db->prepare("UPDATE online_orders SET payment_txn_id=?,payment_amount=?,payment_fee=?,payment_total=?,payment_qr_string=?,payment_va_number=?,payment_link=?,payment_status=?,payment_expired_at=?,updated_at=NOW() WHERE id=?");
    $stmt->execute([
        $payment['txn_id'],(float) $payment['amount'],(float) $payment['fee'],(float) $payment['total_payment'],
        $payment['qr_string'] ?: null,$payment['va_number'] ?: null,$payment['payment_link'] ?: null,
        $payment['status'] === 'completed' ? 'paid' : 'pending',$expired,(int) $order['id']
    ]);
    marketplace_add_event($db, (int) $order['id'], 'payment_created', 'Instruksi pembayaran diperbarui', marketplace_pakasir_methods()[(string) $order['payment_method']] ?? 'Pembayaran');
    if ($payment['status'] === 'completed') {
        $db->prepare("UPDATE online_orders SET order_status='paid',fulfillment_status='waiting_pick',paid_at=COALESCE(paid_at,NOW()),updated_at=NOW() WHERE id=?")->execute([(int)$order['id']]);
        marketplace_add_event($db,(int)$order['id'],'paid','Pembayaran berhasil','Pembayaran terkonfirmasi saat membuat ulang instruksi pembayaran.');
        marketplace_award_customer_points($db,(int)$order['id']);
    }
    return marketplace_get_order($db, (string) $order['order_number']) ?: $order;
}

function marketplace_award_customer_points(PDO $db, int $orderId): int
{
    marketplace_install($db);
    $started = !$db->inTransaction();
    if ($started) $db->beginTransaction();
    try {
        $stmt=$db->prepare("SELECT id,order_number,customer_id,subtotal,discount,points_discount,payment_status,points_awarded_at FROM online_orders WHERE id=? FOR UPDATE");
        $stmt->execute([$orderId]); $o=$stmt->fetch();
        if (!$o || (int)$o['customer_id']<=0 || (string)$o['payment_status']!=='paid' || !empty($o['points_awarded_at'])) { if($started)$db->commit(); return 0; }
        $rate=max(0,(float)setting('member_point_earn_rate','10000'));
        $base=max(0,(float)$o['subtotal']-(float)$o['discount']-(float)($o['points_discount']??0));
        $points=$rate>0?(int)floor($base/$rate):0;
        if($points>0){
            $db->exec("CREATE TABLE IF NOT EXISTS customer_points (id BIGINT AUTO_INCREMENT PRIMARY KEY,customer_id BIGINT NOT NULL,transaction_id BIGINT NULL,type ENUM('in','out') NOT NULL DEFAULT 'in',points INT NOT NULL DEFAULT 0,description TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX idx_customer_points_customer(customer_id),INDEX idx_customer_points_transaction(transaction_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->prepare("UPDATE customers SET points=points+?,updated_at=NOW() WHERE id=?")->execute([$points,(int)$o['customer_id']]);
            $db->prepare("INSERT INTO customer_points(customer_id,transaction_id,type,points,description,created_at) VALUES(?,NULL,'in',?,?,NOW())")->execute([(int)$o['customer_id'],$points,'Poin belanja marketplace '.$o['order_number']]);
        }
        $db->prepare("UPDATE online_orders SET points_earned=?,points_awarded_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$points,$orderId]);
        if($started)$db->commit();
        if($points>0) marketplace_add_event($db,$orderId,'points','Poin member bertambah',$points.' poin dari pesanan marketplace.');
        return $points;
    } catch(Throwable $e){ if($started&&$db->inTransaction())$db->rollBack(); return 0; }
}

/**
 * V17.0.11 — CATAT ORDER ONLINE YANG SUDAH COMPLETED KE TRANSAKSI POS.
 *
 * Bug (v17.0.10): order online yang sudah dibayar & selesai TIDAK pernah dicatat
 * ke tabel `transactions`, sehingga penjualan online HILANG dari laporan
 * akuntansi/penjualan (stok tetap terpotong & poin member tetap diberikan,
 * tetapi laporan keuangan tidak mencatat pendapatan).
 *
 * Fungsi ini idempotent (dijaga kolom online_orders.pos_recorded_at): dipanggil
 * saat order menjadi `completed` dari 3 titik: admin Tandai Selesai,
 * kurir toko delivered, dan ambil di toko.
 *
 * Ongkir (shipping_cost) dicatat terpisah di kolom transactions.shipping_cost
 * agar laporan akuntansi dapat memisahkannya dari gross profit barang.
 */
function marketplace_record_pos_transaction(PDO $db, int $orderId): ?int
{
    $started = !$db->inTransaction();
    if ($started) $db->beginTransaction();
    try {
        $stmt = $db->prepare("SELECT * FROM online_orders WHERE id=? LIMIT 1 FOR UPDATE");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) { if ($started && $db->inTransaction()) $db->rollBack(); return null; }

        // Idempotent: sudah dicatat sebelumnya -> jangan duplikat.
        if (!empty($order['pos_recorded_at'])) { if ($started && $db->inTransaction()) $db->rollBack(); return null; }
        // Hanya order yang benar-benar lunas & selesai.
        if ((string)($order['payment_status'] ?? '') !== 'paid' || (string)($order['order_status'] ?? '') !== 'completed') {
            if ($started && $db->inTransaction()) $db->rollBack();
            return null;
        }

        // Barang order.
        $items = marketplace_order_items($db, $orderId);
        if (!$items) { if ($started && $db->inTransaction()) $db->rollBack(); return null; }

        $branchId    = (int)($order['branch_id'] ?? 0);
        $customerId  = (int)($order['customer_id'] ?? 0);
        $subtotal    = (float)($order['subtotal'] ?? 0);
        $discount    = (float)($order['discount'] ?? 0);
        $ongkir      = (float)($order['shipping_cost'] ?? 0);
        $grandTotal  = (float)($order['grand_total'] ?? 0);
        $paidAmount  = (float)($order['payment_amount'] ?? 0);
        if ($paidAmount <= 0) $paidAmount = $grandTotal;

        // Nomor invoice POS mengikuti format yang sama dengan kasir.
        $invoice = marketplace_pos_invoice_number($db);

        $stmt = $db->prepare("INSERT INTO transactions
            (invoice_number, cashier_id, customer_id, branch_id, subtotal, discount, tax, grand_total, paid_amount, change_amount, debt_amount, debt_status, payment_method, status, note, transaction_date, created_at, reference_type, reference_id, shipping_cost)
            VALUES (?, NULL, ?, ?, ?, ?, 0, ?, ?, 0, 0, NULL, ?, 'success', ?, ?, NOW(), 'online_order', ?, ?)");
        $stmt->execute([
            $invoice,
            $customerId > 0 ? $customerId : null,
            $branchId > 0 ? $branchId : null,
            $subtotal,
            $discount,
            $grandTotal,
            $paidAmount,
            $order['payment_method'] ?? 'online',
            'Penjualan online ' . ($order['order_number'] ?? ''),
            $order['created_at'] ?? null,
            $orderId,
            $ongkir,
        ]);
        $transactionId = (int)$db->lastInsertId();

        foreach ($items as $item) {
            $qty       = max(1, (int)($item['qty'] ?? 1));
            $unitPrice = (float)($item['unit_price'] ?? 0);
            $purchase  = (float)($item['purchase_price'] ?? 0);
            $name      = (string)($item['product_name'] ?? '-');
            $variantName = !empty($item['variant_name']) ? (string)$item['variant_name'] : null;
            $variantSku  = !empty($item['variant_sku']) ? (string)$item['variant_sku'] : null;
            $productId   = (int)($item['product_id'] ?? 0);
            $variantId   = !empty($item['variant_id']) ? (int)$item['variant_id'] : null;

            $stmt = $db->prepare("INSERT INTO transaction_items
                (transaction_id, item_type, product_id, variant_id, product_name, variant_name, variant_sku, qty, unit_price, purchase_price, discount, subtotal, created_at)
                VALUES (?, 'product', ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW())");
            $stmt->execute([
                $transactionId,
                $productId > 0 ? $productId : null,
                $variantId,
                $name,
                $variantName,
                $variantSku,
                $qty,
                $unitPrice,
                $purchase,
                round($unitPrice * $qty, 2),
            ]);
        }

        // Poin member: kalau poin marketplace sudah diberikan dengan transaction_id NULL
        // (alur lama), kaitkan baris poin itu ke transaksi POS sekarang.
        if ($customerId > 0 && marketplace_table_exists($db, 'customer_points')) {
            $db->prepare("UPDATE customer_points SET transaction_id=? WHERE customer_id=? AND transaction_id IS NULL AND description LIKE ?")
                ->execute([$transactionId, $customerId, 'Poin belanja marketplace ' . $order['order_number']]);
        }

        // Tandai sudah dicatat (idempotent) + simpan nomor invoice utk penelusuran.
        $db->prepare("UPDATE online_orders SET pos_recorded_at=NOW(), pos_invoice_number=?, updated_at=NOW() WHERE id=?")
            ->execute([$invoice, $orderId]);
        if ($started) $db->commit();

        try { marketplace_add_event($db, $orderId, 'pos_recorded', 'Tercatat ke laporan POS', 'Penjualan online dicatat sebagai invoice ' . $invoice . ' (ongkir ' . rupiah($ongkir) . ')'); } catch (Throwable $ignore) {}
        try { marketplace_v15_log($db, 'online_order.pos_recorded', 'online_order', $orderId, 'Invoice POS ' . $invoice); } catch (Throwable $ignore) {}
        return $transactionId;
    } catch (Throwable $e) {
        if ($started && $db->inTransaction()) $db->rollBack();
        error_log('[VAPEKONG][pos_record] order ' . $orderId . ' gagal: ' . $e->getMessage());
        return null;
    }
}

function marketplace_pos_invoice_number(PDO $db): string
{
    $prefix = 'INV-' . date('Ymd') . '-';
    $stmt = $db->prepare("SELECT invoice_number FROM transactions WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $next = 1;
    if ($last) {
        $parts = explode('-', $last);
        $next = ((int) end($parts)) + 1;
    }
    return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function marketplace_payment_status_normalize(?string $status): string
{
    $status = strtolower(trim((string) $status));
    if (in_array($status, ['completed','paid','success','settlement','settled'], true)) return 'paid';
    if (in_array($status, ['expired'], true)) return 'expired';
    if (in_array($status, ['failed','fail','cancel','canceled','cancelled','deny','denied'], true)) return 'failed';
    return 'pending';
}

function marketplace_apply_payment_webhook(PDO $db, array $payload): ?array
{
    marketplace_install($db);
    $orderNumber = trim((string) ($payload['order_id'] ?? $payload['data']['order_id'] ?? ''));
    if ($orderNumber === '') return null;
    $order = marketplace_get_order($db, $orderNumber);
    if (!$order) return null;

    $project = trim((string) ($payload['project'] ?? $payload['data']['project'] ?? ''));
    $config = marketplace_pakasir_config($db);
    if ($project !== '' && $config['project'] !== '' && !hash_equals($config['project'], $project)) {
        throw new RuntimeException('Project webhook tidak sesuai.');
    }

    $amount = (int) ($payload['amount'] ?? $payload['data']['amount'] ?? 0);
    $expected = (int) round((float) ($order['payment_amount'] ?: $order['grand_total']));
    if ($amount <= 0 || $amount !== $expected) throw new RuntimeException('Amount webhook tidak sesuai.');

    $incomingTxnId = trim((string)($payload['txn_id'] ?? $payload['data']['txn_id'] ?? ''));
    $expectedTxnId = trim((string)($order['payment_txn_id'] ?? ''));
    if ($expectedTxnId !== '' && $incomingTxnId !== '' && !hash_equals($expectedTxnId, $incomingTxnId)) {
        throw new RuntimeException('txn_id webhook Pakasir tidak sesuai.');
    }

    $rawStatus = (string) ($payload['status'] ?? $payload['data']['status'] ?? 'pending');
    $status = marketplace_payment_status_normalize($rawStatus);
    if ($status === 'paid') {
        $wasPaid = (string)($order['payment_status'] ?? '') === 'paid';
        $db->prepare("UPDATE online_orders SET payment_status='paid',order_status=CASE WHEN order_status='pending_payment' THEN 'paid' ELSE order_status END,fulfillment_status=CASE WHEN fulfillment_status='waiting_payment' THEN 'waiting_pick' ELSE fulfillment_status END,paid_at=COALESCE(paid_at,NOW()),updated_at=NOW() WHERE id=?")->execute([(int) $order['id']]);
        if (!$wasPaid) {
            marketplace_add_event($db, (int) $order['id'], 'paid', 'Pembayaran berhasil', 'Pembayaran terkonfirmasi melalui webhook.');
            marketplace_award_customer_points($db, (int) $order['id']);
        }
    } elseif (in_array($status, ['expired','failed'], true) && ($order['payment_status'] ?? '') !== 'paid') {
        $orderStatus = $status === 'expired' ? 'expired' : 'cancelled';
        $db->prepare("UPDATE online_orders SET payment_status=?,order_status=?,cancelled_at=COALESCE(cancelled_at,NOW()),updated_at=NOW() WHERE id=?")->execute([$status,$orderStatus,(int) $order['id']]);
        marketplace_release_stock($db, (int) $order['id'], 'Pembayaran ' . $status . '.');
        marketplace_add_event($db, (int) $order['id'], $status, 'Pembayaran ' . ($status === 'expired' ? 'kedaluwarsa' : 'gagal'), 'Stok otomatis dikembalikan.');
    }
    return marketplace_get_order($db, $orderNumber);
}


function marketplace_pakasir_sync_status(PDO $db, array $order): array
{
    $txnId = trim((string)($order['payment_txn_id'] ?? ''));
    if ($txnId === '') return $order;
    if (in_array((string)($order['payment_status'] ?? ''), ['paid','expired','failed','cancelled'], true)) return $order;

    $config = marketplace_pakasir_config($db);
    if ($config['project'] === '' || $config['api_key'] === '') return $order;

    $url = $config['base_url'] . '/api/v2/transaction-status/'
        . rawurlencode($config['project']) . '/' . rawurlencode($txnId);
    $json = marketplace_http_json($url, 'GET', [
        'Accept: application/json',
        'X-Api-Key: ' . $config['api_key'],
    ]);

    if (!empty($json['order_id']) && !hash_equals((string)$order['order_number'], (string)$json['order_id'])) {
        throw new RuntimeException('Order ID status Pakasir v2 tidak sesuai.');
    }
    $expected = (int)round((float)($order['payment_amount'] ?: $order['grand_total']));
    if (isset($json['amount']) && (int)$json['amount'] !== $expected) {
        throw new RuntimeException('Nominal status Pakasir v2 tidak sesuai.');
    }

    $payload = [
        'txn_id' => (string)($json['txn_id'] ?? $txnId),
        'order_id' => (string)($json['order_id'] ?? $order['order_number']),
        'amount' => (int)($json['amount'] ?? $expected),
        'status' => (string)($json['status'] ?? 'pending'),
        'completed_at' => (string)($json['completed_at'] ?? ''),
    ];
    $updated = marketplace_apply_payment_webhook($db, $payload);
    return $updated ?: $order;
}

function marketplace_order_status_label(string $status): string
{
    $labels = [
        'pending_payment' => 'Menunggu Pembayaran',
        'paid' => 'Sudah Dibayar',
        'processing' => 'Diproses',
        'shipped' => 'Dikirim',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
        'expired' => 'Kedaluwarsa',
    ];
    return $labels[$status] ?? ucwords(str_replace('_', ' ', $status));
}

/** V17.0.34 — COD hanya untuk Ambil di Toko / Kurir Toko. */
/** V17.0.34 — notifikasi WhatsApp ke kurir toko saat paket ditugaskan. */
function marketplace_courier_wa_message(PDO $db, array $order, array $courier): string
{
    $lines = [];
    $lines[] = '*TUGAS KURIR TOKO*';
    $lines[] = 'Halo ' . (string) ($courier['name'] ?? 'Kurir') . ', ada paket baru untuk diantar.';
    $lines[] = '';
    $lines[] = 'No: ' . (string) ($order['order_number'] ?? '-');
    $lines[] = 'Cabang: ' . (string) ($order['branch_name'] ?? '-');
    $lines[] = 'Penerima: ' . (string) ($order['recipient_name'] ?? '-') . ' (' . (string) ($order['recipient_phone'] ?? '-') . ')';
    $addr = trim((string) ($order['address'] ?? ''));
    if ($addr !== '') $lines[] = 'Alamat: ' . preg_replace('/\s+/', ' ', $addr);
    if (!empty($order['destination_label'])) $lines[] = 'Wilayah: ' . (string) $order['destination_label'];
    $lines[] = 'Ongkir: Rp ' . number_format((float) ($order['shipping_cost'] ?? 0), 0, ',', '.');
    $lines[] = 'Total pesanan: Rp ' . number_format((float) ($order['grand_total'] ?? 0), 0, ',', '.');
    if (strtolower((string) ($order['payment_method'] ?? '')) === 'cod' && (string) ($order['payment_status'] ?? '') !== 'paid') {
        $lines[] = '';
        $lines[] = '*BAYAR DI TEMPAT (COD)*';
        $lines[] = 'Tagih tunai: Rp ' . number_format((float) ($order['grand_total'] ?? 0), 0, ',', '.') . ' saat serah terima.';
    }
    $note = trim((string) ($order['customer_note'] ?? ''));
    if ($note !== '') $lines[] = 'Catatan: ' . preg_replace('/\s+/', ' ', $note);
    $items = [];
    try {
        $s = $db->prepare("SELECT product_name, variant_name, qty FROM online_order_items WHERE order_id=? ORDER BY id");
        $s->execute([(int) ($order['id'] ?? 0)]);
        foreach (($s->fetchAll() ?: []) as $it) {
            $items[] = '- ' . (string) $it['product_name'] . (!empty($it['variant_name']) ? ' - ' . $it['variant_name'] : '') . ' x' . (int) $it['qty'];
        }
    } catch (Throwable $e) {}
    if ($items) { $lines[] = ''; $lines[] = 'Item:'; foreach ($items as $l) $lines[] = $l; }
    $lines[] = '';
    $lines[] = 'Buka Portal Kurir untuk menerima tugas & konfirmasi.';
    return implode("\n", $lines);
}

function marketplace_notify_store_courier(PDO $db, int $orderId, int $courierId): array
{
    $s = $db->prepare("SELECT * FROM online_orders WHERE id=? LIMIT 1");
    $s->execute([$orderId]);
    $order = $s->fetch();
    if (!$order) return ['status' => 'skipped', 'error' => 'Pesanan tidak ditemukan.'];

    $courier = function_exists('marketplace_store_courier_get') ? marketplace_store_courier_get($db, $courierId) : null;
    if (!$courier) return ['status' => 'skipped', 'error' => 'Kurir tidak ditemukan.'];

    $phone = trim((string) ($courier['phone'] ?? ''));
    if ($phone === '') return ['status' => 'skipped', 'error' => 'Nomor WhatsApp kurir kosong.'];

    $message = marketplace_courier_wa_message($db, $order, $courier);
    $res = marketplace_order_send_wa($db, $phone, $message, $orderId);

    if (function_exists('marketplace_add_event')) {
        $ok = ($res['status'] ?? '') === 'sent';
        marketplace_add_event($db, $orderId, $ok ? 'courier_notified' : 'courier_notify_failed',
            $ok ? 'Kurir toko diberi tahu via WhatsApp' : 'Notifikasi WhatsApp ke kurir gagal',
            'Kurir: ' . (string) ($courier['name'] ?? '-') . ' (' . $phone . ')' . ($ok ? '' : ' — ' . (string) ($res['error'] ?? 'unknown')));
    }
    return $res + ['courier' => (string) ($courier['name'] ?? ''), 'target' => $phone];
}

function marketplace_cod_enabled(): bool
{
    $v = strtolower(trim((string) setting('marketplace_cod_enabled', 'on')));
    return !in_array($v, ['off', '0', 'no', 'nonaktif', 'false'], true);
}

function marketplace_cod_allowed(string $courierCode): bool
{
    if (!marketplace_cod_enabled()) return false;
    return in_array(strtolower(trim($courierCode)), ['pickup', 'store'], true);
}

function marketplace_cod_instruction(array $order): string
{
    $pickup = strtolower((string) ($order['courier_code'] ?? '')) === 'pickup';
    $total = 'Rp ' . number_format((float) ($order['grand_total'] ?? 0), 0, ',', '.');
    return 'Bayar tunai ' . $total . ' saat ' . ($pickup ? 'ambil di toko' : 'pesanan diterima dari kurir toko') . '.';
}

function marketplace_payment_status_label(string $status): string
{
    $labels = ['pending'=>'Menunggu','unpaid'=>'Bayar di Tempat','paid'=>'Lunas','error'=>'Perlu Coba Lagi','expired'=>'Kedaluwarsa','failed'=>'Gagal','cancelled'=>'Dibatalkan'];
    return $labels[$status] ?? ucwords(str_replace('_', ' ', $status));
}

/**
 * V12 - Optional RajaOngkir/Komerce Shipping Delivery integration.
 * Shipping Delivery uses a DIFFERENT API key and destination-id namespace from Shipping Cost.
 */
function marketplace_delivery_phone(string $value): string
{
    $digits = preg_replace('/\D+/', '', $value) ?: '';
    if ($digits === '') return '';
    if (str_starts_with($digits, '62')) return $digits;
    if (str_starts_with($digits, '0')) return $digits;
    return '0' . $digits;
}

function marketplace_delivery_http(string $path, string $method = 'GET', array $query = [], ?array $body = null): array
{
    $config = marketplace_delivery_config();
    if (!$config['enabled']) throw new RuntimeException('Shipping Delivery belum diaktifkan di Pengaturan Marketplace.');
    if ($config['api_key'] === '') throw new RuntimeException('API Key Shipping Delivery belum diisi.');
    $url = rtrim($config['base_url'], '/') . '/' . ltrim($path, '/');
    if ($query) $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $headers = ['Accept: application/json', 'x-api-key: ' . $config['api_key']];
    if ($body !== null) $headers[] = 'Content-Type: application/json';
    return marketplace_http_json($url, $method, $headers, $body);
}

function marketplace_delivery_search_destination(string $keyword): array
{
    $keyword = trim($keyword);
    if ($keyword === '') return [];
    $json = marketplace_delivery_http('/tariff/api/v1/destination/search', 'GET', ['keyword' => $keyword]);
    $rows = is_array($json['data'] ?? null) ? $json['data'] : [];
    $out = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) continue;
        $out[] = [
            'id' => $id,
            'label' => trim((string) ($row['label'] ?? '')),
            'subdistrict' => trim((string) ($row['subdistrict_name'] ?? '')),
            'district' => trim((string) ($row['district_name'] ?? '')),
            'city' => trim((string) ($row['city_name'] ?? '')),
            'zip_code' => trim((string) ($row['zip_code'] ?? '')),
        ];
    }
    return $out;
}

function marketplace_delivery_norm(string $value): string
{
    $value = strtoupper(trim($value));
    $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?: '';
    return trim(preg_replace('/\s+/', ' ', $value) ?: '');
}

function marketplace_delivery_resolve_destination(array $wanted): array
{
    $keywords = array_values(array_filter([
        trim((string) ($wanted['zip_code'] ?? '')),
        trim((string) ($wanted['subdistrict'] ?? '')),
        trim((string) ($wanted['district'] ?? '')),
        trim((string) ($wanted['city'] ?? '')),
    ], static fn($v) => $v !== '' && $v !== '0'));
    if (!$keywords) throw new RuntimeException('Data wilayah belum cukup untuk membuat pengiriman otomatis.');

    $wantedNorm = [
        'subdistrict' => marketplace_delivery_norm((string) ($wanted['subdistrict'] ?? '')),
        'district' => marketplace_delivery_norm((string) ($wanted['district'] ?? '')),
        'city' => marketplace_delivery_norm((string) ($wanted['city'] ?? '')),
        'zip_code' => trim((string) ($wanted['zip_code'] ?? '')),
    ];
    $best = null; $bestScore = -1;
    foreach (array_slice($keywords, 0, 3) as $keyword) {
        $rows = marketplace_delivery_search_destination($keyword);
        foreach ($rows as $row) {
            $score = 0;
            if ($wantedNorm['zip_code'] !== '' && $wantedNorm['zip_code'] !== '0' && $row['zip_code'] === $wantedNorm['zip_code']) $score += 100;
            if ($wantedNorm['subdistrict'] !== '' && marketplace_delivery_norm($row['subdistrict']) === $wantedNorm['subdistrict']) $score += 60;
            if ($wantedNorm['district'] !== '' && marketplace_delivery_norm($row['district']) === $wantedNorm['district']) $score += 35;
            if ($wantedNorm['city'] !== '' && marketplace_delivery_norm($row['city']) === $wantedNorm['city']) $score += 20;
            if ($score > $bestScore) { $bestScore = $score; $best = $row; }
        }
        if ($bestScore >= 100) break;
    }
    if (!$best || $bestScore < 20) throw new RuntimeException('Wilayah tidak dapat dipetakan ke Shipping Delivery. Periksa kecamatan/kelurahan/kode pos.');
    return $best;
}

function marketplace_delivery_calculate(int $originId, int $destinationId, int $weightGram, float $itemValue): array
{
    $weightKg = max(0.001, $weightGram / 1000);
    $json = marketplace_delivery_http('/tariff/api/v1/calculate', 'GET', [
        'shipper_destination_id' => $originId,
        'receiver_destination_id' => $destinationId,
        'weight' => rtrim(rtrim(number_format($weightKg, 3, '.', ''), '0'), '.'),
        'item_value' => (int) round($itemValue),
        'cod' => 'no',
    ]);
    $data = is_array($json['data'] ?? null) ? $json['data'] : [];
    $options = [];
    foreach (['calculate_reguler','calculate_cargo','calculate_instant'] as $group) {
        $rows = is_array($data[$group] ?? null) ? $data[$group] : [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $options[] = [
                'shipping_name' => trim((string) ($row['shipping_name'] ?? '')),
                'service_name' => trim((string) ($row['service_name'] ?? '')),
                'shipping_cost' => (float) ($row['shipping_cost'] ?? 0),
                'shipping_cashback' => (float) ($row['shipping_cashback'] ?? 0),
                'shipping_cost_net' => (float) ($row['shipping_cost_net'] ?? $row['shipping_cost'] ?? 0),
                'service_fee' => (float) ($row['service_fee'] ?? 0),
                'etd' => trim((string) ($row['etd'] ?? '')),
            ];
        }
    }
    return $options;
}

function marketplace_delivery_match_option(array $order, array $options): array
{
    $courierNeedle = marketplace_delivery_norm((string) ($order['courier_name'] ?: $order['courier_code']));
    $serviceNeedle = marketplace_delivery_norm((string) $order['courier_service']);
    $best = null; $scoreBest = -1;
    foreach ($options as $option) {
        $courier = marketplace_delivery_norm((string) ($option['shipping_name'] ?? ''));
        $service = marketplace_delivery_norm((string) ($option['service_name'] ?? ''));
        $score = 0;
        if ($courierNeedle !== '' && ($courier === $courierNeedle || str_contains($courier, $courierNeedle) || str_contains($courierNeedle, $courier))) $score += 50;
        if ($serviceNeedle !== '' && ($service === $serviceNeedle || str_contains($service, $serviceNeedle) || str_contains($serviceNeedle, $service))) $score += 50;
        if ($score > $scoreBest) { $scoreBest = $score; $best = $option; }
    }
    if (!$best || $scoreBest < 50) throw new RuntimeException('Layanan kurir checkout tidak tersedia pada Shipping Delivery. Gunakan resi manual atau pilih layanan lain.');
    return $best;
}

function marketplace_delivery_create_order(PDO $db, int $orderId): array
{
    marketplace_install($db);
    $stmt = $db->prepare('SELECT * FROM online_orders WHERE id=? LIMIT 1');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) throw new RuntimeException('Pesanan marketplace tidak ditemukan.');
    if (($order['payment_status'] ?? '') !== 'paid') throw new RuntimeException('Pengiriman otomatis hanya dapat dibuat setelah pembayaran lunas.');
    if (!empty($order['delivery_order_no'])) return $order;

    $config = marketplace_delivery_config();
    foreach (['shipper_name','shipper_phone','shipper_email','shipper_address'] as $key) {
        if (trim((string) $config[$key]) === '') throw new RuntimeException('Lengkapi data pengirim Shipping Delivery di Pengaturan Marketplace.');
    }
    $raja = marketplace_rajaongkir_config();
    $origin = marketplace_delivery_resolve_destination([
        'subdistrict' => $raja['origin_subdistrict_name'], 'district' => $raja['origin_district_name'],
        'city' => $raja['origin_city_name'], 'zip_code' => $raja['origin_zip_code'],
    ]);
    $destination = marketplace_delivery_resolve_destination([
        'subdistrict' => $order['subdistrict'], 'district' => $order['district'], 'city' => $order['city'], 'zip_code' => $order['postal_code'],
    ]);
    $deliveryOptions = marketplace_delivery_calculate((int) $origin['id'], (int) $destination['id'], (int) $order['total_weight_gram'], (float) $order['subtotal']);
    $shipping = marketplace_delivery_match_option($order, $deliveryOptions);
    $items = marketplace_order_items($db, $orderId);
    if (!$items) throw new RuntimeException('Item pesanan tidak ditemukan.');
    $details = [];
    foreach ($items as $item) {
        $details[] = [
            'product_name' => (string) $item['product_name'],
            'product_variant_name' => (string) ($item['unit'] ?: '-'),
            'product_price' => (int) round((float) $item['unit_price']),
            'product_weight' => max(1, (int) $item['weight_gram']),
            'product_width' => $config['default_width_cm'],
            'product_height' => $config['default_height_cm'],
            'product_length' => $config['default_length_cm'],
            'qty' => max(1, (int) $item['qty']),
            'subtotal' => (int) round((float) $item['line_total']),
        ];
    }
    $deliveryGrandTotal = (int) round((float) $order['subtotal'] + (float) $shipping['shipping_cost']);
    $payload = [
        'order_date' => date('Y-m-d'),
        'brand_name' => (string) setting('store_name', 'Marketplace'),
        'shipper_name' => $config['shipper_name'],
        'shipper_phone' => marketplace_delivery_phone($config['shipper_phone']),
        'shipper_destination_id' => (int) $origin['id'],
        'shipper_address' => $config['shipper_address'],
        'shipper_email' => $config['shipper_email'],
        'receiver_name' => (string) $order['recipient_name'],
        'receiver_phone' => marketplace_delivery_phone((string) $order['recipient_phone']),
        'receiver_destination_id' => (int) $destination['id'],
        'receiver_address' => (string) $order['address'],
        'receiver_email' => (string) ($order['recipient_email'] ?? ''),
        'shipping' => (string) $shipping['shipping_name'],
        'shipping_type' => (string) $shipping['service_name'],
        'payment_method' => 'BANK TRANSFER',
        'shipping_cost' => (int) round((float) $shipping['shipping_cost']),
        'shipping_cashback' => (int) round((float) $shipping['shipping_cashback']),
        'service_fee' => 0,
        'additional_cost' => 0,
        'grand_total' => $deliveryGrandTotal,
        'cod_value' => 0,
        'insurance_value' => 0,
        'notes' => trim((string) ($order['customer_note'] ?? '')),
        'order_details' => $details,
    ];
    if ($payload['receiver_email'] === '') unset($payload['receiver_email']);
    $json = marketplace_delivery_http('/order/api/v1/orders/store', 'POST', [], $payload);
    $data = is_array($json['data'] ?? null) ? $json['data'] : [];
    $deliveryOrderNo = trim((string) ($data['order_no'] ?? ''));
    if ($deliveryOrderNo === '') throw new RuntimeException('Shipping Delivery tidak mengembalikan nomor order.');
    $deliveryOrderId = (int) ($data['order_id'] ?? 0);
    $db->prepare("UPDATE online_orders SET delivery_order_id=?,delivery_order_no=?,delivery_origin_id=?,delivery_destination_id=?,delivery_status='Diajukan',delivery_created_at=NOW(),updated_at=NOW() WHERE id=?")
        ->execute([$deliveryOrderId ?: null,$deliveryOrderNo,(int)$origin['id'],(int)$destination['id'],$orderId]);
    marketplace_add_event($db,$orderId,'delivery_created','Pengiriman otomatis dibuat','Shipping Delivery order: '.$deliveryOrderNo);
    return marketplace_get_order($db, (string) $order['order_number']) ?: $order;
}

function marketplace_delivery_detail(PDO $db, array $order): array
{
    if (empty($order['delivery_order_no'])) return ['available'=>false,'data'=>[]];
    $json = marketplace_delivery_http('/order/api/v1/orders/detail', 'GET', ['order_no'=>(string)$order['delivery_order_no']]);
    $data = is_array($json['data'] ?? null) ? $json['data'] : [];
    $awb = trim((string)($data['awb'] ?? ''));
    $status = trim((string)($data['order_status'] ?? $order['delivery_status'] ?? ''));
    $live = trim((string)($data['live_tracking_url'] ?? $data['tracking_url'] ?? ''));
    $db->prepare("UPDATE online_orders SET awb_number=CASE WHEN ?<>'' THEN ? ELSE awb_number END,delivery_status=?,delivery_live_tracking_url=CASE WHEN ?<>'' THEN ? ELSE delivery_live_tracking_url END,delivery_last_sync_at=NOW(),order_status=CASE WHEN ?<>'' AND order_status IN ('paid','processing') THEN 'shipped' ELSE order_status END,fulfillment_status=CASE WHEN ?<>'' THEN 'shipped' ELSE fulfillment_status END,shipped_at=CASE WHEN ?<>'' THEN COALESCE(shipped_at,NOW()) ELSE shipped_at END,updated_at=NOW() WHERE id=?")
       ->execute([$awb,$awb,$status,$live,$live,$awb,$awb,$awb,(int)$order['id']]);
    return ['available'=>true,'data'=>$data,'awb'=>$awb,'status'=>$status,'live_tracking_url'=>$live];
}

function marketplace_delivery_request_pickup(PDO $db, int $orderId, string $date, string $time, string $vehicle): array
{
    $stmt=$db->prepare('SELECT * FROM online_orders WHERE id=? LIMIT 1'); $stmt->execute([$orderId]); $order=$stmt->fetch();
    if(!$order) throw new RuntimeException('Pesanan tidak ditemukan.');
    if(empty($order['delivery_order_no'])) throw new RuntimeException('Buat pengiriman otomatis terlebih dahulu.');
    $vehicle = ucfirst(strtolower(trim($vehicle)));
    if (!in_array($vehicle,['Motor','Mobil','Truk'],true)) throw new RuntimeException('Kendaraan pickup tidak valid.');
    $pickupTs = strtotime($date . ' ' . $time);
    if (!$pickupTs || $pickupTs < time() + 85*60) throw new RuntimeException('Jadwal pickup minimal sekitar 90 menit dari sekarang.');
    $json = marketplace_delivery_http('/order/api/v1/pickup/request','POST',[],[
        'pickup_date'=>$date,'pickup_time'=>$time,'pickup_vehicle'=>$vehicle,
        'orders'=>[['order_no'=>(string)$order['delivery_order_no']]],
    ]);
    $rows = is_array($json['data'] ?? null) ? $json['data'] : [];
    $row = $rows[0] ?? [];
    $status = strtolower(trim((string)($row['status'] ?? 'success')));
    if ($status === 'failed') throw new RuntimeException((string)($row['message'] ?? 'Request pickup ditolak Shipping Delivery.'));
    $awb = trim((string)($row['awb'] ?? ''));
    $db->prepare("UPDATE online_orders SET delivery_pickup_date=?,delivery_pickup_time=?,delivery_pickup_vehicle=?,delivery_pickup_at=NOW(),delivery_status='Pickup dijadwalkan',awb_number=CASE WHEN ?<>'' THEN ? ELSE awb_number END,order_status=CASE WHEN ?<>'' THEN 'shipped' ELSE order_status END,fulfillment_status=CASE WHEN ?<>'' THEN 'shipped' ELSE fulfillment_status END,shipped_at=CASE WHEN ?<>'' THEN COALESCE(shipped_at,NOW()) ELSE shipped_at END,updated_at=NOW() WHERE id=?")
        ->execute([$date,$time,$vehicle,$awb,$awb,$awb,$awb,$awb,$orderId]);
    marketplace_add_event($db,$orderId,'pickup_requested','Pickup kurir dijadwalkan',$date.' '.$time.' • '.$vehicle.($awb!==''?' • Resi: '.$awb:''));
    $fresh = marketplace_get_order($db,(string)$order['order_number']) ?: $order;
    if ($awb === '') {
        try { marketplace_delivery_detail($db, $fresh); } catch (Throwable $ignore) {}
        $fresh = marketplace_get_order($db,(string)$order['order_number']) ?: $fresh;
    }
    return $fresh;
}

function marketplace_delivery_print_label(PDO $db, int $orderId): array
{
    $stmt=$db->prepare('SELECT * FROM online_orders WHERE id=? LIMIT 1'); $stmt->execute([$orderId]); $order=$stmt->fetch();
    if(!$order || empty($order['delivery_order_no'])) throw new RuntimeException('Order Shipping Delivery belum tersedia.');
    $config=marketplace_delivery_config();
    $json=marketplace_delivery_http('/order/api/v1/orders/print-label','POST',['page'=>$config['label_page'],'order_no'=>(string)$order['delivery_order_no']]);
    $data=is_array($json['data']??null)?$json['data']:[];
    $relative='';
    $base64=trim((string)($data['base_64']??''));
    if($base64!==''){
        if(str_contains($base64,',')) $base64=substr($base64,strpos($base64,',')+1);
        $binary=base64_decode($base64,true);
        if($binary!==false && str_starts_with($binary,'%PDF')){
            $dir=dirname(__DIR__,3).'/public/uploads/shipping-labels';
            if(!is_dir($dir) && !mkdir($dir,0775,true) && !is_dir($dir)) throw new RuntimeException('Folder label pengiriman tidak dapat dibuat.');
            $filename='label-'.preg_replace('/[^A-Za-z0-9_-]+/','-',(string)$order['order_number']).'.pdf';
            if(file_put_contents($dir.'/'.$filename,$binary,LOCK_EX)===false) throw new RuntimeException('Gagal menyimpan label PDF.');
            $relative='uploads/shipping-labels/'.$filename;
        }
    }
    if($relative===''){
        $path=trim((string)($data['path']??''));
        if($path!=='') $relative=rtrim($config['base_url'],'/').'/order'.($path[0]==='/'?$path:'/'.$path);
    }
    if($relative==='') throw new RuntimeException('Label berhasil diminta tetapi file label tidak tersedia.');
    $db->prepare('UPDATE online_orders SET delivery_label_file=?,updated_at=NOW() WHERE id=?')->execute([$relative,$orderId]);
    marketplace_add_event($db,$orderId,'label_ready','Label pengiriman siap','Label pengiriman berhasil dibuat.');
    return marketplace_get_order($db,(string)$order['order_number']) ?: $order;
}

function marketplace_delivery_tracking(PDO $db, array $order, bool $force=false): array
{
    $config=marketplace_delivery_config();
    if(!$config['enabled'] || $config['api_key']==='' || empty($order['awb_number'])) return ['available'=>false,'history'=>[]];
    if(!$force && !empty($order['delivery_last_sync_at']) && strtotime((string)$order['delivery_last_sync_at']) > time()-300){
        return ['available'=>true,'history'=>[],'last_status'=>(string)($order['delivery_status']??''),'cached'=>true];
    }
    $shipping=trim((string)($order['courier_name'] ?: strtoupper((string)$order['courier_code'])));
    $json=marketplace_delivery_http('/order/api/v1/orders/history-airway-bill','GET',['shipping'=>$shipping,'airway_bill'=>(string)$order['awb_number']]);
    $data=is_array($json['data']??null)?$json['data']:[];
    $history=is_array($data['history']??null)?$data['history']:[];
    $last=trim((string)($data['last_status']??''));
    $old=trim((string)($order['delivery_status']??''));
    $db->prepare('UPDATE online_orders SET delivery_status=?,delivery_last_sync_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$last ?: $old,(int)$order['id']]);
    if($last!=='' && $last!==$old) marketplace_add_event($db,(int)$order['id'],'delivery_update','Update pengiriman',$last);
    $lastNorm=strtolower($last);
    if($last!=='' && (str_contains($lastNorm,'selesai') || str_contains($lastNorm,'delivered') || str_contains($lastNorm,'diterima'))){
        $db->prepare("UPDATE online_orders SET order_status='completed',fulfillment_status='completed',completed_at=COALESCE(completed_at,NOW()),updated_at=NOW() WHERE id=? AND order_status<>'completed'")->execute([(int)$order['id']]);
    }
    return ['available'=>true,'history'=>$history,'last_status'=>$last,'cached'=>false];
}

// V14 marketplace customer-service + Teknorilis WA Gateway.
require_once __DIR__ . '/WaGateway.php';
require_once __DIR__ . '/CustomerService.php';
