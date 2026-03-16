<?php
$dotenv = parse_ini_file(__DIR__ . '/../.env');
if (!$dotenv) {
    die('Error: No se pudo cargar el archivo .env');
}

// Cargar las variables en $_ENV para uso global
foreach ($dotenv as $key => $value) {
    $_ENV[$key] = $value;
}