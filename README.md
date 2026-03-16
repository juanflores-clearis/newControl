# NewControl — Plataforma de Monitoreo y Seguridad Web

Aplicación PHP con arquitectura MVC propia para gestionar la seguridad, monitoreo y accesos de múltiples sitios web.

---

## Estructura del Proyecto

```
/app
├── Controllers/          Controladores HTTP
├── Models/               Modelos de datos (PDO)
├── Views/                Vistas PHP
├── Services/             Servicios de lógica de negocio
├── Helpers/              Helpers (Logger)
└── Core/                 Router, Auth, View, DB
/config
└── db.php                Clase DB (PDO singleton)
/public
└── index.php             Punto de entrada único
/routes
└── web.php               Definición de rutas
/templates
├── header.php            Layout cabecera + nav
└── footer.php            Layout pie de página + Bootstrap JS
/scripts
├── analyze_all.php       CLI: analiza todos los sitios y envía email de reporte
├── check_domains.php     CLI: verifica expiración de dominios (API Virtualname)
└── get_version.php       CLI: detecta versiones de CMS en access_control
/tests
└── test_functionality.php  Tests unitarios PHP (CLI)
/tests_e2e
└── *.spec.js             Tests end-to-end con Playwright
/assets
├── css/custom.css        Hoja de estilos personalizada (variables de marca)
└── js/filtrosWebsites.js JS de la vista de Websites
/DB
└── appsclea_control.sql  Schema de la base de datos
```

---

## Tecnologías

- **Backend:** PHP 8+, PDO (MySQL/MariaDB)
- **Frontend:** Bootstrap 5, Bootstrap Icons, Font Awesome 6
- **Email:** PHPMailer ^6.9
- **Config:** vlucas/phpdotenv ^5.6
- **Tests E2E:** Playwright ^1.41
- **APIs externas:** VirusTotal v3, Sucuri SiteCheck, ipinfo.io, Virtualname API

---

## Variables de Entorno (.env)

```
DB_HOST=
DB_NAME=
DB_USER=
DB_PASS=
VIRUSTOTAL_API_KEY=
```

---

## Funcionalidades Implementadas

### ✅ Autenticación
- Login/Logout con sesión PHP
- Roles: `admin`, `analyst`, `viewer`
- Protección de rutas con `Auth::requireLogin()`

### ✅ Dashboard
- Resumen: total sitios, caídos, con malware, con problemas DNS

### ✅ Gestión de Sitios Web (`/websites`)
- Listar, añadir, analizar y eliminar URLs
- Análisis automático al insertar una URL nueva
- Análisis manual por sitio (VirusTotal + Sucuri + ipinfo.io)
- Historial de cambios DNS (IP, ASN, hostname)
- Filtros por URL, estado, malware VT, malware Sucuri
- Modal de detalles avanzados (SSL, DNS, IP, hostname, ASN)

### ✅ Control de Accesos (`/access`)
- Inventario de credenciales de infraestructura (hosting, back, FTP, backup)
- Filtros por URL, tecnología, responsable
- Alta y edición de registros (solo admin)
- Eliminación de registros (solo admin)
- Permisos por usuario vía tabla `access_permissions`

### ✅ Detector de Tecnología (`/herramientas/detector-tecnologia`)
- Identifica CMS/plataforma de cualquier URL
- Soporte: WordPress, WooCommerce, PrestaShop, Shopify, Magento, Joomla, Drupal, Squarespace, Wix, BigCommerce, osCommerce, OpenCart, Sylius
- Puntuación de confianza con evidencias detalladas

### ✅ Configuración SMTP (`/config_email`) — solo admin
- Configurar servidor SMTP desde la interfaz
- Prueba de envío de email
- Contraseña cifrada en base de datos (AES-256)

### ✅ Tests E2E (`/tests`)
- Interfaz para ejecutar tests Playwright desde el navegador
- Tests: autenticación, websites, herramientas, access control, dashboard

### ✅ Scripts de Automatización (CLI / Cron)
- `scripts/analyze_all.php` — Análisis masivo + email de reporte diario
- `scripts/check_domains.php` — Alertas de expiración de dominios (Virtualname API)
- `scripts/get_version.php` — Detección automática de versión de CMS

---

## ❌ Funcionalidades Pendientes / En Desarrollo

### WhatsApp Monitor (`/whatsapp`) — Prioridad baja
- La vista actual usa URLs hardcodeadas (`apps.clearis.es`) y un HTML independiente sin layout
- No tiene autenticación (`Auth::requireLogin()` comentado)
- Los eventos se almacenan en fichero JSON (concurrencia no controlada)
- **Pendiente:** Integrar layout, autenticación y persistencia en BD

### Importar / Exportar Websites
- Rutas previstas (`POST /websites/import`, `POST /websites/export`) están comentadas
- **Pendiente:** Implementar importación CSV/Excel y exportación de la lista de sitios monitorizados

### Botones de Vista Rápida en Access Control
- Los botones "Ver Hosting", "Ver Back", "Backup", "FTP" y "Comentario" están renderizados pero sin handler JS
- **Pendiente:** Modales de visualización de credenciales para cada sección

### Gestión de Permisos desde UI (Access Control)
- El botón "Permisos" existe pero no tiene ninguna acción asociada
- **Pendiente:** Modal para asignar y revocar permisos de usuario por registro

### Detección Real de Errores PHP
- `AnalyzerService::checkForPhpErrors()` devuelve siempre `0` (simulado con comentario)
- **Pendiente:** Implementar análisis real del HTML descargado para detectar errores PHP visibles

### Clave de Cifrado SMTP en Entorno
- La clave AES para cifrar la contraseña SMTP está hardcodeada como `"change it"` en `EmailSetting.php`
- **Pendiente:** Moverla a variable de entorno `SMTP_ENCRYPTION_KEY` en `.env`

### Credenciales Hardcodeadas en check_domains.php
- Token de API Virtualname y credenciales SMTP están en texto plano en el script
- **Pendiente:** Migrar a variables de entorno (`.env`)

### Análisis Admin sobre Sitios de Otros Usuarios
- El botón "Analizar" falla para admins que ven sitios de otros usuarios porque `findByIdAndUser` filtra por `user_id`
- **Pendiente:** Añadir método `Website::findById()` sin restricción de usuario para el rol admin

---

## Instalación

```bash
# 1. Instalar dependencias PHP
composer install

# 2. Instalar dependencias Node (tests E2E)
npm install && npx playwright install

# 3. Configurar entorno
cp .env.example .env
# Editar .env con credenciales de BD y API keys

# 4. Importar esquema de BD
mysql -u user -p database < DB/appsclea_control.sql
```

## Ejecutar Tests

```bash
# Tests unitarios PHP (requiere BD configurada)
php tests/test_functionality.php

# Tests E2E Playwright (requiere servidor web activo)
npx playwright test

# Tests E2E con interfaz visual
npx playwright test --ui
```

## Cron Jobs Sugeridos

```bash
# Análisis diario de todos los sitios (00:00)
0 0 * * * php /ruta/proyecto/scripts/analyze_all.php

# Verificación de dominios (lunes 08:00)
0 8 * * 1 php /ruta/proyecto/scripts/check_domains.php

# Detección de versiones de CMS (domingos 02:00)
0 2 * * 0 php /ruta/proyecto/scripts/get_version.php
```