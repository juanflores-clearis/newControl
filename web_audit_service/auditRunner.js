const { chromium } = require('@playwright/test');

function normalizeUrl(rawUrl) {
  if (!/^https?:\/\//i.test(rawUrl)) {
    return `https://${rawUrl}`;
  }
  return rawUrl;
}

function sameOrigin(origin, target) {
  try {
    return new URL(target).origin === origin;
  } catch {
    return false;
  }
}

function isSkippableLink(href) {
  return !href
    || href.startsWith('javascript:')
    || href.startsWith('mailto:')
    || href.startsWith('tel:')
    || href.startsWith('#')
    || /logout|salir/i.test(href);
}

function pushIssue(issues, issue) {
  issues.push({
    severity: issue.severity || 'info',
    type: issue.type || 'general',
    title: issue.title || 'Incidencia detectada',
    message: issue.message || '',
    page: issue.page || '',
    details: issue.details || '',
  });
}

async function collectPageData(page, pageUrl, origin, issues) {
  const response = await page.goto(pageUrl, { waitUntil: 'domcontentloaded', timeout: 15000 });
  await page.waitForTimeout(800);

  const status = response ? response.status() : null;
  const finalUrl = page.url();

  if (status && status >= 400) {
    pushIssue(issues, {
      severity: 'critical',
      type: 'broken-page',
      title: 'Pagina con error HTTP',
      message: `La pagina respondio con codigo ${status}.`,
      page: finalUrl,
    });
  }

  const pageData = await page.evaluate(() => {
    const visibleSelector = 'a, button, input[type="button"], input[type="submit"], [role="button"]';
    const controls = Array.from(document.querySelectorAll(visibleSelector))
      .slice(0, 12)
      .map((element, index) => {
        const rect = element.getBoundingClientRect();
        const text = (element.innerText || element.getAttribute('aria-label') || element.getAttribute('value') || '').trim();
        return {
          index,
          text: text || '(sin texto)',
          tag: element.tagName.toLowerCase(),
          width: Math.round(rect.width),
          height: Math.round(rect.height),
          disabled: !!element.disabled,
        };
      });

    const links = Array.from(document.querySelectorAll('a[href]')).map((anchor) => ({
      href: anchor.getAttribute('href') || '',
      absoluteHref: anchor.href || anchor.getAttribute('href') || '',
      text: (anchor.innerText || anchor.getAttribute('aria-label') || '').trim(),
    }));

    const brokenImages = Array.from(document.images)
      .filter((img) => img.complete && img.naturalWidth === 0)
      .map((img) => img.currentSrc || img.src || '');

    const html = document.documentElement;
    const viewportMeta = document.querySelector('meta[name="viewport"]');

    return {
      title: document.title || '',
      hasH1: !!document.querySelector('h1'),
      controls,
      links,
      brokenImages,
      horizontalOverflow: html.scrollWidth > window.innerWidth + 4,
      hasViewportMeta: !!viewportMeta,
    };
  });

  for (const control of pageData.controls) {
    if (control.disabled) {
      pushIssue(issues, {
        severity: 'warning',
        type: 'control',
        title: 'Control deshabilitado',
        message: `Se encontro un control deshabilitado: ${control.text}.`,
        page: finalUrl,
        details: `Elemento ${control.tag}.`,
      });
      continue;
    }

    if (control.width === 0 || control.height === 0) {
      pushIssue(issues, {
        severity: 'warning',
        type: 'control',
        title: 'Control sin area visible',
        message: `El control ${control.text} no tiene tamano visible.`,
        page: finalUrl,
        details: `Elemento ${control.tag}.`,
      });
    }
  }

  if (!pageData.hasViewportMeta) {
    pushIssue(issues, {
      severity: 'warning',
      type: 'layout',
      title: 'Falta meta viewport',
      message: 'La pagina no declara meta viewport.',
      page: finalUrl,
    });
  }

  if (!pageData.hasH1) {
    pushIssue(issues, {
      severity: 'info',
      type: 'content',
      title: 'Pagina sin H1',
      message: 'No se encontro ningun encabezado H1 en la pagina.',
      page: finalUrl,
    });
  }

  if (pageData.horizontalOverflow) {
    pushIssue(issues, {
      severity: 'warning',
      type: 'layout',
      title: 'Posible desbordamiento horizontal',
      message: 'El contenido parece exceder el ancho visible de la ventana.',
      page: finalUrl,
    });
  }

  if (pageData.brokenImages.length) {
    pushIssue(issues, {
      severity: 'warning',
      type: 'assets',
      title: 'Imagenes rotas',
      message: `Se detectaron ${pageData.brokenImages.length} imagenes con carga fallida.`,
      page: finalUrl,
      details: pageData.brokenImages.slice(0, 3).join(' | '),
    });
  }

  const internalLinks = [];
  for (const link of pageData.links) {
    if (isSkippableLink(link.href) || isSkippableLink(link.absoluteHref)) continue;
    if (!sameOrigin(origin, link.absoluteHref)) continue;
    internalLinks.push(link.absoluteHref.split('#')[0]);
  }

  return {
    url: finalUrl,
    status,
    title: pageData.title,
    notes: pageData.horizontalOverflow ? 'Revisar posible desbordamiento horizontal.' : '',
    internalLinksCount: internalLinks.length,
    internalLinks,
  };
}

async function runAudit(input) {
  const startUrl = normalizeUrl(String(input.url || '').trim());
  const maxPages = Math.max(1, Math.min(25, Number(input.maxPages || 10)));

  if (!startUrl) {
    return { success: false, error: 'La URL es obligatoria.' };
  }

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const issues = [];
  const pages = [];
  const visited = new Set();
  const queue = [startUrl];
  const origin = new URL(startUrl).origin;
  const startedAt = new Date().toISOString();

  try {
    while (queue.length && visited.size < maxPages) {
      const currentUrl = queue.shift();
      if (!currentUrl || visited.has(currentUrl)) continue;
      visited.add(currentUrl);

      const page = await context.newPage();

      page.on('pageerror', (error) => {
        pushIssue(issues, {
          severity: 'warning',
          type: 'javascript',
          title: 'Error de JavaScript',
          message: error.message,
          page: currentUrl,
        });
      });

      page.on('console', (message) => {
        if (message.type() === 'error') {
          pushIssue(issues, {
            severity: 'warning',
            type: 'console',
            title: 'Error de consola',
            message: message.text(),
            page: currentUrl,
          });
        }
      });

      page.on('requestfailed', (request) => {
        pushIssue(issues, {
          severity: 'warning',
          type: 'network',
          title: 'Recurso con carga fallida',
          message: `${request.method()} ${request.url()}`,
          page: currentUrl,
          details: request.failure() ? request.failure().errorText : '',
        });
      });

      try {
        const pageResult = await collectPageData(page, currentUrl, origin, issues);
        pages.push({
          url: pageResult.url,
          status: pageResult.status,
          title: pageResult.title,
          notes: pageResult.notes,
          internalLinksCount: pageResult.internalLinksCount,
        });

        for (const link of pageResult.internalLinks) {
          if (!visited.has(link) && !queue.includes(link) && queue.length + visited.size < maxPages * 3) {
            queue.push(link);
          }
        }
      } catch (error) {
        pushIssue(issues, {
          severity: 'critical',
          type: 'navigation',
          title: 'No se pudo cargar la pagina',
          message: error.message,
          page: currentUrl,
        });
        pages.push({
          url: currentUrl,
          status: null,
          title: '',
          notes: 'No se pudo completar la navegacion.',
          internalLinksCount: 0,
        });
      } finally {
        await page.close();
      }
    }
  } finally {
    await context.close();
    await browser.close();
  }

  return {
    success: true,
    url: startUrl,
    startedAt,
    finishedAt: new Date().toISOString(),
    summary: {
      scannedPages: pages.length,
      totalIssues: issues.length,
      brokenPages: pages.filter((page) => page.status && page.status >= 400).length,
      problematicControls: issues.filter((issue) => issue.type === 'control').length,
      description: issues.length
        ? `Se revisaron ${pages.length} paginas y se detectaron ${issues.length} incidencias que conviene revisar.`
        : `Se revisaron ${pages.length} paginas y no se detectaron incidencias en esta ejecucion.`,
    },
    pages,
    issues,
  };
}

module.exports = { runAudit };
