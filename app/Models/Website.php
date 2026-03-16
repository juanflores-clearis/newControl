<?php

namespace App\Models;

use App\Core\DB;
use PDO;

require_once __DIR__ . '/../../config/db.php';

class Website {
    public static function getAllByUser($userId) {
        $stmt = DB::pdo()->prepare("SELECT * FROM websites WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findByIdAndUser($id, $userId) {
        $stmt = DB::pdo()->prepare("SELECT * FROM websites WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function insert($userId, $url) {
        $stmt = DB::pdo()->prepare("INSERT INTO websites (user_id, url) VALUES (?, ?)");
        $stmt->execute([$userId, $url]);
        return DB::pdo()->lastInsertId();
    }

    public static function existsForUser($userId, $url) {
        $stmt = DB::pdo()->prepare("SELECT COUNT(*) FROM websites WHERE user_id = ? AND url = ?");
        $stmt->execute([$userId, $url]);
        return $stmt->fetchColumn() > 0;
    }

    public static function delete($id, $userId) {
        $stmt = DB::pdo()->prepare("DELETE FROM websites WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $userId]);
    }
	
	public static function getWithLatestAnalysis($userId, $isAdmin = false) {
		if ($isAdmin) {
			$stmt = DB::pdo()->prepare('
				SELECT w.*, a.is_online, a.has_malware_virustotal, a.has_malware_sucuri, 
					   a.screenshot_url, a.have_ssl, a.bad_dns, a.error_php, a.created_at, a.ip, a.asn, a.hostname,
					   (
						   SELECT MAX(created_at)
						   FROM analysis_logs
						   WHERE website_id = w.id AND is_online = 1
					   ) AS last_online_ok,
					   (
						   SELECT MAX(created_at)
						   FROM analysis_logs
						   WHERE website_id = w.id AND has_malware_virustotal = 0 AND has_malware_sucuri = 0
					   ) AS last_clean
				FROM websites w
				LEFT JOIN (
					SELECT website_id, is_online, has_malware_virustotal, has_malware_sucuri, 
						   screenshot_url, have_ssl, bad_dns, error_php, created_at, ip, asn, hostname
					FROM analysis_logs
					WHERE created_at = (
						SELECT MAX(created_at)
						FROM analysis_logs AS al
						WHERE al.website_id = analysis_logs.website_id
					)
				) a ON w.id = a.website_id
				ORDER BY w.url ASC
			');
			$stmt->execute();
		} else {
			$stmt = DB::pdo()->prepare('
				SELECT w.*, a.is_online, a.has_malware_virustotal, a.has_malware_sucuri, 
					   a.screenshot_url, a.have_ssl, a.bad_dns, a.error_php, a.created_at, a.ip, a.asn, a.hostname,
					   (
						   SELECT MAX(created_at)
						   FROM analysis_logs
						   WHERE website_id = w.id AND is_online = 1
					   ) AS last_online_ok,
					   (
						   SELECT MAX(created_at)
						   FROM analysis_logs
						   WHERE website_id = w.id AND has_malware_virustotal = 0 AND has_malware_sucuri = 0
					   ) AS last_clean
				FROM websites w
				LEFT JOIN (
					SELECT al1.*
					FROM analysis_logs al1
					INNER JOIN (
						SELECT website_id, MAX(created_at) AS latest_created_at
						FROM analysis_logs
						GROUP BY website_id
					) al2 ON al1.website_id = al2.website_id AND al1.created_at = al2.latest_created_at
				) a ON w.id = a.website_id
				LEFT JOIN access_control ac ON ac.url = w.url 
				LEFT JOIN access_permissions ap ON ap.record_id = ac.id 
				WHERE ap.user_id = ?
				ORDER BY w.url ASC
			');
			$stmt->execute([$userId]);
		}

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public static function getDashboardStats($userId, $isAdmin = false) {
		$pdo = DB::pdo();

		$params = [];
		$where = '';

		if (!$isAdmin) {
			$where = 'WHERE ap.user_id = ?';
			$params[] = $userId;
		}

		$query = "
			SELECT 
				COUNT(DISTINCT w.id) AS total,
				SUM(CASE WHEN al.is_online = 0 THEN 1 ELSE 0 END) AS caidos,
				SUM(CASE WHEN al.has_malware_virustotal = 1 OR al.has_malware_sucuri = 1 THEN 1 ELSE 0 END) AS malware,
				SUM(CASE WHEN al.error_php = 1 THEN 1 ELSE 0 END) AS errores_php,
				SUM(CASE WHEN al.bad_dns = 1 THEN 1 ELSE 0 END) AS bad_dns
			FROM websites w
			LEFT JOIN (
				SELECT al1.*
				FROM analysis_logs al1
				INNER JOIN (
					SELECT website_id, MAX(created_at) as latest
					FROM analysis_logs
					GROUP BY website_id
				) al2 ON al1.website_id = al2.website_id AND al1.created_at = al2.latest
			) al ON al.website_id = w.id
			" . (!$isAdmin ? "
			LEFT JOIN access_control ac ON ac.url = w.url
			LEFT JOIN access_permissions ap ON ap.record_id = ac.id
			" : "") . "
			$where
		";

		$stmt = $pdo->prepare($query);
		$stmt->execute($params);
		return $stmt->fetch(PDO::FETCH_ASSOC);
	}
	
	public static function getLatestAnalysis($websiteId) {
		$pdo = DB::pdo();

		$stmt = $pdo->prepare("
			SELECT w.id, w.url, a.is_online, a.has_malware_virustotal,
				   a.has_malware_sucuri, a.have_ssl, a.bad_dns,
				   a.error_php, a.created_at,
				   a.ip, a.asn, a.hostname,
				   (
				   		SELECT MAX(created_at)
				   		FROM analysis_logs
				   		WHERE website_id = w.id AND is_online = 1
				   ) AS last_online_ok,
				   (
				   		SELECT MAX(created_at)
				   		FROM analysis_logs
				   		WHERE website_id = w.id AND has_malware_virustotal = 0 AND has_malware_sucuri = 0
				   ) AS last_clean
			FROM websites w
			LEFT JOIN analysis_logs a ON a.website_id = w.id
			WHERE w.id = ?
			ORDER BY a.created_at DESC
			LIMIT 1
		");
		$stmt->execute([$websiteId]);
		return $stmt->fetch(PDO::FETCH_ASSOC);
	}
}
