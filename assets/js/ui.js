(function () {
  function ensureToastContainer() {
    let el = document.getElementById('appToastContainer');
    if (!el) {
      el = document.createElement('div');
      el.id = 'appToastContainer';
      el.className = 'toast-container position-fixed top-0 end-0 p-3';
      el.style.zIndex = '1080';
      document.body.appendChild(el);
    }
    return el;
  }

  function notice(message, type = 'info', title = 'Hinweis') {
    const container = ensureToastContainer();
    const toastEl = document.createElement('div');
    const headerClass = type === 'danger' ? 'text-bg-danger' : type === 'success' ? 'text-bg-success' : type === 'warning' ? 'text-bg-warning' : 'text-bg-primary';
    toastEl.className = 'toast shadow-sm border-0';
    toastEl.role = 'alert';
    toastEl.ariaLive = 'assertive';
    toastEl.ariaAtomic = 'true';
    toastEl.innerHTML = `
      <div class="toast-header ${headerClass}">
        <strong class="me-auto">${escapeHtml(title)}</strong>
        <button type="button" class="btn-close ${type === 'warning' ? '' : 'btn-close-white'}" data-bs-dismiss="toast" aria-label="Schließen"></button>
      </div>
      <div class="toast-body">${escapeHtml(message)}</div>`;
    container.appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl, { delay: 4500 });
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    toast.show();
  }

  function confirmDialog(message, options = {}) {
    return new Promise(resolve => {
      let modalEl = document.getElementById('appConfirmModal');
      if (!modalEl) {
        modalEl = document.createElement('div');
        modalEl.id = 'appConfirmModal';
        modalEl.className = 'modal fade';
        modalEl.tabIndex = -1;
        modalEl.innerHTML = `
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="appConfirmTitle">Bestätigung</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
              </div>
              <div class="modal-body"><p id="appConfirmMessage" class="mb-0"></p></div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="appConfirmCancel">Abbrechen</button>
                <button type="button" class="btn btn-danger" id="appConfirmOk">Bestätigen</button>
              </div>
            </div>
          </div>`;
        document.body.appendChild(modalEl);
      }

      modalEl.querySelector('#appConfirmTitle').textContent = options.title || 'Bestätigung';
      modalEl.querySelector('#appConfirmMessage').textContent = message;
      modalEl.querySelector('#appConfirmOk').textContent = options.confirmText || 'Bestätigen';
      modalEl.querySelector('#appConfirmCancel').textContent = options.cancelText || 'Abbrechen';
      modalEl.querySelector('#appConfirmOk').className = 'btn ' + (options.confirmClass || 'btn-danger');

      const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      const ok = modalEl.querySelector('#appConfirmOk');
      let answered = false;
      const cleanup = () => {
        ok.removeEventListener('click', onOk);
        modalEl.removeEventListener('hidden.bs.modal', onHidden);
      };
      const onOk = () => {
        answered = true;
        cleanup();
        modal.hide();
        resolve(true);
      };
      const onHidden = () => {
        cleanup();
        if (!answered) resolve(false);
      };
      ok.addEventListener('click', onOk);
      modalEl.addEventListener('hidden.bs.modal', onHidden);
      modal.show();
    });
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  }

  document.addEventListener('submit', async event => {
    const form = event.target.closest('form[data-confirm]');
    if (!form || form.dataset.confirmed === '1') return;
    event.preventDefault();
    const ok = await confirmDialog(form.dataset.confirm, {
      title: form.dataset.confirmTitle || 'Bestätigung',
      confirmText: form.dataset.confirmButton || 'Bestätigen',
      confirmClass: form.dataset.confirmClass || 'btn-danger'
    });
    if (ok) {
      form.dataset.confirmed = '1';
      form.submit();
    }
  });

  window.AppUI = {
    notice,
    success: msg => notice(msg, 'success', 'Erfolg'),
    error: msg => notice(msg, 'danger', 'Fehler'),
    warning: msg => notice(msg, 'warning', 'Achtung'),
    info: msg => notice(msg, 'info', 'Hinweis'),
    confirm: confirmDialog
  };
})();
