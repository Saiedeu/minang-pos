/**
 * Service Worker for Minang Restaurant POS
 * Handles caching and offline functionality
 */

const CACHE_NAME = 'minang-pos-v1.2.0';
const urlsToCache = [
    '/pos/',
    '/pos/dashboard.php',
    '/pos/mobile-pos.php',
    '/assets/css/common.css',
    '/assets/js/common.js',
    '/assets/manifest.json',
    'https://cdn.tailwindcss.com',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
    'https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js'
];

// Install event - cache resources
self.addEventListener('install', (event) => {
    console.log('[SW] Installing service worker...');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[SW] Caching app shell');
                return cache.addAll(urlsToCache);
            })
            .catch((error) => {
                console.error('[SW] Caching failed:', error);
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating service worker...');
    
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('[SW] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', (event) => {
    // Skip cross-origin requests
    if (!event.request.url.startsWith(self.location.origin)) {
        return;
    }
    
    // Skip API requests - always go to network
    if (event.request.url.includes('/api/')) {
        event.respondWith(
            fetch(event.request)
                .then((response) => {
                    // Clone the response for caching
                    const responseToCache = response.clone();
                    
                    // Cache successful API responses
                    if (response.status === 200) {
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(event.request, responseToCache);
                        });
                    }
                    
                    return response;
                })
                .catch(() => {
                    // Return cached response if network fails
                    return caches.match(event.request);
                })
        );
        return;
    }
    
    // For other requests, try cache first, then network
    event.respondWith(
        caches.match(event.request)
            .then((response) => {
                // Return cached version if available
                if (response) {
                    console.log('[SW] Serving from cache:', event.request.url);
                    return response;
                }
                
                // Otherwise, fetch from network
                console.log('[SW] Fetching from network:', event.request.url);
                return fetch(event.request).then((response) => {
                    // Don't cache if not successful
                    if (!response || response.status !== 200 || response.type !== 'basic') {
                        return response;
                    }
                    
                    // Clone the response for caching
                    const responseToCache = response.clone();
                    
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseToCache);
                    });
                    
                    return response;
                });
            })
            .catch(() => {
                // Return offline page for navigation requests
                if (event.request.mode === 'navigate') {
                    return caches.match('/offline.html');
                }
            })
    );
});

// Background sync for offline transactions
self.addEventListener('sync', (event) => {
    console.log('[SW] Background sync triggered:', event.tag);
    
    if (event.tag === 'sync-sales') {
        event.waitUntil(syncOfflineSales());
    }
});

// Push notification handling
self.addEventListener('push', (event) => {
    console.log('[SW] Push notification received');
    
    const options = {
        body: event.data ? event.data.text() : 'New notification from Minang POS',
        icon: '/assets/icons/icon-192x192.png',
        badge: '/assets/icons/badge-72x72.png',
        vibrate: [100, 50, 100],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        },
        actions: [
            {
                action: 'explore',
                title: 'Open POS',
                icon: '/assets/icons/icon-192x192.png'
            },
            {
                action: 'close',
                title: 'Close notification',
                icon: '/assets/icons/icon-192x192.png'
            }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification('Minang Restaurant POS', options)
    );
});

// Notification click handling
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification clicked:', event.action);
    
    event.notification.close();
    
    if (event.action === 'explore') {
        event.waitUntil(
            clients.openWindow('/pos/dashboard.php')
        );
    }
});

// Sync offline sales data
async function syncOfflineSales() {
    try {
        // Get offline sales from IndexedDB
        const offlineSales = await getOfflineSales();
        
        for (const sale of offlineSales) {
            try {
                const response = await fetch('/api/sales.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'create_sale',
                        sale_data: sale.saleData,
                        sale_items: sale.saleItems
                    })
                });
                
                if (response.ok) {
                    // Remove from offline storage
                    await removeOfflineSale(sale.id);
                    console.log('[SW] Synced offline sale:', sale.id);
                }
            } catch (error) {
                console.error('[SW] Failed to sync sale:', error);
            }
        }
    } catch (error) {
        console.error('[SW] Background sync failed:', error);
    }
}

// Helper functions for offline storage
async function getOfflineSales() {
    // Implementation would use IndexedDB
    return [];
}

async function removeOfflineSale(saleId) {
    // Implementation would remove from IndexedDB
    console.log('[SW] Removing offline sale:', saleId);
}

// Update cache when new version available
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'UPDATE_CACHE') {
        event.waitUntil(
            caches.open(CACHE_NAME).then((cache) => {
                return cache.addAll(urlsToCache);
            })
        );
    }
});

console.log('[SW] Service worker loaded successfully');