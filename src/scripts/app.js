function load() {
    UI.view("home");
}

function generate() {
    let application = prompt("Enter your service name:", "OpenPush GUI");
    if (application !== null && application.length > 0) {
        API.send("push", "issue", {
            application: application
        }, (success, token) => {
            if (success) {
                // Create URL
                let url = window.location.origin + "/apis/push/?api=" + (JSON.stringify({
                    push: {
                        action: "push",
                        parameters: {
                            title: "Your title",
                            message: "Your message",
                            token: token
                        }
                    }
                }));
                // Set values and onclicks
                let tokenHolder = UI.find("token");
                let urlHolder = UI.find("url");
                // Set value
                tokenHolder.value = token;
                urlHolder.value = url;
                // Set onclick
                tokenHolder.addEventListener("click", function () {
                    tokenHolder.select();
                    tokenHolder.setSelectionRange(0, tokenHolder.value.length);
                    document.execCommand("copy");
                });
                urlHolder.addEventListener("click", function () {
                    urlHolder.select();
                    urlHolder.setSelectionRange(0, urlHolder.value.length);
                    document.execCommand("copy");
                });
                // Change view
                UI.view("result");
            }
        }, Authenticate.authenticate());
    }
}