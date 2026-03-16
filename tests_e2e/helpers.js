// tests_e2e/helpers.js
// Utilidades compartidas para los tests E2E de NewControl

/**
 * Credenciales de prueba — ajusta según tu entorno local.
 * Para CI puedes usar variables de entorno:
 *   process.env.TEST_USER || 'admin@example.com'
 */
const TEST_USER     = process.env.TEST_USER     || 'admin@example.com';
const TEST_PASSWORD = process.env.TEST_PASSWORD || 'password';

/**
 * Realiza login y espera la redirección al dashboard.
 * @param {import('@playwright/test').Page} page
 */
async function login(page) {
    await page.goto('/?route=/login');
    await page.fill('input[name="username"]', TEST_USER);
    await page.fill('input[name="password"]', TEST_PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForURL(/route=\/dashboard/);
}

/**
 * Navega a una ruta de la aplicación.
 * @param {import('@playwright/test').Page} page
 * @param {string} route  Ej: '/websites'
 */
async function goTo(page, route) {
    await page.goto(`/?route=${route}`);
}

module.exports = { login, goTo, TEST_USER, TEST_PASSWORD };
