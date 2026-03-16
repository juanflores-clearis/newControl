<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/Services/AnalyzerService.php';
require_once __DIR__ . '/../app/Services/EmailNotifier.php';
require_once __DIR__ . '/../app/Helpers/Logger.php';

use App\Core\DB;
use App\Services\AnalyzerService;

logToCustomFile("== Iniciando análisis masivo ==");

$pdo = DB::pdo();
$stmt = $pdo->query("SELECT id, url FROM websites ORDER BY url ASC");
$websites = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($websites as $site) {
    $id = (int)$site['id'];
    $url = $site['url'];

    try {
        $success = AnalyzerService::analyzeWebsite($id, $url);
        logToCustomFile("Analizando $url → " . ($success ? 'OK' : 'FALLÓ'));
    } catch (Exception $e) {
        logToCustomFile("ERROR en $url: " . $e->getMessage());
    }

    sleep(1); // evitar abuso de servicios externos
}

// Enviar informe por email
if (EmailNotifier::sendDailyReport()) {
    logToCustomFile("Informe enviado por email correctamente");
} else {
    logToCustomFile("Error al enviar el informe por email");
}

logToCustomFile("== Fin del análisis ==");
