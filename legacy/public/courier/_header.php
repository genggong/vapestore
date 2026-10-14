<?php
$courierPageTitle=$courierPageTitle??'Portal Kurir';
$courierActive=$courierActive??'jobs';
$courierUser=marketplace_courier_session();
$siteTitle=trim((string)setting('marketplace_site_title',''))?:trim((string)setting('store_name','Vapekong'));
$marketplaceTheme=marketplace_theme_config();
$marketplaceThemeSync=!empty($marketplaceTheme['sync_internal']);
$courierCssFile=dirname(__DIR__).'/assets/css/courier-portal.css';
$themeCssFile=dirname(__DIR__).'/assets/css/marketplace-internal-theme.css';
$courierJsFile=dirname(__DIR__).'/assets/js/courier-portal.js';
$courierCssVersion=is_file($courierCssFile)?(string)filemtime($courierCssFile):'17.0.10';
$themeCssVersion=is_file($themeCssFile)?(string)filemtime($themeCssFile):'17.0.10';
$courierJsVersion=is_file($courierJsFile)?(string)filemtime($courierJsFile):'17.0.10';
?>
<!doctype html>
<html lang="id" style="<?= e(marketplace_theme_style_vars($marketplaceTheme)); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="<?= e($marketplaceThemeSync?$marketplaceTheme['primary']:'#7C3AED'); ?>">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="color-scheme" content="light">
    <title><?= e($courierPageTitle); ?> - <?= e($siteTitle); ?></title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(legacy_base_url('assets/css/courier-portal.css')); ?>?v=<?= e($courierCssVersion); ?>">
    <link rel="stylesheet" href="<?= e(legacy_base_url('assets/css/marketplace-internal-theme.css')); ?>?v=<?= e($themeCssVersion); ?>">
    <style>
      /* Fail-safe: halaman tetap usable jika stylesheet eksternal tertahan cache/CDN. */
      html,body{min-height:100%}body.courier-body{visibility:visible}.courier-form input,.courier-form textarea,.courier-form select{max-width:100%}
    </style>
</head>
<body class="courier-body <?= $marketplaceThemeSync?'courier-marketplace-sync':''; ?>" data-marketplace-theme-sync="<?= $marketplaceThemeSync?'on':'off'; ?>" data-courier-js="<?= e(legacy_base_url('assets/js/courier-portal.js')); ?>?v=<?= e($courierJsVersion); ?>">
<header class="courier-topbar">
    <a class="courier-brand" href="<?= legacy_base_url('kurir'); ?>" aria-label="Beranda Portal Kurir">
        <span class="courier-brand-icon"><i class="bi bi-bicycle"></i></span>
        <span><b><?= e($siteTitle); ?></b><small>Portal Kurir</small></span>
    </a>
    <?php if($courierUser): ?>
    <a class="courier-top-profile" href="<?= legacy_base_url('kurir/profil'); ?>" aria-label="Profil kurir">
        <span><?= e(mb_strtoupper(mb_substr((string)$courierUser['name'],0,1))); ?></span>
        <div><b><?= e($courierUser['name']); ?></b><small><?= e($courierUser['vehicle_type'].($courierUser['vehicle_number']!==''?' • '.$courierUser['vehicle_number']:'')); ?></small></div>
    </a>
    <?php endif; ?>
</header>
<?php if($msg=flash('success')): ?><div class="courier-toast success" role="status"><i class="bi bi-check-circle-fill"></i><span><?= e($msg); ?></span></div><?php endif; ?>
<?php if($msg=flash('error')): ?><div class="courier-toast error" role="alert"><i class="bi bi-exclamation-triangle-fill"></i><span><?= e($msg); ?></span></div><?php endif; ?>
