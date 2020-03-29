function generate(service = "OpenPush GUI") {
    API.send("notify", "issue", {
        application: service
    }, (success, token) => {
        if (success) {
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
        }
    }, Authenticate.authenticate());
}