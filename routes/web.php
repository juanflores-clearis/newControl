<?php

// Ruta raíz - redirige a login si no hay sesión
$router->get('/', 'AuthController@showLoginForm');

// Sitio principal del usuario
$router->get('/dashboard', 'DashboardController@index');

// Gestión de sitios web
$router->get('/websites', 'WebsiteController@index');
$router->post('/websites/check-ajax', 'WebsiteController@checkAjax');
$router->post('/websites/delete-ajax', 'WebsiteController@deleteAjax');
$router->post('/websites/insert-ajax', 'WebsiteController@insertAjax');
//$router->post('/websites/import', 'WebsiteController@import');
//$router->post('/websites/export', 'WebsiteController@export');
$router->get('/websites/dns-history', 'WebsiteController@dnsHistoryAjax');

// Control de acceso (para roles)
$router->get('/access', 'AccessController@index');
$router->post('/access/add', 'AccessController@add');
$router->post('/access/delete', 'AccessController@delete');

// Configuración de email
$router->get('/config_email', 'SmtpController@configForm');
$router->post('/config_email', 'SmtpController@configForm');

// Autenticación
$router->get('/login', 'AuthController@showLoginForm');
$router->post('/login', 'AuthController@login');
$router->get('/logout', 'AuthController@logout');

// Whatsapp Automation
$router->get('/whatsapp', 'WhatsappController@index');
$router->get('/api/whatsapp_events', 'WhatsappController@getEvents');
$router->post('/api/send_whatsapp_event', 'WhatsappController@whatsappEvent');

// Web Test Runner
$router->get('/tests', 'TestController@index');
$router->post('/tests/run', 'TestController@run');

// Herramientas
$router->get('/herramientas', 'HerramientasController@index');
$router->get('/herramientas/detector-tecnologia', 'HerramientasController@detectorTecnologia');
$router->get('/herramientas/test-web', 'HerramientasController@testWeb');
$router->post('/herramientas/detectar-ajax', 'HerramientasController@detectarAjax');
$router->post('/herramientas/detectar-csv', 'HerramientasController@detectarCsv');
$router->post('/herramientas/exportar-resultados', 'HerramientasController@exportarResultados');
$router->post('/herramientas/test-web/auditar', 'HerramientasController@auditWebServiceAjax');
