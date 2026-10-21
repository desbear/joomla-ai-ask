/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

// The console's service worker (0.3.0): it shows what a push brings, and a click on one opens the
// console at that chat. It does nothing else: no page of the site is fetched or kept through it.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

// Safari (iPhone, iPad, Mac) wants every push shown; others let one go when the console is in front
const ua = self.navigator.userAgent;
const showsAlways = /iPhone|iPad|iPod/.test(ua) || (/Safari/.test(ua) && !/Chrome|Chromium|Edg|Firefox/.test(ua));

self.addEventListener('push', (event) => {
  let data = {};

  try {
    data = event.data ? event.data.json() : {};
  } catch (error) {
    data = { body: event.data ? event.data.text() : '' };
  }

  const title = data.title || 'AI Ask Desk';
  const options = {
    body: data.body || '',
    tag: data.tag || 'askdesk',
    renotify: true,
    icon: new URL('media/com_askdesk/images/icon-192.png', self.registration.scope).href,
    badge: new URL('media/com_askdesk/images/badge-96.png', self.registration.scope).href,
    data: { url: data.url || self.registration.scope },
  };

  event.waitUntil((async () => {
    // A console in front, looked at, already says so with its own sound: told, not notified
    const open = (await self.clients.matchAll({ type: 'window', includeUncontrolled: true })).filter((client) => client.focused && client.visibilityState === 'visible');

    open.forEach((client) => client.postMessage({ askdesk: 'push', data }));

    if (open.length && !showsAlways) {
      return;
    }

    await self.registration.showNotification(title, options);
  })());
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const url = (event.notification.data && event.notification.data.url) || self.registration.scope;
  const room = (url.match(/#room=(\d+)/) || [])[1];
  const page = url.replace(/#.*$/, '');

  event.waitUntil((async () => {
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const console = windows.find((client) => client.url.replace(/#.*$/, '') === page);

    if (console) {
      await console.focus();
      console.postMessage({ askdesk: 'open', room: Number(room) || 0 });

      return;
    }

    await self.clients.openWindow(url);
  })());
});
