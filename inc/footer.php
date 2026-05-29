<footer class="app-footer mt-auto py-3">
  <div class="container small text-center text-muted d-flex flex-column flex-md-row gap-2 justify-content-center align-items-center">
    <span>© <?= date('Y') ?> <?= e(APP_NAME) ?></span>
    <span class="d-none d-md-inline">·</span>
    <a class="d-inline-flex align-items-center gap-1" href="<?= e(APP_GITHUB_URL) ?>" target="_blank" rel="noopener noreferrer" aria-label="GitHub Repository">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
        <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82A7.65 7.65 0 0 1 8 3.87c.68 0 1.36.09 2 .26 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0 0 16 8c0-4.42-3.58-8-8-8z"/>
      </svg>
      GitHub
    </a>
    <span class="d-none d-md-inline">·</span>
    <span>Version <?= e(APP_VERSION) ?></span>
    <?php if (($user ?? null) && (($user['role'] ?? '') === 'admin')): ?>
      <?php $availableUpdate = available_update_info(); ?>
      <?php if ($availableUpdate): ?>
        <span class="d-none d-md-inline">·</span>
        <a class="badge text-bg-warning text-decoration-none" href="<?= e(app_url('settings#updater')) ?>" title="Zur Updater-Seite">
          <i class="bi bi-exclamation-triangle me-1"></i>Update verfügbar: <?= e($availableUpdate['tag_name']) ?>
        </a>
      <?php else: ?>
        <span class="d-none d-md-inline">·</span>
        <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Aktuell</span>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(app_url('assets/js/ui.js')) ?>"></script>
<?php if (!empty($pageScript)): ?><script src="<?= e(app_url($pageScript)) ?>"></script><?php endif; ?>
</body>
</html>
