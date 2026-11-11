const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
const sidebar = document.getElementById('sidebar');
function closeSidebar() {
  const wasOpen = document.body.classList.contains('sidebar-open');
  document.body.classList.remove('sidebar-open');
  sidebarToggle?.setAttribute('aria-expanded', 'false');
  if (wasOpen && sidebar?.contains(document.activeElement)) sidebarToggle?.focus();
}
sidebarToggle?.addEventListener('click', () => {
  const open = document.body.classList.toggle('sidebar-open');
  sidebarToggle.setAttribute('aria-expanded', String(open));
  if (open) sidebar?.querySelector('[data-sidebar-close]')?.focus();
});
document.querySelectorAll('[data-sidebar-close]').forEach(button => button.addEventListener('click', closeSidebar));
const mobileLayout = window.matchMedia('(max-width: 760px)');
mobileLayout.addEventListener('change', () => {
  if (!mobileLayout.matches) closeSidebar();
});
document.querySelectorAll('[data-open]').forEach(button => button.addEventListener('click', () => {
  document.getElementById(button.dataset.open)?.showModal();
}));
document.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
document.querySelectorAll('dialog').forEach(dialog => dialog.addEventListener('click', event => {
  if (event.target !== dialog) return;
  const rect = dialog.getBoundingClientRect();
  if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
}));
document.querySelectorAll('[data-dismiss]').forEach(button => button.addEventListener('click', () => button.closest('.alert').remove()));
document.addEventListener('keydown', event => {
  if (event.key === 'Escape') closeSidebar();
  if (event.key === '/' && !['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement.tagName) && !document.querySelector('dialog[open]')) {
    event.preventDefault(); document.querySelector('.global-search input[type=search]')?.focus();
  }
});
let pendingForm;
const confirmation = document.getElementById('confirm-dialog');
document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => {
  if (form.dataset.confirmed) return;
  event.preventDefault(); pendingForm = form;
  document.getElementById('confirm-message').textContent = form.dataset.confirm;
  confirmation.showModal();
}));
document.getElementById('confirm-submit')?.addEventListener('click', () => {
  if (!pendingForm) return;
  pendingForm.dataset.confirmed = 'true';
  confirmation.close(); pendingForm.requestSubmit();
});
document.querySelectorAll('form[method=post]').forEach(form => form.addEventListener('submit', event => {
  if (event.defaultPrevented) return;
  const button = event.submitter;
  if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); }
}));
const requestedDialog = new URLSearchParams(location.search).get('open');
if (requestedDialog) document.getElementById(requestedDialog)?.showModal?.();
const oldInput = document.getElementById('old-input');
if (oldInput) {
  const values = JSON.parse(oldInput.dataset.values);
  let form = Array.from(document.querySelectorAll('.operation-form')).find(form => form.elements.action.value === values.action);
  if (form) {
    const error = document.querySelector('main > .alert.error');
    if (error) {
      const inlineError = document.createElement('div');
      inlineError.className = 'alert error dialog-error';
      inlineError.setAttribute('role', 'alert');
      inlineError.textContent = error.querySelector('span')?.textContent || error.textContent;
      form.before(inlineError);
    }
    for (const [name, value] of Object.entries(values)) {
      if (['csrf', 'action', 'return'].includes(name)) continue;
      const field = form.elements.namedItem(name);
      if (field && typeof value === 'string') field.value = value;
    }
    if (values.original) {
      form.elements.no_resi.value = values.original;
      form.elements.no_resi.readOnly = true;
      form.closest('dialog').querySelector('h2').textContent = 'Edit informasi paket';
    }
    form.closest('dialog').showModal();
  }
}
