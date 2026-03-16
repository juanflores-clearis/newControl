<?php

namespace App\Models;

use App\Core\DB;
use PDO;

class AccessControl {
    public static function getAll(int $userId, array $filters = [], bool $isAdmin = false) {
        $pdo = DB::pdo();

        if ($isAdmin) {
            // Admin ve todos los registros sin restricción de permisos
            $sql    = "SELECT ac.* FROM access_control ac WHERE 1=1";
            $params = [];
        } else {
            // Usuario normal: solo ve los registros que tiene asignados
            $sql    = "
                SELECT ac.*
                FROM access_control ac
                INNER JOIN access_permissions ap ON ac.id = ap.record_id
                WHERE ap.user_id = :user_id
            ";
            $params = ['user_id' => $userId];
        }

        if (!empty($filters['url'])) {
            $sql .= " AND ac.url LIKE :url";
            $params['url'] = '%' . $filters['url'] . '%';
        }

        if (!empty($filters['technology'])) {
            $sql .= " AND ac.technology LIKE :technology";
            $params['technology'] = '%' . $filters['technology'] . '%';
        }

        if (!empty($filters['responsible'])) {
            $sql .= " AND ac.responsible LIKE :responsible";
            $params['responsible'] = '%' . $filters['responsible'] . '%';
        }

        $sql .= " ORDER BY ac.url ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getById(int $id) {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT * FROM access_control WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function insert(array $data) {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('
            INSERT INTO access_control 
            (url, technology, technology_version, responsible, access_hosting_old, access_hosting_dev, access_hosting_prod, access_back_old, access_back_dev, access_back_prod, backup_info, ftp_access, ftp_access_development, comentario, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ');
        $stmt->execute([
            $data['url'], $data['technology'], $data['version'] ,$data['responsible'], $data['hostingOld'], 
            $data['hostingDev'], $data['hostingProd'], $data['backOld'], 
            $data['backDev'], $data['backProd'], $data['backup'], $data['ftp'], $data['ftpDev'], $data['comentario']
        ]);

        return $pdo->lastInsertId();
    }

    public static function update(array $data) {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE access_control 
                                SET url = ?, technology = ?, technology_version = ?, responsible = ?, 
                                access_hosting_old = ?, access_hosting_dev = ?, access_hosting_prod = ?, 
                                access_back_old = ?, access_back_dev = ?, access_back_prod = ?, 
                                backup_info = ?, ftp_access = ?, ftp_access_development = ?, comentario = ? 
                                WHERE id = ?');

        $stmt->execute([
            $data['url'], $data['technology'], $data['version'] , $data['responsible'], 
            $data['hostingOld'], $data['hostingDev'], $data['hostingProd'], 
            $data['backOld'], $data['backDev'], $data['backProd'], 
            $data['backup'], $data['ftp'], $data['ftpDev'], $data['comentario'], 
            $data['recordId']
        ]);

        return $data['recordId'];
    }

    public static function delete(int $id) {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('DELETE FROM access_control WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public static function assignPermissions($responsible, $recordId) {
        $pdo = DB::pdo();
        // Buscar usuario relacionado con el responsable
        $stmt = $pdo->prepare('SELECT u.id FROM rel_fullname_username rfu LEFT JOIN users u ON rfu.username = u.username  WHERE rfu.fullname = ?');
        $stmt->execute([$responsible]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Verificar si ya existe el permiso para evitar duplicados
            $checkStmt = $pdo->prepare('SELECT id FROM access_permissions WHERE record_id = ? AND user_id = ?');
            $checkStmt->execute([$recordId, $user['id']]);
            if (!$checkStmt->fetch()) {
                 $stmt = $pdo->prepare('INSERT INTO access_permissions (record_id, user_id, created_at) VALUES (?, ?, NOW())');
                 $stmt->execute([$recordId, $user['id']]);
            }
        }
    }
}
