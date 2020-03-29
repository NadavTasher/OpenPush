/**
 * Copyright (c) 2019 Nadav Tasher
 * https://github.com/NadavTasher/AuthenticationTemplate/
 **/

const AUTHENTICATE_API = "authenticate";
const NOTIFIER_API = "notifier";

/**
 * Authenticate API for user authentication.
 */
class Authenticate {

    /**
     * Authenticates the user by requiring signup, signin and session validation.
     * @param callback Post authentication callback
     */
    static authentication(callback = null) {
        // View the authentication panel
        UI.page("authenticate");
        // Check authentication
        let token = PathStorage.getItem(AUTHENTICATE_API);
        if (token !== null) {
            // Hide the inputs
            UI.hide("authenticate-inputs");
            // Change the output message
            this.output("Hold on - Authenticating...");
            // Send the API call
            API.call(AUTHENTICATE_API, this.authenticate((success, result) => {
                if (success) {
                    // Change the page
                    UI.page("authenticated");
                    // Run the callback
                    if (callback !== null) {
                        callback();
                    }
                } else {
                    // Show the inputs
                    UI.show("authenticate-inputs");
                    // Change the output message
                    this.output(result, true);
                }
            }));
        }
    }

    /**
     * Compiles an authenticated API hook.
     * @param callback Callback
     * @param APIs Inherited APIs
     * @return API list
     */
    static authenticate(callback = null, APIs = API.hook()) {
        // Check if the session cookie exists
        let token = PathStorage.getItem(AUTHENTICATE_API);
        if (token !== null) {
            // Compile the API hook
            APIs = API.hook(AUTHENTICATE_API, "authenticate", {
                token: token
            }, callback, APIs);
        }
        return APIs;
    }

    /**
     * Sends a signup API call and handles the results.
     */
    static signUp(callback = null) {
        // Hide the inputs
        UI.hide("authenticate-inputs");
        // Change the output message
        this.output("Hold on - Signing you up...");
        // Send the API call
        API.send(AUTHENTICATE_API, "signup", {
            name: UI.find("authenticate-name").value,
            password: UI.find("authenticate-password").value
        }, (success, result) => {
            if (success) {
                // Call the signin function
                this.signIn(callback);
            } else {
                // Show the inputs
                UI.show("authenticate-inputs");
                // Change the output message
                this.output(result, true);
            }
        });
    }

    /**
     * Sends a signin API call and handles the results.
     */
    static signIn(callback = null) {
        // Hide the inputs
        UI.hide("authenticate-inputs");
        // Change the output message
        this.output("Hold on - Signing you in...");
        // Send the API call
        API.send(AUTHENTICATE_API, "signin", {
            name: UI.find("authenticate-name").value,
            password: UI.find("authenticate-password").value
        }, (success, result) => {
            if (success) {
                // Push the session cookie
                PathStorage.setItem(AUTHENTICATE_API, result);
                // Call the authentication function
                this.authentication(callback);
            } else {
                // Show the inputs
                UI.show("authenticate-inputs");
                // Change the output message
                this.output(result, true);
            }
        });
    }

    /**
     * Signs the user out.
     */
    static signOut() {
        // Push 'undefined' to the session cookie
        PathStorage.removeItem(AUTHENTICATE_API);
    }

    /**
     * Changes the output message.
     * @param text Output message
     * @param error Is the message an error?
     */
    static output(text, error = false) {
        // Store the output view
        let output = UI.find("authenticate-output");
        // Set the output message
        output.innerText = text;
        // Check if the message is an error
        if (error) {
            // Set the text color to red
            output.style.setProperty("color", "red");
        } else {
            // Clear the text color
            output.style.removeProperty("color");
        }
    }

}

/**
 * Authenticate API for notification delivery.
 */
class Notifier {

    /**
     * Start the pull loop.
     */
    static init(timeout = 60, callback = this.notify) {
        // Start the interval
        setInterval(() => {
            this.checkout(callback);
        }, timeout * 1000);
    }

    /**
     * Fetches the latest messages from the notification delivery API.
     */
    static checkout(callback = null) {
        API.send(NOTIFIER_API, "checkout", {}, (success, result) => {
            if (success) {
                // Send notifications
                if (callback !== null) {
                    for (let notification of result) {
                        callback(notification);
                    }
                }
            }
        }, Authenticate.authenticate());
    }

    /**
     * Default checkout callback.
     * @param notification Notification
     */
    static notify(notification) {
        // Check compatibility
        if ("Notification" in window) {
            // Parse object
            let notificationTitle = notification.title || "No Title";
            let notificationMessage = notification.message || undefined;
            // Create options object
            let notificationOptions = {
                body: notificationMessage,
                icon: "images/icons/icon.png",
                badge: "images/icons/icon.png"
            };
            // Check permission
            if (Notification.permission === "granted") {
                // Send notification
                new Notification(notificationTitle, notificationOptions);
            } else {
                // Request permission
                Notification.requestPermission().then((permission) => {
                    // Check permission
                    if (permission === "granted") {
                        // Send notification
                        new Notification(notificationTitle, notificationOptions);
                    }
                });
            }
        }
    }

}