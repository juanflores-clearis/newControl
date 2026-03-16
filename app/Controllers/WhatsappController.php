<?php

//require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Core/View.php';
//require_once __DIR__ . '/../Models/Website.php';

class WhatsappController
{
    public function index()
    {
    	View::render('whatsapp/index');
    }
	
	public function whatsappEvent()
	{
		header('Content-Type: application/json');

		$input = json_decode(file_get_contents('php://input'), true);

		if (!$input) {
			http_response_code(400);
			echo json_encode(['error' => 'JSON inválido']);
			exit;
		}

		$event = [
			'time' => date('Y-m-d H:i:s'),
			'chat' => $input['chat'] ?? 'desconocido',
			'message' => $input['message'] ?? '',
		];

		// Guardamos en archivo (simple)
		$file = __DIR__ . '/events.json';
		$events = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
		$events[] = $event;

		// Limitar tamaño
		$events = array_slice($events, -100);

		file_put_contents($file, json_encode($events, JSON_PRETTY_PRINT));

		echo json_encode(['status' => 'ok']);
	}
	
	public function getEvents()
	{
		header('Content-Type: application/json');

		$file = __DIR__ . '/events.json';

		if (!file_exists($file)) {
			echo json_encode([]);
			exit;
		}

		echo file_get_contents($file);
	}
}
