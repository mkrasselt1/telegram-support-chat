/* =============================================================================
   Telegram Support Chat — Service Worker
   ============================================================================= */

const CACHE_NAME   = 'support-chat-v1';
const ASSETS       = []; // Optionally list JS/CSS to cache for offline

// ---------------------------------------------------------------------------
// Install
// ---------------------------------------------------------------------------
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS))
  );
  self.skipWaiting();
});

// ---------------------------------------------------------------------------
// Activate — clean old caches, claim clients
// ---------------------------------------------------------------------------
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

// ---------------------------------------------------------------------------
// Message from widget page → show notification when tab is in background
// ---------------------------------------------------------------------------
self.addEventListener('message', (event) => {
  const data = event.data;
  if (!data) return;

  if (data.type === 'NEW_MESSAGES') {
    event.waitUntil(
      self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
        const hasFocus = clientList.some((c) => c.focused);
        if (hasFocus || !data.messages || data.messages.length === 0) return;

        const last    = data.messages[data.messages.length - 1];
        const body    = last.content
          ? String(last.content).substring(0, 120)
          : (last.type === 'image' ? '📷 Image' : last.type === 'voice' ? '🎤 Voice message' : 'New message');
        const agentName = last.agent_name || 'Support';

        return self.registration.showNotification(agentName, {
          body:  body,
          icon:  data.iconUrl || '/support-chat-icon.png',
          badge: data.iconUrl || '/support-chat-icon.png',
          tag:   'support-chat-' + (data.sessionId || 'session'),
          renotify:  true,
          requireInteraction: false,
          data: {
            sessionId: data.sessionId,
            url:       data.pageUrl || self.registration.scope,
          },
        });
      })
    );
  }

  // Widget explicitly requesting SW to show a notification
  if (data.type === 'SHOW_NOTIFICATION') {
    event.waitUntil(
      self.registration.showNotification(data.title || 'Support Chat', {
        body:  data.body  || '',
        icon:  data.icon  || '/support-chat-icon.png',
        tag:   'support-chat',
        data:  { url: data.url || self.registration.scope },
      })
    );
  }
});

// ---------------------------------------------------------------------------
// Notification click → focus or open the originating tab
// ---------------------------------------------------------------------------
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = event.notification.data?.url || self.registration.scope;

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      // Try to find an existing tab with the same URL
      for (const client of clientList) {
        if (client.url === targetUrl && 'focus' in client) {
          client.postMessage({ type: 'OPEN_CHAT' });
          return client.focus();
        }
      }
      // Open new tab if none found
      if (self.clients.openWindow) {
        return self.clients.openWindow(targetUrl);
      }
    })
  );
});

// ---------------------------------------------------------------------------
// Push event (optional — requires VAPID setup & server-side push API)
// ---------------------------------------------------------------------------
self.addEventListener('push', (event) => {
  if (!event.data) return;

  let payload;
  try {
    payload = event.data.json();
  } catch {
    payload = { body: event.data.text() };
  }

  event.waitUntil(
    self.registration.showNotification(payload.title || 'Support Chat', {
      body:  payload.body  || 'New message from support',
      icon:  payload.icon  || '/support-chat-icon.png',
      badge: payload.badge || '/support-chat-icon.png',
      tag:   'support-chat-push',
      data:  { url: payload.url || self.registration.scope },
    })
  );
});
