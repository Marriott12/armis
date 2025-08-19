/**
 * ARMIS Service Worker
 * Provides offline functionality and caching for PWA capabilities
 */

const CACHE_NAME = 'armis-v1.0.0';
const OFFLINE_URL = '/offline.html';

// Files to cache for offline functionality
const CACHE_URLS = [
  '/',
  '/login.php',
  '/assets/css/bootstrap.min.css',
  '/assets/css/font-awesome.min.css',
  '/shared/armis-styles.css',
  '/shared/mobile-responsive.css',
  '/assets/js/bootstrap.bundle.min.js',
  '/assets/js/jquery.min.js',
  '/shared/dashboard-utils.js',
  '/shared/notifications.js',
  '/pwa/css/pwa-styles.css',
  '/pwa/js/pwa-app.js',
  '/favicon.ico',
  '/logo.png',
  OFFLINE_URL
];

// API endpoints to cache
const API_CACHE_URLS = [
  '/api/v1/health',
  '/api/v1/status',
  '/api/v1/version'
];

// Install event - cache resources
self.addEventListener('install', event => {
  console.log('[SW] Install event');
  
  event.waitUntil(
    (async () => {
      try {
        const cache = await caches.open(CACHE_NAME);
        console.log('[SW] Caching app shell');
        
        // Cache core files
        await cache.addAll(CACHE_URLS);
        
        // Cache API endpoints
        for (const url of API_CACHE_URLS) {
          try {
            const response = await fetch(url);
            if (response.ok) {
              await cache.put(url, response.clone());
            }
          } catch (e) {
            console.warn(`[SW] Failed to cache API endpoint: ${url}`, e);
          }
        }
        
        console.log('[SW] App shell cached successfully');
      } catch (error) {
        console.error('[SW] Failed to cache app shell:', error);
      }
    })()
  );
  
  // Force the waiting service worker to become the active service worker
  self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  console.log('[SW] Activate event');
  
  event.waitUntil(
    (async () => {
      try {
        const cacheNames = await caches.keys();
        await Promise.all(
          cacheNames.map(cacheName => {
            if (cacheName !== CACHE_NAME) {
              console.log('[SW] Deleting old cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
        
        // Enable navigation preload if supported
        if ('navigationPreload' in self.registration) {
          await self.registration.navigationPreload.enable();
        }
        
      } catch (error) {
        console.error('[SW] Activation failed:', error);
      }
    })()
  );
  
  // Take control of all pages
  self.clients.claim();
});

// Fetch event - implement caching strategy
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);
  
  // Skip non-GET requests
  if (request.method !== 'GET') {
    return;
  }
  
  // Skip chrome-extension requests
  if (url.protocol === 'chrome-extension:') {
    return;
  }
  
  // Handle navigation requests
  if (request.mode === 'navigate') {
    event.respondWith(handleNavigationRequest(request));
    return;
  }
  
  // Handle API requests
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(handleAPIRequest(request));
    return;
  }
  
  // Handle asset requests
  event.respondWith(handleAssetRequest(request));
});

// Handle navigation requests with network-first strategy
async function handleNavigationRequest(request) {
  try {
    // Try network first
    const networkResponse = await fetch(request);
    
    if (networkResponse.ok) {
      // Cache successful navigation responses
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
    
  } catch (error) {
    console.log('[SW] Navigation request failed, trying cache:', error);
    
    // Try cache
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }
    
    // Return offline page
    return caches.match(OFFLINE_URL);
  }
}

// Handle API requests with cache-first strategy for certain endpoints
async function handleAPIRequest(request) {
  const url = new URL(request.url);
  
  // Use cache-first for status endpoints
  if (API_CACHE_URLS.some(endpoint => url.pathname === endpoint)) {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      // Update cache in background
      fetch(request).then(response => {
        if (response.ok) {
          caches.open(CACHE_NAME).then(cache => {
            cache.put(request, response.clone());
          });
        }
      }).catch(console.warn);
      
      return cachedResponse;
    }
  }
  
  // Network-first for other API requests
  try {
    const networkResponse = await fetch(request);
    
    // Cache successful responses for certain endpoints
    if (networkResponse.ok && API_CACHE_URLS.some(endpoint => url.pathname === endpoint)) {
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
    
  } catch (error) {
    console.log('[SW] API request failed:', error);
    
    // Return cached version if available
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }
    
    // Return error response
    return new Response(
      JSON.stringify({ 
        error: 'Network unavailable', 
        offline: true,
        timestamp: new Date().toISOString()
      }),
      {
        status: 503,
        headers: { 'Content-Type': 'application/json' }
      }
    );
  }
}

// Handle asset requests with cache-first strategy
async function handleAssetRequest(request) {
  // Try cache first
  const cachedResponse = await caches.match(request);
  if (cachedResponse) {
    return cachedResponse;
  }
  
  // Try network
  try {
    const networkResponse = await fetch(request);
    
    if (networkResponse.ok) {
      // Cache successful asset responses
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
    
  } catch (error) {
    console.log('[SW] Asset request failed:', error);
    
    // Return placeholder for images
    if (request.destination === 'image') {
      return new Response(
        '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="200" height="200" fill="#f0f0f0"/><text x="100" y="100" text-anchor="middle" dy=".3em" fill="#666">Image Unavailable</text></svg>',
        {
          headers: { 'Content-Type': 'image/svg+xml' }
        }
      );
    }
    
    // Return 404 for other assets
    return new Response('Not found', { status: 404 });
  }
}

// Handle background sync
self.addEventListener('sync', event => {
  console.log('[SW] Background sync:', event.tag);
  
  if (event.tag === 'sync-data') {
    event.waitUntil(syncData());
  } else if (event.tag === 'sync-metrics') {
    event.waitUntil(syncMetrics());
  }
});

// Sync data when online
async function syncData() {
  try {
    // Get pending data from IndexedDB
    const pendingData = await getPendingData();
    
    for (const data of pendingData) {
      try {
        const response = await fetch('/api/v1/sync', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data)
        });
        
        if (response.ok) {
          // Remove from pending queue
          await removePendingData(data.id);
        }
      } catch (e) {
        console.warn('[SW] Failed to sync data item:', e);
      }
    }
    
    console.log('[SW] Data sync completed');
  } catch (error) {
    console.error('[SW] Data sync failed:', error);
  }
}

// Sync performance metrics
async function syncMetrics() {
  try {
    // Collect performance metrics
    const metrics = {
      timestamp: Date.now(),
      connection: navigator.connection ? {
        effectiveType: navigator.connection.effectiveType,
        downlink: navigator.connection.downlink,
        rtt: navigator.connection.rtt
      } : null,
      memory: performance.memory ? {
        usedJSHeapSize: performance.memory.usedJSHeapSize,
        totalJSHeapSize: performance.memory.totalJSHeapSize
      } : null
    };
    
    await fetch('/api/v1/metrics', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(metrics)
    });
    
    console.log('[SW] Metrics sync completed');
  } catch (error) {
    console.warn('[SW] Metrics sync failed:', error);
  }
}

// Handle push notifications
self.addEventListener('push', event => {
  console.log('[SW] Push received');
  
  const options = {
    badge: '/pwa/icons/badge-72x72.png',
    icon: '/pwa/icons/icon-192x192.png',
    vibrate: [100, 50, 100],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: 1
    },
    actions: [
      {
        action: 'view',
        title: 'View',
        icon: '/pwa/icons/view-action.png'
      },
      {
        action: 'dismiss',
        title: 'Dismiss',
        icon: '/pwa/icons/dismiss-action.png'
      }
    ]
  };
  
  let title = 'ARMIS Notification';
  let body = 'New notification received';
  
  if (event.data) {
    try {
      const payload = event.data.json();
      title = payload.title || title;
      body = payload.body || body;
      
      if (payload.icon) options.icon = payload.icon;
      if (payload.badge) options.badge = payload.badge;
      if (payload.data) options.data = { ...options.data, ...payload.data };
    } catch (e) {
      console.warn('[SW] Invalid push payload:', e);
    }
  }
  
  event.waitUntil(
    self.registration.showNotification(title, {
      body,
      ...options
    })
  );
});

// Handle notification clicks
self.addEventListener('notificationclick', event => {
  console.log('[SW] Notification click received:', event);
  
  event.notification.close();
  
  if (event.action === 'view') {
    // Open the app
    event.waitUntil(
      clients.openWindow('/')
    );
  } else if (event.action === 'dismiss') {
    // Just close the notification
    console.log('[SW] Notification dismissed');
  } else {
    // Default action - open the app
    event.waitUntil(
      clients.openWindow('/')
    );
  }
});

// Utility functions for IndexedDB operations
async function getPendingData() {
  // Implementation would use IndexedDB to store/retrieve pending data
  return [];
}

async function removePendingData(id) {
  // Implementation would remove data from IndexedDB
  console.log('[SW] Removing pending data:', id);
}

// Log service worker version
console.log(`[SW] ARMIS Service Worker v${CACHE_NAME} initialized`);