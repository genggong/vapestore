<?php if(marketplace_courier_session()): ?>
<nav class="courier-bottom-nav" aria-label="Navigasi Portal Kurir">
    <a class="<?= $courierActive==='jobs'?'active':''; ?>" href="<?= legacy_base_url('kurir'); ?>"><i class="bi bi-box-seam"></i><span>Tugas</span></a>
    <a class="<?= $courierActive==='done'?'active':''; ?>" href="<?= legacy_base_url('kurir/selesai'); ?>"><i class="bi bi-check2-circle"></i><span>Selesai</span></a>
    <a class="<?= $courierActive==='profile'?'active':''; ?>" href="<?= legacy_base_url('kurir/profil'); ?>"><i class="bi bi-person-circle"></i><span>Profil</span></a>
</nav>
<?php endif; ?>
<script src="<?= e(legacy_base_url('assets/js/courier-portal.js')); ?>?v=<?= e($courierJsVersion??'17.0.10'); ?>" defer></script>
</body></html>
