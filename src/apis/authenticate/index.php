<?php
// Include the authenticate API
include_once __DIR__ . DIRECTORY_SEPARATOR . "api.php";
// Initialize the base API
API::init();
// Handle the API call
Authenticate::handle();
// Echo the results
API::echo();