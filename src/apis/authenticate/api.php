<?php

/**
 * Copyright (c) 2019 Nadav Tasher
 * https://github.com/NadavTasher/AuthenticationTemplate/
 **/

// Include Base API
include_once __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "base" . DIRECTORY_SEPARATOR . "api.php";

/**
 * Authenticate API for user authentication.
 */
class Authenticate
{
    // API string
    public const API = "authenticate";

    // Column names
    private const COLUMN_NAME = "name";
    private const COLUMN_SALT = "salt";
    private const COLUMN_HASH = "hash";
    private const COLUMN_LOCK = "lock";

    // API mode
    private const TOKENS = true;

    // Configuration
    private static stdClass $configuration;

    // Base APIs
    private static Database $database;
    private static Authority $authority;

    /**
     * API initializer.
     */
    public static function init()
    {
        // Load configuration
        self::$configuration = new stdClass();
        self::$configuration->hooks = json_decode(file_get_contents(Utils::hostDirectory(self::API) . DIRECTORY_SEPARATOR . "hooks.json"));
        self::$configuration->locks = json_decode(file_get_contents(Utils::hostDirectory(self::API) . DIRECTORY_SEPARATOR . "locks.json"));
        self::$configuration->lengths = json_decode(file_get_contents(Utils::hostDirectory(self::API) . DIRECTORY_SEPARATOR . "lengths.json"));
        self::$configuration->permissions = json_decode(file_get_contents(Utils::hostDirectory(self::API) . DIRECTORY_SEPARATOR . "permissions.json"));
        // Make sure the database is initiated.
        self::$database = new Database(self::API);
        self::$database->createColumn(self::COLUMN_NAME);
        self::$database->createColumn(self::COLUMN_SALT);
        self::$database->createColumn(self::COLUMN_HASH);
        self::$database->createColumn(self::COLUMN_LOCK);
        // Make sure the authority is set-up
        self::$authority = new Authority(self::API);
    }

    /**
     * Main API hook.
     * @return mixed|null Result
     */
    public static function handle()
    {
        // Init the API
        self::init();
        // Return the result so that other APIs could use it.
        return Base::handle(self::API, function ($action, $parameters) {
            if (isset(self::$configuration->hooks->$action)) {
                if (self::$configuration->hooks->$action === true) {
                    if ($action === "authenticate") {
                        if (isset($parameters->token)) {
                            if (is_string($parameters->token)) {
                                if (self::TOKENS) {
                                    // Authenticate the user using tokens
                                    return self::authenticateToken($parameters->token);
                                } else {
                                    // Authenticate the user using sessions
                                    return self::authenticateSession($parameters->token);
                                }
                            }
                            return [false, "Incorrect type"];
                        }
                        return [false, "Missing parameters"];
                    } else if ($action === "signin") {
                        // Authenticate the user using the password, return the new session
                        if (isset($parameters->name) &&
                            isset($parameters->password)) {
                            if (is_string($parameters->name) &&
                                is_string($parameters->password)) {
                                $search = self::$database->search(self::COLUMN_NAME, $parameters->name);
                                if ($search[0]) {
                                    if (count($ids = $search[1]) === 1) {
                                        if (self::TOKENS) {
                                            return self::createToken($ids[0], $parameters->password);
                                        } else {
                                            return self::createSession($ids[0], $parameters->password);
                                        }
                                    }
                                    return [false, "User not found"];
                                }
                                return $search;
                            }
                            return [false, "Incorrect type"];
                        }
                        return [false, "Missing parameters"];
                    } else if ($action === "signup") {
                        // Create a new user
                        if (isset($parameters->name) &&
                            isset($parameters->password)) {
                            if (is_string($parameters->name) &&
                                is_string($parameters->password)) {
                                return self::createUser($parameters->name, $parameters->password);
                            }
                            return [false, "Incorrect type"];
                        }
                        return [false, "Missing parameters"];
                    }
                    return [false, "Unhandled hook"];
                }
                return [false, "Locked hook"];
            }
            return [false, "Undefined hook"];
        }, true);
    }

    /**
     * Finds a user's name by its ID.
     * @param string $id User ID
     * @return array Results
     */
    public static function findName($id)
    {
        // Check if the user's row exists
        if (self::$database->hasRow($id)[0]) {
            // Retrieve the name value
            return self::$database->get($id, self::COLUMN_NAME);
        }
        // Fallback result
        return [false, "User doesn't exist"];
    }

    /**
     * Finds a user's ID by its name.
     * @param string $name User Name
     * @return array Result
     */
    public static function findID($name)
    {
        $search = self::$database->search(self::COLUMN_NAME, $name);
        if ($search[0]) {
            if (count($search[1]) > 0) {
                return [true, $search[1][0]];
            }
            return [false, "User doesn't exist"];
        }
        // Fallback result
        return $search;
    }

    /**
     * Creates a new user.
     * @param string $name User Name
     * @param string $password User Password
     * @return array Results
     */
    private static function createUser($name, $password)
    {
        // Check user name
        $search = self::$database->search(self::COLUMN_NAME, $name);
        if ($search[0]) {
            if (count($search[1]) === 0) {
                // Check password length
                if (strlen($password) >= self::$configuration->lengths->password) {
                    // Generate a unique user id
                    $id = self::$database->createRow();
                    if ($id[0]) {
                        // Generate salt and hash
                        $salt = Utils::randomString(self::$configuration->lengths->salt);
                        $hash = Utils::hashMessage($password . $salt);
                        // Set user information
                        self::$database->set($id[1], self::COLUMN_NAME, $name);
                        self::$database->set($id[1], self::COLUMN_SALT, $salt);
                        self::$database->set($id[1], self::COLUMN_HASH, $hash);
                        self::$database->set($id[1], self::COLUMN_LOCK, strval(0));
                        // Return a success result
                        return [true, null];
                    }
                    // Fallback result
                    return $id;
                }
                // Fallback result
                return [false, "Password too short"];
            }
            // Fallback result
            return [false, "User already exists"];
        }
        // Fallback result
        return $search;
    }

    /**
     * Authenticates a user using $id and $password, then returns a User ID.
     * @param string $id User ID
     * @param string $password User Password
     * @return array Result
     */
    private static function authenticatePassword($id, $password)
    {
        // Check if the user's row exists
        if (self::$database->hasRow($id)[0]) {
            // Retrieve the lock value
            $lock = self::$database->get($id, self::COLUMN_LOCK);
            if ($lock[0]) {
                // Verify that the user isn't locked
                if (intval($lock[1]) < time()) {
                    // Retrieve the salt and hash
                    $salt = self::$database->get($id, self::COLUMN_SALT);
                    $hash = self::$database->get($id, self::COLUMN_HASH);
                    if ($salt[0] && $hash[0]) {
                        // Check password match
                        if (Utils::hashMessage($password . $salt[1]) === $hash[1]) {
                            // Return a success result
                            return [true, null];
                        } else {
                            // Lock the user
                            self::$database->set($id, self::COLUMN_LOCK, strval(time() + self::$configuration->lock->timeout));
                            // Return a failure result
                            return [false, "Wrong password"];
                        }
                    }
                    // Fallback result
                    return [false, "Internal error"];
                }
                // Fallback result
                return [false, "User is locked"];
            }
            // Fallback result
            return [false, "Internal error"];
        }
        // Fallback result
        return [false, "User doesn't exist"];
    }

    /**
     * Authenticates a user and creates a new token for that user.
     * @param string $id User ID
     * @param string $password User password
     * @return array Result
     */
    private static function createToken($id, $password)
    {
        // Authenticate the user by an ID and password
        $authentication = self::authenticatePassword($id, $password);
        // Check authentication result
        if ($authentication[0]) {
            // Return a success result
            return self::$authority->issue($id, self::$configuration->permissions->issuing);
        }
        // Fallback result
        return $authentication;
    }

    /**
     * Authenticates a user using $token then returns a User ID.
     * @param string $token Token
     * @return array Result
     */
    private static function authenticateToken($token)
    {
        // Check if the token is valid
        $result = self::$authority->validate($token, self::$configuration->permissions->validating);
        if ($result[0]) {
            // Token is valid
            return [true, null, $result[1]];
        }
        // Return fallback with error
        return $result;
    }

    /**
     * Authenticates a user and creates a new session for that user.
     * @param string $id User ID
     * @param string $password User password
     * @return array Result
     */
    private static function createSession($id, $password)
    {
        // Authenticate the user by an ID and password
        $authentication = self::authenticatePassword($id, $password);
        // Check authentication result
        if ($authentication[0]) {
            // Generate a new session ID
            $session = Utils::randomString(self::$configuration->lengths->session);
            // Create a database link with the session's hash
            $create_link = self::$database->createLink($id, Utils::hashMessage($session));
            if ($create_link[0]) {
                return [true, $session];
            }
            // Fallback result
            return $create_link;
        }
        // Fallback result
        return $authentication;
    }

    /**
     * Authenticates a user using $session then returns a User ID.
     * @param string $session Session
     * @return array Result
     */
    private static function authenticateSession($session)
    {
        // Check if a link with the session's hash value
        $has_link = self::$database->hasLink(Utils::hashMessage($session));
        if ($has_link[0]) {
            // Return a success result with a server result of the user's ID
            return [true, null, $has_link[1]];
        }
        // Fallback result
        return [false, "Invalid session"];
    }
}

/**
 * Authenticate API for notification delivery.
 */
class Manager
{
    // API string
    public const API = "manager";

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
     * Pushes a new message to the user.
     * @param string $id User ID
     * @param string $title Title
     * @param string $message Message
     * @return array Results
     */
    public static function push($id, $title = null, $message = null)
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
     * Pulls the messages to the user.
     * @param string $id User ID
     * @return array Results
     */
    public static function pull($id)
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