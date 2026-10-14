<?php
require_once __DIR__.'/_bootstrap.php';
if(marketplace_courier_session())legacy_redirect('kurir');
if(is_post()){
    if(!CSRF::verify()){flash('error','Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');legacy_redirect('kurir/login');}
    $identity=trim((string)($_POST['identity']??''));$password=(string)($_POST['password']??'');
    if($identity===''||$password===''){flash('error','Username/WhatsApp/Email dan password wajib diisi.');legacy_redirect('kurir/login');}

    // Throttle ringan per sesi/browser agar endpoint publik tidak mudah dibombardir brute-force.
    $now=time();$attempts=$_SESSION['marketplace_courier_login_failures']??[];
    $attempts=is_array($attempts)?array_values(array_filter($attempts,static fn($ts)=>(int)$ts>$now-600)):[];
    if(count($attempts)>=8){
        $wait=max(1,(int)ceil((600-($now-(int)min($attempts)))/60));
        flash('error','Terlalu banyak percobaan login. Coba lagi sekitar '.$wait.' menit.');legacy_redirect('kurir/login');
    }

    if(marketplace_courier_login($db,$identity,$password)){
        unset($_SESSION['marketplace_courier_login_failures']);
        flash('success','Login berhasil. Selamat bertugas.');legacy_redirect('kurir');
    }
    $attempts[]=$now;$_SESSION['marketplace_courier_login_failures']=$attempts;
    usleep(250000);
    flash('error','Username/nomor/email atau password salah.');legacy_redirect('kurir/login');
}
$courierPageTitle='Login Kurir';$courierUser=null;require __DIR__.'/_header.php';
?>
<main class="courier-login-wrap">
<section class="courier-login-card">
    <div class="courier-login-brand-row">
        <div class="courier-login-icon"><i class="bi bi-bicycle"></i></div>
        <div><span class="courier-eyebrow">PORTAL KURIR TOKO</span><small class="courier-secure-label"><i class="bi bi-shield-check"></i> Akses khusus kurir aktif</small></div>
    </div>
    <h1>Masuk untuk kelola paket</h1>
    <p>Lihat tugas yang ditugaskan kepada akun Anda, buka alamat pelanggan, update status, dan kirim bukti penerimaan.</p>
    <form method="post" class="courier-form" data-loading-text="Memeriksa akun...">
        <?= CSRF::input(); ?>
        <label>Username / WhatsApp / Email
            <span class="courier-input-icon"><i class="bi bi-person"></i><input name="identity" required maxlength="190" autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="kurir01 atau 08xxxxxxxxxx"></span>
        </label>
        <label>Password
            <span class="courier-input-icon courier-password-wrap"><i class="bi bi-lock"></i><input type="password" name="password" required maxlength="120" autocomplete="current-password" placeholder="Masukkan password"><button type="button" class="courier-password-toggle" data-password-toggle aria-label="Tampilkan password"><i class="bi bi-eye"></i></button></span>
        </label>
        <button class="courier-btn primary" type="submit"><i class="bi bi-box-arrow-in-right"></i> Masuk Portal Kurir</button>
    </form>
    <div class="courier-login-help"><i class="bi bi-info-circle"></i><span>Akun kurir dibuat dan diaktifkan oleh admin toko.</span></div>
</section>
</main>
<?php require __DIR__.'/_footer.php'; ?>
