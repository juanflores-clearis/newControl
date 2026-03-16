<?php

namespace App\Controllers;

use App\Core\DB;
use App\Core\Auth;
use App\Core\View;
use App\Models\AccessControl;

require_once __DIR__ . '/../Models/AccessControl.php';
require_once __DIR__ . '/../Core/View.php';
require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../../config/db.php';

class AccessController {
    public function index() {
        Auth::requireLogin();
        
        $filters = [
            'url' => $_GET['url'] ?? '',
            'technology' => $_GET['technology'] ?? '',
            'responsible' => $_GET['responsible'] ?? ''
        ];

        try {
            $isAdmin = (Auth::role() === 'admin');
            $records = AccessControl::getAll((int)Auth::userId(), $filters, $isAdmin);
        } catch (Throwable $e) {
            die("Error en AccessController: " . $e->getMessage());
        }

        View::render('access/index', [
            'records' => $records,
            'filters' => $filters
        ]);
    }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            return;
        }

        // Verificar si es admin
        if (Auth::role() !== 'admin') {
             echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
             return;
        }

        try {
            $data = $this->validateRecordData($_POST);
            $pdo = DB::pdo();
            $pdo->beginTransaction();

            if (!empty($data['recordId'])) {
                $recordId = AccessControl::update($data);
            } else {
                $recordId = AccessControl::insert($data);
            }
            
            AccessControl::assignPermissions($data['responsible'], $recordId);

            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function delete() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            return;
        }

        if (Auth::role() !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            return;
        }

        $id = $_POST['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID requerido']);
            return;
        }

        if (AccessControl::delete($id)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar']);
        }
    }

    private function validateRecordData($data) {
        $recordId = isset($data['recordId']) ? filter_var($data['recordId'], FILTER_SANITIZE_NUMBER_INT) : null;
        $url = filter_var($data['Dominio'] ?? '', FILTER_SANITIZE_URL);
        $technology = filter_var($data['Tecnologia'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $version = filter_var($data['version'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $responsible = filter_var($data['Responsable'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        
        // Hostings & Backups
        $hostingOld = filter_var($data['Acceso_Hosting_Antiguo'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $hostingDev = filter_var($data['Acceso_Hosting_Desarrollo'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $hostingProd = filter_var($data['Acceso_Hosting_Produccion'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        
        $backOld = filter_var($data['Acceso_Back_Antiguo'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $backDev = filter_var($data['Acceso_Back_Desarrollo'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $backProd = filter_var($data['Acceso_Back_Produccion'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        
        $backup = filter_var($data['Copia_de_seguridad'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $ftp = filter_var($data['FTP'] ?? '', FILTER_SANITIZE_URL);
        $ftpDev = filter_var($data['FTP_DEV'] ?? '', FILTER_SANITIZE_URL);
        $comentario = filter_var($data['Comentario'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if (!$url || !$technology || !$responsible) {
            throw new Exception('Datos incompletos o inválidos');
        }

        return compact('recordId', 'url', 'technology', 'version', 'responsible', 'hostingOld', 'hostingDev', 'hostingProd', 'backOld', 'backDev', 'backProd', 'backup', 'ftp', 'ftpDev', 'comentario');
    }
}
