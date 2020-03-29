/**
 * Copyright (c) 2020 Nadav Tasher
 * https://github.com/NadavTasher/AuthenticationTemplate/
 **/

const NOTIFIER_API = "notifier";

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
        API.send(NOTIFIER_API, null, null, (success, result) => {
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