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

  registerPush();
  setupNotifications();
});

const NOTIFICATION_STORAGE_KEY = 'flex_notifications_v1';
const NOTIFICATION_SEEN_KEY = 'flex_notifications_seen_at';

function injectToastStack() {
  if (document.querySelector('.toast-stack')) return;
  const stack = document.createElement('div');
  stack.className = 'toast-stack';
  document.body.appendChild(stack);
}

function showToast(message, type = 'info', title = '', persist = true) {
  const stack = document.querySelector('.toast-stack');
  if (!stack) return;
  const effectiveTitle = title || (type === 'success' ? 'OK' : type === 'error' ? 'Errore' : 'Info');
  const toast = document.createElement('div');
  toast.className = `toast-item ${type}`;

  const content = document.createElement('div');
  const titleEl = document.createElement('div');
  titleEl.className = 'toast-title';
  titleEl.textContent = effectiveTitle;
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

  if (persist) {
    addNotificationEntry({
      title: effectiveTitle,
      message,
      type,
      createdAt: new Date().toISOString(),
    });
  }

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

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = atob(base64);
  const outputArray = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

async function registerPush() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window) || Notification.permission === 'denied') {
    return;
  }

  const vapidMeta = document.querySelector('meta[name="vapid-public-key"]');
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const publicKey = vapidMeta ? vapidMeta.content : '';
  const csrfToken = csrfMeta ? csrfMeta.content : '';
  if (!publicKey || !csrfToken) return;

  try {
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return;

    const registration = await navigator.serviceWorker.register('/public/sw.js');
    let subscription = await registration.pushManager.getSubscription();
    if (!subscription) {
      subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(publicKey),
      });
    }

    await fetch('/push/subscribe.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
      },
      body: JSON.stringify(subscription),
    });
  } catch (err) {
    console.error('Push registration failed', err);
  }
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

function readStoredNotifications() {
  try {
    const raw = localStorage.getItem(NOTIFICATION_STORAGE_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch (err) {
    return [];
  }
}

function persistNotifications(list) {
  try {
    localStorage.setItem(NOTIFICATION_STORAGE_KEY, JSON.stringify(list));
  } catch (err) {
    // ignore write failures (private mode, quota, etc.)
  }
}

function addNotificationEntry(entry) {
  if (!entry) return;
  const normalized = {
    id: entry.id || (typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(16).slice(2)}`),
    title: entry.title || 'Info',
    message: entry.message || '',
    type: entry.type || 'info',
    createdAt: entry.createdAt || new Date().toISOString(),
  };

  const existing = readStoredNotifications();
  const updated = [normalized, ...existing].slice(0, 60);
  persistNotifications(updated);
  window.dispatchEvent(new CustomEvent('notifications:updated'));
}

function getUnreadNotificationCount(notifications = readStoredNotifications()) {
  const rawSeen = (() => {
    try {
      return localStorage.getItem(NOTIFICATION_SEEN_KEY);
    } catch (err) {
      return '';
    }
  })();
  const lastSeen = Number(rawSeen) || 0;
  return notifications.filter(n => {
    const created = new Date(n.createdAt || '').getTime();
    return !Number.isNaN(created) && created > lastSeen;
  }).length;
}

function markNotificationsSeen() {
  try {
    localStorage.setItem(NOTIFICATION_SEEN_KEY, `${Date.now()}`);
  } catch (err) {
    // ignore write failures
  }
  window.dispatchEvent(new CustomEvent('notifications:updated'));
}

function clearNotifications() {
  persistNotifications([]);
  window.dispatchEvent(new CustomEvent('notifications:updated'));
}

function formatNotificationTime(isoString) {
  const date = new Date(isoString);
  if (Number.isNaN(date.getTime())) return '';
  const now = Date.now();
  const diff = now - date.getTime();

  const minute = 60 * 1000;
  const hour = 60 * minute;
  const day = 24 * hour;

  if (diff < minute) return 'Ora';
  if (diff < hour) return `${Math.floor(diff / minute)} min fa`;
  if (diff < day) return `${Math.floor(diff / hour)} h fa`;

  return date.toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function buildNotificationNode(note) {
  const wrapper = document.createElement('div');
  wrapper.className = 'notification-item';

  const header = document.createElement('div');
  header.className = 'd-flex justify-content-between align-items-center mb-1';

  const titleEl = document.createElement('div');
  titleEl.className = 'title';
  titleEl.textContent = note.title || 'Info';

  const badge = document.createElement('span');
  const badgeClasses = {
    success: 'bg-success-subtle text-success',
    error: 'bg-danger-subtle text-danger',
    info: 'bg-primary-subtle text-primary',
  };
  badge.className = `badge rounded-pill ${badgeClasses[note.type] || badgeClasses.info}`;
  badge.textContent = note.type === 'success' ? 'Successo' : note.type === 'error' ? 'Errore' : 'Info';

  header.appendChild(titleEl);
  header.appendChild(badge);

  const body = document.createElement('p');
  body.className = 'body mb-1';
  body.textContent = note.message || '';

  const meta = document.createElement('div');
  meta.className = 'meta';
  meta.textContent = formatNotificationTime(note.createdAt);

  wrapper.appendChild(header);
  wrapper.appendChild(body);
  wrapper.appendChild(meta);

  return wrapper;
}

function setupNotifications() {
  const trigger = document.querySelector('[data-notification-trigger]');
  const sheet = document.querySelector('[data-notification-sheet]');
  const backdrop = document.querySelector('[data-notification-backdrop]');
  const list = document.querySelector('[data-notification-list]');
  const empty = document.querySelector('[data-notification-empty]');
  const markReadBtn = document.querySelector('[data-notification-mark-read]');
  const clearBtn = document.querySelector('[data-notification-clear]');
  const badge = document.querySelector('[data-notification-badge]');
  if (!trigger || !sheet || !backdrop || !list || !empty || !badge) return;

  const render = () => {
    const notifications = readStoredNotifications();
    list.innerHTML = '';

    if (!notifications.length) {
      empty.classList.remove('d-none');
      return notifications;
    }

    empty.classList.add('d-none');
    notifications.forEach(note => list.appendChild(buildNotificationNode(note)));
    return notifications;
  };

  const updateBadge = () => {
    const notifications = readStoredNotifications();
    const unread = getUnreadNotificationCount(notifications);
    if (unread > 0) {
      badge.classList.add('show');
      badge.textContent = unread > 9 ? '9+' : `${unread}`;
    } else {
      badge.classList.remove('show');
      badge.textContent = '';
    }
  };

  const open = () => {
    render();
    sheet.classList.add('show');
    backdrop.classList.add('show');
    document.body.classList.add('no-scroll');
    markNotificationsSeen();
    updateBadge();
  };

  const close = () => {
    sheet.classList.remove('show');
    backdrop.classList.remove('show');
    document.body.classList.remove('no-scroll');
  };

  trigger.addEventListener('click', open);
  backdrop.addEventListener('click', close);

  if (markReadBtn) {
    markReadBtn.addEventListener('click', () => {
      markNotificationsSeen();
      updateBadge();
    });
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      clearNotifications();
      render();
      updateBadge();
    });
  }

  window.addEventListener('notifications:updated', () => {
    render();
    updateBadge();
  });

  render();
  updateBadge();
}
