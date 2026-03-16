<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Ejecución de Tests E2E</h2>
    <div class="test-status-badge">
        <span class="badge bg-secondary" id="global-test-status">Listo</span>
    </div>
</div>

<div class="card mb-4 shadow-sm border-0">
    <div class="card-body">
        <h5 class="card-title">Tests Disponibles</h5>
        <div class="list-group list-group-flush">
            <?php foreach ($tests as $test): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center bg-transparent border-0 px-0">
                    <div>
                        <i class="fas fa-file-code text-primary me-2"></i>
                        <span class="fw-bold"><?= htmlspecialchars($test) ?></span>
                    </div>
                    <button class="btn btn-primary btn-sm run-test-btn" data-test="<?= htmlspecialchars($test) ?>">
                        <i class="fas fa-play me-1"></i> Ejecutar
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <span><i class="fas fa-terminal me-2"></i>Consola de Resultados</span>
        <button class="btn btn-sm btn-outline-light" id="clear-console">Limpiar</button>
    </div>
    <div class="card-body bg-black text-light font-monospace" id="test-console" style="height: 400px; overflow-y: auto; font-size: 0.9rem;">
        <div class="text-muted">Esperando ejecución...</div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const runButtons = document.querySelectorAll('.run-test-btn');
    const consoleDiv = document.getElementById('test-console');
    const globalStatus = document.getElementById('global-test-status');

    runButtons.forEach(btn => {
        btn.addEventListener('click', async function() {
            const testFile = this.getAttribute('data-test');
            
            // UI State
            runButtons.forEach(b => b.disabled = true);
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Ejecutando...';
            consoleDiv.innerHTML = '<div class="text-info">>> Iniciando test: ' + testFile + '...</div>';
            globalStatus.className = 'badge bg-warning text-dark';
            globalStatus.innerText = 'Ejecutando';

            try {
                const response = await fetch('<?= $basePath ?>/index.php?route=/tests/run', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'test_file=' + encodeURIComponent(testFile)
                });
                const data = await response.json();
                
                if (data.success) {
                    consoleDiv.innerHTML += '<pre class="text-white mt-2">' + data.output + '</pre>';
                    globalStatus.className = 'badge bg-success';
                    globalStatus.innerText = 'Completado';
                } else {
                    consoleDiv.innerHTML += '<div class="text-danger mt-2">Error: ' + data.output + '</div>';
                    globalStatus.className = 'badge bg-danger';
                    globalStatus.innerText = 'Error';
                }
            } catch (error) {
                consoleDiv.innerHTML += '<div class="text-danger mt-2">Error de conexión: ' + error + '</div>';
                globalStatus.className = 'badge bg-danger';
                globalStatus.innerText = 'Error';
            } finally {
                runButtons.forEach(b => b.disabled = false);
                this.innerHTML = '<i class="fas fa-play me-1"></i> Ejecutar';
            }
        });
    });

    document.getElementById('clear-console').addEventListener('click', () => {
        consoleDiv.innerHTML = '<div class="text-muted">Consola limpia. Esperando ejecución...</div>';
    });
});
</script>

<style>
#test-console pre {
    background: transparent;
    color: inherit;
    border: none;
    padding: 0;
    margin: 0;
    white-space: pre-wrap;
    word-break: break-all;
}
.test-status-badge .badge {
    font-size: 1rem;
    padding: 0.5rem 1rem;
}
</style>
