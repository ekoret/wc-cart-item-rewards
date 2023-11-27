<?php

class WCIRRewardsLogger
{
    /**
     * Adds a log into the logger table.
     */
    public static function add_log($data)
    {
        global $wpdb;

        $table = $wpdb->prefix . WCIRPlugin::$rewards_logger_table_name;

        $result = $wpdb->insert($table, $data);

        if ($result === false) {
            $error_message = $wpdb->last_error;
            error_log('WCIR Add Reward Log Database Error: ' . $error_message);
        }
    }
}
