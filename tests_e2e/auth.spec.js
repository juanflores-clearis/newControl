// tests_e2e/auth.spec.js
// Tests de autenticación: login, logout, acceso protegido
const { test, expect } = require('@playwright/test');
const { login, goTo, TEST_USER, TEST_PASSWORD } = require('./helpers');

test.describe('Autenticación', () => {

    test('Muestra el formulario de login al acceder a la raíz sin sesión', async ({ page }) => {
        await page.goto('/');
        await expect(page.locator('input[name="username"]')).toBeVisible();
        await expect(page.locator('input[name="password"]')).toBeVisible();
        await expect(page.locator('button[type="submit"]')).toBeVisible();
    });

    test('Redirige a /login al intentar acceder a ruta protegida sin sesión', async ({ page }) => {
        await page.goto('/?route=/dashboard');
        await expect(page).toHaveURL(/route=\/login|\/$/);
        await expect(page.locator('input[name="username"]')).toBeVisible();
    });

    test('Muestra error con credenciales incorrectas', async ({ page }) => {
        await page.goto('/?route=/login');
        await page.fill('input[name="username"]', 'usuario_inexistente@example.com');
        await page.fill('input[name="password"]', 'contraseña_incorrecta');
        await page.click('button[type="submit"]');

        // Debe permanecer en login y mostrar algún mensaje de error
        await expect(page).toHaveURL(/route=\/login|\/$/);
        // Busca cualquier indicador de error (alert, texto de error, etc.)
        const errorVisible = await page.locator('.alert-danger, .text-danger, [class*="error"]').isVisible();
        expect(errorVisible).toBeTruthy();
    });

    test('Login correcto redirige al dashboard', async ({ page }) => {
        await login(page);
        await expect(page).toHaveURL(/route=\/dashboard/);
        // El navbar debe estar visible después del login
        await expect(page.locator('nav')).toBeVisible();
    });

    test('Logout cierra la sesión y redirige al login', async ({ page }) => {
        await login(page);
        await page.click('a[href*="route=/logout"]');
        await expect(page).toHaveURL(/route=\/login|\/$/);
        await expect(page.locator('input[name="username"]')).toBeVisible();
    });

    test('Después de logout no se puede acceder a rutas protegidas', async ({ page }) => {
        await login(page);
        await page.click('a[href*="route=/logout"]');
        await page.goto('/?route=/websites');
        await expect(page).toHaveURL(/route=\/login|\/$/);
    });
});
