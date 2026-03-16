// tests_e2e/access_control.spec.js
// Tests del módulo Control de Acceso (/access)
const { test, expect } = require('@playwright/test');
const { login, goTo } = require('./helpers');

const TEST_URL = `https://playwright-access-${Date.now()}.com`;

test.describe('Control de Acceso (/access)', () => {

    test.beforeEach(async ({ page }) => {
        await login(page);
        await goTo(page, '/access');
    });

    // ------------------------------------------------------------------
    // Carga básica
    // ------------------------------------------------------------------

    test('La página carga sin errores PHP', async ({ page }) => {
        await expect(page).toHaveURL(/route=\/access/);
        const bodyText = await page.locator('body').innerText();
        expect(bodyText).not.toContain('Fatal error');
        expect(bodyText).not.toContain('Warning:');
        expect(bodyText).not.toContain('Parse error');
    });

    test('Muestra la tabla de credenciales o el estado vacío', async ({ page }) => {
        const hasTable = await page.locator('table').isVisible();
        const hasEmpty = await page.locator('.text-muted').isVisible();
        expect(hasTable || hasEmpty).toBeTruthy();
    });

    test('El botón de añadir nueva credencial está visible', async ({ page }) => {
        // Buscar cualquier botón para abrir el modal de inserción
        const addBtn = page.locator('#btnAddAccess, [data-bs-target="#accessModal"], button:has-text("Nueva"), button:has-text("Añadir")').first();
        await expect(addBtn).toBeVisible();
    });

    // ------------------------------------------------------------------
    // Filtros de búsqueda
    // ------------------------------------------------------------------

    test('El campo de búsqueda por URL filtra los resultados', async ({ page }) => {
        const filterInput = page.locator('input[id*="filter"], input[placeholder*="URL"], input[placeholder*="url"]').first();
        const exists = await filterInput.isVisible();
        if (!exists) {
            test.skip();
            return;
        }

        await filterInput.fill('xxxxxxxxxxx_unlikely_match');
        // Las filas visibles deben reducirse
        const visibleRows = await page.locator('table tbody tr:visible').count();
        expect(visibleRows).toBe(0);
    });

    // ------------------------------------------------------------------
    // Añadir credencial
    // ------------------------------------------------------------------

    test('Abrir modal de nueva credencial muestra el formulario', async ({ page }) => {
        const addBtn = page.locator('#btnAddAccess, [data-bs-target="#accessModal"], button:has-text("Nueva"), button:has-text("Añadir")').first();
        await addBtn.click();

        const modal = page.locator('#accessModal, .modal.show').first();
        await expect(modal).toBeVisible({ timeout: 3000 });

        // El formulario debe tener un campo de URL
        await expect(page.locator('#url, input[name="url"]').first()).toBeVisible();
    });

    test('Insertar una credencial la añade a la tabla', async ({ page }) => {
        const addBtn = page.locator('#btnAddAccess, [data-bs-target="#accessModal"], button:has-text("Nueva"), button:has-text("Añadir")').first();
        await addBtn.click();

        const modal = page.locator('#accessModal, .modal.show').first();
        await expect(modal).toBeVisible({ timeout: 3000 });

        // Rellenar campos mínimos
        await page.locator('#url, input[name="url"]').first().fill(TEST_URL);

        const techInput = page.locator('#technology, input[name="technology"]').first();
        if (await techInput.isVisible()) {
            await techInput.fill('Playwright Tech');
        }

        const responsibleInput = page.locator('#responsible, input[name="responsible"]').first();
        if (await responsibleInput.isVisible()) {
            await responsibleInput.fill('Tester Automatizado');
        }

        // Guardar
        await page.locator('#btnSaveAccess, button:has-text("Guardar"), button[type="submit"]').first().click();

        // Esperar a que el modal se cierre o aparezca en la tabla
        await page.waitForTimeout(1000);

        // La URL debe aparecer en la tabla
        await expect(page.locator('table')).toContainText(TEST_URL, { timeout: 5000 });
    });

    // ------------------------------------------------------------------
    // Editar credencial
    // ------------------------------------------------------------------

    test('El botón editar abre el modal con datos precargados', async ({ page }) => {
        const hasRows = await page.locator('table tbody tr').count() > 0;
        if (!hasRows) {
            test.skip();
            return;
        }

        const editBtn = page.locator('button.edit-btn, button[title*="ditar"], button:has([class*="pencil"])').first();
        const editExists = await editBtn.isVisible();
        if (!editExists) {
            test.skip();
            return;
        }

        await editBtn.click();
        const modal = page.locator('#accessModal, .modal.show').first();
        await expect(modal).toBeVisible({ timeout: 3000 });

        // El campo URL debe tener un valor (datos precargados)
        const urlValue = await page.locator('#url, input[name="url"]').first().inputValue();
        expect(urlValue.length).toBeGreaterThan(0);
    });

    // ------------------------------------------------------------------
    // Eliminar credencial
    // ------------------------------------------------------------------

    test('El botón eliminar pide confirmación antes de borrar', async ({ page }) => {
        const hasRows = await page.locator('table tbody tr').count() > 0;
        if (!hasRows) {
            test.skip();
            return;
        }

        const deleteBtn = page.locator('button.delete-btn, button[title*="liminar"], button:has([class*="trash"])').first();
        const deleteExists = await deleteBtn.isVisible();
        if (!deleteExists) {
            test.skip();
            return;
        }

        await deleteBtn.click();

        // Debe aparecer un modal de confirmación o un dialog del navegador
        const confirmModal = page.locator('.modal.show, #confirmDeleteModal');
        const dialogVisible = await confirmModal.isVisible();
        expect(dialogVisible).toBeTruthy();
    });

    test('Cancelar eliminación no borra el registro', async ({ page }) => {
        const initialCount = await page.locator('table tbody tr').count();
        if (initialCount === 0) {
            test.skip();
            return;
        }

        const deleteBtn = page.locator('button.delete-btn, button[title*="liminar"], button:has([class*="trash"])').first();
        if (!await deleteBtn.isVisible()) {
            test.skip();
            return;
        }

        await deleteBtn.click();

        // Cancelar en el modal
        const cancelBtn = page.locator('.modal.show button[data-bs-dismiss="modal"], .modal.show button:has-text("Cancelar")').first();
        if (await cancelBtn.isVisible()) {
            await cancelBtn.click();
        }

        await page.waitForTimeout(500);
        const afterCount = await page.locator('table tbody tr').count();
        expect(afterCount).toBe(initialCount);
    });

    // ------------------------------------------------------------------
    // Limpieza: eliminar el registro de prueba si se insertó
    // ------------------------------------------------------------------

    test('Limpiar: eliminar la credencial de prueba si existe', async ({ page }) => {
        // Buscar la fila con la URL de prueba
        const testRow = page.locator(`table tbody tr:has-text("${TEST_URL}")`);
        const exists = await testRow.isVisible().catch(() => false);
        if (!exists) return; // ya fue eliminada o nunca se creó

        const deleteBtn = testRow.locator('button.delete-btn, button[title*="liminar"], button:has([class*="trash"])').first();
        await deleteBtn.click();

        // Confirmar eliminación
        const confirmBtn = page.locator('.modal.show #confirmDeleteBtn, .modal.show button:has-text("Eliminar"), .modal.show button[class*="danger"]').first();
        if (await confirmBtn.isVisible({ timeout: 2000 })) {
            await confirmBtn.click();
        }

        await page.waitForTimeout(1000);
        await expect(page.locator(`text=${TEST_URL}`)).not.toBeVisible();
    });
});
