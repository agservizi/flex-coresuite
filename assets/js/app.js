document.addEventListener('DOMContentLoaded', () => {
  const filterForms = document.querySelectorAll('[data-auto-submit="true"]');
  filterForms.forEach(form => {
    form.addEventListener('change', () => form.submit());
  });

  const themeToggle = document.querySelector('[data-toggle-theme]');
  if (themeToggle) {
    themeToggle.addEventListener('click', () => {
      const html = document.documentElement;
      const next = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
      html.setAttribute('data-bs-theme', next);
    });
  }

  setupOfferPicker();
});

function setupOfferPicker() {
  const trigger = document.querySelector('[data-offer-picker-trigger]');
  const sheet = document.querySelector('[data-offer-picker]');
  const backdrop = document.querySelector('[data-offer-picker-backdrop]');
  const select = document.querySelector('[data-offer-select]');
  const label = document.querySelector('[data-offer-label]');
  if (!trigger || !sheet || !backdrop || !select || !label) return;

  const closeBtn = sheet.querySelector('[data-offer-picker-close]');
  const options = sheet.querySelectorAll('[data-offer-option]');

  const open = () => {
    sheet.classList.add('show');
    backdrop.classList.add('show');
    document.body.classList.add('no-scroll');
  };

  const close = () => {
    sheet.classList.remove('show');
    backdrop.classList.remove('show');
    document.body.classList.remove('no-scroll');
  };

  trigger.addEventListener('click', open);
  backdrop.addEventListener('click', close);
  if (closeBtn) closeBtn.addEventListener('click', close);

  options.forEach(opt => {
    opt.addEventListener('click', () => {
      const id = opt.dataset.id || '';
      const text = opt.dataset.label || 'Seleziona offerta';
      select.value = id;
      if ('value' in label) {
        label.value = text;
      } else {
        label.textContent = text;
      }
      close();
    });
  });
}
