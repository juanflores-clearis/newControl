<?php
// templates/footer.php
?>
</main>

<footer class="mt-auto py-4 bg-white border-top">
    <div class="container text-center">
        <span class="text-muted small">
            &copy; <?= date('Y') ?> <strong>NewControl</strong>. Todos los derechos reservados.
        </span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($pageScript)): ?>
<?= $pageScript ?>
<?php endif; ?>
</body>
</html>
