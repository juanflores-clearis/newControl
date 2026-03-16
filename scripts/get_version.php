<?php

require_once __DIR__ . '/../config/db.php';

$pdo = DB::pdo();

/**
 * CONFIG LOG
 */
$logFile = __DIR__ . '/../logs/Versiones_' . date('Y-m-d') . '.log';

/**
 * LOGGER LOCAL
 */
function logMsg(string $message): void
{
    global $logFile;

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;

    echo $line;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

function extractAdminUrl(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') return null;

    // Normaliza saltos de línea
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);

    // Primera línea no vacía = URL
    foreach (explode("\n", $raw) as $line) {
        $line = trim($line);
        if ($line !== '') {
            // Por si alguien pegó "URL: https://...."
            $line = preg_replace('/^url\s*:\s*/i', '', $line);
            return trim($line);
        }
    }

    return null;
}


/**
 * OBTENER URLs WORDPRESS + DRUPAL
 */
$sql = "
    SELECT id, url, technology, access_back_prod
    FROM access_control
    WHERE technology IN ('Wordpress', 'Drupal', 'Prestashop')
";
$stmt = $pdo->query($sql);
$sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getPrestashopVersionFromAdminRaw(string $access_back_prod): ?string
{
    $adminUrl = extractAdminUrl($access_back_prod);
    if (!$adminUrl) return null;

    // Probar https y luego http si no viene esquema
    $schemes = ['https://', 'http://'];
    if (preg_match('#^https?://#', $adminUrl)) {
        $schemes = [''];
    }

    foreach ($schemes as $scheme) {
        $fullUrl = $scheme ? $scheme . $adminUrl : $adminUrl;

        $ch = curl_init($fullUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Prestashop Admin Scanner)',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($html && $httpCode >= 200 && $httpCode < 400) {
            if (preg_match('/login\.js\?v=([0-9\.]+)/i', $html, $m)) {
                return $m[1];
            }
        }
    }

    return null;
}


/**
 * OBTENER HTML PROBANDO HTTPS Y HTTP
 */
function getHtmlWithFallback(string $url): ?string
{
    $schemes = ['https://', 'http://'];

    if (preg_match('#^https?://#', $url)) {
        $schemes = [''];
    }

    foreach ($schemes as $scheme) {
        $fullUrl = $scheme ? $scheme . $url : $url;

        $ch = curl_init($fullUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (CMS Version Scanner)',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($html && $httpCode >= 200 && $httpCode < 400) {
            return $html;
        }
    }

    return null;
}

/**
 * PROCESAR WEBS
 */
foreach ($sites as $site) {
    $id         = $site['id'];
    $url        = trim($site['url']);
    $technology = $site['technology'];

    logMsg("Analizando: {$url} ({$technology})");

    $html = getHtmlWithFallback($url);

    if (!$html) {
        logMsg("❌ No accesible por HTTP/HTTPS");
        continue;
    }

    $version = null;

    // WORDPRESS
    if ($technology === 'Wordpress') {
        if (preg_match(
            '/<meta\s+name=["\']generator["\']\s+content=["\']WordPress\s*([0-9\.]+)["\']/i',
            $html,
            $matches
        )) {
            $version = $matches[1];
        }
    }

    // DRUPAL
    if ($technology === 'Drupal') {
        if (preg_match(
            '/<meta\s+name=["\']generator["\']\s+content=["\']Drupal\s+([0-9]+)\b/i',
            $html,
            $matches
        )) {
            $version = $matches[1];
        }
    }

    if ($version !== null) {
        logMsg("✅ Versión detectada: {$version}");

        $update = $pdo->prepare("
            UPDATE access_control
            SET technology_version = :version
            WHERE id = :id
        ");

        $update->execute([
            ':version' => $version,
            ':id'      => $id
        ]);
    } else {
        logMsg("⚠️ Versión no detectada");
    }
	
	if ($technology === 'Prestashop') {

    $adminUrlOnly = extractAdminUrl((string)$site['access_back_prod']);
    logMsg("Analizando PrestaShop backoffice (raw): " . str_replace(["\r","\n"], ['\\r','\\n'], (string)$site['access_back_prod']));
    logMsg("Backoffice URL extraída: " . ($adminUrlOnly ?: 'NULL'));

    $version = getPrestashopVersionFromAdminRaw((string)$site['access_back_prod']);

    if ($version !== null) {
        logMsg("✅ Prestashop versión detectada: {$version}");

        $update = $pdo->prepare("
            UPDATE access_control
            SET technology_version = :version
            WHERE id = :id
        ");
        $update->execute([':version' => $version, ':id' => $id]);
    } else {
        logMsg("⚠️ No se pudo detectar versión desde backoffice (login.js?v=...)");
    }

    continue;
}
}

logMsg("Proceso finalizado.");
