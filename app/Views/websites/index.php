<?php
// Registrar el script de esta página para que footer.php lo cargue DESPUÉS de Bootstrap
// $assetPath viene de header.php (un nivel por encima de /public); lo recalculamos como fallback
if (empty($assetPath)) {
    $assetPath = rtrim(dirname($basePath), '/');
}
$pageScript = '<script src="' . $assetPath . '/assets/js/filtrosWebsites.js"></script>';

// Helper local para formatear fechas
function fmtDate(?string $dt): string {
    return $dt ? date('d/m/Y H:i', strtotime($dt)) : 'Nunca';
}
?>

<div class="d-flex justify-content-between align-items-center mb-5">
	<h2 class="fw-bold mb-0">Gestión de Sitios Web</h2>
	<button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#insertUrlModal">
		<i class="bi bi-plus-lg me-2"></i>Nueva URL
	</button>
</div>

<!-- ALERTAS TOAST -->
<div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 1100;"></div>

<!-- FILTROS -->
<div class="card border-0 mb-4 bg-white shadow-sm">
    <div class="card-body p-4">
        <div class="row g-3" id="filters">
            <div class="col-md-3">
                <label for="filter-url" class="form-label small fw-bold text-muted">Buscar por URL</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control bg-light border-start-0" id="filter-url" placeholder="ejemplo.com">
                </div>
            </div>
            <div class="col-md-2">
                <label for="filter-status" class="form-label small fw-bold text-muted">Estado</label>
                <select class="form-select bg-light" id="filter-status">
                    <option value="">Todos</option>
                    <option value="OK">OK</option>
                    <option value="Caída">Caída</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="filter-malware-vt" class="form-label small fw-bold text-muted">Malware (VirusTotal)</label>
                <select class="form-select bg-light" id="filter-malware-vt">
                    <option value="">Todos</option>
                    <option value="Sí">Sí</option>
                    <option value="No">No</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="filter-malware-sucuri" class="form-label small fw-bold text-muted">Malware (Sucuri)</label>
                <select class="form-select bg-light" id="filter-malware-sucuri">
                    <option value="">Todos</option>
                    <option value="Sí">Sí</option>
                    <option value="No">No</option>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button class="btn btn-outline-secondary w-100" id="reset-filters" title="Reiniciar filtros">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- TABLA DE SITIOS WEB -->
<div class="card border-0 bg-white shadow-sm overflow-hidden">
    <div class="card-body p-0">
        <?php if (count($websites) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="websites-table">
                    <thead class="bg-light">
                        <tr>
                            <!-- col 0 --> <th class="px-4 py-3 border-0">URL</th>
                            <!-- col 1 --> <th class="py-3 border-0">Estado</th>
                            <!-- col 2 --> <th class="py-3 border-0">Última OK</th>
                            <!-- col 3 --> <th class="py-3 border-0">Malware VT</th>
                            <!-- col 4 --> <th class="py-3 border-0">Malware Sucuri</th>
                            <!-- col 5 --> <th class="py-3 border-0">Última limpia</th>
                            <!-- col 6 --> <th class="py-3 border-0">Último chequeo</th>
                            <!-- col 7 --> <th class="py-3 border-0 text-center">Detalles</th>
                            <!-- col 8 --> <th class="px-4 py-3 border-0 text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($websites as $site): ?>
                            <tr>
                                <!-- col 0: URL -->
                                <td class="px-4 fw-medium text-dark"><?= htmlspecialchars($site['url']) ?></td>

                                <!-- col 1: Estado -->
                                <td>
                                    <?php if ($site['is_online'] === null): ?>
                                        <span class="badge rounded-pill bg-secondary bg-opacity-10 text-secondary">Sin datos</span>
                                    <?php elseif ($site['is_online']): ?>
                                        <span class="badge rounded-pill bg-success bg-opacity-10 text-success">OK</span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill bg-danger bg-opacity-10 text-danger">Caída</span>
                                    <?php endif; ?>
                                </td>

                                <!-- col 2: Última vez online -->
                                <td class="small text-muted text-nowrap"><?= fmtDate($site['last_online_ok'] ?? null) ?></td>

                                <!-- col 3: Malware VT -->
                                <td>
                                    <?php if ($site['has_malware_virustotal'] === null): ?>
                                        <span class="badge rounded-pill bg-secondary bg-opacity-10 text-secondary">Sin datos</span>
                                    <?php elseif ($site['has_malware_virustotal'] == -1 || $site['has_malware_virustotal'] === '-1'): ?>
                                        <span class="badge rounded-pill bg-warning bg-opacity-10 text-dark small" data-bs-toggle="tooltip" title="Cuota de VirusTotal excedida. Inténtalo más tarde.">No info</span>
                                    <?php elseif ($site['has_malware_virustotal']): ?>
                                        <span class="badge rounded-pill bg-danger bg-opacity-10 text-danger">Sí</span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill bg-success bg-opacity-10 text-success">No</span>
                                    <?php endif; ?>
                                </td>

                                <!-- col 4: Malware Sucuri -->
                                <td>
                                    <?php if ($site['has_malware_sucuri'] === null): ?>
                                        <span class="badge rounded-pill bg-secondary bg-opacity-10 text-secondary">Sin datos</span>
                                    <?php elseif ($site['has_malware_sucuri']): ?>
                                        <span class="badge rounded-pill bg-danger bg-opacity-10 text-danger">Sí</span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill bg-success bg-opacity-10 text-success">No</span>
                                    <?php endif; ?>
                                </td>

                                <!-- col 5: Última vez limpia -->
                                <td class="small text-muted text-nowrap"><?= fmtDate($site['last_clean'] ?? null) ?></td>

                                <!-- col 6: Último chequeo -->
                                <td class="small text-muted text-nowrap"><?= fmtDate($site['created_at'] ?? null) ?></td>

                                <!-- col 7: Botón detalles -->
                                <td class="text-center">
                                    <button class="btn btn-white btn-sm px-2 other-data-btn"
                                        data-ssl="<?= $site['have_ssl'] ?? '' ?>"
                                        data-bad-dns="<?= $site['bad_dns'] ?? '' ?>"
                                        data-error-php="<?= $site['error_php'] ?? '' ?>"
                                        data-ip="<?= htmlspecialchars($site['ip'] ?? '') ?>"
                                        data-hostname="<?= htmlspecialchars($site['hostname'] ?? '') ?>"
                                        data-org="<?= htmlspecialchars($site['asn'] ?? '') ?>"
                                        data-bs-toggle="modal" data-bs-target="#otherDataModal"
                                        title="Detalles avanzados">
                                        <i class="bi bi-info-circle text-info"></i>
                                    </button>
                                </td>

                                <!-- col 8: Acciones -->
                                <td class="px-4 text-end">
                                    <div class="btn-group shadow-sm rounded-3">
                                        <button class="btn btn-white btn-sm px-3 analyze-btn"
                                            data-id="<?= $site['id'] ?>"
                                            data-url="<?= htmlspecialchars($site['url']) ?>"
                                            title="Analizar ahora">
                                            <i class="bi bi-play-fill text-primary"></i>
                                        </button>
                                        <button class="btn btn-white btn-sm px-3 delete-btn"
                                            data-id="<?= $site['id'] ?>"
                                            title="Eliminar">
                                            <i class="bi bi-trash text-danger"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="p-5 text-center">
                <i class="bi bi-globe-americas display-1 text-light mb-4 d-block"></i>
                <h5 class="text-muted">No tienes sitios web registrados aún.</h5>
                <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#insertUrlModal">Comienza añadiendo uno</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== MODAL: Confirmar eliminación ===== -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-danger text-white border-0">
        <h5 class="modal-title">Confirmar eliminación</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body p-4">
        <p class="mb-0">¿Estás seguro de que deseas eliminar la URL <strong id="confirm-url-preview"></strong>?</p>
        <p class="text-muted small mt-2">Esta acción no se puede deshacer.</p>
      </div>
      <div class="modal-footer border-0 p-4 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Eliminar permanentemente</button>
      </div>
    </div>
  </div>
</div>

<!-- ===== MODAL: Añadir URL ===== -->
<div class="modal fade" id="insertUrlModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content border-0 shadow-lg">
			<div class="modal-header bg-primary text-white border-0">
				<h5 class="modal-title">Insertar nueva URL</h5>
				<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>
			<div class="modal-body p-4">
				<div class="mb-3">
					<label for="new-url" class="form-label fw-bold small text-muted">URL del sitio</label>
					<input type="text" class="form-control bg-light border-0" id="new-url" placeholder="ej. ejemplo.com">
					<div class="form-text text-muted small">Formato: dominio.com o www.dominio.es (sin https://)</div>
				</div>
			</div>
			<div class="modal-footer border-0 p-4 pt-0">
				<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-primary px-4" id="insertUrlBtn">Guardar y analizar</button>
			</div>
		</div>
	</div>
</div>

<!-- ===== MODAL: Detalles avanzados ===== -->
<div class="modal fade" id="otherDataModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-info text-white border-0">
        <h5 class="modal-title">Detalles avanzados</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body p-4">
        <div class="mb-4">
            <h6 class="fw-bold small text-muted text-uppercase mb-3">Seguridad y DNS</h6>
            <div class="list-group list-group-flush border rounded-3">
              <div class="list-group-item d-flex justify-content-between align-items-center bg-transparent">
                <span class="small">SSL</span>
                <span id="modal-ssl"></span>
              </div>
              <div class="list-group-item d-flex justify-content-between align-items-center bg-transparent">
                <span class="small">DNS Status</span>
                <span id="modal-bad-dns"></span>
              </div>
              <div class="list-group-item d-flex justify-content-between align-items-center bg-transparent">
                <span class="small">Error PHP</span>
                <span id="modal-error-php"></span>
              </div>
            </div>
        </div>
        <div>
            <h6 class="fw-bold small text-muted text-uppercase mb-3">Servidor e IP</h6>
            <div class="list-group list-group-flush border rounded-3">
              <div class="list-group-item d-flex justify-content-between align-items-center bg-transparent">
                <span class="small">IP</span>
                <span id="modal-ip" class="font-monospace small"></span>
              </div>
              <div class="list-group-item d-flex justify-content-between align-items-center bg-transparent">
                <span class="small">Hostname</span>
                <span id="modal-hostname" class="small text-muted text-truncate ms-3" style="max-width:200px"></span>
              </div>
              <div class="list-group-item d-flex justify-content-between align-items-center bg-transparent">
                <span class="small">Organización (ASN)</span>
                <span id="modal-org" class="small text-muted text-truncate ms-3" style="max-width:200px"></span>
              </div>
            </div>
        </div>
      </div>
      <div class="modal-footer border-0 p-4 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-outline-info" id="showDnsHistoryBtn">
            <i class="bi bi-clock-history me-1"></i>Historial DNS
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ===== MODAL: Historial DNS ===== -->
<div class="modal fade" id="dnsHistoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-secondary text-white border-0">
        <h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Historial de cambios DNS</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body p-4">
        <ul class="list-group" id="dns-history-list">
            <li class="list-group-item text-muted text-center">Cargando historial...</li>
        </ul>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<style>
.btn-white {
    background: white;
    border: 1px solid #e2e8f0;
}
.btn-white:hover {
    background: #f8fafc;
}
</style>
