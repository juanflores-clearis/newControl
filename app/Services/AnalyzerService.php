<?php

namespace App\Services;

use App\Core\DB;
use Exception;

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../Helpers/Logger.php'; // incluye appendApiLog

class AnalyzerService
{
    public static function analyzeWebsite(int $websiteId, string $url): bool
    {
        try {
            $pdo = DB::pdo();

            $is_online = self::checkUrlStatus($url);
			
            [$virustotal_malicious_count, $vtRaw, $vtRawPost] = self::analyzeWithVirusTotal($url);
			$has_malware_virustotal = $virustotal_malicious_count > 0 ? 1 : 0;
            [$data_sucuri, $sucuriRaw] = self::analyzeWithSucuri($url);
            $php_errors = self::checkForPhpErrors([$url]);

            // Obtener IP y datos de hosting
            $host = preg_replace('#^https?://#', '', rtrim($url, '/'));
            $host = explode('/', $host)[0];
            $ip = gethostbyname($host);
            $org = $hostname = null;
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $ipinfo = self::getIpInfo($ip);
                $org = $ipinfo['org'];
                $hostname = $ipinfo['hostname'];
            }

            if (php_sapi_name() === 'cli') {
                appendApiLog('virustotal', $url, $vtRaw);
                appendApiLog('sucuri', $url, $sucuriRaw);
            }
            else {
                logApiRawResponse('virustotal', $url, $vtRaw);
                logApiRawResponse('virustotal_post', $url, $vtRawPost);
                logApiRawResponse('sucuri', $url, $sucuriRaw);
            }

            $stmt = $pdo->prepare("
                INSERT INTO analysis_logs 
                (website_id, is_online, has_malware_virustotal, has_malware_sucuri, have_ssl, bad_dns, error_php, json_sucuri, virustotal_malicious_count, ip, asn, hostname)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $websiteId,
                $is_online,
                $has_malware_virustotal,
                $data_sucuri['malware'],
                $data_sucuri['ssl'],
                $data_sucuri['bad_dns'],
                $php_errors[$url] ?? 0,
                $data_sucuri['json_sucuri'] ?? null,
				$virustotal_malicious_count,
                $ip,
                $org,
                $hostname,
            ]);

            return true;

        } catch (Exception $e) {
            return false;
        }
    }

    private static function checkUrlStatus(string $url): int
    {
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = "https://$url";
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => "Mozilla/5.0 (compatible; MonitoringBot/1.0)"
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_errno($ch);
        $errorMsg = curl_error($ch);
        curl_close($ch);

        // logToCustomFile("Verificando $url → HTTP $httpCode");

        if (in_array($httpCode, [200, 201, 202, 203, 204, 206, 301, 302, 403])) {
            return 1;
        }

        if ($error) {
            //logToCustomFile("cURL error en $url: [$error] $errorMsg");
        }
		
		return 0;
    }

	private static function analyzeWithVirusTotal(string $url): array
	{
		$apiKey = $_ENV['VIRUSTOTAL_API_KEY'];

		// Paso 1: Enviar la URL para análisis
		$postCh = curl_init();
		curl_setopt_array($postCh, [
			CURLOPT_URL => 'https://www.virustotal.com/api/v3/urls',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 40,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => http_build_query(["url" => urlencode($url)]),
			CURLOPT_HTTPHEADER => [
				'accept: application/json',
				'content-type: application/x-www-form-urlencoded',
				'x-apikey: ' . $apiKey
			],
			CURLOPT_USERAGENT => 'Mozilla/5.0'
		]);

		$postResponse = curl_exec($postCh);
		$httpCode = curl_getinfo($postCh, CURLINFO_HTTP_CODE);
		curl_close($postCh);

		if ($httpCode !== 200) {
			return [-1, ['error' => 'HTTP error: ' . $httpCode], []];
		}

		$postData = json_decode($postResponse, true);

		// Controlar error de cuota
		if (isset($postData['error']['code']) && $postData['error']['code'] === 'QuotaExceededError') {
			return [-1, [], $postData];
		}

		if (!isset($postData['data']['links']['self'])) {
			return [0, [], $postData];
		}

		$analysisUrl = $postData['data']['links']['self'];

		// Paso 2: Reintentar mientras el análisis esté en cola
		$maxRetries = 5;
		$baseDelay = 2;
		$maxDelay = 16;
		$attempts = 0;
		$startTime = time();
		$timeout = 70;

		do {
			$delay = min($baseDelay * pow(2, $attempts), $maxDelay);
			sleep($delay);

			$getCh = curl_init();
			curl_setopt_array($getCh, [
				CURLOPT_URL => $analysisUrl,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 40,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'GET',
				CURLOPT_HTTPHEADER => [
					'x-apikey: ' . $apiKey
				]
			]);

			$getResponse = curl_exec($getCh);
			$httpCode = curl_getinfo($getCh, CURLINFO_HTTP_CODE);
			curl_close($getCh);

			if ($httpCode !== 200) {
				return [-1, ['error' => 'HTTP error en polling: ' . $httpCode], $postData];
			}

			$getData = json_decode($getResponse, true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				return [-1, ['error' => 'JSON error: ' . json_last_error_msg()], $postData];
			}

			$status = $getData['data']['attributes']['status'] ?? '';

			$attempts++;

			if (time() - $startTime > $timeout) {
				return [0, ['error' => 'Timeout alcanzado'], $postData];
			}

		} while ($status === 'queued' && $attempts < $maxRetries);

		// Validar resultado
		if (!isset($getData['data']['attributes']['stats']['malicious'])) {
			return [0, $getData, $postData];
		}

		$maliciousCount = $getData['data']['attributes']['stats']['malicious'] ?? 0;

		return [$maliciousCount, $getData, $postData];
	}

    private static function analyzeWithSucuri(string $url): array
    {
        // Remove any existing protocol prefix
        $apiUrl = "https://sitecheck.sucuri.net/api/v3/?scan=$url";

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0'
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return [['malware' => 0, 'ssl' => 2, 'bad_dns' => 0, 'json_sucuri' => null], []];
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [['malware' => 0, 'ssl' => 2, 'bad_dns' => 0, 'json_sucuri' => null], []];
        }

        $malware = isset($data['warnings']['security']) && !empty($data['warnings']['security']) ? 1 : 0;
        $ssl = isset($data['tls']) && !$data['tls']['error'] ? 1 : (isset($data['warnings']['scan_failed'][0]['msg']) && $data['warnings']['scan_failed'][0]['msg'] === "Timeout reached" ? 2 : 0);
        $bad_dns = isset($data['warnings']['scan_failed'][0]['msg']) && $data['warnings']['scan_failed'][0]['msg'] === "Host not found" ? 1 : 0;

        return [[
            'malware' => $malware,
            'ssl' => $ssl,
            'bad_dns' => $bad_dns,
            'json_sucuri' => json_encode($data)
        ], $data];
    }

    private static function checkForPhpErrors(array $urls): array
    {
        // Lógica personalizada de detección de errores PHP por URL
        $results = [];
        foreach ($urls as $url) {
            $results[$url] = 0; // Simulación: sin error
        }
        return $results;
    }

    private static function getIpInfo($ip) {
        $ch = curl_init('https://ipinfo.io/' . $ip . '/json');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MonitoringBot/1.0)'
        ]);
        $json = curl_exec($ch);
        curl_close($ch);
        if ($json) {
            $info = json_decode($json, true);
            return [
                'ip' => $ip,
                'hostname' => $info['hostname'] ?? null,
                'org' => $info['org'] ?? null
            ];
        }
        return ['ip' => $ip, 'hostname' => null, 'org' => null];
    }
}
