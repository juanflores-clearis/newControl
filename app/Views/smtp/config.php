<h1 class="mb-4"><i class="bi bi-envelope-at"></i> Configuración SMTP</h1>

<?php if (isset($message) && $message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-5">
    <div class="card-body">
        <form method="post">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="smtp_server" class="form-label">Servidor SMTP</label>
                    <input type="text" id="smtp_server" name="smtp_server" class="form-control" value="<?php echo htmlspecialchars($config['smtp_server'] ?? ''); ?>" required>
                    <div class="form-text">Ejemplo: smtp.gmail.com</div>
                </div>
                <div class="col-md-3">
                    <label for="smtp_port" class="form-label">Puerto SMTP</label>
                    <input type="number" id="smtp_port" name="smtp_port" class="form-control" value="<?php echo htmlspecialchars($config['smtp_port'] ?? '587'); ?>" required min="1" max="65535">
                    <div class="form-text">Común: 587 (TLS) o 465 (SSL)</div>
                </div>
                <div class="col-md-3">
                    <label for="smtp_encryption" class="form-label">Cifrado</label>
                    <input type="text" id="smtp_encryption" name="smtp_encryption" class="form-control" value="<?php echo htmlspecialchars($config['smtp_encryption'] ?? 'tls'); ?>" required>
                    <div class="form-text">Usar 'tls' o 'ssl'</div>
                </div>
                <div class="col-md-6">
                    <label for="smtp_user" class="form-label">Usuario SMTP</label>
                    <input type="text" id="smtp_user" name="smtp_user" class="form-control" value="<?php echo htmlspecialchars($config['smtp_user'] ?? ''); ?>" required>
                    <div class="form-text">Tu dirección de email completa</div>
                </div>
                <div class="col-md-6">
                    <label for="smtp_password" class="form-label">Contraseña SMTP</label>
                    <input type="password" id="smtp_password" name="smtp_password" class="form-control" value="<?php echo htmlspecialchars($config['smtp_password'] ?? ''); ?>" required>
                    <div class="form-text">Para Gmail, usa una contraseña de aplicación</div>
                </div>
                <div class="col-md-6">
                    <label for="mail_from_name" class="form-label">Nombre del Remitente</label>
                    <input type="text" id="mail_from_name" name="mail_from_name" class="form-control" value="<?php echo htmlspecialchars($config['mail_from_name'] ?? ''); ?>" required>
                    <div class="form-text">Nombre que aparecerá como remitente</div>
                </div>
                <div class="col-md-6">
                    <label for="mail_from_email" class="form-label">Email del Remitente</label>
                    <input type="email" id="mail_from_email" name="mail_from_email" class="form-control" value="<?php echo htmlspecialchars($config['mail_from_email'] ?? ''); ?>" required>
                    <div class="form-text">Dirección desde la que se enviarán los emails</div>
                </div>
                <div class="col-md-8">
                    <label for="mail_subject" class="form-label">Asunto del Correo</label>
                    <input type="text" id="mail_subject" name="mail_subject" class="form-control" value="<?php echo htmlspecialchars($config['mail_subject'] ?? ''); ?>" required>
                    <div class="form-text">Puedes usar <code>{date}</code> para insertar la fecha actual automáticamente.</div>
                </div>
                <div class="col-md-4">
                    <label for="recipient_email" class="form-label">Destinatarios</label>
                    <input type="text" id="recipient_email" name="recipient_email" class="form-control" value="<?php echo htmlspecialchars($config['recipient_email'] ?? ''); ?>" required>
                    <div class="form-text">Lista de emails separados por comas</div>
                </div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button type="submit" name="action" value="update" class="btn btn-success"><i class="bi bi-save"></i> Guardar Cambios</button>
                <button type="submit" name="action" value="test" class="btn btn-secondary"><i class="bi bi-envelope-check"></i> Probar Configuración</button>
            </div>
        </form>
    </div>
</div>

