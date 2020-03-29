<?php
// Include the notifier API
include_once __DIR__ . DIRECTORY_SEPARATOR . "api.php";
// Initialize the base API
API::init();
// Handle the API call
Notifier::handle();
// Echo the results
API::echo();