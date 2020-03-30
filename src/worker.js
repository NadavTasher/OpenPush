/**
 * Copyright (c) 2019 Nadav Tasher
 * https://github.com/NadavTasher/BaseTemplate/
 **/

const CACHE_NAME = "offline";
const CACHE_FILE = "offline.html";

self.importScripts("scripts/base/api.js", "scripts/authenticate/api.js");

self.addEventListener("install", (event) => {
    // Create cache storage
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            // Fetch the page
            fetch("resources/" + CACHE_FILE).then((response) => {
                // Put in cache
                cache.put(CACHE_FILE, response).then();
            });
        })
    );
});

self.addEventListener("fetch", function (event) {
    // Set a no cache policy
    event.request.cache = "no-store";
    // Try fetching or return a cached response
    event.respondWith(fetch(event.request).then(response => response).catch(() => caches.match(new Request(CACHE_FILE)) || new Response("Offline")));
});

self.addEventListener("message", function (event) {
    // Set the token
    Authenticate.token = event.data;
    // Start the pull service
    Pull.init(self.registration);
});