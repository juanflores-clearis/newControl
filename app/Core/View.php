<?php

namespace App\Core;

class View
{
    /**
     * Renderiza una vista con control opcional de layout.
     *
     * @param string $view Nombre de la vista (ej: "auth/login")
     * @param array $data Datos que estarán disponibles como variables en la vista
     * @param bool $withLayout Si se debe incluir layout general (cabecera)
     * @param bool $withFooter Si se debe incluir el footer (solo aplica si $withLayout es true)
     */
	public static function render(string $view, array $data = [], bool $withLayout = true, bool $withFooter = true)
	{
		extract($data);

		$viewPath = __DIR__ . '/../Views/' . $view . '.php';

		if (!file_exists($viewPath)) {
			die("La vista <strong>$view</strong> no existe.");
		}

		// Siempre se incluye el header (pero puede esconder el menú)
		require_once __DIR__ . '/../../templates/header.php';

		require_once $viewPath;

		if ($withLayout && $withFooter) {
			require_once __DIR__ . '/../../templates/footer.php';
		}
	}
}
