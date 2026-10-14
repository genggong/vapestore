<?php
// Clean domain entry point: vapekongstore.com/ langsung membuka marketplace.
$public = __DIR__ . '/public/index.php';

if (!is_file($public)) {
    http_response_code(500);
    exit('Marketplace entry file tidak ditemukan.');
}

require $public;
