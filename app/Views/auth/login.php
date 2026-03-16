<?php
$basePath = dirname($_SERVER['PHP_SELF']);
if ($basePath === '/') {
    $basePath = '';
}
?>

<div class="row justify-content-center align-items-center min-vh-75">
    <div class="col-md-5 col-lg-4">
        <div class="card border-0 shadow-lg p-3">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-5">
                    <div class="bg-primary bg-opacity-10 p-3 rounded-4 d-inline-block mb-4">
                        <i class="bi bi-shield-check text-primary display-5"></i>
                    </div>
                    <h2 class="fw-bold h3">Bienvenido</h2>
                    <p class="text-muted small">Ingresa a NewControl para monitorear tus sitios</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger bg-danger bg-opacity-10 border-0 text-danger small mb-4 py-3 rounded-3" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-4">
                        <label for="username" class="form-label small fw-bold text-muted">Usuario</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-person text-muted"></i></span>
                            <input type="text" name="username" id="username" class="form-control bg-light border-0 py-2" placeholder="ej. admin" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <label for="password" class="form-label small fw-bold text-muted">Contraseña</label>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="bi bi-key text-muted"></i></span>
                            <input type="password" name="password" id="password" class="form-control bg-light border-0 py-2" placeholder="••••••••" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-3 rounded-3 shadow-sm mt-2">
                        Iniciar sesión
                    </button>
                </form>
            </div>
        </div>
        <div class="text-center mt-4">
            <p class="text-muted small">&copy; <?= date('Y') ?> NewControl Dashboard</p>
        </div>
    </div>
</div>

<style>
.min-vh-75 {
    min-height: 75vh;
}
</style>
