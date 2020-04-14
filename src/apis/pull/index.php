<?php

/**
 * Copyright (c) 2020 Nadav Tasher
 * https://github.com/NadavTasher/AuthenticationTemplate/
 **/

// Include the Authenticate API
include_once __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "authenticate" . DIRECTORY_SEPARATOR . "api.php";

// Initialize the authenticate API
Authenticate::initialize();

// Initialize the manager API
Manager::initialize();

// Handle the API call
Base::handle(function ($action, $parameters) {
    // Try authenticating user
    if (isset($parameters->token)) {
        if (is_string($parameters->token)) {
            $userID = Authenticate::validate($parameters->token);
            if ($userID[0]) {
                return Manager::pull($userID[1]);
            }
        }
    }
    // Return fallback
    return [false, "Authentication failure"];
});