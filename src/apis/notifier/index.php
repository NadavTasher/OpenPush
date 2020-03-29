<?php
// Include the notifier API
include_once __DIR__ . DIRECTORY_SEPARATOR . "api.php";
// Initialize the base API
Base::init();
// Handle the API call
Notifier::handle();
// Echo the results
Base::echo();