<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class LegacyController extends BaseController
{
    private string $legacyPublic;

    public function __construct()
    {
        $this->legacyPublic = rtrim(ROOTPATH . 'legacy/public', '/\\') . DIRECTORY_SEPARATOR;
    }

    public function root(): ResponseInterface
    {
        return $this->runLegacy('');
    }

    /**
     * CI4 catch-all entry. Read the URI directly instead of receiving (:any)
     * as a route parameter. This keeps all nested legacy paths in one string.
     */
    public function dispatch(): ResponseInterface
    {
        $uriPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        $path = $this->stripApplicationBasePath($uriPath);
        return $this->runLegacy($path);
    }

    /**
     * Backward-compatible method kept for old route caches/configs.
     */
    public function handle(string $path = ''): ResponseInterface
    {
        if ($path === '') {
            $uriPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
            $path = $this->stripApplicationBasePath($uriPath);
        }
        return $this->runLegacy($path);
    }

    private function stripApplicationBasePath(string $path): string
    {
        $path = rawurldecode($path);
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('~/+~', '/', $path) ?? $path;

        // When installed in a subfolder, strip the directory containing the
        // CI4 front controller from the URI before resolving the legacy target.
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $base = trim(str_replace('\\', '/', dirname($script)), '/.');
        $clean = ltrim($path, '/');
        if ($base !== '' && ($clean === $base || str_starts_with($clean, $base . '/'))) {
            $clean = ltrim(substr($clean, strlen($base)), '/');
        }

        if (str_starts_with($clean, 'index.php/')) {
            $clean = substr($clean, strlen('index.php/'));
        } elseif ($clean === 'index.php') {
            $clean = '';
        }

        return $clean;
    }

    private function runLegacy(string $path): ResponseInterface
    {
        $path = rawurldecode($path);
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $path = preg_replace('~/+~', '/', $path) ?? $path;
        $path = rtrim($path, '/');

        // Compatibility aliases from the original Apache rewrite rules.
        if ($path === 'adm' || $path === 'admin') {
            $path = 'admin/dashboard.php';
        }

        $cleanMap = [
            'produk' => 'store/products.php',
            'masuk' => 'store/login.php',
            'daftar' => 'store/register.php',
            'akun' => 'store/account.php',
            'profil' => 'store/profile.php',
            'pesanan-saya' => 'store/orders.php',
            'alamat-saya' => 'store/addresses.php',
            'wishlist' => 'store/wishlist.php',
            'notifikasi' => 'store/notifications.php',
            'bantuan-pesanan' => 'store/support.php',
            'keluar-akun' => 'store/customer-logout.php',
            'keranjang' => 'store/cart.php',
            'checkout' => 'store/checkout.php',
            'tracking' => 'store/tracking.php',
            'kurir' => 'courier/index.php',
            'kurir/login' => 'courier/login.php',
            'kurir/logout' => 'courier/logout.php',
            'kurir/selesai' => 'courier/done.php',
            'kurir/riwayat' => 'courier/done.php',
            'kurir/profil' => 'courier/profile.php',
            'kurir/aksi' => 'courier/action.php',
            'login' => 'login.php',
            'staff/login' => 'login.php',
            'admin/login' => 'login.php',
            'logout' => 'logout.php',
        ];

        if (isset($cleanMap[$path])) {
            $path = $cleanMap[$path];
        } elseif (preg_match('~^produk/([0-9]+)(?:-[A-Za-z0-9-]+)?$~', $path, $m)) {
            $_GET['id'] = (int) $m[1];
            $path = 'store/product.php';
        } elseif (preg_match('~^bantuan-pesanan/([0-9]+)$~', $path, $m)) {
            $_GET['id'] = (int) $m[1];
            $path = 'store/support-detail.php';
        } elseif (preg_match('~^pesanan/([A-Za-z0-9-]+)$~', $path, $m)) {
            $_GET['order'] = $m[1];
            $path = 'store/order.php';
        } elseif (preg_match('~^kurir/paket/([0-9]+)$~', $path, $m)) {
            $_GET['id'] = (int) $m[1];
            $path = 'courier/order.php';
        }

        // Never allow traversal, null-byte payloads, or hidden path segments.
        if ($path !== '' && (
            str_contains($path, '..')
            || str_contains($path, "\0")
            || preg_match('~(?:^|/)\.(?:/|$)~', $path)
        )) {
            return $this->response->setStatusCode(400)->setBody('Bad Request');
        }

        $candidate = $path !== '' ? $this->legacyPublic . str_replace('/', DIRECTORY_SEPARATOR, $path) : '';
        if ($candidate !== '' && is_file($candidate) && strtolower(pathinfo($candidate, PATHINFO_EXTENSION)) === 'php') {
            $target = $candidate;
        } elseif ($candidate !== '' && is_dir($candidate) && is_file(rtrim($candidate, '/\\') . DIRECTORY_SEPARATOR . 'index.php')) {
            $target = rtrim($candidate, '/\\') . DIRECTORY_SEPARATOR . 'index.php';
        } elseif ($path === '') {
            $target = $this->legacyPublic . 'index.php';
        } else {
            return $this->response->setStatusCode(404)->setBody('Halaman tidak ditemukan.');
        }

        if (!is_file($target)) {
            return $this->response->setStatusCode(404)->setBody('Legacy target not found.');
        }

        if (!defined('VAPEKONG_CI4_BRIDGE')) {
            define('VAPEKONG_CI4_BRIDGE', true);
        }

        // Emulate the environment the old script had when Apache executed it
        // directly. Several old modules inspect SCRIPT_NAME (for example
        // promo create/edit), and relative file operations can depend on cwd.
        $relative = str_replace('\\', '/', substr($target, strlen($this->legacyPublic)));
        $virtualScript = '/' . ltrim($relative, '/');
        $oldServer = [];
        foreach (['SCRIPT_NAME','PHP_SELF','SCRIPT_FILENAME','DOCUMENT_URI'] as $key) {
            $oldServer[$key] = $_SERVER[$key] ?? null;
        }
        $oldCwd = getcwd();

        $_SERVER['SCRIPT_NAME'] = $virtualScript;
        $_SERVER['PHP_SELF'] = $virtualScript;
        $_SERVER['SCRIPT_FILENAME'] = $target;
        $_SERVER['DOCUMENT_URI'] = $virtualScript;
        $_SERVER['VAPEKONG_LEGACY_TARGET'] = $relative;

        if (@chdir(dirname($target)) === false && is_string($oldCwd) && $oldCwd !== '') {
            @chdir($oldCwd);
        }

        ob_start();
        try {
            require $target;
            $body = (string) ob_get_clean();
            $status = http_response_code();
            if (is_int($status) && $status >= 100 && $status <= 599) {
                $this->response->setStatusCode($status);
            }
            return $this->response->setBody($body);
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            log_message('error', '[Legacy bridge] {type}: {message} in {file}:{line}', [
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            if (ENVIRONMENT !== 'production') {
                throw $e;
            }
            return $this->response->setStatusCode(500)->setBody('Terjadi kesalahan sistem. Periksa writable/logs.');
        } finally {
            if (is_string($oldCwd) && $oldCwd !== '') {
                @chdir($oldCwd);
            }
            foreach ($oldServer as $key => $value) {
                if ($value === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $value;
                }
            }
            unset($_SERVER['VAPEKONG_LEGACY_TARGET']);
        }
    }
}
