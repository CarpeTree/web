// Service Worker for Carpe Tree Admin PWA
const CACHE_NAME = 'carpetree-admin-v1';
const urlsToCache = [
  '/admin/',
  '/admin/index.html',
  '/admin/manifest.json',
  '/images/favicon.png'
];

// Install event - cache resources
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
      .then(() => self.skipWaiting())
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch event - network first, fallback to cache
self.addEventListener('fetch', event => {
  // Skip non-GET requests
  if (event.request.method !== 'GET') return;
  
  // Skip API requests - always go to network
  if (event.request.url.includes('/server/api/')) {
    return;
  }
  
  event.respondWith(
    fetch(event.request)
      .then(response => {
        // Clone the response before caching
        const responseToCache = response.clone();
        
        caches.open(CACHE_NAME)
          .then(cache => {
            cache.put(event.request, responseToCache);
          });
        
        return response;
      })
      .catch(() => {
        // Network failed, try cache
        return caches.match(event.request)
          .then(response => {
            if (response) {
              return response;
            }
            // Return offline page if available
            return caches.match('/admin/');
          });
      })
  );
});

// Background sync for offline quote submissions
self.addEventListener('sync', event => {
  if (event.tag === 'sync-quotes') {
    event.waitUntil(syncQuotes());
  }
});

async function syncQuotes() {
  // Get pending quotes from IndexedDB and sync
  // This would be implemented with your actual sync logic
  console.log('Syncing offline quotes...');
}
