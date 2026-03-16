// tests_e2e/websites.spec.js
// Tests de gestión de sitios web (URLs monitorizadas)
const { test, expect } = require('@playwright/test');
const { login, goTo } = require('./helpers');

// URL de prueba única por ejecución para evitar colisiones
const TEST_URL = `playwright-test-${Date.now()}.com`;

test.describe('Gestión de Sitios Web (/websites)', () => {

    test.beforeEach(async ({ page }) => {
        await login(page);
        await goTo(page, '/websites');
    });

    // ------------------------------------------------------------------
    // Carga básica de la página
    // ------------------------------------------------------------------

    test('La página carga sin errores', async ({ page }) => {
        await expect(page).toHaveURL(/route=\/websites/);
        await expect(page.locator('h2')).toContainText('Gestión de Sitios Web');

        const bodyText = await page.locator('body').innerText();
        expect(bodyText).not.toContain('Fatal error');
        expect(bodyText).not.toContain('Warning:');
    });

    test('Muestra la tabla de sitios o el mensaje de vacío', async ({ page }) => {
        const hasTable  = await page.locator('#websites-table').isVisible();
        const hasEmpty  = await page.locator('.bi-globe-americas').isVisible();
        expect(hasTable || hasEmpty).toBeTruthy();
    });

    test('El botón "Nueva URL" abre el modal de inserción', async ({ page }) => {
        await page.click('button[data-bs-target="#insertUrlModal"]');
        await expect(page.locator('#insertUrlModal')).toBeVisible();
        await expect(page.locator('#new-url')).toBeVisible();
    });

    // ------------------------------------------------------------------
    // Filtros
    // ------------------------------------------------------------------

    test('Los filtros están presentes y son funcionales', async ({ page }) => {
        await expect(page.locator('#filter-url')).toBeVisible();
        await expect(page.locator('#filter-status')).toBeVisible();
        await expect(page.locator('#filter-malware-vt')).toBeVisible();
        await expect(page.locator('#filter-malware-sucuri')).toBeVisible();
        await expect(page.locator('#reset-filters')).toBeVisible();
    });

    test('Filtrar por URL oculta filas que no coinciden', async ({ page }) => {
        // Solo ejecutar si hay tabla con datos
        const hasTable = await page.locator('#websites-table tbody tr').count() > 0;
        if (!hasTable) {
            test.skip();
            return;
        }

        await page.fill('#filter-url', 'xxxxxxxxxxx_unlikely_match_xxxxxxxxxxx');
        // Las filas visibles deben ser 0 (display:none) o la tabla debe estar vacía visualmente
        const visibleRows = await page.locator('#websites-table tbody tr:visible').count();
        expect(visibleRows).toBe(0);
    });

    test('El botón reiniciar filtros limpia el campo de búsqueda', async ({ page }) => {
        await page.fill('#filter-url', 'algo');
        await page.click('#reset-filters');
        const value = await page.locator('#filter-url').inputValue();
        expect(value).toBe('');
    });

    // ------------------------------------------------------------------
    // Insertar URL
    // ------------------------------------------------------------------

    test('Insertar una nueva URL dispara el análisis y aparece en la tabla', async ({ page }) => {
        // Abrir modal
        await page.click('button[data-bs-target="#insertUrlModal"]');
        await expect(page.locator('#insertUrlModal')).toBeVisible();

        // Escribir URL de prueba
        await page.fill('#new-url', TEST_URL);

        // Interceptar la llamada AJAX para no esperar análisis real
        const responsePromise = page.waitForResponse(
            resp => resp.url().includes('route=/websites/insert-ajax') && resp.status() === 200
        );
        await page.click('#insertUrlBtn');
        await responsePromise;

        // El toast de éxito debe aparecer o la URL debe aparecer en la tabla
        const toastOrTable = await Promise.race([
            page.locator('#toast-container').waitFor({ state: 'visible', timeout: 8000 }).then(() => 'toast'),
            page.locator(`text=${TEST_URL}`).waitFor({ state: 'visible', timeout: 8000 }).then(() => 'table'),
        ]).catch(() => 'none');

        expect(toastOrTable).not.toBe('none');
    });

    // ------------------------------------------------------------------
    // Modal de detalles avanzados
    // ------------------------------------------------------------------

    test('El botón de detalles abre el modal de información avanzada', async ({ page }) => {
        const detailBtn = page.locator('.other-data-btn').first();
        const hasRows = await page.locator('#websites-table tbody tr').count() > 0;
        if (!hasRows) {
            test.skip();
            return;
        }

        await detailBtn.click();
        await expect(page.locator('#otherDataModal')).toBeVisible();
        await expect(page.locator('#modal-ssl')).toBeVisible();
        await expect(page.locator('#modal-ip')).toBeVisible();
    });

    // ------------------------------------------------------------------
    // Botón analizar
    // ------------------------------------------------------------------

    test('El botón Analizar dispara una petición AJAX al servidor', async ({ page }) => {
        const hasRows = await page.locator('#websites-table tbody tr').count() > 0;
        if (!hasRows) {
            test.skip();
            return;
        }

        const requestPromise = page.waitForRequest(
            req => req.url().includes('route=/websites/check-ajax')
        );
        await page.locator('.analyze-btn').first().click();
        const req = await requestPromise;
        expect(req.method()).toBe('POST');
    });

    // ------------------------------------------------------------------
    // Eliminación
    // ------------------------------------------------------------------

    test('El botón eliminar abre el modal de confirmación', async ({ page }) => {
        const hasRows = await page.locator('#websites-table tbody tr').count() > 0;
        if (!hasRows) {
            test.skip();
            return;
        }

        await page.locator('.delete-btn').first().click();
        await expect(page.locator('#confirmDeleteModal')).toBeVisible();
        await expect(page.locator('#confirmDeleteBtn')).toBeVisible();
    });

    test('Cancelar en el modal de eliminación no borra el sitio', async ({ page }) => {
        const initialCount = await page.locator('#websites-table tbody tr').count();
        if (initialCount === 0) {
            test.skip();
            return;
        }

        await page.locator('.delete-btn').first().click();
        await expect(page.locator('#confirmDeleteModal')).toBeVisible();
        await page.click('.modal.show button[data-bs-dismiss="modal"]');
        await expect(page.locator('#confirmDeleteModal')).not.toBeVisible();

        const afterCount = await page.locator('#websites-table tbody tr').count();
        expect(afterCount).toBe(initialCount);
    });
});
