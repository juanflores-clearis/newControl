<?php

namespace App\Controllers;

use App\Models\Website;
use App\Core\View;
use App\Core\Auth;
use App\Services\AnalyzerService;
use App\Core\DB;
use PDO;

require_once __DIR__ . '/../Models/Website.php';
require_once __DIR__ . '/../Core/View.php';
require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Services/AnalyzerService.php';

class WebsiteController {
	public function index() {
        Auth::requireLogin();

		$websites = Website::getWithLatestAnalysis(Auth::userId(), Auth::role() === 'admin');
        View::render('websites/index', [
            'websites' => $websites,
            'is_admin' => Auth::role() === 'admin'
        ]);
    }

	public function checkAjax()
	{
		Auth::requireLogin();

		$input = json_decode(file_get_contents('php://input'), true);
		$websiteId = intval($input['website_id'] ?? 0);
		$userId = Auth::userId();

		$website = Website::findByIdAndUser($websiteId, $userId);

		if (!$website) {
			echo json_encode(['success' => false, 'message' => 'Sitio no encontrado']);
			return;
		}

		$ok = AnalyzerService::analyzeWebsite($websiteId, $website['url']);

		echo json_encode([
			'success' => $ok,
			'message' => $ok ? 'Análisis completado' : 'Error al analizar',
			'url' => $website['url'],
			'data' => $ok ? Website::getLatestAnalysis($website['id']) : null
		]);
	}
	
	public function deleteAjax()
	{
		Auth::requireLogin();
		$input = json_decode(file_get_contents('php://input'), true);
		$websiteId = intval($input['website_id'] ?? 0);

		$userId = Auth::userId();
		$pdo = DB::pdo();

		$stmt = $pdo->prepare("DELETE FROM websites WHERE id = ? AND user_id = ?");
		$success = $stmt->execute([$websiteId, $userId]);

		echo json_encode([
			'success' => $success,
			'message' => $success ? 'Sitio eliminado correctamente' : 'No se pudo eliminar'
		]);
	}
	
	public function insertAjax()
	{
		Auth::requireLogin();
		$pdo = DB::pdo();
		$input = json_decode(file_get_contents('php://input'), true);
		$url = trim($input['url'] ?? '');
		$userId = Auth::userId();

		if (!preg_match('/^(www\.)?[a-z0-9\-]+\.[a-z]{2,}$/i', $url)) {
			echo json_encode(['success' => false, 'message' => 'Formato de URL inválido']);
			return;
		}

		$stmt = $pdo->prepare("SELECT id FROM websites WHERE user_id = ? AND url = ?");
		$stmt->execute([$userId, $url]);
		if ($stmt->fetchColumn()) {
			echo json_encode(['success' => false, 'message' => 'La URL ya existe']);
			return;
		}

		$stmt = $pdo->prepare("INSERT INTO websites (user_id, url) VALUES (?, ?)");
		$stmt->execute([$userId, $url]);
		$websiteId = $pdo->lastInsertId();

		// Ejecutar análisis
		require_once __DIR__ . '/../Services/AnalyzerService.php';
		$success = AnalyzerService::analyzeWebsite((int)$websiteId, $url);

		if (!$success) {
			echo json_encode([
				'success' => true,
				'message' => 'URL insertada, pero falló el análisis automático'
			]);
			return;
		}

		// Obtener últimos datos para la tabla
		$row = Website::getLatestAnalysis($websiteId);

		echo json_encode([
			'success' => true,
			'message' => 'URL insertada y analizada correctamente',
			'data' => $row
		]);
	}

	public function dnsHistoryAjax()
	{
		Auth::requireLogin();
		$websiteId = intval($_GET['website_id'] ?? 0);
		$userId = Auth::userId();
		$website = Website::findByIdAndUser($websiteId, $userId);
		if (!$website) {
			echo json_encode(['success' => false, 'message' => 'Sitio no encontrado']);
			return;
		}
		$pdo = DB::pdo();
		$stmt = $pdo->prepare("SELECT ip, asn, hostname, created_at
			FROM (
				SELECT
					ip, asn, hostname, created_at,
					LEAD(ip) OVER (ORDER BY created_at DESC) AS next_ip,
					LEAD(asn) OVER (ORDER BY created_at DESC) AS next_asn,
					LEAD(hostname) OVER (ORDER BY created_at DESC) AS next_hostname
				FROM analysis_logs
				WHERE website_id = ? AND ip IS NOT NULL
				ORDER BY created_at DESC
			) t
			WHERE ip != next_ip OR asn != next_asn OR hostname != next_hostname OR next_ip IS NULL
			ORDER BY created_at DESC");
		$stmt->execute([$websiteId]);
		$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
		echo json_encode(['success' => true, 'history' => $history]);
	}
}
