<div class="d-flex justify-content-between align-items-center mb-5">
    <h2 class="fw-bold mb-0">Resumen General</h2>
    <div class="text-muted small">Última actualización: <?= date('H:i') ?></div>
</div>

<div class="row g-4">
    <div class="col-md-3">
        <div class="card border-0 bg-white p-2">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-primary bg-opacity-10 p-2 rounded-3 me-3">
                        <i class="bi bi-globe text-primary fs-4"></i>
                    </div>
                    <h6 class="card-subtitle text-muted mb-0">Total Sitios</h6>
                </div>
                <h3 class="fw-bold mb-0"><?= $stats['total'] ?? 0 ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 bg-white p-2">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-danger bg-opacity-10 p-2 rounded-3 me-3">
                        <i class="bi bi-cloud-slash text-danger fs-4"></i>
                    </div>
                    <h6 class="card-subtitle text-muted mb-0">Sitios Caídos</h6>
                </div>
                <h3 class="fw-bold mb-0 text-danger"><?= $stats['caidos'] ?? 0 ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 bg-white p-2">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-warning bg-opacity-10 p-2 rounded-3 me-3">
                        <i class="bi bi-bug text-warning fs-4"></i>
                    </div>
                    <h6 class="card-subtitle text-muted mb-0">Malware</h6>
                </div>
                <h3 class="fw-bold mb-0"><?= $stats['malware'] ?? 0 ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 bg-white p-2">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-info bg-opacity-10 p-2 rounded-3 me-3">
                        <i class="bi bi-dns text-info fs-4"></i>
                    </div>
                    <h6 class="card-subtitle text-muted mb-0">Problemas DNS</h6>
                </div>
                <h3 class="fw-bold mb-0"><?= $stats['bad_dns'] ?? 0 ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="mt-5 text-center">
    <a href="<?= $basePath ?>?route=/websites" class="btn btn-primary btn-lg shadow-sm">
        <i class="bi bi-gear me-2"></i>
        Gestionar Sitios Web
    </a>
</div>
