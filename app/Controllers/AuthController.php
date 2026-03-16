<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Auth;
use App\Models\User;

require_once __DIR__ . '/../Core/View.php';
require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Models/User.php';

class AuthController
{
    private function getBasePath() {
        $basePath = dirname($_SERVER['PHP_SELF']);
        return $basePath === '/' ? '' : $basePath;
    }

    // Mostrar el formulario de login
    public function showLoginForm()
    {
        // Si ya está logueado, redirige al dashboard
        if (Auth::check()) {
            header('Location: ' . $this->getBasePath() . '?route=/dashboard');
            exit;
        }

        View::render('auth/login', [], false);
    }

    // Procesar login
    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo "Método no permitido";
            return;
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $user = User::findByEmail($username);

        if ($user && password_verify($password, $user['password'])) {
            // Guardar datos de sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            header('Location: ' . $this->getBasePath() . '?route=/dashboard');
            exit;
        } else {
            $error = "Credenciales inválidas";
            View::render('auth/login', ['error' => $error], false);
        }
    }

    // Cerrar sesión
    public function logout()
    {
        session_destroy();
        header('Location: ' . $this->getBasePath() . '?route=/login');
        exit;
    }
}
