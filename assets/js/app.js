document.addEventListener('DOMContentLoaded', () => {
  setupSheetSelects();
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

  injectToastStack();
  hydrateFlashMessages();
});

function injectToastStack() {
  if (document.querySelector('.toast-stack')) return;
  const stack = document.createElement('div');
  stack.className = 'toast-stack';
  document.body.appendChild(stack);
}

function showToast(message, type = 'info', title = '') {
  const stack = document.querySelector('.toast-stack');
  if (!stack) return;
  const toast = document.createElement('div');
  toast.className = `toast-item ${type}`;

  const content = document.createElement('div');
  const titleEl = document.createElement('div');
  titleEl.className = 'toast-title';
  titleEl.textContent = title || (type === 'success' ? 'OK' : type === 'error' ? 'Errore' : 'Info');
  const msgEl = document.createElement('p');
  msgEl.className = 'toast-msg';
  msgEl.textContent = message;
  content.appendChild(titleEl);
  content.appendChild(msgEl);

  const closeBtn = document.createElement('button');
  closeBtn.className = 'toast-close';
  closeBtn.innerHTML = '&times;';
  closeBtn.addEventListener('click', () => toast.remove());

  toast.appendChild(content);
  toast.appendChild(closeBtn);
  stack.appendChild(toast);

  setTimeout(() => {
    toast.classList.add('fade-out');
    toast.addEventListener('animationend', () => toast.remove());
  }, 4000);
}

function hydrateFlashMessages() {
  const flash = document.querySelector('[data-flash]');
  if (!flash) return;
  const type = flash.dataset.type || 'info';
  const msg = flash.dataset.flash || '';
  const title = flash.dataset.title || '';
  if (msg) {
    showToast(msg, type, title);
  }
}

function setupSheetSelects() {
  const sheet = document.querySelector('[data-sheet-select]');
  const backdrop = document.querySelector('[data-sheet-select-backdrop]');
  const list = document.querySelector('[data-sheet-select-list]');
  const titleEl = document.querySelector('[data-sheet-select-title]');
  const closeBtn = document.querySelector('[data-sheet-select-close]');
  if (!sheet || !backdrop || !list || !titleEl) return;

  const open = () => {
    sheet.classList.add('show');
    backdrop.classList.add('show');
    document.body.classList.add('no-scroll');
  };

  const close = () => {
    sheet.classList.remove('show');
    backdrop.classList.remove('show');
    document.body.classList.remove('no-scroll');
    list.innerHTML = '';
    sheet.dataset.activeSelect = '';
  };

  backdrop.addEventListener('click', close);
  if (closeBtn) closeBtn.addEventListener('click', close);

  const selects = document.querySelectorAll('select.form-select:not([data-offer-select]):not([data-native-select])');
  selects.forEach(select => enhanceSelect(select, { open, close, list, titleEl, sheet }));
}

function enhanceSelect(select, ctx) {
  if (select.dataset.sheetEnhanced === '1') return;

  const trigger = document.createElement('input');
  trigger.type = 'text';
  trigger.readOnly = true;
  trigger.className = select.className;
  trigger.classList.add('sheet-trigger');
  trigger.value = getOptionLabel(select, select.value) || select.getAttribute('data-placeholder') || 'Seleziona';

  select.classList.add('visually-hidden');
  select.dataset.sheetEnhanced = '1';
  select.insertAdjacentElement('afterend', trigger);

  trigger.addEventListener('click', () => openSheetForSelect(select, trigger, ctx));
}

function openSheetForSelect(select, trigger, ctx) {
  const { open, close, list, titleEl, sheet } = ctx;
  titleEl.textContent = select.getAttribute('data-sheet-title') || 'Seleziona';
  list.innerHTML = '';
  sheet.dataset.activeSelect = select.name || select.id || 'select';

  select.querySelectorAll('option').forEach(opt => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
    btn.dataset.value = opt.value;
    btn.textContent = opt.textContent;
    if (opt.value === select.value) {
      btn.classList.add('active');
    }
    btn.addEventListener('click', () => {
      select.value = opt.value;
      trigger.value = opt.textContent;
      // fire change event so auto-submit filters still work
      const evt = new Event('change', { bubbles: true });
      select.dispatchEvent(evt);
      close();
    });
    list.appendChild(btn);
  });

  open();
}

function getOptionLabel(select, value) {
  if (typeof CSS !== 'undefined' && CSS.escape) {
    const opt = select.querySelector(`option[value="${CSS.escape(value)}"]`);
    return opt ? opt.textContent : '';
  }
  const opt = Array.from(select.options).find(o => o.value === value);
  return opt ? opt.textContent : '';
}

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
