<?php

class WCIRRewardsLogger
{
    public static function add_log($data)
    {
        global $wpdb;

        $table = $wpdb->prefix . WCIRPlugin::$rewards_logger_table_name;

        $result = $wpdb->insert($wpdb->prepare(""));
    }
}
