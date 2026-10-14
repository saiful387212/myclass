/**
 * ClassMate - Service Worker
 * Provides offline support and caching
 */

const CACHE_NAME = 'classmate-v2.0.2';
const APP_ROOT = new URL('./', self.location.href).pathname;
const OFFLINE_URL = APP_ROOT + 'offline.html';
const appAsset = function(path) {
  return APP_ROOT + path;
};

// Assets to cache on install
const ASSETS_TO_CACHE = [
  APP_ROOT,
  OFFLINE_URL,
  appAsset('assets/css/style.css'),
  appAsset('assets/css/components.css'),
  appAsset('assets/css/responsive.css'),
  appAsset('assets/css/student.css'),
  appAsset('assets/js/main.js'),
  appAsset('assets/js/notifications.js'),
  appAsset('assets/js/validation.js')
];

function cacheAvailableAssets(cache) {
  return Promise.all(ASSETS_TO_CACHE.map(function(asset) {
    return fetch(asset, {cache: 'no-store'}).then(function(response) {
      const contentType = response.headers.get('Content-Type') || '';
      const isHtmlPage = asset === APP_ROOT || asset === OFFLINE_URL;
      if (response.ok && (isHtmlPage || !/text\/html/i.test(contentType))) {
        return cache.put(asset, response);
      }
      console.warn('Service Worker: Skipping unavailable asset:', asset);
      return null;
    }).catch(function(error) {
      console.warn('Service Worker: Skipping asset:', asset, error);
      return null;
    });
  }));
}

// ===== INSTALL EVENT =====
self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        console.log('Service Worker: Caching assets');
        return cacheAvailableAssets(cache);
      })
      .then(function() {
        console.log('Service Worker: Skip waiting');
        return self.skipWaiting();
      })
      .catch(function(error) {
        console.error('Service Worker: Cache installation failed:', error);
      })
  );
});

// ===== ACTIVATE EVENT =====
self.addEventListener('activate', function(event) {
  event.waitUntil(
    caches.keys().then(function(cacheNames) {
      return Promise.all(
        cacheNames.map(function(cacheName) {
          if (cacheName !== CACHE_NAME) {
            console.log('Service Worker: Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(function() {
      console.log('Service Worker: Claiming clients');
      return self.clients.claim();
    })
  );
});

// ===== FETCH EVENT =====
self.addEventListener('fetch', function(event) {
  // Skip cross-origin requests
  if (!event.request.url.startsWith(self.location.origin)) {
    return;
  }

  // Skip non-GET requests
  if (event.request.method !== 'GET') {
    return;
  }

  // Skip requests that include 'api' or 'admin' (dynamic content)
  const url = new URL(event.request.url);
  if (url.pathname.includes('/api/') || url.pathname.includes('/admin/')) {
    // Network first for API/admin requests
    event.respondWith(
      fetch(event.request)
        .catch(function() {
          return caches.match(event.request);
        })
    );
    return;
  }

  // For HTML pages - Network first with cache fallback
  if (event.request.headers.get('Accept').includes('text/html')) {
    event.respondWith(
      fetch(event.request)
        .then(function(response) {
          // Cache the fetched page
          if (response.ok) {
            const responseClone = response.clone();
            caches.open(CACHE_NAME).then(function(cache) {
              cache.put(event.request, responseClone);
            });
          }
          return response;
        })
        .catch(function() {
          // Return cached page or offline page
          return caches.match(event.request)
            .then(function(cachedResponse) {
              if (cachedResponse) {
                return cachedResponse;
              }
              return caches.match(OFFLINE_URL);
            });
        })
    );
    return;
  }

  // For static assets - Cache first with network fallback
  event.respondWith(
    caches.match(event.request)
      .then(function(cachedResponse) {
        if (cachedResponse) {
          // Return cached response and update cache in background
          fetch(event.request).then(function(networkResponse) {
            const contentType = networkResponse.headers.get('Content-Type') || '';
            if (!networkResponse.ok || /text\/html/i.test(contentType)) {
              return;
            }
            caches.open(CACHE_NAME).then(function(cache) {
              cache.put(event.request, networkResponse);
            });
          }).catch(function() {
            // Ignore network errors
          });
          return cachedResponse;
        }
        // Not in cache - fetch from network
        return fetch(event.request)
          .then(function(networkResponse) {
            const contentType = networkResponse.headers.get('Content-Type') || '';
            if (!networkResponse.ok || /text\/html/i.test(contentType)) {
              return networkResponse;
            }
            const responseClone = networkResponse.clone();
            caches.open(CACHE_NAME).then(function(cache) {
              cache.put(event.request, responseClone);
            });
            return networkResponse;
          })
          .catch(function() {
            // If both cache and network fail, return offline page for HTML
            if (event.request.headers.get('Accept').includes('text/html')) {
              return caches.match(OFFLINE_URL);
            }
            return new Response('Offline', {
              status: 503,
              statusText: 'Service Unavailable'
            });
          });
      })
  );
});

// ===== PUSH NOTIFICATION EVENT =====
self.addEventListener('push', function(event) {
  let data = {
    title: 'ClassMate',
    body: 'You have a new notification',
    icon: appAsset('assets/icons/web-app-manifest-192x192.png'),
    badge: appAsset('assets/icons/favicon-96x96.png'),
    url: APP_ROOT
  };

  if (event.data) {
    try {
      data = JSON.parse(event.data.text());
    } catch (e) {
      data.body = event.data.text();
    }
  }

  const options = {
    body: data.body,
    icon: data.icon || appAsset('assets/icons/web-app-manifest-192x192.png'),
    badge: data.badge || appAsset('assets/icons/favicon-96x96.png'),
    vibrate: [100, 50, 100],
    data: {
      url: data.url || APP_ROOT
    },
    actions: [
      {
        action: 'open',
        title: 'Open'
      },
      {
        action: 'close',
        title: 'Dismiss'
      }
    ]
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// ===== NOTIFICATION CLICK EVENT =====
self.addEventListener('notificationclick', function(event) {
  event.notification.close();

  if (event.action === 'close') {
    return;
  }

  const url = event.notification.data.url || '/';
  event.waitUntil(
    clients.matchAll({
      type: 'window',
      includeUncontrolled: true
    }).then(function(windowClients) {
      // Check if there's already a window/tab open with the target URL
      for (var i = 0; i < windowClients.length; i++) {
        var client = windowClients[i];
        if (client.url === url && 'focus' in client) {
          return client.focus();
        }
      }
      // If not, open a new window/tab
      if (clients.openWindow) {
        return clients.openWindow(url);
      }
    })
  );
});

// ===== BACKGROUND SYNC =====
self.addEventListener('sync', function(event) {
  if (event.tag === 'sync-data') {
    event.waitUntil(
      // Handle background sync for offline form submissions
      syncData()
    );
  }
});

function syncData() {
  // Implement offline form submission sync logic here
  console.log('Background sync triggered');
  return Promise.resolve();
}

// ===== MESSAGE EVENT =====
self.addEventListener('message', function(event) {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});