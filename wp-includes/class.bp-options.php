<?php

class BP_Options {
    private static $db;
    private static $table_name = 'bp_options'; // Change as needed
    private static $prefix = 'bp_';

    /**
     * Set the database connection.
     */
    public static function set_db(BPDB $db) {
        self::$db = $db;
        self::initialize_table();
    }

    /**
     * Retrieve the prefix to be appended to the option key.
     */
    public static function prefix() {
        return self::$prefix;
    }

    /**
     * Retrieve the value of an option.
     */
    public static function get($option) {
        if (!self::$db) return null; // Ensure DB connection is set

        $option = self::$prefix . $option;
        $sql = self::$db->prepare("SELECT option_value FROM " . self::$table_name . " WHERE option_name = %s", $option);
        $row = self::$db->get_row($sql, ARRAY_A);

        return $row ? unserialize($row['option_value']) : null;
    }

    /**
     * Adds a new option.
     */
    public static function add($option, $value) {
        if (!self::$db) return false;

        if (self::get($option) !== null) {
            return false; // Option already exists
        }
        $option = self::$prefix . $option;
        $value = serialize($value);

        return self::$db->insert(self::$table_name, [
            'option_name'  => $option,
            'option_value' => $value
        ]);
    }

    /**
     * Updates an existing option.
     */
    public static function update($option, $value) {
        if (!self::$db) return false;

        if (self::get($option) === null) {
            return self::add($option, $value);
        }
        $option = self::$prefix . $option;
        $value = serialize($value);

        return self::$db->update(self::$table_name, ['option_value' => $value], ['option_name' => $option]);
    }

    /**
     * Deletes an existing option.
     */
    public static function delete($option) {
        if (!self::$db) return false;

        $option = self::$prefix . $option;

        return self::$db->delete(self::$table_name, ['option_name' => $option]);
    }

    /**
     * Initializes the options table if it does not exist.
     */
    private static function initialize_table() {
        if (!self::$db) return;

        $sql = "CREATE TABLE IF NOT EXISTS " . self::$table_name . " (
            option_name VARCHAR(191) PRIMARY KEY,
            option_value TEXT NOT NULL
        )";
        
        self::$db->query($sql);
    }
}
