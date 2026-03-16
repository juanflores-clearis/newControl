<?php
$basePath = dirname($_SERVER['PHP_SELF']);
if ($basePath === '/') $basePath = '';

$tools = [
    [
        'route' => '/herramientas/detector-tecnologia',
        'title' => 'Detector de Tecnología',
        'description' => 'Analiza una URL y detecta qué CMS o plataforma e-commerce utiliza (WordPress, Shopify, PrestaShop, Sylius, Magento...)',
        'icon' => 'bi-search',
        'color' => '#2E9935'
    ],
    [
        'route' => '/herramientas/test-web',
        'title' => 'Pruebas Web',
        'description' => 'Recorre una web de forma automÃ¡tica para detectar errores 404, fallos de carga, problemas de controles y seÃ±ales bÃ¡sicas de maquetaciÃ³n.',
        'icon' => 'bi-bug',
        'color' => '#d97706'
    ],
];
?>

<div class="d-flex align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1">Herramientas</h2>
        <p class="text-muted mb-0">Utilidades para análisis y gestión web</p>
    </div>
</div>

<div class="row g-4">
    <?php foreach ($tools as $tool): ?>
        <div class="col-md-4">
            <a href="<?= $basePath ?>?route=<?= $tool['route'] ?>" class="text-decoration-none">
                <div class="card h-100 tool-card">
                    <div class="card-body d-flex flex-column align-items-center text-center p-4">
                        <div class="tool-icon-wrapper mb-3" style="background: <?= $tool['color'] ?>15;">
                            <i class="<?= $tool['icon'] ?>" style="color: <?= $tool['color'] ?>; font-size: 1.75rem;"></i>
                        </div>
                        <h5 class="fw-semibold text-dark mb-2"><?= $tool['title'] ?></h5>
                        <p class="text-muted small mb-0"><?= $tool['description'] ?></p>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<style>
    .tool-card {
        cursor: pointer;
        transition: all 0.25s ease;
    }
    .tool-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
    }
    .tool-icon-wrapper {
        width: 64px;
        height: 64px;
        border-radius: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }
</style>
