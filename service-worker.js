// Service Worker for Carpe Tree Field Capture PWA
const CACHE_NAME = 'field-capture-v1';
const DATA_CACHE_NAME = 'field-data-v1';
const SYNC_TAG = 'sync-quotes';

// Files to cache for offline use
const urlsToCache = [
  '/field-quote.html',
  '/style.css',
  '/images/favicon.png',
  '/images/carpeclear.png',
  'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap'
];

// Install event - cache app shell
self.addEventListener('install', event => {
  console.log('[ServiceWorker] Install');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('[ServiceWorker] Caching app shell');
        return cache.addAll(urlsToCache);
      })
      .then(() => self.skipWaiting())
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  console.log('[ServiceWorker] Activate');
  event.waitUntil(
    caches.keys().then(keyList => {
      return Promise.all(keyList.map(key => {
        if (key !== CACHE_NAME && key !== DATA_CACHE_NAME) {
          console.log('[ServiceWorker] Removing old cache', key);
          return caches.delete(key);
        }
      }));
    }).then(() => self.clients.claim())
  );
});

// Fetch event - serve from cache when offline
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  // Handle API calls differently
  if (url.pathname.startsWith('/server/api/')) {
    event.respondWith(handleApiRequest(request));
    return;
  }

  // Handle Google Maps API
  if (url.hostname === 'maps.googleapis.com' || url.hostname === 'maps.gstatic.com') {
    event.respondWith(
      fetch(request).catch(() => {
        // Return a fallback for maps when offline
        return new Response('', { status: 503 });
      })
    );
    return;
  }

  // Cache-first strategy for static assets
  event.respondWith(
    caches.match(request)
      .then(response => {
        if (response) {
          return response;
        }
        return fetch(request).then(response => {
          // Don't cache non-successful responses
          if (!response || response.status !== 200 || response.type === 'opaque') {
            return response;
          }

          // Clone the response
          const responseToCache = response.clone();

          caches.open(CACHE_NAME)
            .then(cache => {
              cache.put(request, responseToCache);
            });

          return response;
        });
      })
      .catch(() => {
        // Offline fallback
        if (request.destination === 'document') {
          return caches.match('/field-quote.html');
        }
      })
  );
});

// Handle API requests with offline queue
async function handleApiRequest(request) {
  try {
    // Try to fetch from network
    const response = await fetch(request.clone());
    return response;
  } catch (error) {
    // Network failed, queue for later if it's a POST
    if (request.method === 'POST') {
      return queueRequest(request);
    }
    
    // For GET requests, try cache
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }
    
    // Return offline response
    return new Response(
      JSON.stringify({ 
        success: false, 
        offline: true, 
        message: 'Request queued for sync' 
      }),
      { 
        status: 200,
        headers: { 'Content-Type': 'application/json' }
      }
    );
  }
}

// Queue request for background sync
async function queueRequest(request) {
  const cache = await caches.open(DATA_CACHE_NAME);
  
  // Store request data
  const requestData = {
    url: request.url,
    method: request.method,
    headers: [...request.headers.entries()],
    body: await request.blob(),
    timestamp: Date.now()
  };
  
  // Get existing queue
  const queueResponse = await cache.match('queue');
  let queue = [];
  
  if (queueResponse) {
    queue = await queueResponse.json();
  }
  
  // Add to queue
  queue.push(requestData);
  
  // Save updated queue
  await cache.put('queue', new Response(JSON.stringify(queue)));
  
  // Register for background sync
  if ('sync' in self.registration) {
    await self.registration.sync.register(SYNC_TAG);
  }
  
  return new Response(
    JSON.stringify({ 
      success: true, 
      offline: true,
      queued: true,
      message: 'Quote saved offline. Will sync when connection returns.' 
    }),
    { 
      status: 200,
      headers: { 'Content-Type': 'application/json' }
    }
  );
}

// Background sync event
self.addEventListener('sync', event => {
  console.log('[ServiceWorker] Sync event', event.tag);
  
  if (event.tag === SYNC_TAG) {
    event.waitUntil(syncQueue());
  }
});

// Sync queued requests
async function syncQueue() {
  const cache = await caches.open(DATA_CACHE_NAME);
  const queueResponse = await cache.match('queue');
  
  if (!queueResponse) {
    return;
  }
  
  const queue = await queueResponse.json();
  const failedRequests = [];
  
  for (const requestData of queue) {
    try {
      // Recreate request
      const request = new Request(requestData.url, {
        method: requestData.method,
        headers: requestData.headers,
        body: requestData.body
      });
      
      // Try to send
      const response = await fetch(request);
      
      if (!response.ok) {
        failedRequests.push(requestData);
      } else {
        // Notify clients of successful sync
        const clients = await self.clients.matchAll();
        clients.forEach(client => {
          client.postMessage({
            type: 'SYNC_SUCCESS',
            data: { url: requestData.url, timestamp: requestData.timestamp }
          });
        });
      }
    } catch (error) {
      console.error('[ServiceWorker] Sync failed for request', error);
      failedRequests.push(requestData);
    }
  }
  
  // Update queue with failed requests only
  if (failedRequests.length > 0) {
    await cache.put('queue', new Response(JSON.stringify(failedRequests)));
  } else {
    await cache.delete('queue');
  }
  
  return failedRequests.length === 0;
}

// Push notification support
self.addEventListener('push', event => {
  console.log('[ServiceWorker] Push received');
  
  let data = { title: 'Carpe Tree', body: 'New notification' };
  
  if (event.data) {
    data = event.data.json();
  }
  
  const options = {
    body: data.body,
    icon: '/images/favicon.png',
    badge: '/images/favicon.png',
    vibrate: [100, 50, 100],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: 1
    },
    actions: [
      {
        action: 'view',
        title: 'View Quote'
      },
      {
        action: 'close',
        title: 'Close'
      }
    ]
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Notification click handler
self.addEventListener('notificationclick', event => {
  console.log('[ServiceWorker] Notification click received');
  
  event.notification.close();
  
  if (event.action === 'view') {
    event.waitUntil(
      clients.openWindow('/field-quote.html')
    );
  }
});

// Periodic background sync for regular updates
self.addEventListener('periodicsync', event => {
  if (event.tag === 'update-quotes') {
    event.waitUntil(updateQuotes());
  }
});

async function updateQuotes() {
  try {
    const response = await fetch('/server/api/get-quotes-with-location.php');
    const data = await response.json();
    
    // Cache the response
    const cache = await caches.open(DATA_CACHE_NAME);
    await cache.put('/api/quotes', new Response(JSON.stringify(data)));
    
    // Notify clients
    const clients = await self.clients.matchAll();
    clients.forEach(client => {
      client.postMessage({
        type: 'QUOTES_UPDATED',
        data: data
      });
    });
  } catch (error) {
    console.error('[ServiceWorker] Failed to update quotes', error);
  }
}











