/**
 * Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
 * All rights reserved.
 *
 * This source code is proprietary and confidential.
 * Unauthorised copying, modification, or distribution is strictly prohibited.
 */

/**
 * ============================================================================
 * 📱 Go2My.Link — Service Worker (Admin Dashboard — admin.go2my.link)
 * ============================================================================
 *
 * Network-first strategy for dynamic PHP content with cache fallback
 * for static assets (CSS, JS, icons, fonts).
 *
 * @package    Go2My.Link
 * @subpackage PWA
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.7.0
 * @since      Phase 6
 *
 * 📖 Reference: https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API
 * ============================================================================
 */

// 🏷️ Cache version — bump this to invalidate old caches on deployment
const CACHE_NAME = 'go2mylink-admin-v1';

// 📦 App shell — static assets to pre-cache on install
const APP_SHELL = [
    '/',
    '/css/style.css',
    '/js/theme.js',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
    '/manifest.json'
];

// ============================================================================
// 📥 Install — Pre-cache the app shell
// ============================================================================
self.addEventListener('install', function(event)
{
    event.waitUntil(
        caches.open(CACHE_NAME).then(function(cache)
        {
            return cache.addAll(APP_SHELL);
        })
    );

    // Activate immediately without waiting for existing clients to close
    self.skipWaiting();
});

// ============================================================================
// ♻️ Activate — Clean up old caches from previous versions
// ============================================================================
self.addEventListener('activate', function(event)
{
    event.waitUntil(
        caches.keys().then(function(cacheNames)
        {
            return Promise.all(
                cacheNames.map(function(name)
                {
                    if (name !== CACHE_NAME)
                    {
                        return caches.delete(name);
                    }
                })
            );
        })
    );

    // Claim all open clients immediately
    self.clients.claim();
});

// ============================================================================
// 🌐 Fetch — Network-first for pages, cache-first for static assets
// ============================================================================
self.addEventListener('fetch', function(event)
{
    var request = event.request;

    // Only handle GET requests
    if (request.method !== 'GET')
    {
        return;
    }

    // Skip cross-origin requests (CDN resources are handled by browser cache)
    if (!request.url.startsWith(self.location.origin))
    {
        return;
    }

    // Determine if this is a static asset (CSS, JS, images, fonts, manifest)
    var url = new URL(request.url);
    var isStaticAsset = /\.(css|js|png|jpg|jpeg|gif|svg|ico|woff2?|json)$/i.test(url.pathname);

    if (isStaticAsset)
    {
        // 📦 Cache-first for static assets — fast loads, updated on next SW update
        event.respondWith(
            caches.match(request).then(function(cachedResponse)
            {
                if (cachedResponse)
                {
                    return cachedResponse;
                }

                return fetch(request).then(function(networkResponse)
                {
                    // Cache the new static asset for future use
                    if (networkResponse.ok)
                    {
                        var responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then(function(cache)
                        {
                            cache.put(request, responseClone);
                        });
                    }

                    return networkResponse;
                });
            })
        );
    }
    else
    {
        // 🌐 Network-first for HTML/PHP pages — always get fresh content
        event.respondWith(
            fetch(request).then(function(networkResponse)
            {
                // Cache the page response for offline fallback
                if (networkResponse.ok)
                {
                    var responseClone = networkResponse.clone();
                    caches.open(CACHE_NAME).then(function(cache)
                    {
                        cache.put(request, responseClone);
                    });
                }

                return networkResponse;
            }).catch(function()
            {
                // Offline — try the cache, then fall back to the homepage
                return caches.match(request).then(function(cachedResponse)
                {
                    return cachedResponse || caches.match('/');
                });
            })
        );
    }
});
