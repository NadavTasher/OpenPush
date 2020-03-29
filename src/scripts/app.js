function load() {
    Notifier.init(30);
}

function generate() {
    token((token) => {
        let URL = window.location.origin + "/apis/notify/?api=" + (JSON.stringify({
            notify: {
                action: "notify",
                parameters: {
                    title: "Your title",
                    message: "Your message",
                    token: token
                }
            }
        }));
        // Show the URL to the user
        window.open(URL, "_blank");
    });
}

function issue() {
    token((token) => {
        prompt("Copy your token:", token);
    });
}

function token(callback = null) {
    API.send("notify", "issue", {
        application: prompt("Enter your service name:", "OpenPush GUI")
    }, (success, token) => {
        if (success) {
            if (callback !== null)
                callback(token);
        }
    }, Authenticate.authenticate());
}