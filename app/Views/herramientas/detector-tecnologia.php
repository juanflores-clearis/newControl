<?php
$basePath = dirname($_SERVER['PHP_SELF']);
if ($basePath === '/') $basePath = '';
?>

<div class="d-flex align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= $basePath ?>?route=/herramientas" class="text-decoration-none">Herramientas</a></li>
                <li class="breadcrumb-item active">Detector de Tecnología</li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-1">Detector de Tecnología</h2>
        <p class="text-muted mb-0">Introduce una URL o sube un CSV/Excel para analizar varias a la vez</p>
    </div>
</div>

<!-- ── Análisis individual ── -->
<div class="card mb-4">
    <div class="card-body">
        <form id="detectForm" class="d-flex gap-3 align-items-end">
            <div class="flex-grow-1">
                <label for="urlInput" class="form-label fw-medium">URL del sitio web</label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-white"><i class="bi bi-globe2"></i></span>
                    <input type="text" class="form-control" id="urlInput"
                           placeholder="ejemplo.com o https://ejemplo.com"
                           autocomplete="off" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg px-4" id="btnAnalizar">
                <i class="bi bi-search me-2"></i>Analizar
            </button>
        </form>
    </div>
</div>

<!-- Loading individual -->
<div id="loadingSection" class="d-none">
    <div class="card">
        <div class="card-body text-center py-5">
            <div class="spinner-border text-primary mb-3" role="status" style="width:3rem;height:3rem;">
                <span class="visually-hidden">Analizando...</span>
            </div>
            <p class="text-muted mb-0">Analizando <strong id="loadingUrl"></strong>...</p>
            <p class="text-muted small">Esto puede tardar unos segundos</p>
        </div>
    </div>
</div>

<!-- Error individual -->
<div id="errorSection" class="d-none">
    <div class="alert alert-danger d-flex align-items-center">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
        <span id="errorMessage"></span>
    </div>
</div>

<!-- Resultados individuales -->
<div id="resultsSection" class="d-none">
    <div class="card">
        <div class="card-header bg-white py-3">
            <div class="d-flex align-items-center justify-content-between">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-clipboard-data me-2"></i>Resultados para <span id="resultUrl" class="text-primary"></span>
                </h5>
                <span id="resultBadge" class="badge rounded-pill"></span>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="noResults" class="d-none text-center py-5">
                <i class="bi bi-question-circle text-muted" style="font-size:3rem;"></i>
                <p class="text-muted mt-3 mb-0">No se ha podido identificar la tecnología de este sitio web.</p>
                <p class="text-muted small">Es posible que use un desarrollo a medida o una plataforma no reconocida.</p>
            </div>
            <div id="techList"></div>
        </div>
    </div>
</div>

<style>
.tech-item { padding:1.25rem 1.5rem; border-bottom:1px solid #e2e8f0; transition:background .15s; }
.tech-item:last-child { border-bottom:none; }
.tech-item:hover { background:#f8fafc; }
.confidence-bar { height:8px; border-radius:4px; background:#e2e8f0; overflow:hidden; min-width:120px; }
.confidence-fill { height:100%; border-radius:4px; transition:width .6s ease; }
.confidence-high   { background:#2E9935; }
.confidence-medium { background:#A2C551; }
.confidence-low    { background:#ef4444; }
.tech-logo { width:48px;height:48px;border-radius:.75rem;display:flex;align-items:center;justify-content:center;font-size:1.25rem;font-weight:700;color:#fff;flex-shrink:0; }
.evidence-list { list-style:none;padding:0;margin:.5rem 0 0 0; }
.evidence-list li { font-size:.8rem;color:#64748b;padding:.15rem 0; }
.evidence-list li::before { content:"\2713";margin-right:.5rem;color:#2E9935;font-weight:bold; }
#dropZone.drag-over { background:rgba(46,153,53,.06);border-color:var(--primary) !important; }
</style>

<script>
const TECH_COLORS = { 'WordPress':'#21759b','WooCommerce':'#96588a','Shopify':'#96bf48','PrestaShop':'#df0067','Magento':'#f26322','Sylius':'#1abb9c','Joomla':'#5091cd','Drupal':'#0678be','Squarespace':'#222222','Wix':'#0c6efc','BigCommerce':'#34313f','osCommerce':'#00599c','OpenCart':'#2caadf' };
const TECH_ICONS  = { 'WordPress':'W','WooCommerce':'Wc','Shopify':'S','PrestaShop':'Ps','Magento':'M','Sylius':'Sy','Joomla':'J!','Drupal':'D','Squarespace':'Sq','Wix':'Wx','BigCommerce':'Bc','osCommerce':'Os','OpenCart':'Oc' };

document.getElementById('detectForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const url = document.getElementById('urlInput').value.trim();
    if (!url) return;
    const basePath = '<?= $basePath ?>';
    document.getElementById('loadingSection').classList.remove('d-none');
    document.getElementById('errorSection').classList.add('d-none');
    document.getElementById('resultsSection').classList.add('d-none');
    document.getElementById('loadingUrl').textContent = url;
    document.getElementById('btnAnalizar').disabled = true;
    try {
        const res  = await fetch(basePath + '/index.php?route=/herramientas/detectar-ajax', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({url}) });
        const data = await res.json();
        document.getElementById('loadingSection').classList.add('d-none');
        document.getElementById('btnAnalizar').disabled = false;
        if (!data.success) { document.getElementById('errorSection').classList.remove('d-none'); document.getElementById('errorMessage').textContent = data.error || 'Error desconocido'; return; }
        renderResults(data);
    } catch(err) {
        document.getElementById('loadingSection').classList.add('d-none');
        document.getElementById('btnAnalizar').disabled = false;
        document.getElementById('errorSection').classList.remove('d-none');
        document.getElementById('errorMessage').textContent = 'Error de conexión: ' + err.message;
    }
});

function renderResults(data) {
    const section  = document.getElementById('resultsSection');
    const techList = document.getElementById('techList');
    const noRes    = document.getElementById('noResults');
    const badge    = document.getElementById('resultBadge');
    section.classList.remove('d-none');
    document.getElementById('resultUrl').textContent = data.url;
    const techs = data.technologies || [];
    if (techs.length === 0) { noRes.classList.remove('d-none'); techList.innerHTML=''; badge.textContent='Sin resultados'; badge.className='badge rounded-pill bg-secondary'; return; }
    noRes.classList.add('d-none');
    badge.textContent = techs.length + (techs.length===1?' tecnología':' tecnologías');
    badge.className   = 'badge rounded-pill bg-primary';
    techList.innerHTML = techs.map(tech => {
        const color = TECH_COLORS[tech.name] || '#2E9935';
        const icon  = TECH_ICONS[tech.name]  || tech.name.substring(0,2);
        const cls   = tech.confidence>=70?'confidence-high':tech.confidence>=40?'confidence-medium':'confidence-low';
        const lbl   = tech.confidence>=70?'Alta':tech.confidence>=40?'Media':'Baja';
        return `<div class="tech-item"><div class="d-flex align-items-start gap-3">
            <div class="tech-logo" style="background:${color};">${icon}</div>
            <div class="flex-grow-1">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <h6 class="fw-semibold mb-0">${escapeHtml(tech.name)}</h6>
                    <div class="d-flex align-items-center gap-2">
                        <span class="small text-muted">Confianza: ${tech.confidence}% (${lbl})</span>
                        <div class="confidence-bar"><div class="confidence-fill ${cls}" style="width:${tech.confidence}%;"></div></div>
                    </div>
                </div>
                <ul class="evidence-list">${tech.evidence.map(e=>`<li>${escapeHtml(e)}</li>`).join('')}</ul>
            </div>
        </div></div>`;
    }).join('');
}
function escapeHtml(t) { const d=document.createElement('div'); d.textContent=t; return d.innerHTML; }
</script>


<!-- ============================================================
     ANÁLISIS MASIVO — CSV / Excel
     ============================================================ -->
<div class="card mt-5">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Análisis masivo desde CSV / Excel</h5>
        <p class="text-muted small mb-0 mt-1">
            Sube un fichero con una URL por fila. El análisis se realiza de una en una para evitar timeouts.
        </p>
    </div>
    <div class="card-body">

        <!-- Zona de drop -->
        <div id="dropZone" class="border border-2 border-dashed rounded-3 p-5 text-center mb-3"
             style="border-color:var(--primary-light) !important;cursor:pointer;transition:background .2s;">
            <i class="bi bi-cloud-upload fs-1 text-muted d-block mb-2"></i>
            <p class="mb-1 fw-medium">Arrastra aquí tu fichero o <span class="text-primary text-decoration-underline">haz clic para seleccionar</span></p>
            <p class="text-muted small mb-0">Formatos: .csv · .xlsx · .xls &nbsp;|&nbsp; Máximo 5 MB</p>
            <input type="file" id="csvFileInput" accept=".csv,.xlsx,.xls" class="d-none">
        </div>

        <!-- Info del fichero seleccionado -->
        <div id="fileInfo" class="d-none alert alert-info d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-file-earmark-check-fill fs-5"></i>
            <span id="fileInfoText"></span>
            <button class="btn btn-sm btn-outline-secondary ms-auto" id="btnClearFile">Cambiar fichero</button>
        </div>

        <!-- ── Opciones de exportación ── -->
        <div id="exportOptions" class="d-none mb-4">
            <hr class="my-3">
            <p class="fw-medium mb-3"><i class="bi bi-gear me-1"></i>Opciones de exportación</p>
            <div class="row g-3 align-items-start">

                <!-- Fila inicial de datos -->
                <div class="col-md-3">
                    <label for="startRowInput" class="form-label fw-medium mb-1">
                        Fila inicial de los datos
                        <span class="badge bg-light text-muted border ms-1 fw-normal" style="font-size:.7rem;">Opcional</span>
                    </label>
                    <div class="input-group" style="max-width:160px;">
                        <span class="input-group-text bg-white"><i class="bi bi-list-ol"></i></span>
                        <input type="number" class="form-control" id="startRowInput"
                               min="1" placeholder="Auto" autocomplete="off">
                        <span class="input-group-text bg-white text-muted small">fila</span>
                    </div>
                    <div class="form-text">
                        Número de la primera fila con datos (ej: <strong>2</strong> si la fila 1 es cabecera).
                        Vacío = detección automática.
                    </div>
                </div>

                <!-- Columna donde estÃ¡n las URLs -->
                <div class="col-md-2">
                    <label for="urlColumnInput" class="form-label fw-medium mb-1">
                        Columna de URLs
                        <span class="badge bg-light text-muted border ms-1 fw-normal" style="font-size:.7rem;">Opcional</span>
                    </label>
                    <div class="input-group" style="max-width:150px;">
                        <span class="input-group-text bg-white"><i class="bi bi-link-45deg"></i></span>
                        <input type="text" class="form-control text-uppercase" id="urlColumnInput"
                               maxlength="3" placeholder="Ej: A"
                               style="text-transform:uppercase;"
                               autocomplete="off">
                    </div>
                    <div class="form-text">
                        Si lo indicas, solo se leerÃ¡n las URLs de esa columna.
                    </div>
                </div>

                <!-- Columna de inicio -->
                <div class="col-md-4">
                    <label for="colInput" class="form-label fw-medium mb-1">
                        Escribir resultados en el fichero original
                        <span class="badge bg-light text-muted border ms-1 fw-normal" style="font-size:.7rem;">Opcional</span>
                    </label>
                    <div class="input-group" style="max-width:220px;">
                        <span class="input-group-text bg-white"><i class="bi bi-layout-three-columns"></i></span>
                        <input type="text" class="form-control text-uppercase" id="colInput"
                               maxlength="3" placeholder="Ej: C"
                               style="text-transform:uppercase;"
                               autocomplete="off">
                        <span class="input-group-text bg-white text-muted small">columna</span>
                    </div>
                    <div class="form-text" id="colHelp">
                        Si se rellena, los resultados se añadirán al fichero original a partir de esa columna.
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="insertHeaderRow">
                        <label class="form-check-label small" for="insertHeaderRow">
                            Insertar una fila de cabecera encima si la primera fila ya contiene URLs
                        </label>
                    </div>
                    <div class="form-text">
                        Útil cuando el fichero original no trae títulos y quieres crear esa cabecera automáticamente.
                    </div>
                </div>

                <!-- Formato cuando no hay columna -->
                <div class="col-md-4" id="formatSection">
                    <label class="form-label fw-medium mb-1">Formato del fichero exportado</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="exportFormat" id="fmtCsv" value="csv" checked>
                            <label class="form-check-label" for="fmtCsv">
                                <i class="bi bi-filetype-csv me-1 text-success"></i>CSV
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="exportFormat" id="fmtXlsx" value="xlsx">
                            <label class="form-check-label" for="fmtXlsx">
                                <i class="bi bi-file-earmark-excel me-1 text-success"></i>Excel (.xlsx)
                            </label>
                        </div>
                    </div>
                    <div class="form-text">Nuevo fichero con URL + resultados de la detección.</div>
                </div>

                <!-- Indicador cuando hay columna -->
                <div class="col-md-4 d-none" id="colModeInfo">
                    <div class="alert alert-success py-2 px-3 mb-0 small">
                        <i class="bi bi-check-circle me-1"></i>
                        Los resultados se escribirán en el fichero original a partir de la columna <strong id="colModeLabel">—</strong>.
                        El formato de salida será el mismo que el del fichero subido.
                    </div>
                </div>

            </div>

            <!-- Columnas a exportar -->
            <hr class="my-3">
            <p class="fw-medium mb-2"><i class="bi bi-table me-1"></i>Columnas a exportar</p>
            <div class="d-flex flex-wrap gap-3 mb-1" id="colCheckboxes">
                <div class="form-check">
                    <input class="form-check-input col-check" type="checkbox" value="url" id="colChkUrl" checked>
                    <label class="form-check-label" for="colChkUrl">URL</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input col-check" type="checkbox" value="topTech" id="colChkTopTech" checked>
                    <label class="form-check-label" for="colChkTopTech">Tecnología principal</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input col-check" type="checkbox" value="confidence" id="colChkConf" checked>
                    <label class="form-check-label" for="colChkConf">Confianza (%)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input col-check" type="checkbox" value="others" id="colChkOthers" checked>
                    <label class="form-check-label" for="colChkOthers">Otras tecnologías</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input col-check" type="checkbox" value="evidence" id="colChkEvidence" checked>
                    <label class="form-check-label" for="colChkEvidence">Evidencias</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input col-check" type="checkbox" value="status" id="colChkStatus" checked>
                    <label class="form-check-label" for="colChkStatus">Estado</label>
                </div>
            </div>
            <div class="form-text" id="colChkNote" style="display:none;">
                <i class="bi bi-info-circle me-1"></i>En modo fichero original, la columna <em>URL</em> no se escribe (ya está en el fichero).
            </div>

        </div>
    </div>

        <!-- Botones de acción -->
        <button class="btn btn-primary" id="btnStartBulk" disabled>
            <i class="bi bi-play-fill me-1"></i> Iniciar análisis masivo
        </button>
        <button class="btn btn-outline-danger ms-2 d-none" id="btnStopBulk">
            <i class="bi bi-stop-fill me-1"></i> Detener
        </button>
    </div>
</div>

<!-- Progreso -->
<div id="bulkProgress" class="card mt-3 d-none">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-medium" id="progressLabel">Analizando…</span>
            <span class="text-muted small" id="progressCounter">0 / 0</span>
        </div>
        <div class="progress mb-1" style="height:10px;">
            <div id="progressBar" class="progress-bar bg-primary progress-bar-striped progress-bar-animated"
                 role="progressbar" style="width:0%"></div>
        </div>
        <small class="text-muted" id="progressCurrentUrl"></small>
    </div>
</div>

<!-- Tabla de resultados masivos -->
<div id="bulkResultsCard" class="card mt-3 d-none">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-table me-2"></i>Resultados</h6>
        <div class="d-flex gap-2" id="exportButtons">
            <!-- Botón "descargar en fichero original" — visible sólo si hay columna especificada -->
            <button class="btn btn-sm btn-primary d-none" id="btnExportOriginal">
                <i class="bi bi-download me-1"></i>Descargar con resultados
            </button>
            <!-- Exportar como fichero nuevo -->
            <button class="btn btn-sm btn-outline-success" id="btnExportXlsx">
                <i class="bi bi-file-earmark-excel me-1"></i>Excel
            </button>
            <button class="btn btn-sm btn-outline-primary" id="btnExportCsv">
                <i class="bi bi-filetype-csv me-1"></i>CSV
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height:420px;overflow-y:auto;" id="bulkTableScroll">
            <table class="table table-hover align-middle mb-0" id="bulkResultsTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3 py-3">URL</th>
                        <th class="py-3">Tecnología principal</th>
                        <th class="py-3">Confianza</th>
                        <th class="py-3">Otras tecnologías</th>
                        <th class="py-3">Estado</th>
                        <th class="py-3 text-center">Detalles</th>
                    </tr>
                </thead>
                <tbody id="bulkResultsBody"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function() {
    const basePath    = '<?= $basePath ?>';
    const dropZone    = document.getElementById('dropZone');
    const fileInput   = document.getElementById('csvFileInput');
    const fileInfo    = document.getElementById('fileInfo');
    const fileInfoTx  = document.getElementById('fileInfoText');
    const btnStart    = document.getElementById('btnStartBulk');
    const btnStop     = document.getElementById('btnStopBulk');
    const btnClear    = document.getElementById('btnClearFile');
    const exportOpts  = document.getElementById('exportOptions');
    const urlColumnInput = document.getElementById('urlColumnInput');
    const colInput    = document.getElementById('colInput');
    const insertHeaderRowInput = document.getElementById('insertHeaderRow');
    const formatSect  = document.getElementById('formatSection');
    const colModeInfo = document.getElementById('colModeInfo');
    const colModeLbl  = document.getElementById('colModeLabel');

    const bulkProgress  = document.getElementById('bulkProgress');
    const progressBar   = document.getElementById('progressBar');
    const progressLabel = document.getElementById('progressLabel');
    const progressCount = document.getElementById('progressCounter');
    const progressUrl   = document.getElementById('progressCurrentUrl');

    const resultsCard  = document.getElementById('bulkResultsCard');
    const resultsBody  = document.getElementById('bulkResultsBody');
    const btnExportOrig = document.getElementById('btnExportOriginal');

    // Estado compartido
    let selectedFile    = null;
    let stopRequested   = false;
    let currentFileId   = '';
    let currentOrigName = '';
    let currentFileExt  = '';

    function getCurrentColumn() {
        return colInput.value.trim().toUpperCase();
    }

    function refreshExportModeUi() {
        const col = getCurrentColumn();
        colInput.value = col;

        if (col) {
            formatSect.classList.add('d-none');
            colModeInfo.classList.remove('d-none');
            colModeLbl.textContent = col;
            colChkNote.style.display = '';
            insertHeaderRowInput.disabled = false;
        } else {
            formatSect.classList.remove('d-none');
            colModeInfo.classList.add('d-none');
            colChkNote.style.display = 'none';
            insertHeaderRowInput.checked = false;
            insertHeaderRowInput.disabled = true;
        }

        if (currentFileId && col) {
            btnExportOrig.classList.remove('d-none');
            btnExportOrig.innerHTML = `<i class="bi bi-download me-1"></i>Descargar en ${currentOrigName}.${currentFileExt}`;
        } else {
            btnExportOrig.classList.add('d-none');
        }
    }

    // ── Drag & drop / selección ───────────────────────────────
    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
    dropZone.addEventListener('drop', e => { e.preventDefault(); dropZone.classList.remove('drag-over'); if (e.dataTransfer.files[0]) setFile(e.dataTransfer.files[0]); });
    fileInput.addEventListener('change', () => { if (fileInput.files[0]) setFile(fileInput.files[0]); });

    function setFile(f) {
        const ext = f.name.split('.').pop().toLowerCase();
        if (!['csv','xlsx','xls'].includes(ext)) { alert('Formato no soportado. Use .csv, .xlsx o .xls'); return; }
        if (f.size > 5 * 1024 * 1024)           { alert('El fichero supera el límite de 5 MB'); return; }
        selectedFile = f;
        fileInfoTx.textContent = `${f.name}  (${(f.size/1024).toFixed(1)} KB)`;
        fileInfo.classList.remove('d-none');
        dropZone.classList.add('d-none');
        exportOpts.classList.remove('d-none');
        btnStart.disabled = false;
        refreshExportModeUi();
    }

    btnClear.addEventListener('click', () => {
        selectedFile = null;
        fileInput.value = '';
        fileInfo.classList.add('d-none');
        dropZone.classList.remove('d-none');
        exportOpts.classList.add('d-none');
        btnStart.disabled = true;
        urlColumnInput.value = '';
        insertHeaderRowInput.checked = false;
        insertHeaderRowInput.disabled = true;
        currentFileId = currentOrigName = currentFileExt = '';
        btnExportOrig.classList.add('d-none');
    });

    // ── Mostrar/ocultar indicadores de modo al cambiar columna ───
    const colChkNote = document.getElementById('colChkNote');

    colInput.addEventListener('input', refreshExportModeUi);
    urlColumnInput.addEventListener('input', () => {
        urlColumnInput.value = urlColumnInput.value.trim().toUpperCase();
    });
    colInput.addEventListener('input', () => {
        const col = colInput.value.trim().toUpperCase();
        colInput.value = col; // forzar mayúsculas
        if (col) {
            formatSect.classList.add('d-none');
            colModeInfo.classList.remove('d-none');
            colModeLbl.textContent = col;
            colChkNote.style.display = '';
            insertHeaderRowInput.disabled = false;
        } else {
            formatSect.classList.remove('d-none');
            colModeInfo.classList.add('d-none');
            colChkNote.style.display = 'none';
            insertHeaderRowInput.checked = false;
            insertHeaderRowInput.disabled = true;
        }
    });

    insertHeaderRowInput.disabled = true;
    refreshExportModeUi();

    // ── Iniciar análisis masivo ───────────────────────────────
    btnStart.addEventListener('click', async () => {
        if (!selectedFile) return;

        // 1. Subir fichero y obtener lista de URLs + fileId
        const formData = new FormData();
        formData.append('file', selectedFile);
        const startRow = parseInt(document.getElementById('startRowInput').value) || 0;
        const urlColumn = urlColumnInput.value.trim().toUpperCase();
        if (startRow > 0) formData.append('startRow', startRow);
        if (urlColumn) formData.append('urlColumn', urlColumn);
        btnStart.disabled = true;
        btnStart.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando fichero…';

        let urls;
        try {
            const resp = await fetch(basePath + '/index.php?route=/herramientas/detectar-csv', { method:'POST', body:formData });
            const json = await resp.json();
            if (!json.success) {
                alert('Error: ' + json.error);
                btnStart.disabled = false;
                btnStart.innerHTML = '<i class="bi bi-play-fill me-1"></i> Iniciar análisis masivo';
                return;
            }
            urls            = json.urls;
            currentFileId   = json.fileId   || '';
            currentOrigName = json.origName || 'resultados';
            currentFileExt  = json.fileExt  || 'csv';
        } catch(err) {
            alert('Error al subir el fichero: ' + err.message);
            btnStart.disabled = false;
            btnStart.innerHTML = '<i class="bi bi-play-fill me-1"></i> Iniciar análisis masivo';
            return;
        }

        // 2. Mostrar botón "descargar original" si hay columna
        const col = colInput.value.trim().toUpperCase();
        const btnExportOrig = document.getElementById('btnExportOriginal');
        if (col) {
            btnExportOrig.classList.remove('d-none');
            btnExportOrig.textContent = '';
            btnExportOrig.innerHTML = `<i class="bi bi-download me-1"></i>Descargar en ${currentOrigName}.${currentFileExt}`;
        } else {
            btnExportOrig.classList.add('d-none');
        }

        // 3. Lanzar análisis URL por URL
        stopRequested = false;
        resultsBody.innerHTML = '';
        resultsCard.classList.remove('d-none');
        bulkProgress.classList.remove('d-none');
        btnStop.classList.remove('d-none');
        btnStart.innerHTML = '<i class="bi bi-play-fill me-1"></i> Iniciar análisis masivo';
        progressBar.classList.add('progress-bar-animated');

        const total = urls.length;
        let done = 0, errors = 0;

        for (const url of urls) {
            if (stopRequested) break;

            progressUrl.textContent   = url;
            progressCount.textContent = `${done} / ${total}`;
            const pct = Math.round((done / total) * 100);
            progressBar.style.width = pct + '%';
            progressBar.setAttribute('aria-valuenow', pct);
            progressLabel.textContent = `Analizando (${done}/${total})…`;

            const rowId = 'row-' + done;
            resultsBody.insertAdjacentHTML('beforeend', `
                <tr id="${rowId}">
                    <td class="ps-3 text-break small">${escapeHtml(url)}</td>
                    <td colspan="3"><span class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Analizando…</span></td>
                    <td><span class="badge bg-warning text-dark">En curso</span></td>
                    <td></td>
                </tr>`);

            const tableScroll = document.getElementById('bulkTableScroll');
            tableScroll.scrollTop = tableScroll.scrollHeight;

            try {
                const res  = await fetch(basePath + '/index.php?route=/herramientas/detectar-ajax', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({url}) });
                const data = await res.json();
                updateRow(rowId, url, data);
            } catch(err) {
                updateRowError(rowId, url, err.message);
                errors++;
            }
            done++;
        }

        // Finalizado
        const stopped = stopRequested;
        progressBar.style.width = '100%';
        progressBar.classList.remove('progress-bar-animated');
        progressLabel.textContent = stopped
            ? `Detenido — ${done} de ${total} analizadas`
            : `Completado — ${total} URLs analizadas${errors ? ` (${errors} errores)` : ''}`;
        progressCount.textContent = `${done} / ${total}`;
        progressUrl.textContent   = '';
        btnStop.classList.add('d-none');
        btnStart.disabled = false;
    });

    btnStop.addEventListener('click', () => { stopRequested = true; });

    // ── Helpers de fila ──────────────────────────────────────
    function updateRow(rowId, url, data) {
        const row = document.getElementById(rowId);
        if (!row) return;
        row.dataset.result = JSON.stringify(data);
        row.dataset.url    = url;

        if (!data.success) {
            row.innerHTML = `
                <td class="ps-3 text-break small">${escapeHtml(url)}</td>
                <td colspan="3"><span class="text-muted small">${escapeHtml(data.error||'Error desconocido')}</span></td>
                <td><span class="badge bg-danger">Error</span></td>
                <td></td>`;
            return;
        }

        const techs = data.technologies || [];
        if (techs.length === 0) {
            row.innerHTML = `
                <td class="ps-3 text-break small">${escapeHtml(url)}</td>
                <td colspan="3"><span class="text-muted small">No detectada</span></td>
                <td><span class="badge bg-secondary">Sin datos</span></td>
                <td></td>`;
            return;
        }

        const top   = techs[0];
        const conf  = top.confidence;
        const color = conf>=70?'success':conf>=40?'warning':'danger';
        const rest  = techs.slice(1).map(t=>`<span class="badge bg-light text-dark border me-1">${escapeHtml(t.name)}</span>`).join('');

        row.innerHTML = `
            <td class="ps-3 text-break small">${escapeHtml(url)}</td>
            <td><strong>${escapeHtml(top.name)}</strong></td>
            <td><span class="badge bg-${color}">${conf}%</span></td>
            <td>${rest||'<span class="text-muted small">—</span>'}</td>
            <td><span class="badge bg-success">OK</span></td>
            <td class="text-center">
                <button class="btn btn-sm btn-outline-primary btn-detail py-0 px-2" title="Ver evidencias">
                    <i class="bi bi-search"></i>
                </button>
            </td>`;
    }

    function updateRowError(rowId, url, msg) {
        const row = document.getElementById(rowId);
        if (!row) return;
        row.dataset.result = JSON.stringify({success:false, error:msg});
        row.dataset.url    = url;
        row.innerHTML = `
            <td class="ps-3 text-break small">${escapeHtml(url)}</td>
            <td colspan="3"><span class="text-danger small">${escapeHtml(msg)}</span></td>
            <td><span class="badge bg-danger">Error</span></td>
            <td></td>`;
    }

    // ── Recoger resultados de la tabla ────────────────────────
    function collectResults() {
        return Array.from(resultsBody.querySelectorAll('tr[data-result]')).map(tr => {
            const url  = tr.dataset.url || '';
            const data = JSON.parse(tr.dataset.result || '{}');
            if (!data.success) return { url, topTech:'', confidence:'', others:'', evidence:'', status: data.error||'Error' };
            const techs = data.technologies || [];
            if (techs.length === 0) return { url, topTech:'', confidence:'', others:'', evidence:'', status:'Sin datos' };
            const top = techs[0];
            return {
                url,
                topTech:    top.name,
                confidence: top.confidence + '%',
                others:     techs.slice(1).map(t=>t.name).join(' | '),
                evidence:   (top.evidence||[]).join(' | '),
                status:     'OK'
            };
        });
    }

    // ── Recoger columnas seleccionadas ────────────────────────
    function getSelectedColumns() {
        return Array.from(document.querySelectorAll('.col-check:checked')).map(cb => cb.value);
    }

    // ── Exportar (todos los botones van al servidor) ──────────
    async function doExport(format, column) {
        const results = collectResults();
        if (results.length === 0) { alert('No hay resultados para exportar.'); return; }

        const selectedColumns = getSelectedColumns();
        if (selectedColumns.length === 0) { alert('Selecciona al menos una columna para exportar.'); return; }

        const payload = {
            fileId:   currentFileId,
            column:   column || '',
            format:   format || 'csv',
            insertHeaderRow: column ? insertHeaderRowInput.checked : false,
            columns:  selectedColumns,
            results
        };

        try {
            const resp = await fetch(basePath + '/index.php?route=/herramientas/exportar-resultados', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (!resp.ok) {
                const err = await resp.json().catch(() => ({error: 'Error desconocido'}));
                alert('Error al exportar: ' + (err.error || resp.statusText));
                return;
            }

            const blob = await resp.blob();
            const a    = document.createElement('a');
            a.href     = URL.createObjectURL(blob);

            // Nombre de descarga: leerlo del header Content-Disposition si es posible
            const cd   = resp.headers.get('Content-Disposition') || '';
            const match = cd.match(/filename="([^"]+)"/);
            a.download = match ? match[1] : ('resultados.' + (format||'csv'));
            a.click();
            URL.revokeObjectURL(a.href);
        } catch(err) {
            alert('Error de conexión al exportar: ' + err.message);
        }
    }

    document.getElementById('btnExportCsv').addEventListener('click', () => {
        doExport('csv', '');
    });

    document.getElementById('btnExportXlsx').addEventListener('click', () => {
        doExport('xlsx', '');
    });

    document.getElementById('btnExportOriginal').addEventListener('click', () => {
        const col = colInput.value.trim().toUpperCase();
        if (!col) { alert('Introduce una columna de inicio antes de exportar.'); return; }
        doExport(currentFileExt === 'xlsx' ? 'xlsx' : 'csv', col);
    });

    // ── Modal de detalles ─────────────────────────────────────
    resultsBody.addEventListener('click', e => {
        const btn = e.target.closest('.btn-detail');
        if (!btn) return;
        const tr     = btn.closest('tr');
        const url    = tr.dataset.url    || '—';
        const result = JSON.parse(tr.dataset.result || '{}');
        openDetailModal(url, result);
    });

    function openDetailModal(url, data) {
        document.getElementById('detailModalUrl').textContent = url;
        const body = document.getElementById('detailModalBody');
        if (!data.success) {
            body.innerHTML = `<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle me-2"></i>${escapeHtml(data.error||'Error desconocido')}</div>`;
        } else if (!data.technologies || data.technologies.length===0) {
            body.innerHTML = `<p class="text-muted mb-0">No se detectó ninguna tecnología conocida.</p>`;
        } else {
            body.innerHTML = data.technologies.map((t,i) => {
                const color = t.confidence>=70?'success':t.confidence>=40?'warning':'danger';
                const ev    = (t.evidence||[]).map(e=>`<li class="small text-muted"><i class="bi bi-check2 text-success me-1"></i>${escapeHtml(e)}</li>`).join('');
                return `<div class="mb-3 pb-3 ${i<data.technologies.length-1?'border-bottom':''}">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <strong class="fs-6">${escapeHtml(t.name)}</strong>
                        <span class="badge bg-${color}">${t.confidence}% confianza</span>
                    </div>
                    <ul class="list-unstyled mb-0 ps-1">${ev||'<li class="small text-muted">Sin evidencias registradas</li>'}</ul>
                </div>`;
            }).join('');
        }
        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }

    // Exportar desde el modal (CSV por defecto)
    document.getElementById('btnExportCsvModal')?.addEventListener('click', () => {
        document.getElementById('detailModal').querySelector('[data-bs-dismiss]').click();
        doExport('csv', '');
    });

    function escapeHtml(t) { const d=document.createElement('div'); d.textContent=t; return d.innerHTML; }
})();
</script>

<!-- Modal de detalles -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0"><i class="bi bi-search me-2"></i>Evidencias de detección</h5>
                    <small class="text-muted" id="detailModalUrl"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnExportCsvModal">
                    <i class="bi bi-download me-1"></i>Exportar CSV completo
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
