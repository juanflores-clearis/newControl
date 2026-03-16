// tests_e2e/dashboard.spec.js
// Tests del dashboard principal
const { test, expect } = require('@playwright/test');
const { login, goTo } = require('./helpers');

test.describe('Dashboard', () => {

    test.beforeEach(async ({ page }) => {
        await login(page);
        await goTo(page, '/dashboard');
    });

    test('Carga el dashboard correctamente', async ({ page }) => {
        await expect(page).toHaveURL(/route=\/dashboard/);
        // Debe mostrar el título o algún encabezado
        await expect(page.locator('h1, h2, h3').first()).toBeVisible();
    });

    test('El navbar muestra los enlaces de navegación', async ({ page }) => {
        await expect(page.locator('a[href*="route=/websites"]')).toBeVisible();
        await expect(page.locator('a[href*="route=/herramientas"]')).toBeVisible();
        await expect(page.locator('a[href*="route=/access"]')).toBeVisible();
    });

    test('El enlace de logout está presente en el navbar', async ({ page }) => {
        await expect(page.locator('a[href*="route=/logout"]')).toBeVisible();
    });

    test('No hay errores PHP visibles en la página', async ({ page }) => {
        const bodyText = await page.locator('body').innerText();
        // PHP fatal errors / warnings suelen empezar con estas cadenas
        expect(bodyText).not.toContain('Fatal error');
        expect(bodyText).not.toContain('Warning:');
        expect(bodyText).not.toContain('Parse error');
        expect(bodyText).not.toContain('Uncaught');
    });

    test('El dashboard muestra estadísticas o tarjetas resumen', async ({ page }) => {
        // Debe haber al menos un .card en el dashboard
        const cards = page.locator('.card');
        await expect(cards.first()).toBeVisible();
    });
});
