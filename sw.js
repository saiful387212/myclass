/**
 * ClassMate - Service Worker
 * Provides offline support and caching
 */

const CACHE_NAME = 'classmate-v2.0.0';
const OFFLINE_URL = '/offline.html';

// Assets to cache on install
const ASSETS_TO_CACHE = [
  '/',
  '/offline.html',
  '/assets/css/style.css',
  '/assets/css/components.css',
  '/assets/css/responsive.css',
  '/assets/css/student.css',
  '/assets/js/main.js',
  '/assets/js/notifications.js',
  '/assets/js/validation.js',
  '/assets/images/logo.svg',
  '/assets/images/favicon.ico',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
  'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap',
  'https://cdn.jsdelivr.net/npm/chart.js'
];

// ===== INSTALL EVENT =====
self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        console.log('Service Worker: Caching assets');
        return cache.addAll(ASSETS_TO_CACHE);
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
          const responseClone = response.clone();
          caches.open(CACHE_NAME).then(function(cache) {
            cache.put(event.request, responseClone);
          });
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
    icon: '/assets/images/logo.svg',
    badge: '/assets/images/favicon.ico',
    url: '/'
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
    icon: data.icon || '/assets/images/logo.svg',
    badge: data.badge || '/assets/images/favicon.ico',
    vibrate: [100, 50, 100],
    data: {
      url: data.url || '/'
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