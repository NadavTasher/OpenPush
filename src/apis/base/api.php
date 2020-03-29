<?php

/**
 * Copyright (c) 2019 Nadav Tasher
 * https://github.com/NadavTasher/BaseTemplate/
 **/

/**
 * Base API for handling requests.
 */
class API
{
    // Base API name
    public const BASE = "base";

    // APIs directory
    private const APIS_DIRECTORY = __DIR__ . DIRECTORY_SEPARATOR . "..";

    // API results
    private static stdClass $result;

    /**
     * Creates the result JSON.
     */
    public static function init()
    {
        self::$result = new stdClass();
    }

    /**
     * Echos the result JSON.
     */
    public static function echo()
    {
        echo json_encode(self::$result);
    }

    /**
     * Returns the directory of an API.
     * @param string $API API name
     * @return string API directory
     */
    public static function directory($API)
    {
        return self::APIS_DIRECTORY . DIRECTORY_SEPARATOR . $API;
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
                    if (is_string($APIs->$API->action)) {
                        // Parse the action
                        $action = $APIs->$API->action;
                        $action_parameters = new stdClass();
                        // Parse the parameters
                        if (isset($APIs->$API->parameters)) {
                            if (is_object($APIs->$API->parameters)) {
                                $action_parameters = $APIs->$API->parameters;
                            }
                        }
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
}

/**
 * Base API for general functions.
 */
class Utils
{
    private const HASHING_ALGORITHM = "sha256";
    private const HASHING_ROUNDS = 16;

    /**
     * Creates a random string.
     * @param int $length String length
     * @return string String
     */
    public static function random($length = 0)
    {
        if ($length > 0) {
            return str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz")[0] . self::random($length - 1);
        }
        return "";
    }

    /**
     * Hashes a message.
     * @param string $message Message
     * @param int $rounds Number of rounds
     * @return string Hash
     */
    public static function hash($message, $rounds = self::HASHING_ROUNDS)
    {
        if ($rounds === 0) {
            return hash(self::HASHING_ALGORITHM, $message);
        }
        return hash(self::HASHING_ALGORITHM, self::hash($message, $rounds - 1));
    }

    /**
     * Signs a message.
     * @param string $message Message
     * @param string $secret Shared secret
     * @param int $rounds Number of rounds
     * @return string Signature
     */
    public static function sign($message, $secret, $rounds = self::HASHING_ROUNDS)
    {
        if ($rounds === 0) {
            return hash_hmac(self::HASHING_ALGORITHM, $message, $secret);
        }
        return hash_hmac(self::HASHING_ALGORITHM, self::sign($message, $secret, $rounds - 1), $secret);
    }
}

/**
 * Base API for storing user data.
 */
class Database
{
    // Root directory
    private string $directory;
    // Subdirectories
    private string $directory_rows;
    private string $directory_columns;
    private string $directory_links;
    private string $access_file;
    // Const properties
    private const LENGTH_ID = 32;
    private const SEPARATOR = "\n";

    /**
     * Database constructor.
     * @param string $API API name
     * @param string $name Database name
     */
    public function __construct($API = API::BASE, $name = "database")
    {
        $this->directory = API::directory($API) . DIRECTORY_SEPARATOR . basename($name);
        $this->directory_rows = $this->directory . DIRECTORY_SEPARATOR . "rows";
        $this->directory_columns = $this->directory . DIRECTORY_SEPARATOR . "columns";
        $this->directory_links = $this->directory . DIRECTORY_SEPARATOR . "links";
        $this->access_file = $this->directory . DIRECTORY_SEPARATOR . ".htaccess";
        $this->create();
    }

    /**
     * Validates existence of database files and directories.
     */
    private function create()
    {
        // Check if database directories exists
        foreach ([$this->directory, $this->directory_columns, $this->directory_links, $this->directory_rows] as $directory) {
            if (!file_exists($directory)) {
                // Create the directory
                mkdir($directory);
            } else {
                // Make sure it is a directory
                if (!is_dir($directory)) {
                    // Remove the path
                    unlink($directory);
                    // Redo the whole thing
                    $this->create();
                    // Finish
                    return;
                }
            }
        }
        // Make sure the .htaccess exists
        if (!file_exists($this->access_file)) {
            // Write contents
            file_put_contents($this->access_file, "Deny from all");
        }
    }

    /**
     * Creates a new database row.
     * @param null $id ID
     * @return array Result
     */
    public function createRow($id = null)
    {
        // Generate a row ID
        if ($id === null)
            $id = Utils::random(self::LENGTH_ID);
        // Check if the row already exists
        $has_row = $this->hasRow($id);
        if (!$has_row[0]) {
            // Create row directory
            mkdir($this->directory_rows . DIRECTORY_SEPARATOR . $id);
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
        $hashed = Utils::hash($name);
        // Check if the column already exists
        $has_column = $this->hasColumn($name);
        if (!$has_column[0]) {
            // Create column directory
            mkdir($this->directory_columns . DIRECTORY_SEPARATOR . $hashed);
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
        $hashed = Utils::hash($link);
        // Check if the link already exists
        $has_link = $this->hasLink($link);
        if (!$has_link[0]) {
            // Make sure the row exists
            $has_row = $this->hasRow($row);
            if ($has_row[0]) {
                // Generate link file
                file_put_contents($this->directory_links . DIRECTORY_SEPARATOR . $hashed, $row);
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
        $path = $this->directory_rows . DIRECTORY_SEPARATOR . $id;
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
        $hashed = Utils::hash($name);
        // Store path
        $path = $this->directory_columns . DIRECTORY_SEPARATOR . $hashed;
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
        $hashed = Utils::hash($link);
        // Store path
        $path = $this->directory_links . DIRECTORY_SEPARATOR . $hashed;
        // Check if path exists and is a file
        if (file_exists($path) && is_file($path)) {
            // Generate hashed string
            $hashed = Utils::hash($link);
            // Store path
            $path = $this->directory_links . DIRECTORY_SEPARATOR . $hashed;
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
                $hashed = Utils::hash($column);
                // Store path
                $path = $this->directory_rows . DIRECTORY_SEPARATOR . $row . DIRECTORY_SEPARATOR . $hashed;
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
            $hashed_name = Utils::hash($column);
            // Store path
            $value_path = $this->directory_rows . DIRECTORY_SEPARATOR . $row . DIRECTORY_SEPARATOR . $hashed_name;
            // Create hashed string
            $hashed_value = Utils::hash($value);
            // Write path
            file_put_contents($value_path, $value);
            // Store new path
            $index_path = $this->directory_columns . DIRECTORY_SEPARATOR . $hashed_name . DIRECTORY_SEPARATOR . $hashed_value;
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
                $hashed_name = Utils::hash($column);
                // Store path
                $value_path = $this->directory_rows . DIRECTORY_SEPARATOR . $row . DIRECTORY_SEPARATOR . $hashed_name;
                // Get value & Hash it
                $value = file_get_contents($value_path);
                $hashed_value = Utils::hash($value);
                // Remove path
                unlink($value_path);
                // Store new path
                $index_path = $this->directory_columns . DIRECTORY_SEPARATOR . $hashed_name . DIRECTORY_SEPARATOR . $hashed_value;
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
            $hashed = Utils::hash($column);
            // Store path
            $path = $this->directory_rows . DIRECTORY_SEPARATOR . $row . DIRECTORY_SEPARATOR . $hashed;
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
            $hashed_name = Utils::hash($column);
            // Create hashed string
            $hashed_value = Utils::hash($value);
            // Store new path
            $index_path = $this->directory_columns . DIRECTORY_SEPARATOR . $hashed_name . DIRECTORY_SEPARATOR . $hashed_value;
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
    // Root directory
    private string $directory;
    // Subdirectories
    private string $secret_file;
    private string $access_file;
    // Token issuer
    private string $issuer;
    // Lengths
    private const LENGTH_SECRET = 512;
    // Token properties
    private const VALIDITY = 31 * 24 * 60 * 60;
    private const SEPARATOR = ":";

    /**
     * Authority constructor.
     * @param string $API API name
     */
    public function __construct($API = API::BASE)
    {
        $this->directory = API::directory($API) . DIRECTORY_SEPARATOR . "authority";
        $this->secret_file = $this->directory . DIRECTORY_SEPARATOR . "secret";
        $this->access_file = $this->directory . DIRECTORY_SEPARATOR . ".htaccess";
        $this->issuer = $API;
        $this->create();
    }

    /**
     * Validates existence of configuration files and directories.
     */
    private function create()
    {
        // Make sure configuration directory exists
        if (!file_exists($this->directory)) {
            // Create the directory
            mkdir($this->directory);
        } else {
            // Make sure it is a directory
            if (!is_dir($this->directory)) {
                // Remove the path
                unlink($this->directory);
                // Redo the whole thing
                $this->create();
                // Finish
                return;
            }
        }
        // Make sure a shared secret exists
        if (!file_exists($this->secret_file)) {
            // Create the secret file
            file_put_contents($this->secret_file, Utils::random(self::LENGTH_SECRET));
        }
        // Make sure the .htaccess exists
        if (!file_exists($this->access_file)) {
            // Write contents
            file_put_contents($this->access_file, "Deny from all");
        }
    }

    /**
     * Returns the shared secret.
     * @return array Result
     */
    private function secret()
    {
        // Read secret file
        if (file_exists($this->secret_file) && is_file($this->secret_file)) {
            return [true, file_get_contents($this->secret_file)];
        }
        // Fallback error
        return [false, "Secret doesn't exist"];
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
        // Load secret
        $secret = $this->secret();
        // Make sure secret exists
        if ($secret[0]) {
            // Create token object
            $token_object = new stdClass();
            $token_object->contents = $contents;
            $token_object->permissions = $permissions;
            $token_object->issuer = Utils::hash($this->issuer);
            $token_object->expiry = time() + intval($validity);
            // Create token string
            $token_object_string = bin2hex(json_encode($token_object));
            // Calculate signature
            $token_signature = Utils::sign($token_object_string, $secret[1]);
            // Create parts
            $token_parts = [$token_object_string, $token_signature];
            // Combine all into token
            $token = implode(self::SEPARATOR, $token_parts);
            // Return combined message
            return [true, $token];
        }
        // Fallback error
        return $secret;
    }

    /**
     * Validates a token.
     * @param string $token Token
     * @param array $permissions Permissions
     * @return array Validation result
     */
    public function validate($token, $permissions = [])
    {
        // Load secret
        $secret = $this->secret();
        // Make sure secret exists
        if ($secret[0]) {
            // Try parsing
            // Separate string
            $token_parts = explode(self::SEPARATOR, $token);
            // Validate content count
            if (count($token_parts) === 2) {
                // Store parts
                $token_object_string = $token_parts[0];
                $token_signature = $token_parts[1];
                // Validate signature
                if (Utils::sign($token_object_string, $secret[1]) === $token_signature) {
                    // Parse token object
                    $token_object = json_decode(hex2bin($token_object_string));
                    // Validate existence
                    if (isset($token_object->contents) && isset($token_object->permissions) && isset($token_object->issuer) && isset($token_object->expiry)) {
                        // Validate issuer
                        if ($token_object->issuer === Utils::hash($this->issuer)) {
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
        // Fallback error
        return $secret;
    }
}