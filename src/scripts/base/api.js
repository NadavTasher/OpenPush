/**
 * Copyright (c) 2019 Nadav Tasher
 * https://github.com/NadavTasher/BaseTemplate/
 **/

/**
 * Prepares the web page (loads ServiceWorker).
 * @param callback Function to be executed when loading finishes
 */
if (typeof window !== typeof undefined) {
    window.prepare = function (callback = null) {
        // Register worker
        if ("serviceWorker" in navigator)
            navigator.serviceWorker.register("worker.js", {scope: "./"}).then();
        // Callback
        if (callback !== null)
            callback();
    };
}

/**
 * Base API for sending requests.
 */
class API {

    /**
     * Sends an API call.
     * @param endpoint API to call
     * @param action Action
     * @param parameters Parameters
     * @param callback Callback
     * @param APIs API list
     */
    static send(endpoint = null, action = null, parameters = null, callback = null, APIs = {}) {
        this.call(endpoint, this.hook(endpoint, action, parameters, callback, APIs));
    }

    /**
     * Makes an API call.
     * @param endpoint Endpoint API
     * @param APIs API list
     */
    static call(endpoint, APIs = {}) {
        // Make sure the APIs list is well structured
        if (APIs.hasOwnProperty("apis") && APIs.hasOwnProperty("callbacks")) {
            // Create a form
            let form = new FormData();
            // Append the compiled hook as "api"
            form.append("api", JSON.stringify(APIs.apis));
            // Make sure the device is online
            if (typeof window === typeof undefined || window.navigator.onLine) {
                // Perform the request
                fetch("apis/" + endpoint + "/", {
                    method: "post",
                    body: form
                }).then(response => {
                    response.text().then((result) => {
                        // Try to parse the result as JSON
                        try {
                            let json = JSON.parse(result);
                            // Loop through APIs
                            for (let api in APIs.callbacks) {
                                // Check if the callback really exists
                                if (APIs.callbacks.hasOwnProperty(api)) {
                                    // Try parsing and calling
                                    try {
                                        // Store the callback
                                        let callback = APIs.callbacks[api];
                                        // Make sure the callback isn't null
                                        if (callback !== null) {
                                            // Make sure the requested API exists in the result
                                            if (json.hasOwnProperty(api)) {
                                                // Check the result's integrity
                                                if (json[api].hasOwnProperty("success") && json[api].hasOwnProperty("result")) {
                                                    // Call the callback with the result
                                                    callback(json[api]["success"] === true, json[api]["result"]);
                                                } else {
                                                    // Call the callback with an error
                                                    callback(false, "API parameters not found");
                                                }
                                            } else {
                                                // Call the callback with an error
                                                callback(false, "API not found");
                                            }
                                        }
                                    } catch (ignored) {
                                    }
                                }
                            }
                        } catch (ignored) {
                        }
                    });
                });
            }
        }
    }

    /**
     * Compiles an API call hook.
     * @param api API name
     * @param action Action
     * @param parameters Parameters
     * @param callback Callback
     * @param APIs API list
     * @returns API list
     */
    static hook(api = null, action = null, parameters = null, callback = null, APIs = {}) {
        // Make sure the APIs list is well structured
        if (!APIs.hasOwnProperty("apis")) {
            APIs["apis"] = {};
        }
        if (!APIs.hasOwnProperty("callbacks")) {
            APIs["callbacks"] = {};
        }
        // Make sure the API isn't already compiled in the API list
        if (!(APIs["apis"].hasOwnProperty(api) || APIs["callbacks"].hasOwnProperty(api))) {
            // Make sure none are null
            if (api !== null && action !== null && parameters !== null) {
                // Compile API
                APIs["apis"][api] = {
                    action: action,
                    parameters: parameters
                };
                // Compile callback
                APIs["callbacks"][api] = callback;
            }
        }
        // Return updated API list
        return APIs;
    }

}

/**
 * Base API for token validation.
 */
class Authority {

    /**
     * Validates a given token and return its contents.
     * @param token Token
     * @param permissions Permissions array
     */
    static validate(token, permissions = []) {
        // Split the token
        let token_parts = token.split(":");
        // Make sure the token is two parts
        if (token_parts.length === 2) {
            // Parse object
            let token_object = JSON.parse(this.hex2bin(token_parts[0]));
            // Validate structure
            if (token_object.hasOwnProperty("contents") && token_object.hasOwnProperty("permissions") && token_object.hasOwnProperty("issuer") && token_object.hasOwnProperty("expiry")) {
                // Validate time
                if (Math.floor(Date.now() / 1000) < token_object.expiry) {
                    // Validate permissions
                    for (let permission of permissions) {
                        // Make sure permission exists
                        if (!token_object.permissions.includes(permission)) {
                            // Fallback error
                            return [false, "Insufficient token permissions"];
                        }
                    }
                    // Return token
                    return [true, token_object.contents];
                }
                // Fallback error
                return [false, "Invalid token expiry"];
            }
            // Fallback error
            return [false, "Invalid token structure"];
        }
        // Fallback error
        return [false, "Invalid token format"];
    }

    /**
     * Converts a hexadecimal string to a raw byte string.
     * @param hexadecimal Hexadecimal string
     * @return {string} String
     */
    static hex2bin(hexadecimal) {
        let string = "";
        for (let n = 0; n < hexadecimal.length; n += 2) {
            string += String.fromCharCode(parseInt(hexadecimal.substr(n, 2), 16));
        }
        return string;
    }

}

/**
 * Base API for storage management.
 */
class PathStorage {

    /**
     * Set a value in the storage.
     * @param key Key
     * @param value Value
     */
    static setItem(key, value) {
        // Load the storage
        let storage = this._load();
        // Put the value
        storage[key] = value;
        // Unload the storage
        this._unload(storage);
    }

    /**
     * Removes a value from the storage.
     * @param key Key
     */
    static removeItem(key) {
        // Load the storage
        let storage = this._load();
        // Put the value
        storage[key] = undefined;
        // Unload the storage
        this._unload(storage);
    }

    /**
     * Get a value from the storage.
     * @param key Key
     */
    static getItem(key) {
        if (this.hasItem(key)) {
            // Load the storage
            let storage = this._load();
            // Pull the value
            return storage[key];
        }
        return null;
    }

    /**
     * Checks is a value exists in the storage.
     * @param key Key
     * @return {boolean} Exists
     */
    static hasItem(key) {
        // Load the storage
        let storage = this._load();
        // Check existence
        return storage.hasOwnProperty(key);
    }

    /**
     * Clears the storage.
     */
    static clear() {
        this._unload({});
    }

    /**
     * Unloads a storage object.
     * @param storage Storage
     */
    static _unload(storage) {
        let storageString = JSON.stringify(storage);
        window.localStorage.setItem(this._path(), storageString);
    }

    /**
     * Loads a storage object.
     * @return {object} Storage
     */
    static _load() {
        let storageString = window.localStorage.getItem(this._path());
        if (storageString !== null) {
            return JSON.parse(storageString);
        } else {
            return {};
        }
    }

    /**
     * Return the current path.
     * @return {string} Path
     */
    static _path() {
        let fullPath = window.location.pathname;
        // Check if its a path
        if (fullPath.endsWith("/")) {
            return fullPath;
        }
        // Remove until the last /
        return fullPath.substr(0, fullPath.lastIndexOf("/"));
    }

}

/**
 * Base API for creating the UI.
 */
class UI {

    /**
     * Returns a view by its ID or by it's own value.
     * @param v View
     * @returns {HTMLElement} View
     */
    static find(v) {
        if (typeof "" === typeof v || typeof '' === typeof v) {
            // ID lookup
            if (document.getElementById(v) !== undefined) {
                return document.getElementById(v);
            }
            // Query lookup
            if (document.querySelector(v) !== undefined) {
                return document.querySelector(v);
            }
        }
        // Return the input
        return v;
    }

    /**
     * Hides a given view.
     * @param v View
     */
    static hide(v) {
        // Set style to none
        this.find(v).setAttribute("hidden", "true");
    }

    /**
     * Shows a given view.
     * @param v View
     */
    static show(v) {
        // Set style to original value
        this.find(v).setAttribute("hidden", "false");
    }

    /**
     * Shows a given view while hiding it's brothers.
     * @param v View
     */
    static view(v) {
        // Store view
        let element = this.find(v);
        // Store parent
        let parent = element.parentNode;
        // Hide all
        for (let child of parent.children) {
            this.hide(child);
        }
        // Show view
        this.show(element);
    }

    /**
     * Sets a given target as the only visible part of the page.
     * @param target View
     */
    static page(target) {
        // Store current target
        let temporary = this.find(target);
        // Recursively get parent
        while (temporary.parentNode !== document.body && temporary.parentNode !== document.body) {
            // View temporary
            this.view(temporary);
            // Set temporary to it's parent
            temporary = temporary.parentNode;
        }
        // View temporary
        this.view(temporary);
    }

    /**
     * Removes all children of a given view.
     * @param v View
     */
    static clear(v) {
        // Store view
        let view = this.find(v);
        // Remove all views
        view.innerHTML = "";
    }

}