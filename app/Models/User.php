<?php

namespace App\Models;

use App\Core\DB;
use PDO;

require_once __DIR__ . '/../../config/db.php';

class User {
    public static function findByEmail($email) {
        $stmt = DB::pdo()->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
