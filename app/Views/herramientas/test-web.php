<?php
$basePath = dirname($_SERVER['PHP_SELF']);
if ($basePath === '/') $basePath = '';
?>

<div class="d-flex align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= $basePath ?>?route=/herramientas" class="text-decoration-none">Herramientas</a></li>
                <li class="breadcrumb-item active">Pruebas Web</li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-1">Pruebas Web Automatizadas</h2>
        <p class="text-muted mb-0">Analiza una web para encontrar 404, errores de carga, controles problemáticos y señales básicas de maquetación.</p>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <form id="webAuditForm" class="row g-3 align-items-end">
            <div class="col-lg-8">
                <label for="auditUrl" class="form-label fw-medium">URL a revisar</label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white"><i class="bi bi-globe2"></i></span>
                    <input type="text" class="form-control" id="auditUrl" placeholder="https://ejemplo.com" autocomplete="off" required>
                </div>
            </div>
            <div class="col-lg-2">
                <label for="auditMaxPages" class="form-label fw-medium">Páginas máx.</label>
                <input type="number" class="form-control form-control-lg" id="auditMaxPages" min="1" max="25" value="10">
            </div>
            <div class="col-lg-2 d-grid">
                <button type="submit" class="btn btn-primary btn-lg" id="auditSubmitBtn">
                    <i class="bi bi-play-circle me-2"></i>Auditar
                </button>
            </div>
        </form>
        <div class="form-text mt-3">
            La prueba intenta recorrer enlaces internos de la misma web. Es una auditoría heurística, no sustituye una revisión manual completa.
        </div>
    </div>
</div>

<div id="auditLoading" class="card border-0 shadow-sm d-none">
    <div class="card-body text-center py-5">
        <div class="spinner-border text-primary mb-3" role="status" style="width:3rem;height:3rem;">
            <span class="visually-hidden">Analizando...</span>
        </div>
        <h5 class="fw-semibold mb-1">Ejecutando la auditoría</h5>
        <p class="text-muted mb-0">Esto puede tardar un poco según el tamaño de la web y el número de páginas a revisar.</p>
    </div>
</div>

<div id="auditError" class="alert alert-danger d-none" role="alert"></div>

<div id="auditResults" class="d-none">
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-2">Páginas revisadas</div>
                    <div class="fs-3 fw-bold" id="summaryPages">0</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-2">Incidencias totales</div>
                    <div class="fs-3 fw-bold" id="summaryIssues">0</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-2">Páginas con error</div>
                    <div class="fs-3 fw-bold text-danger" id="summaryBroken">0</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-2">Controles problemáticos</div>
                    <div class="fs-3 fw-bold text-warning" id="summaryControls">0</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-semibold">Resumen</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="small text-muted mb-1">URL analizada</div>
                    <div class="fw-semibold text-break" id="resultUrl">-</div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted mb-1">Inicio</div>
                    <div id="resultStartedAt">-</div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted mb-1">Fin</div>
                    <div id="resultFinishedAt">-</div>
                </div>
            </div>
            <hr>
            <p class="mb-0 text-muted" id="resultSummaryText"></p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-semibold">Páginas revisadas</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 py-3">URL</th>
                        <th class="py-3">Estado</th>
                        <th class="py-3">Título</th>
                        <th class="py-3">Links internos</th>
                        <th class="pe-4 py-3">Observaciones</th>
                    </tr>
                </thead>
                <tbody id="pageResultsBody"></tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-semibold">Incidencias detectadas</h5>
        </div>
        <div class="card-body" id="issuesContainer"></div>
    </div>
</div>

<script>
(function() {
    const basePath = '<?= $basePath ?>';
    const form = document.getElementById('webAuditForm');
    const submitBtn = document.getElementById('auditSubmitBtn');
    const loading = document.getElementById('auditLoading');
    const errorBox = document.getElementById('auditError');
    const results = document.getElementById('auditResults');
    const pageResultsBody = document.getElementById('pageResultsBody');
    const issuesContainer = document.getElementById('issuesContainer');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const url = document.getElementById('auditUrl').value.trim();
        const maxPages = parseInt(document.getElementById('auditMaxPages').value || '10', 10);

        if (!url) return;

        loading.classList.remove('d-none');
        errorBox.classList.add('d-none');
        results.classList.add('d-none');
        submitBtn.disabled = true;

        try {
            const response = await fetch(basePath + '/index.php?route=/herramientas/test-web/auditar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ url, maxPages })
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'No se pudo completar la auditoría');
            }

            renderResults(data);
        } catch (error) {
            errorBox.textContent = error.message;
            errorBox.classList.remove('d-none');
        } finally {
            loading.classList.add('d-none');
            submitBtn.disabled = false;
        }
    });

    function renderResults(data) {
        const summary = data.summary || {};
        const pages = data.pages || [];
        const issues = data.issues || [];

        document.getElementById('summaryPages').textContent = summary.scannedPages || pages.length;
        document.getElementById('summaryIssues').textContent = summary.totalIssues || issues.length;
        document.getElementById('summaryBroken').textContent = summary.brokenPages || 0;
        document.getElementById('summaryControls').textContent = summary.problematicControls || 0;
        document.getElementById('resultUrl').textContent = data.url || '-';
        document.getElementById('resultStartedAt').textContent = formatDate(data.startedAt);
        document.getElementById('resultFinishedAt').textContent = formatDate(data.finishedAt);
        document.getElementById('resultSummaryText').textContent = summary.description || 'La auditoría ha finalizado.';

        pageResultsBody.innerHTML = pages.map((page) => `
            <tr>
                <td class="ps-4 py-3 text-break">${escapeHtml(page.url || '')}</td>
                <td>${renderStatusBadge(page.status)}</td>
                <td>${escapeHtml(page.title || 'Sin título')}</td>
                <td>${page.internalLinksCount || 0}</td>
                <td class="pe-4">${escapeHtml(page.notes || 'Sin observaciones relevantes')}</td>
            </tr>
        `).join('') || '<tr><td colspan="5" class="text-center py-4 text-muted">No se registraron páginas revisadas.</td></tr>';

        if (!issues.length) {
            issuesContainer.innerHTML = '<p class="text-muted mb-0">No se detectaron incidencias en esta ejecución.</p>';
        } else {
            issuesContainer.innerHTML = issues.map((issue) => `
                <div class="issue-card border rounded-3 p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                        <div>
                            <div class="fw-semibold">${escapeHtml(issue.title || issue.type || 'Incidencia')}</div>
                            <div class="small text-muted">${escapeHtml(issue.page || data.url || '-')}</div>
                        </div>
                        <span class="badge ${severityClass(issue.severity)}">${escapeHtml(issue.severity || 'info')}</span>
                    </div>
                    <p class="mb-1">${escapeHtml(issue.message || '')}</p>
                    ${issue.details ? `<div class="small text-muted">${escapeHtml(issue.details)}</div>` : ''}
                </div>
            `).join('');
        }

        results.classList.remove('d-none');
    }

    function renderStatusBadge(status) {
        if (!status) return '<span class="badge bg-secondary-subtle text-secondary">Sin datos</span>';
        if (status >= 400) return `<span class="badge bg-danger-subtle text-danger">${status}</span>`;
        if (status >= 300) return `<span class="badge bg-warning-subtle text-warning">${status}</span>`;
        return `<span class="badge bg-success-subtle text-success">${status}</span>`;
    }

    function severityClass(severity) {
        switch ((severity || '').toLowerCase()) {
            case 'critical': return 'bg-danger';
            case 'warning': return 'bg-warning text-dark';
            default: return 'bg-info text-dark';
        }
    }

    function formatDate(value) {
        if (!value) return '-';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
</script>
