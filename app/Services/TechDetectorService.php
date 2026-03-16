<?php

namespace App\Services;

class TechDetectorService
{
    private string $url;
    private string $html;
    private array $headers;
    private array $detectedTechs = [];

    public function analyze(string $url): array
    {
        $this->url = $this->normalizeUrl($url);
        $this->html = '';
        $this->headers = [];
        $this->detectedTechs = [];

        $fetchResult = $this->fetchUrl($this->url);

        if (!$fetchResult['success']) {
            return [
                'success' => false,
                'error' => $fetchResult['error'],
                'url' => $this->url
            ];
        }

        $this->html = $fetchResult['html'];
        $this->headers = $fetchResult['headers'];

        $this->detectSylius();
        $this->detectPrestaShop();
        $this->detectWordPress();
        $this->detectWooCommerce();
        $this->detectShopify();
        $this->detectMagento();
        $this->detectJoomla();
        $this->detectDrupal();
        $this->detectSquarespace();
        $this->detectWix();
        $this->detectBigCommerce();
        $this->detectOsCommerce();
        $this->detectOpenCart();

        // Post-procesado: si Sylius es la ÚNICA tecnología detectada y tiene la señal
        // de Rackcdn (evidencia débil por sí sola), subimos la confianza porque la
        // ausencia de otras tecnologías refuerza que realmente es Sylius.
        $this->boostSoliusSingleDetection();

        // Sort by confidence descending
        usort($this->detectedTechs, fn($a, $b) => $b['confidence'] <=> $a['confidence']);

        return [
            'success' => true,
            'url' => $this->url,
            'technologies' => $this->detectedTechs
        ];
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        return rtrim($url, '/');
    }

    private function fetchUrl(string $url): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HEADER => true,
            CURLOPT_ENCODING => '',
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'error' => "No se pudo conectar: $error"];
        }

        if ($httpCode >= 400) {
            return ['success' => false, 'error' => "La URL respondió con código HTTP $httpCode"];
        }

        $rawHeaders = substr($response, 0, $headerSize);
        $html = substr($response, $headerSize);

        $headers = [];
        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($key))] = trim($value);
            }
        }

        return [
            'success' => true,
            'html' => $html,
            'headers' => $headers
        ];
    }

    private function addDetection(string $name, string $icon, int $confidence, array $evidence): void
    {
        // Check if already detected, merge evidence and increase confidence
        foreach ($this->detectedTechs as &$tech) {
            if ($tech['name'] === $name) {
                $tech['evidence'] = array_merge($tech['evidence'], $evidence);
                $tech['confidence'] = min(100, $tech['confidence'] + $confidence);
                return;
            }
        }

        $this->detectedTechs[] = [
            'name' => $name,
            'icon' => $icon,
            'confidence' => min(100, $confidence),
            'evidence' => $evidence
        ];
    }

    /**
     * Si Sylius es la única tecnología detectada y entre sus evidencias está
     * la señal de Rackcdn (que de por sí es débil), reforzamos la confianza
     * porque la ausencia de otras tecnologías reduce ambigüedad.
     */
    private function boostSoliusSingleDetection(): void
    {
        if (count($this->detectedTechs) !== 1) return;
        if ($this->detectedTechs[0]['name'] !== 'Sylius') return;

        $rackcdnEvidence = 'Recursos CSS/JS servidos desde Rackcdn de Sylius';
        $hasRackcdn = in_array($rackcdnEvidence, $this->detectedTechs[0]['evidence'], true);

        if ($hasRackcdn) {
            $this->detectedTechs[0]['confidence'] = min(100, $this->detectedTechs[0]['confidence'] + 25);
            $this->detectedTechs[0]['evidence'][] = 'Sin otras tecnologías detectadas — mayor probabilidad de Sylius';
        }
    }

    // ── Sylius ───────────────────────────────────────────────────────────

    private function detectSylius(): void
    {
        $rackcdn = '1db94ed809223264ca44-6c020ac3a16bbdd10cbf80e156daee8a.ssl.cf3.rackcdn.com';

        // Script JS con SRC de rackcdn translations
        if (stripos($this->html, $rackcdn . '/js/translations') !== false) {
            $this->addDetection('Sylius', 'sylius', 40, ['Script JS de traducciones en Rackcdn']);
        }

        // URL de imágenes con media/cache/sylius
        if (stripos($this->html, 'media/cache/sylius') !== false) {
            $this->addDetection('Sylius', 'sylius', 40, ['URLs de imágenes con media/cache/sylius']);
        }

        // dataLayer[0].route = "sylius_*" → naming convention exclusivo de Sylius, señal muy fiable
        if (preg_match('/["\']route["\']\s*:\s*["\']sylius_/i', $this->html)) {
            $this->addDetection('Sylius', 'sylius', 60, ['dataLayer.route con prefijo sylius_ detectado']);
        } elseif (preg_match('/dataLayer.*?route.*?sylius/is', $this->html)) {
            // Coincidencia genérica, menos fiable
            $this->addDetection('Sylius', 'sylius', 35, ['Variable dataLayer.route contiene Sylius']);
        }

        // data-route attribute contiene "Sylius"
        if (preg_match('/data-route\s*=\s*["\'][^"\']*sylius/i', $this->html)) {
            $this->addDetection('Sylius', 'sylius', 35, ['Atributo data-route contiene Sylius']);
        }

        // Ruta sylius_shop_order_thank_you dentro de un bloque <script> — muy específico
        if (preg_match('/<script\b[^>]*>(?:(?!<\/script>).)*sylius_shop_order_thank_you(?:(?!<\/script>).)*<\/script>/is', $this->html)) {
            $this->addDetection('Sylius', 'sylius', 70, ['Ruta sylius_shop_order_thank_you detectada en bloque <script>']);
        }

        // Etiqueta <body> con clase sylius_shop_homepage — convención de plantillas Sylius
        if (preg_match('/<body\b[^>]+\bclass\s*=\s*["\'][^"\']*sylius_shop_homepage/i', $this->html)) {
            $this->addDetection('Sylius', 'sylius', 75, ['Clase sylius_shop_homepage en la etiqueta <body>']);
        }

        // Input con name="sylius_cart_item[quantity]" — formulario del carrito de Sylius
        if (stripos($this->html, 'sylius_cart_item[quantity]') !== false) {
            $this->addDetection('Sylius', 'sylius', 80, ['Input sylius_cart_item[quantity] detectado (formulario de carrito)']);
        }

        // Ficheros CSS con URLs de rackcdn
        if (stripos($this->html, $rackcdn) !== false) {
            $this->addDetection('Sylius', 'sylius', 30, ['Recursos CSS/JS servidos desde Rackcdn de Sylius']);
        }
    }

    // ── PrestaShop ───────────────────────────────────────────────────────

    private function detectPrestaShop(): void
    {
        // Variable JS "prestashop" — requiere contexto JS claro para evitar falsos positivos
        // con "prestashop.jpg", "prestashop.png", "prestashop.com", etc.
        if (preg_match('/var\s+prestashop\s*=/i', $this->html) ||
            preg_match('/window\.prestashop\s*=/i', $this->html) ||
            preg_match('/\bprestashop\.(cart|customer|page|urls|currency|language|modules|emit|responsive|on|off)\b/i', $this->html)) {
            $this->addDetection('PrestaShop', 'prestashop', 50, ['Variable JS "prestashop" detectada']);
        }

        // PrestaShop generator meta
        if (preg_match('/content\s*=\s*["\']PrestaShop/i', $this->html)) {
            $this->addDetection('PrestaShop', 'prestashop', 50, ['Meta generator PrestaShop']);
        }

        // /modules/ path typical of PrestaShop
        if (preg_match('/\/modules\/ps_/i', $this->html)) {
            $this->addDetection('PrestaShop', 'prestashop', 40, ['Rutas de módulos PrestaShop (/modules/ps_)']);
        }

        // /themes/ typical PrestaShop paths
        if (preg_match('/\/themes\/[^\/]+\/assets\//i', $this->html) &&
            stripos($this->html, 'prestashop') !== false) {
            $this->addDetection('PrestaShop', 'prestashop', 30, ['Estructura de temas de PrestaShop']);
        }
    }

    // ── WordPress ────────────────────────────────────────────────────────

    private function detectWordPress(): void
    {
        if (stripos($this->html, 'wp-content/') !== false) {
            $this->addDetection('WordPress', 'wordpress', 45, ['Rutas wp-content/ detectadas']);
        }

        if (stripos($this->html, 'wp-includes/') !== false) {
            $this->addDetection('WordPress', 'wordpress', 40, ['Rutas wp-includes/ detectadas']);
        }

        if (preg_match('/content\s*=\s*["\']WordPress/i', $this->html)) {
            $this->addDetection('WordPress', 'wordpress', 50, ['Meta generator WordPress']);
        }

        if (stripos($this->html, 'wp-json') !== false) {
            $this->addDetection('WordPress', 'wordpress', 30, ['Referencia a API REST wp-json']);
        }

        if (stripos($this->html, 'wp-emoji') !== false) {
            $this->addDetection('WordPress', 'wordpress', 25, ['Script wp-emoji detectado']);
        }

        if (isset($this->headers['x-powered-by']) &&
            stripos($this->headers['x-powered-by'], 'wordpress') !== false) {
            $this->addDetection('WordPress', 'wordpress', 40, ['Header X-Powered-By: WordPress']);
        }

        if (isset($this->headers['link']) &&
            stripos($this->headers['link'], 'wp-json') !== false) {
            $this->addDetection('WordPress', 'wordpress', 35, ['Header Link con wp-json']);
        }
    }

    // ── WooCommerce ──────────────────────────────────────────────────────

    private function detectWooCommerce(): void
    {
        if (stripos($this->html, 'woocommerce') !== false) {
            $this->addDetection('WooCommerce', 'woocommerce', 45, ['Clase o referencia "woocommerce" detectada']);
        }

        if (preg_match('/wc-[\w-]+\.js/i', $this->html) ||
            stripos($this->html, 'wc-ajax') !== false) {
            $this->addDetection('WooCommerce', 'woocommerce', 40, ['Scripts wc- de WooCommerce']);
        }

        if (stripos($this->html, 'wc-block-') !== false) {
            $this->addDetection('WooCommerce', 'woocommerce', 35, ['Bloques WooCommerce (wc-block-)']);
        }
    }

    // ── Shopify ──────────────────────────────────────────────────────────

    private function detectShopify(): void
    {
        if (stripos($this->html, 'cdn.shopify.com') !== false) {
            $this->addDetection('Shopify', 'shopify', 50, ['CDN de Shopify detectado']);
        }

        if (preg_match('/Shopify\.\w+/i', $this->html)) {
            $this->addDetection('Shopify', 'shopify', 45, ['Objeto JS Shopify detectado']);
        }

        if (stripos($this->html, 'myshopify.com') !== false) {
            $this->addDetection('Shopify', 'shopify', 50, ['Dominio myshopify.com detectado']);
        }

        if (preg_match('/shopify-section/i', $this->html)) {
            $this->addDetection('Shopify', 'shopify', 35, ['Clases shopify-section detectadas']);
        }

        if (isset($this->headers['x-shopid']) || isset($this->headers['x-shopify-stage'])) {
            $this->addDetection('Shopify', 'shopify', 50, ['Headers de Shopify detectados']);
        }

        if (isset($this->headers['powered-by']) &&
            stripos($this->headers['powered-by'], 'shopify') !== false) {
            $this->addDetection('Shopify', 'shopify', 50, ['Header Powered-By: Shopify']);
        }
    }

    // ── Magento ──────────────────────────────────────────────────────────

    private function detectMagento(): void
    {
        if (preg_match('/\/static\/version\d+\//i', $this->html)) {
            $this->addDetection('Magento', 'magento', 45, ['Rutas /static/version detectadas']);
        }

        if (stripos($this->html, 'text/x-magento-init') !== false) {
            $this->addDetection('Magento', 'magento', 50, ['Scripts x-magento-init detectados']);
        }

        // Buscar "Mage." o rutas "mage/" como token independiente (no dentro de "image/")
        // Usamos regex con word boundary o precedido por espacio/comilla/inicio para evitar falsos positivos
        if (preg_match('/["\'\s(,]mage\//i', $this->html) || strpos($this->html, 'Mage.') !== false) {
            $this->addDetection('Magento', 'magento', 40, ['Referencias a Mage/mage detectadas']);
        }

        if (preg_match('/content\s*=\s*["\']Magento/i', $this->html)) {
            $this->addDetection('Magento', 'magento', 50, ['Meta generator Magento']);
        }

        if (isset($this->headers['x-magento-vary']) || isset($this->headers['x-magento-cache-control'])) {
            $this->addDetection('Magento', 'magento', 50, ['Headers de Magento detectados']);
        }
    }

    // ── Joomla ───────────────────────────────────────────────────────────

    private function detectJoomla(): void
    {
        if (preg_match('/content\s*=\s*["\']Joomla/i', $this->html)) {
            $this->addDetection('Joomla', 'joomla', 50, ['Meta generator Joomla']);
        }

        if (stripos($this->html, '/media/jui/') !== false) {
            $this->addDetection('Joomla', 'joomla', 40, ['Rutas /media/jui/ de Joomla']);
        }

        if (preg_match('/\/components\/com_/i', $this->html)) {
            $this->addDetection('Joomla', 'joomla', 35, ['Rutas de componentes Joomla (/components/com_)']);
        }

        if (stripos($this->html, '/media/system/js/') !== false &&
            preg_match('/joomla/i', $this->html)) {
            $this->addDetection('Joomla', 'joomla', 30, ['Scripts del sistema Joomla']);
        }
    }

    // ── Drupal ───────────────────────────────────────────────────────────

    private function detectDrupal(): void
    {
        if (preg_match('/content\s*=\s*["\']Drupal/i', $this->html)) {
            $this->addDetection('Drupal', 'drupal', 50, ['Meta generator Drupal']);
        }

        if (stripos($this->html, 'drupalSettings') !== false ||
            stripos($this->html, 'Drupal.') !== false) {
            $this->addDetection('Drupal', 'drupal', 45, ['Objetos JS Drupal detectados']);
        }

        if (stripos($this->html, '/sites/default/files/') !== false) {
            $this->addDetection('Drupal', 'drupal', 40, ['Rutas /sites/default/files/ de Drupal']);
        }

        if (isset($this->headers['x-drupal-cache']) || isset($this->headers['x-generator'])) {
            if (isset($this->headers['x-generator']) && stripos($this->headers['x-generator'], 'drupal') !== false) {
                $this->addDetection('Drupal', 'drupal', 50, ['Header X-Generator: Drupal']);
            }
            if (isset($this->headers['x-drupal-cache'])) {
                $this->addDetection('Drupal', 'drupal', 50, ['Header X-Drupal-Cache detectado']);
            }
        }
    }

    // ── Squarespace ─────────────────────────────────────────────────────

    private function detectSquarespace(): void
    {
        if (stripos($this->html, 'squarespace') !== false) {
            $this->addDetection('Squarespace', 'squarespace', 40, ['Referencia a Squarespace en HTML']);
        }

        if (stripos($this->html, 'static1.squarespace.com') !== false ||
            stripos($this->html, 'static.squarespace.com') !== false) {
            $this->addDetection('Squarespace', 'squarespace', 50, ['CDN de Squarespace detectado']);
        }

        if (preg_match('/squarespace-cdn/i', $this->html)) {
            $this->addDetection('Squarespace', 'squarespace', 45, ['CDN específico de Squarespace']);
        }
    }

    // ── Wix ─────────────────────────────────────────────────────────────

    private function detectWix(): void
    {
        if (stripos($this->html, 'wix.com') !== false ||
            stripos($this->html, 'wixstatic.com') !== false) {
            $this->addDetection('Wix', 'wix', 50, ['Dominio wix.com o wixstatic.com detectado']);
        }

        if (preg_match('/X-Wix/i', implode("\n", array_map(
            fn($k, $v) => "$k: $v", array_keys($this->headers), $this->headers
        )))) {
            $this->addDetection('Wix', 'wix', 50, ['Headers X-Wix detectados']);
        }

        if (stripos($this->html, '_wixCIDX') !== false ||
            stripos($this->html, 'wix-code') !== false) {
            $this->addDetection('Wix', 'wix', 40, ['Variables JS de Wix detectadas']);
        }
    }

    // ── BigCommerce ─────────────────────────────────────────────────────

    private function detectBigCommerce(): void
    {
        if (stripos($this->html, 'bigcommerce.com') !== false) {
            $this->addDetection('BigCommerce', 'bigcommerce', 45, ['Referencia a bigcommerce.com']);
        }

        if (preg_match('/content\s*=\s*["\']BigCommerce/i', $this->html)) {
            $this->addDetection('BigCommerce', 'bigcommerce', 50, ['Meta generator BigCommerce']);
        }

        if (isset($this->headers['x-bc-']) ||
            (isset($this->headers['server']) && stripos($this->headers['server'], 'bigcommerce') !== false)) {
            $this->addDetection('BigCommerce', 'bigcommerce', 50, ['Headers de BigCommerce detectados']);
        }
    }

    // ── osCommerce ──────────────────────────────────────────────────────

    private function detectOsCommerce(): void
    {
        if (preg_match('/content\s*=\s*["\']osCommerce/i', $this->html)) {
            $this->addDetection('osCommerce', 'oscommerce', 50, ['Meta generator osCommerce']);
        }

        if (stripos($this->html, 'oscommerce') !== false) {
            $this->addDetection('osCommerce', 'oscommerce', 30, ['Referencia a osCommerce en HTML']);
        }
    }

    // ── OpenCart ─────────────────────────────────────────────────────────

    private function detectOpenCart(): void
    {
        if (preg_match('/content\s*=\s*["\']OpenCart/i', $this->html)) {
            $this->addDetection('OpenCart', 'opencart', 50, ['Meta generator OpenCart']);
        }

        if (stripos($this->html, 'route=common/') !== false ||
            stripos($this->html, 'route=product/') !== false) {
            $this->addDetection('OpenCart', 'opencart', 35, ['Rutas típicas de OpenCart (route=common/, route=product/)']);
        }

        if (stripos($this->html, 'catalog/view/theme') !== false) {
            $this->addDetection('OpenCart', 'opencart', 40, ['Rutas de tema OpenCart (catalog/view/theme)']);
        }
    }
}
