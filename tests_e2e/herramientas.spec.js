// tests_e2e/herramientas.spec.js
// Tests del módulo de Herramientas y el detector de tecnología
const { test, expect } = require('@playwright/test');
const { login, goTo } = require('./helpers');

test.describe('Herramientas (/herramientas)', () => {

    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    // ------------------------------------------------------------------
    // Página índice de herramientas
    // ------------------------------------------------------------------

    test('La página de herramientas carga correctamente', async ({ page }) => {
        await goTo(page, '/herramientas');
        await expect(page).toHaveURL(/route=\/herramientas/);

        const bodyText = await page.locator('body').innerText();
        expect(bodyText).not.toContain('Fatal error');
        expect(bodyText).not.toContain('Warning:');
    });

    test('Hay un enlace al Detector de Tecnología', async ({ page }) => {
        await goTo(page, '/herramientas');
        await expect(page.locator('a[href*="route=/herramientas/detector-tecnologia"]')).toBeVisible();
    });

    // ------------------------------------------------------------------
    // Detector de Tecnología
    // ------------------------------------------------------------------

    test('La página del detector carga con el formulario visible', async ({ page }) => {
        await goTo(page, '/herramientas/detector-tecnologia');
        await expect(page.locator('#detectForm')).toBeVisible();
        await expect(page.locator('#urlInput')).toBeVisible();
        await expect(page.locator('#btnAnalizar')).toBeVisible();
    });

    test('Sin URL no se puede enviar el formulario (validación HTML5)', async ({ page }) => {
        await goTo(page, '/herramientas/detector-tecnologia');
        // El botón está presente pero el campo tiene `required`
        const inputRequired = await page.locator('#urlInput').getAttribute('required');
        expect(inputRequired).not.toBeNull();
    });

    test('La sección de carga aparece al enviar el formulario', async ({ page }) => {
        await goTo(page, '/herramientas/detector-tecnologia');

        // Interceptar la petición AJAX para que no tenga que completarse
        await page.route('**/index.php?route=/herramientas/detectar-ajax', route => {
            // Devolvemos respuesta simulada para que el test sea rápido y determinista
            route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    url: 'wordpress.org',
                    technologies: [
                        {
                            name: 'WordPress',
                            confidence: 95,
                            evidence: ['wp-content/ en el HTML', 'meta generator WordPress']
                        }
                    ]
                })
            });
        });

        await page.fill('#urlInput', 'wordpress.org');
        await page.click('#btnAnalizar');

        // La sección de resultados debe aparecer
        await expect(page.locator('#resultsSection')).toBeVisible({ timeout: 5000 });
        await expect(page.locator('#resultUrl')).toContainText('wordpress.org');
    });

    test('La URL AJAX incluye /index.php en la petición', async ({ page }) => {
        await goTo(page, '/herramientas/detector-tecnologia');

        let requestUrl = '';
        page.on('request', req => {
            if (req.url().includes('detectar-ajax')) {
                requestUrl = req.url();
            }
        });

        // Interceptamos para que no falle por red
        await page.route('**/detectar-ajax', route => route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ success: true, url: 'example.com', technologies: [] })
        }));

        await page.fill('#urlInput', 'example.com');
        await page.click('#btnAnalizar');
        await page.waitForTimeout(500);

        // La URL debe contener index.php (no solo ?route=...)
        expect(requestUrl).toContain('/index.php');
        expect(requestUrl).toContain('route=/herramientas/detectar-ajax');
    });

    test('Cuando el servidor devuelve error se muestra la sección de error', async ({ page }) => {
        await goTo(page, '/herramientas/detector-tecnologia');

        await page.route('**/detectar-ajax', route => route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ success: false, error: 'URL no accesible' })
        }));

        await page.fill('#urlInput', 'sitio-que-no-existe.com');
        await page.click('#btnAnalizar');

        await expect(page.locator('#errorSection')).toBeVisible({ timeout: 5000 });
        await expect(page.locator('#errorMessage')).toContainText('URL no accesible');
    });

    test('Sin tecnologías detectadas se muestra el mensaje vacío', async ({ page }) => {
        await goTo(page, '/herramientas/detector-tecnologia');

        await page.route('**/detectar-ajax', route => route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ success: true, url: 'example.com', technologies: [] })
        }));

        await page.fill('#urlInput', 'example.com');
        await page.click('#btnAnalizar');

        await expect(page.locator('#noResults')).toBeVisible({ timeout: 5000 });
    });
});
