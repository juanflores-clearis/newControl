<?php
// tests/test_functionality.php

// 1. Simular entorno (cargar DB y autoloader si existe)
// Ajusta las rutas según donde se ejecute. Asumimos ejecución desde raíz del proyecto: php tests/test_functionality.php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/Models/Website.php';
require_once __DIR__ . '/../app/Models/AccessControl.php';
require_once __DIR__ . '/../app/Core/Auth.php'; // Si se necesita mockear Auth

echo "=== INICIANDO PRUEBAS DEL SISTEMA ===\n";

// Mock de usuario para pruebas
$testUserId = 1; // Asegúrate de que este usuario exista en tu DB local para pruebas FK
$_SESSION['user_id'] = $testUserId;
$_SESSION['role'] = 'admin';

try {
    $pdo = DB::pdo();
    echo "[PASS] Conexión a Base de Datos exitosa.\n";
} catch (Exception $e) {
    die("[FAIL] Error conexion DB: " . $e->getMessage() . "\n");
}

// ---------------------------------------------------------
// PRUEBA 1: Inserción de URL al control de páginas caídas (Websites)
// ---------------------------------------------------------
echo "\n--- Prueba 1: Control de Páginas Caídas (Websites) ---\n";
$testUrl = "https://example-test-" . time() . ".com";

try {
    if (Website::existsForUser($testUserId, $testUrl)) {
        echo "[INFO] La URL ya existe, eliminando para prueba limpia...\n";
        // Necesitamos buscar ID para borrar
        // Website::delete por ID... implementaremos busca primero
    }

    $websiteId = Website::insert($testUserId, $testUrl);
    
    if ($websiteId) {
        echo "[PASS] Website insertado correctamente. ID: $websiteId\n";
    } else {
        echo "[FAIL] Falló inserción de Website.\n";
    }

    // Verificar lectura
    $site = Website::findByIdAndUser($websiteId, $testUserId);
    if ($site && $site['url'] === $testUrl) {
         echo "[PASS] Website recuperado correctamente.\n";
    } else {
         echo "[FAIL] Website insertado no coincide.\n";
    }

    // Limpieza
    Website::delete($websiteId, $testUserId);
    echo "[PASS] Website de prueba eliminado.\n";

} catch (Exception $e) {
    echo "[FAIL] Excepción en Websites: " . $e->getMessage() . "\n";
}

// ---------------------------------------------------------
// PRUEBA 2: Inserción de Credenciales (AccessControl)
// ---------------------------------------------------------
echo "\n--- Prueba 2: Control de Acceso (AccessControl) ---\n";

$accessData = [
    'url' => 'https://test-access.com',
    'technology' => 'Laravel',
    'version' => '10.0',
    'responsible' => 'Tecnico Test', // Asegúrate de que este "fullname" exista en rel_fullname_username si quieres probar permissions
    'hostingOld' => 'OldHost',
    'hostingDev' => 'DevHost',
    'hostingProd' => 'ProdHost',
    'backOld' => 'BackOld',
    'backDev' => 'BackDev',
    'backProd' => 'BackProd',
    'backup' => 'Daily',
    'ftp' => 'ftp://test.com',
    'ftpDev' => 'ftp://dev.test.com',
    'comentario' => 'Test comment'
];

try {
    // Insertar
    $recordId = AccessControl::insert($accessData);
    if ($recordId) {
        echo "[PASS] AccessControl insertado. ID: $recordId\n";
    } else {
        die("[FAIL] Falló inserción AccessControl.\n");
    }

    // Actualizar
    $accessData['recordId'] = $recordId;
    $accessData['comentario'] = 'Updated Comment';
    $updatedId = AccessControl::update($accessData);
    
    $rec = AccessControl::getById($recordId);
    if ($rec['comentario'] === 'Updated Comment') {
        echo "[PASS] AccessControl actualizado correctamente.\n";
    } else {
        echo "[FAIL] Actualización fallida.\n";
    }

    // Eliminar
    if (AccessControl::delete($recordId)) {
        echo "[PASS] AccessControl eliminado correctamente.\n";
    } else {
        echo "[FAIL] Error al eliminar AccessControl.\n";
    }

} catch (Exception $e) {
    echo "[FAIL] Excepción en AccessControl: " . $e->getMessage() . "\n";
}

// ---------------------------------------------------------
// PRUEBA 3: Simulación de Cron / Análisis
// ---------------------------------------------------------
echo "\n--- Prueba 3: Cron y Análisis ---\n";
// Aquí podríamos invocar scripts/check_domains.php si queremos
// Nota: check_domains.php es un script, no una clase, pero podemos incluirlo si está diseñado para ello.
// O simplemente verificar que el archivo existe y es ejecutable.

$cronScript = __DIR__ . '/../scripts/check_domains.php';
if (file_exists($cronScript)) {
    echo "[PASS] Script de Cron encontrado: $cronScript\n";
    echo "[INFO] Para probar el cron real, ejecuta: php scripts/check_domains.php\n";
} else {
    echo "[FAIL] No se encontró scripts/check_domains.php\n";
}

$analysisScript = __DIR__ . '/../scripts/analyze_all.php';
if (file_exists($analysisScript)) {
    echo "[PASS] Script de Análisis encontrado: $analysisScript\n";
} else {
    echo "[FAIL] No se encontró scripts/analyze_all.php\n";
}

echo "\n=== PRUEBAS FINALIZADAS ===\n";
