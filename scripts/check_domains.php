<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

// ===========================
// CONFIG
// ===========================
$apiUrl   = "https://api.virtualname.net/v1/domains/domains";
$apiToken = "Xi93c04g7jd5xKZ6WT3fG3029M3aDmXrKseyQ1E9uoNGiY7d72";

$smtpHost = "smtp.gmail.com";
$smtpUser = "mauticclearis@gmail.com";
$smtpPass = "hgzk qvgx xbhh ddkr";
$smtpPort = 587;

$emailFrom = "mauticclearis@gmail.com";
$emailTo = [
    'juan.flores@clearis.es',
    'administracion@clearis.es'
];

$avisadosFile = __DIR__ . "/avisados.json";

// ===========================
// CARGAR ESTADO
// ===========================
$avisados = file_exists($avisadosFile)
    ? json_decode(file_get_contents($avisadosFile), true)
    : [];

// ===========================
// API CALL (GET + HEADER)
// ===========================
$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "X-TCpanel-Token: $apiToken",
        "Accept: application/json"
    ],
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
curl_close($ch);

$domains = json_decode($response, true);
if (!is_array($domains)) {
    die("Error API Virtualname");
}

// ===========================
// PROCESAR DOMINIOS
// ===========================
$hoy = new DateTime();
$avisar = [];

foreach ($domains as $d) {

    if (empty($d["name"]) || empty($d["product_info"]["product_expiration"])) {
        continue;
    }

    $dominio = $d["name"];
    $expira  = new DateTime($d["product_info"]["product_expiration"]);
    $dias    = (int)$hoy->diff($expira)->format("%r%a");

    if ($dias <= 0) continue;

    if (!isset($avisados[$dominio])) {
        $avisados[$dominio] = [
            "avisado_90" => false,
            "avisado_30" => false,
            "avisado_7"  => false,
            "fecha"      => $expira->format("Y-m-d")
        ];
    }

    // Reset si se renovó
    if ($avisados[$dominio]["fecha"] !== $expira->format("Y-m-d")) {
        $avisados[$dominio] = [
            "avisado_90" => false,
            "avisado_30" => false,
            "avisado_7"  => false,
            "fecha"      => $expira->format("Y-m-d")
        ];
    }

    if ($dias <= 90 && !$avisados[$dominio]["avisado_90"]) {
        $avisar[] = "🟡 $dominio → $dias días";
        $avisados[$dominio]["avisado_90"] = true;
    }

    if ($dias <= 30 && !$avisados[$dominio]["avisado_30"]) {
        $avisar[] = "🟠 $dominio → $dias días";
        $avisados[$dominio]["avisado_30"] = true;
    }

    if ($dias <= 7 && !$avisados[$dominio]["avisado_7"]) {
        $avisar[] = "🔴 URGENTE: $dominio → $dias días";
        $avisados[$dominio]["avisado_7"] = true;
    }
}

// ===========================
// EMAIL
// ===========================
if (!empty($avisar)) {
    $mail = new PHPMailer(true);

    try {
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->isSMTP();

        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtpPort;

        $mail->setFrom($emailFrom, 'Alertas de Dominios');

		foreach ($emailTo as $email) {
			$mail->addAddress($email);
		}

        $mail->Subject = '⚠️ Dominios próximos a expirar';

        // Construir tabla HTML
        $rows = '';
        foreach ($avisar as $linea) {
            $rows .= "<tr><td style='padding:8px;border-bottom:1px solid #ddd;'>"
                   . htmlspecialchars($linea, ENT_QUOTES, 'UTF-8')
                   . "</td></tr>";
        }

        $mail->isHTML(true);
        $mail->Body = "
        <html>
        <body style='font-family:Arial,Helvetica,sans-serif;background:#f6f6f6;padding:20px;'>
            <div style='max-width:600px;margin:auto;background:#ffffff;border-radius:6px;overflow:hidden;'>
                <div style='background:#d9534f;color:#ffffff;padding:15px;font-size:18px;'>
                    ⚠️ Dominios próximos a expirar
                </div>
                <div style='padding:20px;color:#333333;'>
                    <p>Los siguientes dominios requieren atención:</p>
                    <table width='100%' cellpadding='0' cellspacing='0'>
                        $rows
                    </table>
                    <p style='margin-top:20px;'>
                        Accede al panel para renovarlos:<br>
                        <a href='https://panel.virtualname.net'>https://panel.virtualname.net</a>
                    </p>
                </div>
                <div style='background:#f0f0f0;padding:10px;font-size:12px;color:#666;text-align:center;'>
                    Este aviso se genera automáticamente.
                </div>
            </div>
        </body>
        </html>";

        // Versión texto plano (por compatibilidad)
        $mail->AltBody = "Dominios próximos a expirar:\n\n" . implode("\n", $avisar);

        $mail->send();
    } catch (Exception $e) {
        error_log('Error enviando email: ' . $mail->ErrorInfo);
    }
}
// ===========================
// GUARDAR ESTADO
// ===========================
file_put_contents(
    $avisadosFile,
    json_encode($avisados, JSON_PRETTY_PRINT)
);
