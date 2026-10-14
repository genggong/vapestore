<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setAutoRoute(false);

// IMPORTANT FOR THE LEGACY BRIDGE
// Do not pass (:any) back to the controller as $1. In CI4, a wildcard that
// contains several slash-separated segments can otherwise be split into
// several controller parameters. The controller reads the request URI itself,
// so /admin/products/index.php, /store/api/..., and /produk/123-slug stay intact.
$verbs = ['GET','POST','PUT','PATCH','DELETE','OPTIONS','HEAD'];
$routes->match($verbs, '/', 'LegacyController::root');
$routes->match($verbs, '(:any)', 'LegacyController::dispatch');
