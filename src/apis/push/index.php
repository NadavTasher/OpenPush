<?php

// Include the Authenticate API
include_once __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "authenticate" . DIRECTORY_SEPARATOR . "api.php";

// Initialize the authenticate API
Authenticate::initialize();

// Initialize the manager API
Manager::initialize();

// Handle the API call
Base::handle(function ($action, $parameters) {
    // Initialize the authority
    $authority = new Authority("push");
    // Switch action
    if ($action === "issue") {
        if (isset($parameters->application) && is_string($parameters->application) && isset($parameters->token) && is_string($parameters->token)) {
            // Authenticate the user to issue a new token
            $userID = Authenticate::validate($parameters->token);
            if ($userID[0]) {
                // Create token object
                $tokenObject = new stdClass();
                $tokenObject->id = $userID[1];
                $tokenObject->application = $parameters->application;
                // Notify the user
                Manager::push($userID[1], "You just allowed \"" . $parameters->application . "\" to send you notifications.");
                // Issue the token
                return $authority->issue($tokenObject, ["notify"], 60 * 60 * 24 * 365);
            }
            return [false, "Authentication failure"];
        }
        return [false, "Parameter error"];
    } else if ($action === "push") {
        if (isset($parameters->token) && is_string($parameters->token)) {
            // Validate token
            $validation = $authority->validate($parameters->token, ["notify"]);
            // Check result
            if ($validation[0]) {
                // User ID from token
                $userID = $validation[1]->id;
                // Check inputs
                $title = "Message from " . $validation[1]->application;
                $message = null;
                // Check title
                if (isset($parameters->title) && is_string($parameters->title)) {
                    $title = $parameters->title;
                }
                // Check message
                if (isset($parameters->message) && is_string($parameters->message)) {
                    $message = $parameters->message;
                }
                // Notify user
                Manager::push($userID, $title, $message);
                // Return OK
                return [true, "Pushed"];
            }
            return $validation;
        }
        return [false, "Parameter error"];
    }
    // Fallback error
    return [false, "Undefined hook"];
});