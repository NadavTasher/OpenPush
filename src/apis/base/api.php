<?php

/**
 * Copyright (c) 2019 Nadav Tasher
 * https://github.com/NadavTasher/BaseTemplate/
 **/

/**
 * Base API for handling requests.
 */
class Base
{
    // Constants
    public const API = "base";

    // API results
    private static stdClass $result;

    /**
     * Creates the result object.
     */
    public static function init()
    {
        self::$result = new stdClass();
    }

    /**
     * Handles API calls by handing them over to the callback.
     * @param string $API The API to listen to
     * @param callable $callback The callback to be called with action and parameters
     * @param bool $filter Whether to filter XSS characters
     * @return mixed|null A result array with [success, result|error]
     */
    public static function handle($API, $callback, $filter = true)
    {
        // Initialize the request
        $request = null;
        // Load the request from POST or GET
        if (isset($_POST["api"])) {
            $request = $_POST["api"];
        } else if (isset($_GET["api"])) {
            $request = $_GET["api"];
        }
        // Make sure the request is initialized
        if ($request !== null) {
            // Filter the request
            if ($filter) {
                $request = str_replace("<", "", $request);
                $request = str_replace(">", "", $request);
            }
            // Decode the request
            $APIs = json_decode($request);
            // Parse the APIs
            if (isset($APIs->$API)) {
                if (isset($APIs->$API->action)) {
                    if ((is_string($APIs->$API->action) || is_null($APIs->$API->action)) &&
                        (is_object($APIs->$API->parameters) || is_null($APIs->$API->parameters))) {
                        // Parse the parameter
                        $action = $APIs->$API->action;
                        $action_parameters = $APIs->$API->parameters;
                        // Execute the call
                        $action_result = $callback($action, $action_parameters);
                        // Parse the results
                        if (is_array($action_result)) {
                            if (count($action_result) >= 2) {
                                if (is_bool($action_result[0])) {
                                    self::$result->$API = new stdClass();
                                    self::$result->$API->success = $action_result[0];
                                    self::$result->$API->result = $action_result[1];
                                    if (count($action_result) >= 3) {
                                        return $action_result[2];
                                    } else {
                                        return null;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        // Fallback result
        return null;
    }

    /**
     * Echos the result object as JSON.
     */
    public static function echo()
    {
        echo json_encode(self::$result);
    }
}

/**
 * Base API for general functions.
 */
class Utils
{
    // Hashing properties
    private const HASHING_ROUNDS = 16;
    private const HASHING_ALGORITHM = "sha256";

    // Directory properties
    private const DIRECTORY = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "files";

    /**
     * Creates a host directory for an API.
     * @param string $API API
     * @return string Directory
     */
    public static function hostDirectory($API = Base::API)
    {
        // Create the data directory path
        $directory = self::DIRECTORY . DIRECTORY_SEPARATOR . basename($API);
        // Make sure the directory exists
        if (!file_exists($directory)) {
            mkdir($directory);
        }
        // Return the path
        return $directory;
    }

    /**
     * Creates a guest directory for an API.
     * @param string $hostAPI Host API
     * @param string $guestAPI Guest API
     * @return string Directory
     */
    public static function guestDirectory($hostAPI = Base::API, $guestAPI = Base::API)
    {
        // Create the data directory path
        $directory = self::hostDirectory($hostAPI) . DIRECTORY_SEPARATOR . basename($guestAPI);
        // Make sure the directory exists
        if (!file_exists($directory)) {
            mkdir($directory);
        }
        // Return the path
        return $directory;
    }

    /**
     * Creates a random string.
     * @param int $length String length
     * @return string String
     */
    public static function randomString($length = 0)
    {
        if ($length > 0) {
            return str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz")[0] . self::randomString($length - 1);
        }
        return "";
    }

    /**
     * Hashes a message.
     * @param string $message Message
     * @param int $rounds Number of rounds
     * @return string Hash
     */
    public static function hashMessage($message, $rounds = self::HASHING_ROUNDS)
    {
        if ($rounds === 0) {
            return hash(self::HASHING_ALGORITHM, $message);
        }
        return hash(self::HASHING_ALGORITHM, self::hashMessage($message, $rounds - 1));
    }

    /**
     * Signs a message.
     * @param string $message Message
     * @param string $secret Shared secret
     * @param int $rounds Number of rounds
     * @return string Signature
     */
    public static function signMessage($message, $secret, $rounds = self::HASHING_ROUNDS)
    {
        if ($rounds === 0) {
            return hash_hmac(self::HASHING_ALGORITHM, $message, $secret);
        }
        return hash_hmac(self::HASHING_ALGORITHM, self::signMessage($message, $secret, $rounds - 1), $secret);
    }
}

/**
 * Base API for storing user data.
 */
class Database
{
    // Constants
    public const API = "database";

    private const LENGTH = 32;
    private const SEPARATOR = "\n";
    private const ROWS = "rows", COLUMNS = "columns", LINKS = "links";

    // Guest API
    private string $API;

    /**
     * Database constructor.
     * @param string $API API name
     * @param string $name Database name
     */
    public function __construct($API = Base::API)
    {
        $this->API = $API;
    }

    /**
     * Creates and returns a data path.
     * @param string $subdirectory Directory name
     * @return string Path
     */
    private function directory($subdirectory)
    {
        // Append the subdirectory name
        $directory = Utils::guestDirectory(self::API, $this->API) . DIRECTORY_SEPARATOR . basename($subdirectory);
        // Make sure the directory exists
        if (!file_exists($directory)) {
            mkdir($directory);
        }
        // Return the path
        return $directory;
    }

    /**
     * Creates a new database row.
     * @param null $id ID
     * @return array Result
     */
    public function createRow($id = null)
    {
        // Generate a row ID
        if ($id === null) {
            $id = Utils::randomString(self::LENGTH);
        }
        // Check if the row already exists
        $has_row = $this->hasRow($id);
        if (!$has_row[0]) {
            // Create row directory
            mkdir($this->directory(self::ROWS) . DIRECTORY_SEPARATOR . $id);
            // Return result
            return [true, $id];
        }
        return [false, "Row already exists"];
    }

    /**
     * Creates a new database column.
     * @param string $name Column name
     * @return array Result
     */
    public function createColumn($name)
    {
        // Generate hashed string
        $hashed = Utils::hashMessage($name);
        // Check if the column already exists
        $has_column = $this->hasColumn($name);
        if (!$has_column[0]) {
            // Create column directory
            mkdir($this->directory(self::COLUMNS) . DIRECTORY_SEPARATOR . $hashed);
            // Return result
            return [true, $name];
        }
        return [false, "Column already exists"];
    }

    /**
     * Creates a new database link.
     * @param string $row Row ID
     * @param string $link Link value
     * @return array Result
     */
    public function createLink($row, $link)
    {
        // Generate hashed string
        $hashed = Utils::hashMessage($link);
        // Check if the link already exists
        $has_link = $this->hasLink($link);
        if (!$has_link[0]) {
            // Make sure the row exists
            $has_row = $this->hasRow($row);
            if ($has_row[0]) {
                // Generate link file
                file_put_contents($this->directory(self::LINKS) . DIRECTORY_SEPARATOR . $hashed, $row);
                // Return result
                return [true, $link];
            }
            return $has_row;
        }
        return [false, "Link already exists"];
    }

    /**
     * Check whether a database row exists.
     * @param string $id Row ID
     * @return array Result
     */
    public function hasRow($id)
    {
        // Store path
        $path = $this->directory(self::ROWS) . DIRECTORY_SEPARATOR . $id;
        // Check if path exists and is a directory
        if (file_exists($path) && is_dir($path)) {
            return [true, $id];
        }
        return [false, "Row doesn't exist"];
    }

    /**
     * Check whether a database column exists.
     * @param string $name Column name
     * @return array Result
     */
    public function hasColumn($name)
    {
        // Generate hashed string
        $hashed = Utils::hashMessage($name);
        // Store path
        $path = $this->directory(self::COLUMNS) . DIRECTORY_SEPARATOR . $hashed;
        // Check if path exists and is a directory
        if (file_exists($path) && is_dir($path)) {
            return [true, $name];
        }
        return [false, "Column doesn't exist"];
    }

    /**
     * Check whether a database link exists.
     * @param string $link Link value
     * @return array Result
     */
    public function hasLink($link)
    {
        // Generate hashed string
        $hashed = Utils::hashMessage($link);
        // Store path
        $path = $this->directory(self::LINKS) . DIRECTORY_SEPARATOR . $hashed;
        // Check if path exists and is a file
        if (file_exists($path) && is_file($path)) {
            // Read link
            return [true, file_get_contents($path)];
        }
        return [false, "Link doesn't exist"];
    }

    /**
     * Check whether a database value exists.
     * @param string $row Row ID
     * @param string $column Column name
     * @return array Result
     */
    public function isset($row, $column)
    {
        // Check if row exists
        $has_row = $this->hasRow($row);
        if ($has_row[0]) {
            // Check if the column exists
            $has_column = $this->hasColumn($column);
            if ($has_column[0]) {
                // Generate hashed string
                $hashed = Utils::hashMessage($column);
                // Store path
                $path = $this->directory(self::ROWS) . DIRECTORY_SEPARATOR . $row . DIRECTORY_SEPARATOR . $hashed;
                // Check if path exists and is a file
                if (file_exists($path) && is_file($path)) {
                    return [true, null];
                }
                return [false, "Value doesn't exist"];
            }
            return $has_column;
        }
        return $has_row;
    }

    /**
     * Sets a database value.
     * @param string $row Row ID
     * @param string $column Column name
     * @param string $value Value
     * @return array Result
     */
    public function set($row, $column, $value)
    {
        // Remove previous values
        if ($this->isset($row, $column)[0]) {
            $this->unset($row, $column);
        }
        // Check if the column exists
        $has_column = $this->hasColumn($column);
        if ($has_column[0]) {
            // Create hashed string
            $hashed_name = Utils::hashMessage($column);
            // Store path
            $value_path = $this->directory(self::ROWS) . DIRECTORY_SEPARATOR . $row . DIRECTORY_SEPARATOR . $hashed_name;
            // Create hashed string
            $hashed_value = Utils::hashMessage($value);
            // Write path
            file_put_contents($value_path, $value);
            // Store new path
            $index_path = $this->directory(self::COLUMNS) . DIRECTORY_SEPARATOR . $hashed_name . DIRECTORY_SEPARATOR . $hashed_value;
            // Create rows array
            $rows = array();
            // Make sure the index file exists
            if (file_exists($index_path) && is_file($index_path)) {
                // Read contents
                $contents = file_get_contents($index_path);
                // Separate lines
                $rows = explode(self::SEPARATOR, $contents);
            }
            // Insert row to rows
            array_push($rows, $row);
            // Write contents
            file_put_contents($index_path, implode(self::SEPARATOR, $rows));
            // Result result
            return [true, null];
        }
        return $has_column;
    }

    /**
     * Unsets a database value.
     * @param string $row Row ID
     * @param string $column Column name
     * @return array Result
     */
    public function unset($row, $column)
    {
        // Check if a value is already set
        $isset = $this->isset($row, $column);
        if ($isset[0]) {
            // Check if the column exists
            $has_column = $this->hasColumn($column);
            if ($has_column[0]) {
                // Create hashed string
                $hashed_name = Utils::hashMessage($column);
                // Store path
                $value_path = $this->directory(self::ROWS) . DIRECTORY_SEPARATOR . $row . DIRECTORY_SEPARATOR . $hashed_name;
                // Get value & Hash it
                $value = file_get_contents($value_path);
                $hashed_value = Utils::hashMessage($value);
                // Remove path
                unlink($value_path);
                // Store new path
                $index_path = $this->directory(self::COLUMNS) . DIRECTORY_SEPARATOR . $hashed_name . DIRECTORY_SEPARATOR . $hashed_value;
                // Make sure the index file exists
                if (file_exists($index_path) && is_file($index_path)) {
                    // Read contents
                    $contents = file_get_contents($index_path);
                    // Separate lines
                    $rows = explode(self::SEPARATOR, $contents);
                    // Remove row from rows
                    unset($rows[array_search($row, $rows)]);
                    // Write contents
                    file_put_contents($index_path, implode(self::SEPARATOR, $rows));
                    // Return result
                    return [true, null];
                }
                return [false, "Index doesn't exist"];
            }
            return $has_column;
        }
        return $isset;
    }

    /**
     * Gets a database value.
     * @param string $row Row ID
     * @param string $column Column name
     * @return array Result
     */
    public function get($row, $column)
    {
        // Check if a value is set
        $isset = $this->isset($row, $column);
        if ($isset[0]) {
            // Generate hashed string
            $hashed = Utils::hashMessage($column);
            // Store path
            $path = $this->directory(self::ROWS) . DIRECTORY_SEPARATOR . $row . DIRECTORY_SEPARATOR . $hashed;
            // Read path
            return [true, file_get_contents($path)];
        }
        return $isset;
    }

    /**
     * Searches rows by column values.
     * @param string $column Column name
     * @param string $value Value
     * @return array Matching rows
     */
    public function search($column, $value)
    {
        // Create rows array
        $rows = array();
        // Check if the column exists
        $has_column = $this->hasColumn($column);
        if ($has_column[0]) {
            // Create hashed string
            $hashed_name = Utils::hashMessage($column);
            // Create hashed string
            $hashed_value = Utils::hashMessage($value);
            // Store new path
            $index_path = $this->directory(self::COLUMNS) . DIRECTORY_SEPARATOR . $hashed_name . DIRECTORY_SEPARATOR . $hashed_value;
            // Make sure the index file exists
            if (file_exists($index_path) && is_file($index_path)) {
                // Read contents
                $contents = file_get_contents($index_path);
                // Separate lines
                $rows = explode(self::SEPARATOR, $contents);
            }
            return [true, $rows];
        }
        return $has_column;
    }
}

/**
 * Base API for issuing and validating tokens.
 */
class Authority
{
    // Constants
    public const API = "authority";

    private const LENGTH = 512;
    private const SEPARATOR = ":";
    private const VALIDITY = 31 * 24 * 60 * 60;

    // Guest API
    private string $API;

    // Secret string
    private string $secret;

    /**
     * Authority constructor.
     * @param string $API API name
     */
    public function __construct($API = Base::API)
    {
        $this->API = $API;
        // Create secret
        $path = Utils::guestDirectory(self::API, $this->API) . DIRECTORY_SEPARATOR . "secret.key";
        // Check existence
        if (!file_exists($path)) {
            // Create the secret file
            file_put_contents($path, Utils::randomString(self::LENGTH));
        }
        // Read secret
        $this->secret = file_get_contents($path);
    }

    /**
     * Creates a token.
     * @param string | stdClass | array $contents Content
     * @param array $permissions Permissions
     * @param float | int $validity Validity time
     * @return array Result
     */
    public function issue($contents, $permissions = [], $validity = self::VALIDITY)
    {
        // Create token object
        $token_object = new stdClass();
        $token_object->contents = $contents;
        $token_object->permissions = $permissions;
        $token_object->issuer = Utils::hashMessage($this->API);
        $token_object->expiry = time() + intval($validity);
        // Create token string
        $token_object_string = bin2hex(json_encode($token_object));
        // Calculate signature
        $token_signature = Utils::signMessage($token_object_string, $this->secret);
        // Create parts
        $token_parts = [$token_object_string, $token_signature];
        // Combine all into token
        $token = implode(self::SEPARATOR, $token_parts);
        // Return combined message
        return [true, $token];
    }

    /**
     * Validates a token.
     * @param string $token Token
     * @param array $permissions Permissions
     * @return array Validation result
     */
    public function validate($token, $permissions = [])
    {
        // Try parsing
        // Separate string
        $token_parts = explode(self::SEPARATOR, $token);
        // Validate content count
        if (count($token_parts) === 2) {
            // Store parts
            $token_object_string = $token_parts[0];
            $token_signature = $token_parts[1];
            // Validate signature
            if (Utils::signMessage($token_object_string, $this->secret) === $token_signature) {
                // Parse token object
                $token_object = json_decode(hex2bin($token_object_string));
                // Validate existence
                if (isset($token_object->contents) && isset($token_object->permissions) && isset($token_object->issuer) && isset($token_object->expiry)) {
                    // Validate issuer
                    if ($token_object->issuer === Utils::hashMessage($this->API)) {
                        // Validate expiry
                        if (time() < $token_object->expiry) {
                            // Validate permissions
                            foreach ($permissions as $permission) {
                                // Make sure permission exists
                                if (array_search($permission, $token_object->permissions) === false) {
                                    // Fallback error
                                    return [false, "Insufficient token permissions"];
                                }
                            }
                            // Return token
                            return [true, $token_object->contents];
                        }
                        // Fallback error
                        return [false, "Invalid token expiry"];
                    }
                    // Fallback error
                    return [false, "Invalid token issuer"];
                }
                // Fallback error
                return [false, "Invalid token structure"];
            }
            // Fallback error
            return [false, "Invalid token signature"];
        }
        // Fallback error
        return [false, "Invalid token format"];
    }
}