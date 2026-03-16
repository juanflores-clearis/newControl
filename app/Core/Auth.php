<?php

namespace App\Core;

class Auth {
    public static function check() {
        return isset($_SESSION['user_id']);
    }

    public static function userId() {
        return $_SESSION['user_id'] ?? null;
    }

    public static function role() {
        return $_SESSION['role'] ?? null;
    }

    public static function requireLogin() {
        if (!self::check()) {
            $basePath = dirname($_SERVER['PHP_SELF']);
            if ($basePath === '/') {
                $basePath = '';
            }
            header('Location: ' . $basePath . '?route=/login');
            exit;
        }
    }
}