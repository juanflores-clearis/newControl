<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

require_once __DIR__ . '/../config/db.php';

use App\Core\Router;

$router = new Router();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cargar rutas
require_once __DIR__ . '/../routes/web.php';

session_start();

// Obtener ruta desde query string: ?route=/websites
$route = $_GET['route'] ?? '/';

$router->dispatch($route);
