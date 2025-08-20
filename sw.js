/**
 * ARMIS Service Worker
 * Provides offline functionality and background sync for military operations
 * Implements comprehensive caching strategy for field deployment scenarios
*/

const CACHE_NAME = 'armis-v1.0.0';
const OFFLINE_URL = '/offline.html';
const API_CACHE_NAME = 'armis-api-v1.0.0';
const IMAGES_CACHE_NAME = 'armis-images-v1.0.0';

// Critical files that must be cached for offline functionality
const CRITICAL_CACHE_FILES = [
    '/',
    '/admin_branch/index.php',
    '/shared/armis-styles.css',
    '/shared/mobile-responsive.css',
    '/shared/accessibility.css',
    '/shared/military-themes.css',
    '/shared/dashboard-utils.js',
    '/shared/voice-commands.js',
    '/shared/i18n.php',
    '/shared/translations/en.php',
    '/manifest.json',
    OFFLINE_URL
];

// Files that should be cached when accessed
const RUNTIME_CACHE_FILES = [
    '/admin_branch/edit_staff.php',
    '/admin_branch/create_staff.php',
    '/admin_branch/promote_staff.php',
    '/admin_branch/assign_medal.php',
    '/training/index.php',
    '/operations/index.php',
    '/finance/index.php'
];

// API endpoints that should be cached
const API_CACHE_PATTERNS = [
    /\/api\//,
    /\/admin_branch\/api\.php/,
    /\/admin_branch\/dashboard_api\.php/,
    /\/shared\/ajax-handler\.php/
];

// Image and asset patterns
const ASSET_CACHE_PATTERNS = [
    /\.(?:png|jpg|jpeg|svg|gif|webp|ico)$/,
    /\.(?:woff|woff2|ttf|eot)$/,
    /\.(?:css|js)$/
];

/**
 * Install event - cache critical resources
 */
self.addEventListener('install', (event) => {
    console.log('[ServiceWorker] Install event');
    
    event.waitUntil(
        (async () => {
            try {
                const cache = await caches.open(CACHE_NAME);
                console.log('[ServiceWorker] Caching critical files');
                await cache.addAll(CRITICAL_CACHE_FILES);
                console.log('[ServiceWorker] Critical files cached successfully');
                
                // Skip waiting to activate immediately
                self.skipWaiting();
            } catch (error) {
                console.error('[ServiceWorker] Failed to cache critical files:', error);
            }
        })()
    );
});

/**
 * Activate event - clean up old caches
 */
self.addEventListener('activate', (event) => {
    console.log('[ServiceWorker] Activate event');
    
    event.waitUntil(
        (async () => {
            try {
                // Take control of all clients immediately
                await self.clients.claim();
                
                // Clean up old caches
                const cacheNames = await caches.keys();
                const cachesToDelete = cacheNames.filter(cacheName => 
                    cacheName !== CACHE_NAME && 
                    cacheName !== API_CACHE_NAME && 
                    cacheName !== IMAGES_CACHE_NAME
                );
                
                await Promise.all(
                    cachesToDelete.map(cacheName => {
                        console.log('[ServiceWorker] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    })
                );
                
                console.log('[ServiceWorker] Activation complete');
            } catch (error) {
                console.error('[ServiceWorker] Activation failed:', error);
            }
        })()
    );
});

/**
 * Fetch event - implement caching strategies
 */
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests and chrome-extension requests
    if (request.method !== 'GET' || url.protocol === 'chrome-extension:') {
        return;
    // Handle non-GET requests (e.g., POST/PUT/DELETE) and chrome-extension requests
    if (url.protocol === 'chrome-extension:') {
        return;
    }
    if (request.method !== 'GET') {
        // Log the non-GET request for debugging/monitoring
        console.log('[ServiceWorker] Non-GET request intercepted:', request.method, request.url);
        // Optionally, queue the request for background sync if offline
        // For now, respond with a generic offline message if offline
        event.respondWith(
            (async () => {
                if (!self.navigator.onLine) {
                    return new Response(
                        JSON.stringify({
                            error: 'Offline',
                            message: 'Your request will be sent when connectivity is restored.',
                            method: request.method,
                            url: request.url
                        }),
                        {
                            status: 503,
                            headers: { 'Content-Type': 'application/json' }
                        }
                    );
                } else {
                    // If online, just fetch as normal
                    return fetch(request);
                }
            })()
        );
        return;
    }
    
    // Handle different types of requests with appropriate strategies
    if (isAPIRequest(request)) {
        event.respondWith(handleAPIRequest(request));
    } else if (isAssetRequest(request)) {
        event.respondWith(handleAssetRequest(request));
    } else if (isNavigationRequest(request)) {
        event.respondWith(handleNavigationRequest(request));
    } else {
        event.respondWith(handleGenericRequest(request));
    }
});

/**
 * Background sync event for offline data synchronization
 */
self.addEventListener('sync', (event) => {
    console.log('[ServiceWorker] Background sync event:', event.tag);
    
    if (event.tag === 'staff-data-sync') {
        event.waitUntil(syncStaffData());
    } else if (event.tag === 'training-progress-sync') {
        event.waitUntil(syncTrainingProgress());
    } else if (event.tag === 'offline-actions-sync') {
        event.waitUntil(syncOfflineActions());
    }
});

/**
 * Push notification event
 */
self.addEventListener('push', (event) => {
    console.log('[ServiceWorker] Push notification received');
    
    let notificationData = {
        title: 'ARMIS Notification',
        body: 'You have a new notification',
        icon: '/assets/icons/icon-192x192.png',
        badge: '/assets/icons/badge-72x72.png',
        tag: 'armis-notification',
        requireInteraction: false,
        data: {
            url: '/admin_branch/index.php'
        }
    };
    
    if (event.data) {
        try {
            const pushData = event.data.json();
            notificationData = { ...notificationData, ...pushData };
        } catch (error) {
            console.error('[ServiceWorker] Error parsing push data:', error);
        }
    }
    
    event.waitUntil(
        self.registration.showNotification(notificationData.title, notificationData)
    );
});

/**
 * Notification click event
 */
self.addEventListener('notificationclick', (event) => {
    console.log('[ServiceWorker] Notification clicked');
    
    event.notification.close();
    
    const urlToOpen = event.notification.data?.url || '/admin_branch/index.php';
    
    event.waitUntil(
        self.clients.matchAll({ type: 'window' }).then(clientList => {
            // If a client is already open, focus it
            for (const client of clientList) {
                if (client.url === urlToOpen && 'focus' in client) {
                    return client.focus();
                }
            }
            
            // Otherwise, open a new window
            if (self.clients.openWindow) {
                return self.clients.openWindow(urlToOpen);
            }
        })
    );
});

/**
 * Message event for communication with main thread
 */
self.addEventListener('message', (event) => {
    const { type, data } = event.data;
    
    switch (type) {
        case 'SKIP_WAITING':
            self.skipWaiting();
            break;
            
        case 'CACHE_URLS':
            event.waitUntil(
                cacheUrls(data.urls).then(() => {
                    event.ports[0].postMessage({ success: true });
                }).catch(error => {
                    event.ports[0].postMessage({ success: false, error: error.message });
                })
            );
            break;
            
        case 'CLEAR_CACHE':
            event.waitUntil(
                clearCache(data.cacheName).then(() => {
                    event.ports[0].postMessage({ success: true });
                }).catch(error => {
                    event.ports[0].postMessage({ success: false, error: error.message });
                })
            );
            break;
            
        case 'GET_CACHE_SIZE':
            event.waitUntil(
                getCacheSize().then(size => {
                    event.ports[0].postMessage({ size });
                }).catch(error => {
                    event.ports[0].postMessage({ error: error.message });
                })
            );
            break;
    }
});

/**
 * Handle API requests with network-first strategy
 */
async function handleAPIRequest(request) {
    const cache = await caches.open(API_CACHE_NAME);
    
    try {
        // Try network first for fresh data
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Cache successful responses
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('[ServiceWorker] Network request failed, trying cache:', error);
        
        // Fall back to cache
        const cachedResponse = await cache.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline response for API requests
        return new Response(
            JSON.stringify({ 
                error: 'Offline', 
                message: 'This request requires an internet connection',
                offline: true
            }),
            {
                status: 503,
                statusText: 'Service Unavailable',
                headers: { 'Content-Type': 'application/json' }
            }
        );
    }
}

/**
 * Handle asset requests with cache-first strategy
 */
async function handleAssetRequest(request) {
    const cache = await caches.open(IMAGES_CACHE_NAME);
    
    // Try cache first for assets
    const cachedResponse = await cache.match(request);
    if (cachedResponse) {
        return cachedResponse;
    }
    
    try {
        // Fetch from network and cache
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('[ServiceWorker] Asset request failed:', error);
        
        // Return placeholder for failed image requests
        if (request.destination === 'image') {
            return new Response(
                '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="200" height="200" fill="#f0f0f0"/><text x="50%" y="50%" text-anchor="middle" dy=".3em" fill="#999">Image Unavailable</text></svg>',
                { headers: { 'Content-Type': 'image/svg+xml' } }
            );
        }
        
        throw error;
    }
}

/**
 * Handle navigation requests with cache-first for critical pages
 */
async function handleNavigationRequest(request) {
    const cache = await caches.open(CACHE_NAME);
    
    try {
        // Try network first
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Cache successful navigation responses
            if (shouldCacheNavigation(request)) {
                cache.put(request, networkResponse.clone());
            }
        }
        
        return networkResponse;
    } catch (error) {
        console.log('[ServiceWorker] Navigation request failed, trying cache:', error);
        
        // Try cache for critical pages
        const cachedResponse = await cache.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline page
        return cache.match(OFFLINE_URL);
    }
}

/**
 * Handle generic requests
 */
async function handleGenericRequest(request) {
    try {
        return await fetch(request);
    } catch (error) {
        console.log('[ServiceWorker] Generic request failed:', error);
        
        const cache = await caches.open(CACHE_NAME);
        const cachedResponse = await cache.match(request);
        
        if (cachedResponse) {
            return cachedResponse;
        }
        
        throw error;
    }
}

/**
 * Check if request is for API endpoint
 */
function isAPIRequest(request) {
    return API_CACHE_PATTERNS.some(pattern => pattern.test(request.url));
}

/**
 * Check if request is for assets
 */
function isAssetRequest(request) {
    return ASSET_CACHE_PATTERNS.some(pattern => pattern.test(request.url));
}

/**
 * Check if request is for navigation
 */
function isNavigationRequest(request) {
    return request.mode === 'navigate' || 
           (request.method === 'GET' && request.headers.get('accept').includes('text/html'));
}

/**
 * Check if navigation should be cached
 */
function shouldCacheNavigation(request) {
    const url = new URL(request.url);
    return RUNTIME_CACHE_FILES.some(pattern => url.pathname.includes(pattern));
}

/**
 * Sync staff data when back online
 */
async function syncStaffData() {
    try {
        // Get pending staff data from IndexedDB
        const pendingData = await getFromIndexedDB('pending-staff-updates');
        
        for (const data of pendingData) {
            const response = await fetch('/admin_branch/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            if (response.ok) {
                await removeFromIndexedDB('pending-staff-updates', data.id);
            }
        }
        
        console.log('[ServiceWorker] Staff data synchronized successfully');
    } catch (error) {
        console.error('[ServiceWorker] Failed to sync staff data:', error);
    }
}

/**
 * Sync training progress when back online
 */
async function syncTrainingProgress() {
    try {
        const pendingProgress = await getFromIndexedDB('pending-training-progress');
        
        for (const progress of pendingProgress) {
            const response = await fetch('/training/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(progress)
            });
            
            if (response.ok) {
                await removeFromIndexedDB('pending-training-progress', progress.id);
            }
        }
        
        console.log('[ServiceWorker] Training progress synchronized successfully');
    } catch (error) {
        console.error('[ServiceWorker] Failed to sync training progress:', error);
    }
}

/**
 * Sync offline actions when back online
 */
async function syncOfflineActions() {
    try {
        const offlineActions = await getFromIndexedDB('offline-actions');
        
        for (const action of offlineActions) {
            const response = await fetch(action.url, {
                method: action.method,
                headers: action.headers,
                body: action.body
            });
            
            if (response.ok) {
                await removeFromIndexedDB('offline-actions', action.id);
            }
        }
        
        console.log('[ServiceWorker] Offline actions synchronized successfully');
    } catch (error) {
        console.error('[ServiceWorker] Failed to sync offline actions:', error);
    }
}

/**
 * Cache specific URLs
 */
async function cacheUrls(urls) {
    const cache = await caches.open(CACHE_NAME);
    return cache.addAll(urls);
}

/**
 * Clear specific cache
 */
async function clearCache(cacheName) {
    return caches.delete(cacheName);
}

/**
 * Get total cache size
 */
async function getCacheSize() {
    const cacheNames = await caches.keys();
    let totalSize = 0;
    
    for (const cacheName of cacheNames) {
        const cache = await caches.open(cacheName);
        const requests = await cache.keys();
        
        for (const request of requests) {
            const response = await cache.match(request);
            if (response) {
                const blob = await response.blob();
                totalSize += blob.size;
            }
        }
    }
    
    return totalSize;
}

/**
 * IndexedDB helper functions
 */
async function getFromIndexedDB(storeName) {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('armis-offline', 1);
        
        request.onsuccess = (event) => {
            const db = event.target.result;
            const transaction = db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const getAllRequest = store.getAll();
            
            getAllRequest.onsuccess = () => {
                resolve(getAllRequest.result);
            };
            
            getAllRequest.onerror = () => {
                reject(getAllRequest.error);
            };
        };
        
        request.onerror = () => {
            reject(request.error);
        };
    });
}

async function removeFromIndexedDB(storeName, id) {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('armis-offline', 1);
        
        request.onsuccess = (event) => {
            const db = event.target.result;
            const transaction = db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const deleteRequest = store.delete(id);
            
            deleteRequest.onsuccess = () => {
                resolve();
            };
            
            deleteRequest.onerror = () => {
                reject(deleteRequest.error);
            };
        };
        
        request.onerror = () => {
            reject(request.error);
        };
    });
}
=======

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
