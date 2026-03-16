


<!-- Botón Añadir -->
<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-success" id="btnAddAccess">
        <i class="fa-solid fa-plus"></i> Añadir Nuevo ACCESO
    </button>
</div>

<div class="card shadow border-0 mb-4">
    <div class="card-body p-4">
        <!-- Filtros -->
        <h4 class="mb-3">Filtros</h4>
        <form method="GET" action="" class="row g-3">
            <input type="hidden" name="route" value="/access">
            
            <div class="col-md-4">
                <label class="form-label">Filtrar por URL</label>
                <input type="text" name="url" class="form-control" placeholder="Buscar URL" value="<?= htmlspecialchars($filters['url']) ?>">
            </div>
            
            <div class="col-md-4">
                <label class="form-label">Filtrar por Tecnología</label>
                <input type="text" name="technology" class="form-control" placeholder="Buscar Tecnología" value="<?= htmlspecialchars($filters['technology']) ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Filtrar por Responsable</label>
                <input type="text" name="responsible" class="form-control" placeholder="Buscar Responsable" value="<?= htmlspecialchars($filters['responsible']) ?>">
            </div>

            <div class="col-md-1 d-flex align-items-end">
                <a href="?route=/access" class="btn btn-secondary w-100">Limpiar Filtros</a>
            </div>
            
            <div class="col-12 d-none">
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark text-white">
                    <tr>
                        <th class="py-3 ps-4">URL</th>
                        <th class="py-3">Tecnología</th>
                        <th class="py-3">Versión</th>
                        <th class="py-3">Responsable</th>
                        <th class="py-3 text-center">Hosting</th>
                        <th class="py-3 text-center">Back</th>
                        <th class="py-3 text-center">Backup</th>
                        <th class="py-3 text-center">FTP</th>
                        <th class="py-3 text-center">Comentario</th>
                        <th class="py-3">Actualizado</th>
                        <th class="py-3 pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-4">No se encontraron registros</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $row): ?>
                            <tr data-id="<?= $row['id'] ?>" data-json='<?= json_encode($row, JSON_HEX_APOS) ?>'>
                                <td class="ps-4">
                                    <a href="<?= htmlspecialchars($row['url']) ?>" target="_blank" class="text-decoration-none text-dark fw-bold">
                                        <?= htmlspecialchars($row['url']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($row['technology'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['technology_version'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['responsible'] ?? '-') ?></td>
                                
                                <!-- Icon buttons -->
                                <td class="text-center">
                                    <button class="btn btn-sm btn-primary" title="Ver Hosting">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-secondary" title="Ver Back">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-primary" title="Backup">
                                        <i class="fa-solid fa-paperclip"></i>
                                    </button>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-primary" title="FTP">
                                        <i class="fa-solid fa-folder"></i>
                                    </button>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-info text-white" title="Comentario" style="background-color: #0dcaf0;">
                                        <i class="fa-solid fa-comment-dots"></i>
                                    </button>
                                </td>

                                <td>
                                    <?php 
                                        $date = new DateTime($row['updated_at']);
                                        echo $date->format('Y-m-d') . '<br><small class="text-muted">' . $date->format('H:i:s') . '</small>';
                                    ?>
                                </td>
                                <td class="pe-4">
                                    <div class="d-flex flex-column gap-1">
                                        <button class="btn btn-warning btn-sm text-dark fw-bold">Permisos</button>
                                        <button class="btn btn-danger btn-sm btn-delete" data-id="<?= $row['id'] ?>">Eliminar</button>
                                        <button class="btn btn-info btn-sm text-white btn-edit" style="background-color: #0dcaf0;">Modificar</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Access -->
<div class="modal fade" id="accessModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="accessModalLabel">Gestión de Acceso</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="accessForm">
            <input type="hidden" name="recordId" id="recordId">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Dominio (URL)</label>
                    <input type="url" name="Dominio" id="url" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tecnología</label>
                    <input type="text" name="Tecnologia" id="technology" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Versión</label>
                    <input type="text" name="version" id="version" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Responsable</label>
                    <input type="text" name="Responsable" id="responsible" class="form-control" required>
                </div>
                
                <!-- Campos adicionales -->
                <!-- Hosting -->
                <div class="col-md-4"><input type="text" name="Acceso_Hosting_Antiguo" id="hostingOld" class="form-control" placeholder="Hosting Antiguo"></div>
                <div class="col-md-4"><input type="text" name="Acceso_Hosting_Desarrollo" id="hostingDev" class="form-control" placeholder="Hosting Desarrollo"></div>
                <div class="col-md-4"><input type="text" name="Acceso_Hosting_Produccion" id="hostingProd" class="form-control" placeholder="Hosting Producción"></div>

                <div class="col-12"><label class="form-label">Comentario</label><textarea name="Comentario" id="comentario" class="form-control"></textarea></div>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-primary" id="btnSaveAccess">Guardar</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const accessModal = new bootstrap.Modal(document.getElementById('accessModal'));
    const form = document.getElementById('accessForm');
    
    // Abrir modal para añadir
    document.getElementById('btnAddAccess').addEventListener('click', () => {
        form.reset();
        document.getElementById('recordId').value = '';
        document.getElementById('accessModalLabel').textContent = 'Añadir Acceso';
        accessModal.show();
    });

    // Abrir modal para editar (delegación de eventos)
    document.querySelector('tbody').addEventListener('click', e => {
        if (e.target.classList.contains('btn-edit') || e.target.closest('.btn-edit')) {
            const tr = e.target.closest('tr');
            const data = JSON.parse(tr.dataset.json);
            
            // Llenar formulario
            document.getElementById('recordId').value = data.id;
            document.getElementById('url').value = data.url;
            document.getElementById('technology').value = data.technology;
            document.getElementById('version').value = data.technology_version;
            document.getElementById('responsible').value = data.responsible;
            // ... Mapear el resto de campos si existen en 'data' ...
            
            document.getElementById('accessModalLabel').textContent = 'Editar Acceso';
            accessModal.show();
        }

        if (e.target.classList.contains('btn-delete') || e.target.closest('.btn-delete')) {
            if(!confirm('¿Estás seguro de eliminar este registro?')) return;
            const btn = e.target.closest('.btn-delete');
            const id = btn.dataset.id;
            
            fetch('?route=/access/delete', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=' + id
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) location.reload();
                else alert('Error: ' + data.message);
            });
        }
    });

    // Guardar
    document.getElementById('btnSaveAccess').addEventListener('click', () => {
        const formData = new FormData(form);
        
        fetch('?route=/access/add', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                accessModal.hide();
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => alert('Error de red: ' + err));
    });
});
</script>


