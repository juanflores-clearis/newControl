<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
use App\Models\EmailSetting;
use App\Core\DB;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Models/EmailSetting.php';

class EmailNotifier
{
    private static function getAnalysisResults(): array
    {
        $pdo = DB::pdo();
        
        // Get the latest analysis for each website
        $query = "
            SELECT 
                w.url,
                al.is_online,
                al.has_malware_virustotal,
				al.virustotal_malicious_count,
                al.has_malware_sucuri,
                al.have_ssl,
                al.bad_dns,
                al.error_php,
                al.created_at
            FROM websites w
            INNER JOIN (
                SELECT website_id, MAX(created_at) as max_date
                FROM analysis_logs
                GROUP BY website_id
            ) latest ON w.id = latest.website_id
            INNER JOIN analysis_logs al 
                ON w.id = al.website_id 
                AND al.created_at = latest.max_date
            WHERE al.is_online = 0 
                OR al.has_malware_virustotal = 1 
                OR al.has_malware_sucuri = 1
            ORDER BY w.url ASC";
            
        $stmt = $pdo->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function createMailer(array $settings): PHPMailer
    {
        $mail = new PHPMailer(true);
        
        try {
            // Activar debug SMTP
            // $mail->SMTPDebug = 2;
            // $mail->Debugoutput = 'html';
            // Configuración del servidor
            $mail->isSMTP();
            $mail->Host = $settings['smtp_server'];
            $mail->SMTPAuth = true;
            $mail->Username = $settings['smtp_user'];
            $mail->Password = $settings['smtp_password'];
            $mail->Port = (int)$settings['smtp_port'];

            // Configuración de cifrado
            if (strtolower($settings['smtp_encryption']) === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            // Configuración del remitente
            $mail->setFrom($settings['smtp_user'], 'Malware & Uptime Report');

            // Configuración de destinatarios
            $recipients = explode(',', $settings['recipient_email']);
            foreach ($recipients as $email) {
                $mail->addAddress(trim($email));
            }

            // Configuración general
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);

            return $mail;
        } catch (Exception $e) {
            logToCustomFile("Error configurando PHPMailer: " . $e->getMessage());
            throw $e;
        }
    }
	
	public static function sendDailyReport(): bool
	{
		try {
			$settings = EmailSetting::get();
			if (!$settings) {
				logToCustomFile("No se encontró configuración SMTP en la base de datos.");
				return false;
			}
			$results = self::getAnalysisResults();
			if (empty($results)) {
				return true; // No issues found, no need to send email
			}

			$offline_sites = [];
			$infected_sites = [];

			foreach ($results as $site) {
				if ($site['is_online'] == 0) {
					$offline_sites[] = $site['url'];
				}
				$detected_by = [];
				if ($site['has_malware_virustotal'] == 1) {
					$extra_info = "";
					if (isset($site['virustotal_malicious_count']) && $site['virustotal_malicious_count'] > 0) {
						$extra_info = " <b>({$site['virustotal_malicious_count']} motores antivirus lo marcaron como malicioso)</b>";
					}
					$detected_by[] = 'VirusTotal' . $extra_info;
				}
				if ($site['has_malware_sucuri'] == 1) {
					$detected_by[] = 'Sucuri';
				}
				if (!empty($detected_by)) {
					$infected_sites[] = [
						'url' => $site['url'],
						'detectors' => implode(', ', $detected_by),
					];
				}
			}

			$mail = self::createMailer($settings);
			$subject = str_replace('{date}', date('d/m/Y'), $settings['mail_subject']);
			$mail->Subject = $subject;

			$message = "<html><body>";
			$message .= "<h2>Informe de Análisis de Sitios Web</h2>";
			$message .= "<p>Fecha: " . date('Y-m-d H:i:s') . "</p>";

			if (!empty($offline_sites)) {
				$message .= "<h3>🔴 Sitios Caídos:</h3><ul>";
				foreach ($offline_sites as $url) {
					$message .= "<li>$url</li>";
				}
				$message .= "</ul>";
			}

			if (!empty($infected_sites)) {
				$message .= "<h3>⚠️ Sitios con Malware Detectado:</h3><ul>";
				foreach ($infected_sites as $site) {
					$message .= "<li>{$site['url']} (Detectado por: {$site['detectors']})</li>";
				}
				$message .= "</ul>";
			}

			$message .= "<p>Este informe ha sido generado automáticamente por el sistema de monitoreo.</p>";
			$message .= "</body></html>";

			$mail->Body = $message;
			//$mail->AltBody = strip_tags(str_replace(['<br>', '</h2>', '</h3>', '</p>'], "\n", $message));

			return $mail->send();

		} catch (Exception $e) {
			logToCustomFile("Error enviando email: " . $e->getMessage());
			return false;
		}
	}
}