document.addEventListener('DOMContentLoaded', () => {
  // Svuota cache automaticamente per Capacitor
  if (window.Capacitor && Capacitor.isNativePlatform()) {
    clearCacheForCapacitor();
  }

  const savedTheme = localStorage.getItem('theme');
  if (savedTheme) {
    document.documentElement.setAttribute('data-bs-theme', savedTheme);
  }

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
      try { localStorage.setItem('theme', next); } catch (err) { /* ignore */ }
    });
  }

  setupOfferPicker();
  setupManagerPicker();
  setupInstallerPicker();
  setupSavedFilters();
  setupDocPreviews();
  setupFormValidation();

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
  if (window.Capacitor && Capacitor.isNativePlatform()) {
    // Use Capacitor push notifications
    const { PushNotifications } = Capacitor.Plugins;
    PushNotifications.requestPermissions().then(result => {
      if (result.receive === 'granted') {
        PushNotifications.register();
      }
    });

    PushNotifications.addListener('registration', token => {
      console.log('Push registration success, token: ' + token.value);
      // Send token to server
      const csrfMeta = document.querySelector('meta[name="csrf-token"]');
      const csrfToken = csrfMeta ? csrfMeta.content : '';
      if (csrfToken) {
        fetch('/push/subscribe.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken,
          },
          body: JSON.stringify({ token: token.value, platform: Capacitor.getPlatform() }),
        });
      }
    });

    PushNotifications.addListener('pushNotificationReceived', notification => {
      console.log('Push received: ', notification);
    });

    PushNotifications.addListener('pushNotificationActionPerformed', notification => {
      console.log('Push action performed: ', notification);
    });
  } else {
    // Fallback to web push
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || Notification.permission === 'denied') {
      return;
    }

    const vapidMeta = document.querySelector('meta[name="vapid-public-key"]');
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const assetVersionMeta = document.querySelector('meta[name="asset-version"]');
    const publicKey = vapidMeta ? vapidMeta.content : '';
    const csrfToken = csrfMeta ? csrfMeta.content : '';
    const swVersion = assetVersionMeta ? `?v=${encodeURIComponent(assetVersionMeta.content)}` : '';
    if (!publicKey || !csrfToken) return;

    try {
      const permission = await Notification.requestPermission();
      if (permission !== 'granted') return;

      const registration = await navigator.serviceWorker.register(`/public/sw.js${swVersion}`);
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
}

function setupOfferPicker() {
  console.log('setupOfferPicker called');
  const trigger = document.querySelector('[data-offer-picker-trigger]');
  const sheet = document.querySelector('[data-offer-picker]');
  const backdrop = document.querySelector('[data-offer-picker-backdrop]');
  const select = document.querySelector('[data-offer-select]');
  const label = document.querySelector('[data-offer-label]');
  if (!trigger || !sheet || !select || !label) {
    console.log('Missing elements for offer picker');
    return;
  }
  console.log('Offer picker elements found');

  // Reset to default
  select.value = '';
  if ('value' in label) {
    label.value = 'Seleziona offerta';
  } else {
    label.textContent = 'Seleziona offerta';
  }

  const closeBtn = sheet.querySelector('[data-offer-picker-close]');
  const options = sheet.querySelectorAll('[data-offer-option]');

  const open = () => {
    sheet.classList.add('show');
    if (backdrop) backdrop.classList.add('show');
    document.body.classList.add('no-scroll');
  };

  const close = () => {
    sheet.classList.remove('show');
    if (backdrop) backdrop.classList.remove('show');
    document.body.classList.remove('no-scroll');
  };

  trigger.addEventListener('click', open);
  if (backdrop) backdrop.addEventListener('click', close);
  if (closeBtn) closeBtn.addEventListener('click', close);

  options.forEach(opt => {
    opt.addEventListener('click', () => {
      const id = opt.dataset.id || '';
      const text = opt.dataset.label || 'Seleziona offerta';
      select.value = id;
      console.log('Offer selected, id:', id, 'select.value now:', select.value);
      if ('value' in label) {
        label.value = text;
      } else {
        label.textContent = text;
      }
      close();
    });
  });
}

function setupManagerPicker() {
  const trigger = document.querySelector('[data-manager-picker-trigger]');
  const sheet = document.querySelector('[data-manager-picker]');
  const backdrop = document.querySelector('[data-manager-picker-backdrop]');
  const select = document.querySelector('[data-manager-select]');
  const label = document.querySelector('[data-manager-label]');
  if (!trigger || !sheet || !select || !label) return;

  const closeBtn = sheet.querySelector('[data-manager-picker-close]');
  const options = sheet.querySelectorAll('[data-manager-option]');

  const open = () => {
    sheet.classList.add('show');
    if (backdrop) backdrop.classList.add('show');
    document.body.classList.add('no-scroll');
  };

  const close = () => {
    sheet.classList.remove('show');
    if (backdrop) backdrop.classList.remove('show');
    document.body.classList.remove('no-scroll');
  };

  trigger.addEventListener('click', open);
  if (backdrop) backdrop.addEventListener('click', close);
  if (closeBtn) closeBtn.addEventListener('click', close);

  options.forEach(opt => {
    opt.addEventListener('click', () => {
      const id = opt.dataset.id || '';
      const text = opt.dataset.label || 'Seleziona gestore';
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

function setupInstallerPicker() {
  const trigger = document.querySelector('[data-installer-picker-trigger]');
  const sheet = document.querySelector('[data-installer-picker]');
  const backdrop = document.querySelector('[data-installer-picker-backdrop]');
  const select = document.querySelector('[data-installer-select]');
  const label = document.querySelector('[data-installer-label]');
  if (!trigger || !sheet || !select || !label) return;

  const closeBtn = sheet.querySelector('[data-installer-picker-close]');
  const options = sheet.querySelectorAll('[data-installer-option]');

  const open = () => {
    sheet.classList.add('show');
    if (backdrop) backdrop.classList.add('show');
    document.body.classList.add('no-scroll');
  };

  const close = () => {
    sheet.classList.remove('show');
    if (backdrop) backdrop.classList.remove('show');
    document.body.classList.remove('no-scroll');
  };

  trigger.addEventListener('click', open);
  if (backdrop) backdrop.addEventListener('click', close);
  if (closeBtn) closeBtn.addEventListener('click', close);

  options.forEach(opt => {
    opt.addEventListener('click', () => {
      const id = opt.dataset.id || '';
      const text = opt.dataset.label || 'Seleziona installer';
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

function getCsrfToken() {
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  return csrfMeta ? csrfMeta.content : '';
}

async function fetchNotifications(limit = 50, offset = 0) {
  const res = await fetch(`/notifications.php?limit=${encodeURIComponent(limit)}&offset=${encodeURIComponent(offset)}`, {
    credentials: 'same-origin',
  });
  if (!res.ok) {
    throw new Error('Impossibile caricare le notifiche');
  }
  return res.json();
}

async function addNotificationEntry(entry) {
  const csrf = getCsrfToken();
  if (!csrf || !entry) return;
  try {
    await fetch('/notifications.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf,
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        action: 'add',
        title: entry.title || 'Info',
        body: entry.message || '',
        type: entry.type || 'info',
      }),
    });
    window.dispatchEvent(new CustomEvent('notifications:updated'));
  } catch (err) {
    // ignore network issues silently
  }
}

async function markNotificationsSeen() {
  const csrf = getCsrfToken();
  if (!csrf) return;
  try {
    await fetch('/notifications.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf,
      },
      credentials: 'same-origin',
      body: JSON.stringify({ action: 'mark_read' }),
    });
  } catch (err) {
    // ignore
  }
}

async function clearNotifications() {
  const csrf = getCsrfToken();
  if (!csrf) return;
  try {
    await fetch('/notifications.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf,
      },
      credentials: 'same-origin',
      body: JSON.stringify({ action: 'clear' }),
    });
    window.dispatchEvent(new CustomEvent('notifications:updated'));
  } catch (err) {
    // ignore
  }
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
  body.textContent = note.body || note.message || '';

  const meta = document.createElement('div');
  meta.className = 'meta';
  meta.textContent = formatNotificationTime(note.created_at || note.createdAt);

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

  let cache = { notifications: [], unread: 0 };

  const render = () => {
    list.innerHTML = '';
    if (!cache.notifications.length) {
      empty.classList.remove('d-none');
      return;
    }
    empty.classList.add('d-none');
    cache.notifications.forEach(note => list.appendChild(buildNotificationNode(note)));
  };

  const updateBadge = () => {
    if (cache.unread > 0) {
      badge.classList.add('show');
      badge.textContent = cache.unread > 9 ? '9+' : `${cache.unread}`;
    } else {
      badge.classList.remove('show');
      badge.textContent = '';
    }
  };

  const refresh = async () => {
    try {
      const data = await fetchNotifications();
      cache = {
        notifications: data.notifications || [],
        unread: typeof data.unread === 'number' ? data.unread : 0,
      };
      render();
      updateBadge();
    } catch (err) {
      // ignore load errors to avoid blocking UI
    }
  };

  const open = async () => {
    await refresh();
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

  if (markReadBtn) {
    markReadBtn.addEventListener('click', async () => {
      await markNotificationsSeen();
      await refresh();
    });
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', async () => {
      await clearNotifications();
      cache = { notifications: [], unread: 0 };
      render();
      updateBadge();
    });
  }

  window.addEventListener('notifications:updated', () => {
    refresh();
  });

  refresh();
  updateBadge();
}

function setupDocPreviews() {
  const inputs = document.querySelectorAll('[data-doc-preview]');
  inputs.forEach(input => {
    const list = document.querySelector('[data-doc-preview-list]');
    if (!list) return;
    input.addEventListener('change', () => {
      list.innerHTML = '';
      const files = Array.from(input.files || []);
      files.forEach(file => {
        const item = document.createElement('div');
        item.className = 'doc-preview-item';
        const name = document.createElement('div');
        name.className = 'doc-name';
        name.textContent = file.name;
        item.appendChild(name);

        if (file.type.startsWith('image/')) {
          const img = document.createElement('img');
          img.className = 'doc-thumb';
          img.alt = file.name;
          img.src = URL.createObjectURL(file);
          item.appendChild(img);
        }
        list.appendChild(item);
      });
    });
  });
}

function setupSavedFilters() {
  const forms = document.querySelectorAll('form[data-auto-save]');
  forms.forEach(form => {
    const key = form.dataset.autoSave || '';
    if (!key) return;

    // hydrate saved values if fields are empty and no query params override
    try {
      const stored = localStorage.getItem(`filters:${key}`);
      if (stored) {
        const data = JSON.parse(stored);
        if (data && typeof data === 'object') {
          Object.entries(data).forEach(([name, value]) => {
            const field = form.elements.namedItem(name);
            if (!field || (field.value && field.value !== '')) return;
            if (field instanceof RadioNodeList) {
              const el = Array.from(field).find(f => f.value === value);
              if (el) el.checked = true;
            } else {
              field.value = value;
            }
          });
        }
      }
    } catch (err) {
      // ignore
    }

    form.addEventListener('change', () => {
      const payload = {};
      Array.from(form.elements).forEach(el => {
        if (!el.name) return;
        if (el.type === 'checkbox' || el.type === 'radio') {
          if (!el.checked) return;
          payload[el.name] = el.value;
        } else {
          payload[el.name] = el.value;
        }
      });
      try {
        localStorage.setItem(`filters:${key}`, JSON.stringify(payload));
      } catch (err) {
        // ignore
      }
    });
  });
}

function setupFormValidation() {
  console.log('setupFormValidation called');
  const forms = document.querySelectorAll('form.needs-validation');
  console.log('Found forms:', forms.length);
  forms.forEach(form => {
    form.addEventListener('submit', (e) => {
      const offerSelect = form.querySelector('[data-offer-select]');
      const firstName = form.querySelector('input[name="first_name"]');
      const lastName = form.querySelector('input[name="last_name"]');
      console.log('Form submit, offerSelect value:', offerSelect ? offerSelect.value : 'no select');
      if (offerSelect && !offerSelect.value) {
        e.preventDefault();
        alert('Seleziona un\'offerta valida.');
        showToast('Seleziona un\'offerta valida.', 'error');
        return false;
      }
      if (firstName && !firstName.value.trim()) {
        e.preventDefault();
        alert('Inserisci il nome.');
        showToast('Inserisci il nome.', 'error');
        return false;
      }
      if (lastName && !lastName.value.trim()) {
        e.preventDefault();
        alert('Inserisci il cognome.');
        showToast('Inserisci il cognome.', 'error');
        return false;
      }
      // Notes is optional
    });
  });
}

// Svuota cache automaticamente per Capacitor
async function clearCacheForCapacitor() {
  try {
    // Svuota cache del service worker
    if ('serviceWorker' in navigator) {
      const registrations = await navigator.serviceWorker.getRegistrations();
      for (const registration of registrations) {
        await registration.unregister();
      }
    }

    // Svuota cache del browser
    if ('caches' in window) {
      const cacheNames = await caches.keys();
      await Promise.all(
        cacheNames.map(cacheName => caches.delete(cacheName))
      );
    }

    // Forza reload delle risorse
    if (window.location) {
      // Piccolo delay per permettere la pulizia
      setTimeout(() => {
        window.location.reload(true);
      }, 100);
    }
  } catch (error) {
    console.warn('Errore durante la pulizia della cache:', error);
  }
}

// Intercetta richieste fetch per aggiungere headers browser-like
const originalFetch = window.fetch;
window.fetch = function(...args) {
  const [resource, config] = args;

  // Se è una richiesta alle pagine auth, aggiungi headers per simulare browser
  if (typeof resource === 'string' && resource.includes('/auth/')) {
    console.log('Intercepting auth request:', resource);
    const newConfig = {
      ...config,
      headers: {
        ...config?.headers,
        'User-Agent': 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language': 'it-IT,it;q=0.9,en-US;q=0.8,en;q=0.7',
        'Accept-Encoding': 'gzip, deflate, br',
        'Cache-Control': 'no-cache',
        'Pragma': 'no-cache'
      }
    };
    return originalFetch(resource, newConfig);
  }

  return originalFetch(...args);
};

// Funzionalità Capacitor avanzate
async function initializeCapacitorFeatures() {
  if (!window.Capacitor || !Capacitor.isNativePlatform()) return;

  try {
    // Inizializza splash screen
    await setupSplashScreen();

    // Inizializza status bar
    await setupStatusBar();

    // Richiedi permessi essenziali
    await requestEssentialPermissions();

    // Setup fotocamera
    setupCameraIntegration();

    // Setup geolocalizzazione
    setupGeolocation();

    // Setup condivisione
    setupShareIntegration();

    // Setup deep linking
    setupDeepLinking();

    // Setup gestione app
    setupAppManagement();

    console.log('Capacitor features initialized');
  } catch (error) {
    console.warn('Error initializing Capacitor features:', error);
  }
}

async function setupSplashScreen() {
  const { SplashScreen } = Capacitor.Plugins;
  if (SplashScreen) {
    await SplashScreen.show({
      showDuration: 2000,
      autoHide: true
    });
  }
}

async function setupStatusBar() {
  const { StatusBar } = Capacitor.Plugins;
  if (StatusBar) {
    await StatusBar.setStyle({ style: 'DARK' });
    await StatusBar.setBackgroundColor({ color: '#667eea' });
  }
}

async function requestEssentialPermissions() {
  const { Geolocation } = Capacitor.Plugins;
  const { Camera } = Capacitor.Plugins;

  try {
    // Richiedi permesso geolocalizzazione
    if (Geolocation) {
      await Geolocation.requestPermissions();
    }

    // Richiedi permesso fotocamera
    if (Camera) {
      await Camera.requestPermissions();
    }
  } catch (error) {
    console.warn('Error requesting permissions:', error);
  }
}

function setupCameraIntegration() {
  const { Camera } = Capacitor.Plugins;
  if (!Camera) return;

  // Aggiungi event listener per pulsanti fotocamera (se presenti)
  document.addEventListener('click', async (e) => {
    if (e.target.matches('[data-camera]')) {
      e.preventDefault();
      try {
        const image = await Camera.getPhoto({
          quality: 90,
          allowEditing: true,
          resultType: 'uri'
        });

        // Gestisci l'immagine catturata
        handleCapturedImage(image);
      } catch (error) {
        console.warn('Camera error:', error);
        showToast('Errore accesso fotocamera', 'error');
      }
    }
  });
}

function setupGeolocation() {
  const { Geolocation } = Capacitor.Plugins;
  if (!Geolocation) return;

  // Funzione per ottenere posizione corrente
  window.getCurrentPosition = async () => {
    try {
      const position = await Geolocation.getCurrentPosition({
        enableHighAccuracy: true,
        timeout: 10000
      });

      return {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        accuracy: position.coords.accuracy
      };
    } catch (error) {
      console.warn('Geolocation error:', error);
      throw error;
    }
  };

  // Watch position per aggiornamenti continui
  window.watchPosition = async (callback) => {
    try {
      const watchId = await Geolocation.watchPosition({
        enableHighAccuracy: true,
        timeout: 10000
      }, (position, err) => {
        if (err) {
          console.warn('Watch position error:', err);
          return;
        }

        callback({
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
          accuracy: position.coords.accuracy
        });
      });

      return watchId;
    } catch (error) {
      console.warn('Watch position setup error:', error);
      throw error;
    }
  };

  window.clearPositionWatch = (watchId) => {
    Geolocation.clearWatch({ id: watchId });
  };
}

function setupShareIntegration() {
  const { Share } = Capacitor.Plugins;
  if (!Share) return;

  // Funzione globale per condivisione
  window.shareContent = async (options) => {
    try {
      await Share.share(options);
    } catch (error) {
      console.warn('Share error:', error);
      // Fallback: copia negli appunti
      if (options.text) {
        navigator.clipboard.writeText(options.text);
        showToast('Testo copiato negli appunti', 'success');
      }
    }
  };

  // Aggiungi event listener per pulsanti condivisione
  document.addEventListener('click', async (e) => {
    if (e.target.matches('[data-share]')) {
      e.preventDefault();
      const url = e.target.dataset.shareUrl || window.location.href;
      const title = e.target.dataset.shareTitle || document.title;
      const text = e.target.dataset.shareText || '';

      await window.shareContent({
        title,
        text,
        url
      });
    }

    // Gestisci pulsante geolocalizzazione
    if (e.target.matches('[data-geolocation]') || e.target.closest('[data-geolocation]')) {
      e.preventDefault();
      try {
        const position = await window.getCurrentPosition();
        showToast(`Posizione: ${position.latitude.toFixed(6)}, ${position.longitude.toFixed(6)}`, 'success');
      } catch (error) {
        console.warn('Geolocation error:', error);
        showToast('Errore ottenimento posizione', 'error');
      }
    }
  });
}

function setupDeepLinking() {
  const { App } = Capacitor.Plugins;
  if (!App) return;

  // Gestisci deep links
  App.addListener('appUrlOpen', (data) => {
    console.log('App opened with URL:', data.url);

    // Gestisci il deep link
    handleDeepLink(data.url);
  });

  // Gestisci stato app
  App.addListener('appStateChange', (state) => {
    console.log('App state changed:', state.isActive);

    if (state.isActive) {
      // App è attiva, ricarica dati se necessario
      refreshAppData();
    }
  });

  // Gestisci back button
  App.addListener('backButton', () => {
    // Gestisci navigazione indietro
    if (window.history.length > 1) {
      window.history.back();
    } else {
      App.exitApp();
    }
  });
}

function setupAppManagement() {
  const { App } = Capacitor.Plugins;
  if (!App) return;

  // Ottieni info app
  window.getAppInfo = async () => {
    try {
      const info = await App.getInfo();
      return info;
    } catch (error) {
      console.warn('Error getting app info:', error);
      return null;
    }
  };

  // Ottieni stato app
  window.getAppState = async () => {
    try {
      const state = await App.getState();
      return state;
    } catch (error) {
      console.warn('Error getting app state:', error);
      return null;
    }
  };
}

// Funzioni di utilità
function handleCapturedImage(image) {
  // Implementa gestione immagine catturata
  console.log('Image captured:', image);

  // Esempio: mostra anteprima
  const img = document.createElement('img');
  img.src = image.webPath;
  img.style.maxWidth = '200px';
  img.style.maxHeight = '200px';

  // Aggiungi a un container se esiste
  const container = document.querySelector('#image-preview');
  if (container) {
    container.innerHTML = '';
    container.appendChild(img);
  }

  showToast('Immagine catturata con successo', 'success');
}

function handleDeepLink(url) {
  // Implementa gestione deep link
  console.log('Handling deep link:', url);

  // Esempio: naviga a una pagina specifica
  if (url.includes('/opportunities/')) {
    const opportunityId = url.split('/opportunities/')[1];
    if (opportunityId) {
      window.location.href = `/admin/opportunities.php?id=${opportunityId}`;
    }
  }
}

function refreshAppData() {
  // Implementa refresh dati quando app diventa attiva
  console.log('Refreshing app data...');

  // Esempio: ricarica notifiche
  if (typeof setupNotifications === 'function') {
    setupNotifications();
  }
}

// Smoke test per verificare raggiungibilità pagine auth
async function runSmokeTest() {
  console.log('🚀 Avvio Smoke Test per pagine auth...');

  const testUrls = [
    '/auth/login.php',
    '/auth/forgot_password.php',
    '/auth/reset_password.php'
  ];

  const results = [];

  for (const url of testUrls) {
    try {
      console.log(`Testing ${url}...`);
      const startTime = Date.now();

      const response = await fetch(url, {
        method: 'HEAD', // HEAD request per testare solo raggiungibilità
        headers: {
          'User-Agent': window.Capacitor ?
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1' :
            navigator.userAgent,
          'Cache-Control': 'no-cache'
        }
      });

      const endTime = Date.now();
      const duration = endTime - startTime;

      results.push({
        url,
        status: response.status,
        statusText: response.statusText,
        duration: `${duration}ms`,
        success: response.ok
      });

      console.log(`✅ ${url}: ${response.status} (${duration}ms)`);

    } catch (error) {
      results.push({
        url,
        status: 'ERROR',
        statusText: error.message,
        duration: 'N/A',
        success: false
      });

      console.error(`❌ ${url}: ${error.message}`);
    }
  }

  // Mostra risultati in un toast o alert
  const successCount = results.filter(r => r.success).length;
  const totalCount = results.length;

  const message = `Smoke Test: ${successCount}/${totalCount} pagine OK\n` +
    results.map(r => `${r.url}: ${r.status}`).join('\n');

  if (successCount === totalCount) {
    showToast('✅ Smoke Test passato!', 'success');
  } else {
    showToast(`❌ Smoke Test fallito\n${message}`, 'error');
  }

  console.log('📊 Risultati Smoke Test:', results);
  return results;
}

// Smoke test completo per funzionalità Capacitor
async function runCapacitorSmokeTest() {
  console.log('🚀 Avvio Smoke Test Completo Capacitor...');

  const results = {
    capacitor: { available: false, native: false },
    camera: { available: false, tested: false },
    geolocation: { available: false, tested: false },
    share: { available: false, tested: false },
    notifications: { available: false, tested: false },
    splashScreen: { available: false, tested: false },
    statusBar: { available: false, tested: false },
    app: { available: false, tested: false },
    push: { registered: false, permission: 'unknown' }
  };

  try {
    // Test Capacitor availability
    results.capacitor.available = typeof window.Capacitor !== 'undefined';
    results.capacitor.native = results.capacitor.available && Capacitor.isNativePlatform();
    console.log(`📱 Capacitor: ${results.capacitor.available ? 'Available' : 'Not available'}`);
    console.log(`🔧 Native Platform: ${results.capacitor.native ? 'Yes' : 'No'}`);

    if (!results.capacitor.available) {
      showToast('❌ Capacitor non disponibile', 'error');
      return results;
    }

    // Test Camera
    if (window.Capacitor.Plugins.Camera) {
      results.camera.available = true;
      console.log('📷 Camera plugin available');
      try {
        const permissions = await Capacitor.Plugins.Camera.requestPermissions();
        results.camera.tested = true;
        console.log('📷 Camera permissions:', permissions);
      } catch (error) {
        console.warn('📷 Camera test failed:', error);
      }
    }

    // Test Geolocation
    if (window.Capacitor.Plugins.Geolocation) {
      results.geolocation.available = true;
      console.log('📍 Geolocation plugin available');
      try {
        const permissions = await Capacitor.Plugins.Geolocation.requestPermissions();
        results.geolocation.tested = true;
        console.log('📍 Geolocation permissions:', permissions);
      } catch (error) {
        console.warn('📍 Geolocation test failed:', error);
      }
    }

    // Test Share
    if (window.Capacitor.Plugins.Share) {
      results.share.available = true;
      console.log('🔗 Share plugin available');
      results.share.tested = true;
    }

    // Test Splash Screen
    if (window.Capacitor.Plugins.SplashScreen) {
      results.splashScreen.available = true;
      console.log('💫 SplashScreen plugin available');
      try {
        await Capacitor.Plugins.SplashScreen.show({ showDuration: 1000 });
        results.splashScreen.tested = true;
        console.log('💫 SplashScreen test passed');
      } catch (error) {
        console.warn('💫 SplashScreen test failed:', error);
      }
    }

    // Test Status Bar
    if (window.Capacitor.Plugins.StatusBar) {
      results.statusBar.available = true;
      console.log('📊 StatusBar plugin available');
      try {
        await Capacitor.Plugins.StatusBar.setStyle({ style: 'DARK' });
        results.statusBar.tested = true;
        console.log('📊 StatusBar test passed');
      } catch (error) {
        console.warn('📊 StatusBar test failed:', error);
      }
    }

    // Test App
    if (window.Capacitor.Plugins.App) {
      results.app.available = true;
      console.log('📱 App plugin available');
      try {
        const appInfo = await Capacitor.Plugins.App.getInfo();
        results.app.tested = true;
        console.log('📱 App info:', appInfo);
      } catch (error) {
        console.warn('📱 App test failed:', error);
      }
    }

    // Test Push Notifications
    if (window.Capacitor.Plugins.PushNotifications) {
      results.notifications.available = true;
      console.log('🔔 PushNotifications plugin available');

      try {
        // Check permission
        const permission = await Capacitor.Plugins.PushNotifications.requestPermissions();
        results.push.permission = permission.receive;
        console.log('🔔 Push permission:', permission);

        if (permission.receive === 'granted') {
          // Register for push
          Capacitor.Plugins.PushNotifications.register();
          results.notifications.tested = true;
          results.push.registered = true;
          console.log('🔔 Push notifications registered');
        }
      } catch (error) {
        console.warn('🔔 Push test failed:', error);
      }
    }

    // Test cache clearing
    try {
      await clearCacheForCapacitor();
      console.log('🗑️ Cache cleared successfully');
    } catch (error) {
      console.warn('🗑️ Cache clear failed:', error);
    }

  } catch (error) {
    console.error('❌ Capacitor smoke test failed:', error);
  }

  // Summary
  const availablePlugins = Object.values(results).filter(r => typeof r === 'object' && r.available).length;
  const testedPlugins = Object.values(results).filter(r => typeof r === 'object' && r.tested).length;

  const summary = `
Capacitor Smoke Test Results:
📱 Capacitor: ${results.capacitor.available ? '✅' : '❌'}
🔧 Native: ${results.capacitor.native ? '✅' : '❌'}
📷 Camera: ${results.camera.available ? '✅' : '❌'}
📍 Geolocation: ${results.geolocation.available ? '✅' : '❌'}
🔗 Share: ${results.share.available ? '✅' : '❌'}
🔔 Push: ${results.notifications.available ? '✅' : '❌'}
💫 Splash: ${results.splashScreen.available ? '✅' : '❌'}
📊 StatusBar: ${results.statusBar.available ? '✅' : '❌'}
📱 App: ${results.app.available ? '✅' : '❌'}

Plugins Available: ${availablePlugins}/8
Plugins Tested: ${testedPlugins}/8
Push Permission: ${results.push.permission}
  `;

  console.log(summary);

  if (availablePlugins >= 6) {
    showToast(`✅ Capacitor OK (${availablePlugins}/8 plugins)`, 'success');
  } else {
    showToast(`❌ Capacitor issues (${availablePlugins}/8 plugins)`, 'warning');
  }

  return results;
}

// Esponi funzioni globalmente per debug
window.runSmokeTest = runSmokeTest;
window.runCapacitorSmokeTest = runCapacitorSmokeTest;

// Esponi funzione globalmente per debug
window.runSmokeTest = runSmokeTest;

// Inizializza tutto al caricamento
document.addEventListener('DOMContentLoaded', () => {
  // Svuota cache automaticamente per Capacitor
  if (window.Capacitor && Capacitor.isNativePlatform()) {
    clearCacheForCapacitor();
  }

  const savedTheme = localStorage.getItem('theme');
  if (savedTheme) {
    document.documentElement.setAttribute('data-bs-theme', savedTheme);
  }

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
      try { localStorage.setItem('theme', next); } catch (err) { /* ignore */ }
    });
  }

  setupOfferPicker();
  setupManagerPicker();
  setupInstallerPicker();
  setupSavedFilters();
  setupDocPreviews();
  setupFormValidation();

  injectToastStack();
  hydrateFlashMessages();

  // Registra service worker solo se NON siamo su Capacitor (per evitare conflitti)
  if (!window.Capacitor || !Capacitor.isNativePlatform()) {
    registerPush();
  } else {
    // Su Capacitor, gestisci solo le notifiche push native
    registerPush();
  }

  setupNotifications();

  // Mostra pulsanti smoke test su Capacitor
  if (window.Capacitor && Capacitor.isNativePlatform()) {
    const smokeBtn = document.getElementById('smoke-test-btn');
    const capacitorBtn = document.getElementById('capacitor-test-btn');
    if (smokeBtn) {
      smokeBtn.classList.remove('d-none');
      smokeBtn.addEventListener('click', runSmokeTest);
    }
    if (capacitorBtn) {
      capacitorBtn.classList.remove('d-none');
      capacitorBtn.addEventListener('click', runCapacitorSmokeTest);
    }
  }

  // Inizializza funzionalità Capacitor
  initializeCapacitorFeatures();
});
