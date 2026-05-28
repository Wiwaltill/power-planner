<footer class="app-footer mt-auto py-3">
  <div class="container small text-center text-muted">
    © <?= date('Y') ?> Stromplaner · <a href="https://github.com/Wiwaltill/power-planner" target="_blank" rel="noopener noreferrer">GitHub</a>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($pageScript)): ?>
<script src="<?= htmlspecialchars($pageScript, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
</body>
</html>
