/**
 * Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
 * All rights reserved.
 *
 * This source code is proprietary and confidential.
 * Unauthorised copying, modification, or distribution is strictly prohibited.
 */

/**
 * ============================================================================
 * 📱 Lnks.page — Service Worker (Component C — LinksPage)
 * ============================================================================
 *
 * Caches static files only. Pages are never stored: the previous worker saved
 * a copy of every page it fetched, which stayed readable offline to the next
 * person using the same browser (#213, owner decision 43 on #243).
 *
 *   - Pages (navigation requests): always from the network. When the
 *     network is down, the small offline page is shown instead.
 *   - Static files listed below: from the cache, while a fresh copy is
 *     fetched in the background for next time (asking the server, not the
 *     browser's own week-long cache), so a deploy reaches returning visitors
 *     on their next visit. The offline page is fetched fresh when the worker
 *     installs.
 *   - Anything with a query string, and any other request: not touched.
 *
 * Bump CACHE_NAME when this list changes; activation deletes older caches,
 * including the "-v1" one that held saved pages.
 *
 * @package    Go2My.Link
 * @subpackage PWA
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.8.0
 * @since      Phase 6
 *
 * 📖 Reference: https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API
 * ============================================================================
 */

const CACHE_NAME = 'lnkspage-v2';

// A plain English file (offline.html explains why). A LinksPage name cannot
// contain a dot, so this address takes no name away from anybody.
const OFFLINE_PAGE = '/offline.html';

const STATIC_FILES = [
    '/offline.html',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
    '/manifest.json'
];

self.addEventListener('install', function(event)
{
    event.waitUntil(
        caches.open(CACHE_NAME).then(function(cache)
        {
            // cache: 'reload' asks the server, so a new cache never starts
            // with old copies from the browser's own cache.
            var freshRequests = STATIC_FILES.map(function(path)
            {
                return new Request(path, { cache: 'reload' });
            });
            return cache.addAll(freshRequests);
        })
    );
    self.skipWaiting();
});

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
                    return Promise.resolve(false);
                })
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', function(event)
{
    var request = event.request;

    if (request.method !== 'GET')
    {
        return;
    }

    var url = new URL(request.url);

    if (url.origin !== self.location.origin)
    {
        return;
    }

    if (request.mode === 'navigate')
    {
        event.respondWith(
            fetch(request).catch(function()
            {
                return caches.match(OFFLINE_PAGE).then(function(offlineResponse)
                {
                    if (offlineResponse)
                    {
                        return offlineResponse;
                    }
                    return Response.error();
                });
            })
        );
        return;
    }

    if (url.search !== '' || STATIC_FILES.indexOf(url.pathname) === -1)
    {
        return;
    }

    // A fresh copy for next time. waitUntil keeps the worker alive until it
    // is stored. If storing fails (storage full), the server's copy is still
    // returned; if the network fails, the cached copy is served when there
    // is one.
    var refresh = fetch(request, { cache: 'no-cache' }).then(function(networkResponse)
    {
        if (!networkResponse.ok)
        {
            return networkResponse;
        }

        var copyToStore = networkResponse.clone();

        return caches.open(CACHE_NAME).then(function(cache)
        {
            return cache.put(request, copyToStore);
        }).then(function()
        {
            return networkResponse;
        }, function()
        {
            return networkResponse;
        });
    });

    event.waitUntil(refresh.catch(function()
    {
        return undefined;
    }));

    event.respondWith(
        caches.match(request, { cacheName: CACHE_NAME }).then(function(cachedResponse)
        {
            if (cachedResponse)
            {
                return cachedResponse;
            }
            return refresh;
        })
    );
});
