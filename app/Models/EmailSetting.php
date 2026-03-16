<?php

namespace App\Models;

use App\Core\DB;
use PDO;

class EmailSetting
{
    public static function get(): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->query('SELECT * FROM email_settings ORDER BY id DESC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row['smtp_password'] = self::decryptPassword($row['smtp_password']);
        }
        return $row ?: null;
    }

    public static function update(array $data): bool
    {
        if (!empty($data['smtp_password'])) {
            $data['smtp_password'] = self::encryptPassword($data['smtp_password']);
        }
        
        $pdo = DB::pdo();
        $sql = 'UPDATE email_settings SET 
            smtp_server = :smtp_server,
            smtp_user = :smtp_user,
            smtp_password = :smtp_password,
            smtp_port = :smtp_port,
            smtp_encryption = :smtp_encryption,
            mail_from_name = :mail_from_name,
            mail_from_email = :mail_from_email,
            mail_subject = :mail_subject,
            recipient_email = :recipient_email,
            updated_at = NOW()
        WHERE id = 1';
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':smtp_server' => $data['smtp_server'],
            ':smtp_user' => $data['smtp_user'],
            ':smtp_password' => $data['smtp_password'],
            ':smtp_port' => $data['smtp_port'],
            ':smtp_encryption' => $data['smtp_encryption'],
            ':mail_from_name' => $data['mail_from_name'],
            ':mail_from_email' => $data['mail_from_email'],
            ':mail_subject' => $data['mail_subject'],
            ':recipient_email' => $data['recipient_email'],
        ]);
    }

    public static function decryptPassword($smtpPasswordEncrypted) {
        $payload = base64_decode($smtpPasswordEncrypted);
        $salt = substr($payload, 8, 8);
        $encoded = substr($payload, 16);
        $pwd = "change it"; // Usa tu clave real aquí
    
        $hash1 = hex2bin(md5($pwd . $salt));
        $hash2 = hex2bin(md5($hash1 . $pwd . $salt));
        $key = $hash1 . $hash2;
        $iv = hex2bin(md5($hash2 . $pwd . $salt));
    
        return openssl_decrypt($encoded, "AES-256-CTR", $key, OPENSSL_RAW_DATA, $iv);
    }

    public static function encryptPassword($smtp_password) {
        $pwd = "change it"; // Usa la misma clave que para desencriptar
        $salted = "Salted__";
        $salt = openssl_random_pseudo_bytes(8);
    
        $hash1 = hex2bin(md5($pwd . $salt));
        $hash2 = hex2bin(md5($hash1 . $pwd . $salt));
        $key = $hash1 . $hash2;
        $iv = hex2bin(md5($hash2 . $pwd . $salt));
    
        $encrypt = openssl_encrypt($smtp_password, "AES-256-CTR", $key, OPENSSL_RAW_DATA, $iv);
    
        return base64_encode($salted . $salt . $encrypt);
    }
} 