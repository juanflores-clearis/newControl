<?php
$current_route = $_GET['route'] ?? '/';
$user_role = $_SESSION['role'] ?? null;
$is_admin = ($user_role === 'admin');
$show_nav = isset($_SESSION['user_id']); // ✅ solo si está logueado
$minimal_header = ($current_route === '/herramientas/detector-tecnologia');

$nav_items = [
    '/dashboard' => ['label' => 'Inicio', 'icon' => 'bi-house'],
    '/websites' => ['label' => 'Gestionar URLs', 'icon' => 'bi-globe'],
    '/herramientas' => ['label' => 'Herramientas', 'icon' => 'bi-tools'],
    '/tests' => ['label' => 'Tests E2E', 'icon' => 'bi-terminal'],
    '/access' => ['label' => 'Control de acceso', 'icon' => 'bi-shield-lock'],
    '/whatsapp' => ['label' => 'WhatsApp', 'icon' => 'bi-whatsapp'],
    '/config_email' => ['label' => 'Email', 'icon' => 'bi-envelope'],
];

// Obtener la ruta base (apunta a /newcontrol/public)
$basePath = dirname($_SERVER['PHP_SELF']);
if ($basePath === '/') {
    $basePath = '';
}

// Ruta a la carpeta assets (un nivel por encima de public/)
// $basePath = /newcontrol/public  →  $assetPath = /newcontrol
$assetPath = rtrim(dirname($basePath), '/');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>NewControl | Plataforma de Monitoreo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $assetPath ?>/assets/css/custom.css">
    <style>
        :root {
            --primary: #2E9935;
            --primary-hover: #237a2a;
            --primary-light: #A2C551;
            --primary-lime: #B9D477;
            --primary-olive: #B5C76A;
            --primary-muted: #94A06F;
            --clearis-dark: #000000;
            --bg-body: #f8fafc;
            --navbar-bg: rgba(255, 255, 255, 0.8);
            --card-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: #1e293b;
        }
        .navbar-clearis {
            background-color: var(--navbar-bg);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            padding: 0.75rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--primary) !important;
            letter-spacing: -0.025em;
        }
        .nav-link {
            font-weight: 500;
            color: #64748b !important;
            transition: all 0.2s ease;
            padding: 0.5rem 1rem !important;
            border-radius: 0.5rem;
            margin: 0 0.125rem;
        }
        .nav-link:hover {
            color: var(--primary) !important;
            background: rgba(46, 153, 53, 0.06);
        }
        .nav-link.active {
            color: var(--primary) !important;
            background: rgba(46, 153, 53, 0.12);
        }
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            font-weight: 500;
            padding: 0.5rem 1.25rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            transform: translateY(-1px);
        }
        .btn-primary:focus, .btn-primary:active {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            box-shadow: 0 0 0 0.25rem rgba(46, 153, 53, 0.25);
        }
        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }
        .btn-outline-primary:hover {
            background-color: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }
        .text-primary {
            color: var(--primary) !important;
        }
        .bg-primary {
            background-color: var(--primary) !important;
        }
        .badge.bg-primary {
            background-color: var(--primary) !important;
        }
        .card {
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            box-shadow: var(--card-shadow);
            transition: transform 0.22s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card:hover {
            transform: translateY(-2px);
        }
        main {
            padding-top: 2rem;
            padding-bottom: 3rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 0.25rem rgba(46, 153, 53, 0.15);
        }
        a {
            color: var(--primary);
        }
        a:hover {
            color: var(--primary-hover);
        }
    </style>
</head>
<body class="min-vh-100 d-flex flex-column">

<header>
    <nav class="navbar navbar-expand-lg navbar-clearis">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="<?= $basePath ?>?route=/dashboard">
                <i class="bi bi-shield-check me-2"></i>
                NewControl
            </a>

            <?php if ($minimal_header): ?>
                <!-- Cabecera reducida: solo logo + enlace a Herramientas -->
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a href="<?= $basePath ?>?route=/herramientas"
                           class="nav-link active">
                            <i class="bi bi-tools me-1"></i>
                            Herramientas
                        </a>
                    </li>
                </ul>
            <?php else: ?>
                <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarContent">
                    <?php if ($show_nav): ?>
                        <ul class="navbar-nav mx-auto">
                            <?php foreach ($nav_items as $route => $data): ?>
                                <?php if ($route !== '/config_email' || $is_admin): ?>
                                    <li class="nav-item">
                                        <a href="<?= $basePath ?>?route=<?= $route ?>"
                                           class="nav-link <?= ($current_route === $route || strpos($current_route, $route . '/') === 0) ? 'active' : '' ?>">
                                            <i class="<?= $data['icon'] ?> me-1"></i>
                                            <?= $data['label'] ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                        <div class="navbar-nav">
                            <a href="<?= $basePath ?>?route=/logout" class="nav-link text-danger-hover">
                                <i class="bi bi-box-arrow-right me-1"></i>
                                Salir
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </nav>
</header>

<main class="container flex-grow-1">
