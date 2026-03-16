<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/env.php';

// Simular entorno CLI o permitir acceso directo provisionalmente
echo "<pre>";

try {
    $pdo = DB::pdo();
    
    // 1. Check Users
    echo "<h3>Usuarios (users)</h3>";
    $stmt = $pdo->query("SELECT id, username, role FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($users);

    // 2. Check Access Control Records
    echo "<h3>Registros (access_control)</h3>";
    $stmt = $pdo->query("SELECT id, url, responsible FROM access_control");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($records);

    // 3. Check Permissions
    echo "<h3>Permisos (access_permissions)</h3>";
    $stmt = $pdo->query("SELECT * FROM access_permissions");
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($permissions);

    // 4. Test Query for Admin (assuming User ID 1)
    if (!empty($users)) {
        $firstUserId = $users[0]['id'];
        echo "<h3>Prueba de consulta para Usuario ID $firstUserId</h3>";
        
        $sql = "
            SELECT ac.* 
            FROM access_control ac
            LEFT JOIN access_permissions ap ON ac.id = ap.record_id
            WHERE ap.user_id = ?
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$firstUserId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        print_r($results);
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

echo "</pre>";
