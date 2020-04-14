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
        if ("serviceWorker" in navigator) {
            navigator.serviceWorker.register("worker.js", {scope: "./"}).then((registration) => {
                window.worker = registration.active;
            });
        }
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
     */
    static call(endpoint = null, action = null, parameters = null, callback = null) {
        // Create a form
        let form = new FormData();
        // Append parameters to form
        for (let key in parameters) {
            if (parameters.hasOwnProperty(key))
                form.append(key, parameters[key]);
        }
        // Perform the request
        fetch("apis/" + endpoint + "/" + "?" + action, {
            method: "post",
            body: form
        }).then(response => response.text().then((result) => {
            // Try to parse the result as JSON
            try {
                let API = JSON.parse(result);
                // Check the result's integrity
                if (API.hasOwnProperty("status") && API.hasOwnProperty("result")) {
                    // Call the callback with the result
                    callback(API["status"] === true, API["result"]);
                } else {
                    // Call the callback with an error
                    callback(false, "API response malformed");
                }
            } catch (ignored) {
            }
        }));
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

    /**
     * Finds a template and creates a clone of it.
     * @param template Template name
     * @param parameters Cloning parameters
     */
    static create(template, parameters = {}) {
        // Find the template
        let templateElements = document.getElementsByTagName("template");
        // Find the template
        for (let templateElement of templateElements) {
            if (templateElement.getAttribute("name").toLowerCase() === template.toLowerCase()) {
                // Create the element
                let created = document.createElement("div");
                // Add the HTML
                let html = templateElement.innerHTML;
                // Replace parameters
                for (let key in parameters) {
                    if (key in parameters) {
                        let search = "${" + key + "}";
                        while (html.includes(search))
                            html = html.replace(search, parameters[key]);
                    }
                }
                created.innerHTML = html;
                // Return created element
                return created.children[0];
            }
        }
        // Result default undefined
        return undefined;
    }

}