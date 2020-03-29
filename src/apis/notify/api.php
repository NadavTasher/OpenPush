<?php

/**
 * Copyright (c) 2020 Nadav Tasher
 * https://github.com/NadavTasher/OpenNotifier/
 **/

// Include Base API
include_once __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "base" . DIRECTORY_SEPARATOR . "api.php";

// Include Authenticate API
include_once __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "authenticate" . DIRECTORY_SEPARATOR . "api.php";

// Include Notifier API
include_once __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "notifier" . DIRECTORY_SEPARATOR . "api.php";

/**
 * Notify API.
 */
class Notify
{
    // API string
    public const API = "notify";

    // Permissions
    private const PERMISSION_NOTIFY = "notify-user";

    // Base APIs
    private static Authority $authority;
    private static Database $database;

    /**
     * API initializer.
     */
    public static function init()
    {
        // Initialize database
        self::$authority = new Authority(self::API);
        self::$database = new Database(self::API);
    }

    /**
     * Main API hook.
     */
    public static function handle()
    {
        // Init API
        self::init();
        // Return the result
        return Base::handle(Notifier::API, function ($action, $parameters) {
            // Initialize the notifier
            Notifier::init();
            // Switch action
            if ($action === "issue") {
                if (isset($parameters->application) && is_string($parameters->application)) {
                    // Authenticate the user to issue a new token
                    $userID = Authenticate::handle();
                    if ($userID !== null) {
                        // Create token object
                        $tokenObject = new stdClass();
                        $tokenObject->id = $userID;
                        $tokenObject->application = $parameters->application;
                        // Notify the user
                        Notifier::notify($userID, "You just allowed \"" . $parameters->application . "\" to send you notifications.");
                        // Issue the token
                        return self::$authority->issue($tokenObject, [self::PERMISSION_NOTIFY], 60 * 60 * 24 * 365);
                    }
                    return [false, "Authentication failure"];
                }
                return [false, "Parameter error"];
            } else if ($action === "notify") {
                if (isset($parameters->token) && is_string($parameters->token)) {
                    // Validate token
                    $validation = self::$authority->validate($parameters->token, [self::PERMISSION_NOTIFY]);
                    // Check result
                    if ($validation[0]) {
                        // User ID from token
                        $userID = $validation[1]->userID;
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
                        Notifier::notify($userID, $title, $message);
                        // Return OK
                        return [true, "Notified"];
                    }
                    return $validation;
                }
                return [false, "Parameter error"];
            }
            // Fallback error
            return [false, "Undefined hook"];
        }, true);
    }
}
