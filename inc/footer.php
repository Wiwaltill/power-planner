<footer class="app-footer mt-auto py-3">
  <div class="container small text-center text-muted">
    © <?= date('Y') ?> <?= e(APP_NAME) ?> · <a href="<?= e(GITHUB_URL) ?>" target="_blank" rel="noopener noreferrer">GitHub</a>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(app_url('assets/js/ui.js')) ?>"></script>
<?php if (!empty($pageScript)): ?><script src="<?= e(app_url($pageScript)) ?>"></script><?php endif; ?>
</body>
</html>
