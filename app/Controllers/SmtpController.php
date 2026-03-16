<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\EmailSetting;

require_once __DIR__ . '/../Models/EmailSetting.php';
require_once __DIR__ . '/../Services/EmailNotifier.php';
require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Core/View.php';

class SmtpController
{
    public function configForm() {
        Auth::requireLogin();

        if (Auth::role() !== 'admin') {
            http_response_code(403);
            echo "Acceso denegado. Solo los administradores pueden configurar el email.";
            return;
        }

        $message = '';
        $messageType = '';
        $config = EmailSetting::get();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['action'])) {
                if ($_POST['action'] === 'update') {
                    $result = $this->updateConfig($_POST);
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'error';
                    $config = EmailSetting::get(); // refrescar config
                } elseif ($_POST['action'] === 'test') {
                    $result = $this->testEmail();
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'error';
                }
            }
        }
        View::render('smtp/config', [
            'message'     => $message,
            'messageType' => $messageType,
            'config'      => $config,
        ]);
    }

    public function updateConfig(array $data): array
    {
        $response = ['success' => false, 'message' => ''];
        
        // Validar datos requeridos
        $required = [
            'smtp_server', 'smtp_port', 'smtp_user', 'smtp_password',
            'smtp_encryption', 'recipient_email', 'mail_from_name', 'mail_from_email', 'mail_subject'
        ];
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            $response['message'] = 'Faltan campos requeridos: ' . implode(', ', $missing);
            return $response;
        }
        // Validar emails
        $emails = array_map('trim', explode(',', $data['recipient_email']));
        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response['message'] = "Email inválido: $email";
                return $response;
            }
        }
        if (!filter_var($data['mail_from_email'], FILTER_VALIDATE_EMAIL)) {
            $response['message'] = "Email del remitente inválido";
            return $response;
        }
        
        // Validar puerto
        if (!is_numeric($data['smtp_port']) || $data['smtp_port'] < 1 || $data['smtp_port'] > 65535) {
            $response['message'] = "Puerto SMTP inválido";
            return $response;
        }
        
        // Validar cifrado
        if (!in_array(strtolower($data['smtp_encryption']), ['tls', 'ssl'])) {
            $response['message'] = "Tipo de cifrado inválido. Use 'tls' o 'ssl'";
            return $response;
        }
        
        try {
            if (EmailSetting::update($data)) {
                $response['success'] = true;
                $response['message'] = 'Configuración actualizada correctamente';
            } else {
                $response['message'] = 'Error al guardar la configuración';
            }
        } catch (Exception $e) {
            $response['message'] = 'Error: ' . $e->getMessage();
        }
        
        return $response;
    }

    public function testEmail(): array
    {
        $response = ['success' => false, 'message' => ''];
        
        try {
            $settings = EmailSetting::get();
            if (!$settings) {
                $response['message'] = 'No hay configuración SMTP en la base de datos.';
                return $response;
            }
            $mail = EmailNotifier::createMailer($settings);
            $subject = str_replace('{date}', date('d/m/Y'), $settings['mail_subject']);
            $mail->Subject = $subject;
            $mail->Body = '<h1>Test de Configuración SMTP</h1><p>Si ves este mensaje, la configuración SMTP está funcionando correctamente.</p>';
            $mail->AltBody = 'Test de Configuración SMTP\n\nSi ves este mensaje, la configuración SMTP está funcionando correctamente.';
            
            if ($mail->send()) {
                $response['success'] = true;
                $response['message'] = 'Email de prueba enviado correctamente';
            } else {
                $response['message'] = 'Error al enviar el email';
            }
        } catch (Exception $e) {
            $response['message'] = 'Error: ' . $e->getMessage();
        }
        
        return $response;
    }
} 