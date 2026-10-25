<?php
require_once __DIR__ . '/../app/bootstrap.php';
$db = Database::connect();
$uid = max(1, (int) ($_GET['u'] ?? 1));
$row = $db->query("SELECT u.*, r.name role_name, r.slug role_slug FROM users u LEFT JOIN roles r ON r.id=u.role_id WHERE u.id=$uid")->fetch();
$_SESSION['auth_user'] = ['id'=>(int)$row['id'],'role_id'=>(int)$row['role_id'],'role_name'=>$row['role_name'] ?? 'Super Admin','role_slug'=>$row['role_slug'] ?? 'super_admin','name'=>$row['name'],'username'=>$row['username'],'email'=>$row['email'] ?? null,'avatar'=>$row['avatar'] ?? null];
unset($_SESSION['auth_permissions']);
$path = (string) ($_GET['p'] ?? 'admin/marketplace-courier-rates/index.php');
$path = preg_split('/[?#]/', $path)[0];
chdir(dirname(__DIR__ . '/' . $path));
require __DIR__ . '/' . $path;
