function load() {
    // Send to app
    if (window.hasOwnProperty("android")) {
        if (window.android.hasOwnProperty("setToken")) {
            window.android.setToken(Authenticate.token);
        }
    }
    // View home
    UI.view("home");
}

function generate() {
    // Prompt for service name
    let application = prompt("Enter your service name:", "OpenPush GUI");
    // Validate service name
    if (application !== null && application.length > 0) {
        // Send an issuing request
        API.call("push", "issue", {
            application: application,
            token: Authenticate.token
        }, (success, token) => {
            if (success) {
                // Create URL
                let url = window.location.origin + "/apis/push/?push&title=" + encodeURIComponent("Your title") + "&message=" + encodeURIComponent("Your message") + "&token=" + encodeURIComponent(token);
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
        });
    }
}