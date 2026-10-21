<?php
// SEMENTARA — hanya untuk uji lokal upload foto kategori. Hapus setelah dipakai.
require_once __DIR__ . '/../app/bootstrap.php';
$_SESSION['auth_user'] = ['id' => 1, 'name' => 'Admin', 'username' => 'admin', 'role_slug' => 'super_admin', 'role_id' => 1, 'branch_id' => 1];
echo CSRF::token();
