<?php

namespace App\Core;

class Router {
    private $routes = [];
    private $basePath;

    public function __construct() {
        // Obtener la ruta base de la aplicación
        $this->basePath = dirname($_SERVER['PHP_SELF']);
        if ($this->basePath === '/') {
            $this->basePath = '';
        }
    }

    public function get($uri, $action) {
        $this->routes['GET'][$uri] = $action;
    }

    public function post($uri, $action) {
        $this->routes['POST'][$uri] = $action;
    }

	public function dispatch($uri = null) {
		$method = $_SERVER['REQUEST_METHOD'];
		$uri = $uri ?? '/';
		
		// Si la URI está vacía, usar '/'
		if ($uri === '') {
			$uri = '/';
		}

		$action = $this->routes[$method][$uri] ?? null;

		if (!$action) {
			// Si no hay ruta definida y no hay sesión, redirigir a login
			if (!isset($_SESSION['user_id'])) {
				header('Location: ' . $this->basePath . '?route=/login');
				exit;
			}
			
			http_response_code(404);
			echo "<!DOCTYPE html><html><head><title>Error</title></head><body>";
			echo "<h1>404</h1><p>Ruta no encontrada: <code>$uri</code></p>";
			echo "</body></html>";
			return;
		}

		[$controller, $method] = explode('@', $action);

		$fullControllerClass = "App\\Controllers\\" . $controller;
		$file = __DIR__ . '/../Controllers/' . $controller . '.php';

		if (!file_exists($file)) {
			http_response_code(500);
			echo "Controlador no encontrado: $controller";
			return;
		}

		require_once $file;

		if (!class_exists($fullControllerClass)) {
			http_response_code(500);
			echo "Clase $fullControllerClass no definida.";
			return;
		}

		$controllerInstance = new $fullControllerClass();

		if (!method_exists($controllerInstance, $method)) {
			http_response_code(500);
			echo "Método $method no existe en $controller.";
			return;
		}
		
		call_user_func([$controllerInstance, $method]);
	}

    public function getBasePath() {
        return $this->basePath;
    }
}
