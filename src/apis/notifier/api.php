<?php

/**
 * Copyright (c) 2020 Nadav Tasher
 * https://github.com/NadavTasher/BaseTemplate/
 **/

// Include Base API
include_once __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "base" . DIRECTORY_SEPARATOR . "api.php";

// Include Authenticate API
include_once __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "authenticate" . DIRECTORY_SEPARATOR . "api.php";

/**
 * Authenticate API for notification delivery.
 */
class Notifier
{
    // API string
    private const API = "notifier";
    // Column names
    private const COLUMN_MESSAGES = "messages";
    // Base APIs
    private static Database $database;

    /**
     * API initializer.
     */
    public static function init()
    {
        // Initialize database
        self::$database = new Database(self::API);
        self::$database->createColumn(self::COLUMN_MESSAGES);
    }

    /**
     * Main API hook.
     */
    public static function handle()
    {
        // Init API
        self::init();
        // Return the result
        return API::handle(Notifier::API, function ($action, $parameters) {
            // Authenticate user
            $userID = Authenticate::handle();
            // Handle actions
            if ($action === "checkout") {
                return self::checkout($userID);
            }
            // Fallback error
            return [false, "Undefined hook"];
        }, true);
    }

    /**
     * Notify the user with a new message.
     * @param string $id User ID
     * @param string $title Title
     * @param string $message Message
     * @return array Results
     */
    public static function notify($id, $title = null, $message = null)
    {
        // Make sure the ID exists
        if (!self::$database->hasRow($id)[0]) {
            self::$database->createRow($id);
        }
        // Initialize messages array
        $messages = array();
        // Check the database
        if (self::$database->isset($id, self::COLUMN_MESSAGES)[0]) {
            $messages = json_decode(self::$database->get($id, self::COLUMN_MESSAGES)[1]);
        }
        // Create a new message object
        $messageObject = new stdClass();
        $messageObject->title = $title;
        $messageObject->message = $message;
        $messageObject->timestamp = time();
        // Push into array
        array_push($messages, $messageObject);
        // Set the messages array
        return self::$database->set($id, self::COLUMN_MESSAGES, json_encode($messages));
    }

    /**
     * Fetches the latest messages for the user and clears the database.
     * @param string $id User ID
     * @return array Results
     */
    public static function checkout($id)
    {
        // Make sure the ID exists
        if (!self::$database->hasRow($id)[0]) {
            self::$database->createRow($id);
        }
        // Initialize messages array
        $messages = array();
        // Check the database
        if (self::$database->isset($id, self::COLUMN_MESSAGES)[0]) {
            $messages = json_decode(self::$database->get($id, self::COLUMN_MESSAGES)[1]);
        }
        // Clear the messages array
        $set = self::$database->set($id, self::COLUMN_MESSAGES, json_encode(array()));
        // Check the result
        if ($set[0]) {
            return [true, $messages];
        }
        // Fallback error
        return $set;
    }
}