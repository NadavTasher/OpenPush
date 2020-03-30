<?php

/**
 * Copyright (c) 2020 Nadav Tasher
 * https://github.com/NadavTasher/AuthenticationTemplate/
 **/

// Include the Authenticate API
include_once __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "authenticate" . DIRECTORY_SEPARATOR . "api.php";

// Initialize the base API
Base::init();

// Initialize the manager API
Manager::init();

// Handle the API call
Base::handle("pull", function () {
    // Try authenticating user
    $userID = Authenticate::handle();
    if ($userID !== null) {
        return Manager::pull($userID);
    }
    // Return fallback
    return [false, "Authentication failure"];
}, true);

// Echo the results
Base::echo();