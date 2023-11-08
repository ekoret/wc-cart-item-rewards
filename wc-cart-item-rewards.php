<?php

/**
 * Plugin Name:       WC Cart Item Rewards
 * Description:       Add free items into customers carts depending on order total.
 * Version:           0.0.1
 * Author:            ekoret
 *
 * @package wc-cart-item-rewards
 */

if (!defined('ABSPATH')) die; // die if accessed directly


define('WCIR_STYLES', plugins_url('/assets/css/style.css', __FILE__));
define('WCIR_SCRIPTS', plugins_url('/assets/js', __FILE__));
define('WCIR_VIEWS', plugin_dir_path(__FILE__) . 'views');


if (!class_exists('WCIRPlugin')) {

    class WCIRPlugin
    {

        public static $rewards_table_name = "wcir_cart_item_rewards";
        public static $cron_event_name = "wcir_reward_status_update";


        public function __construct()
        {
            $this->register();

            require_once(plugin_dir_path(__FILE__) . 'inc/wcir-class-rewards.php');
            require_once(plugin_dir_path(__FILE__) . 'inc/wcir-class-rewards-add-edit.php');
            require_once(plugin_dir_path(__FILE__) . 'inc/wcir-functions.php');
        }


        /**
         * Register hooks
         */
        private function register()
        {
            register_activation_hook(__FILE__, array($this, 'activate'));
            register_deactivation_hook(__FILE__, array($this, 'deactivate'));

            // Register cron hook
            add_action(self::$cron_event_name, array('WCIRRewawrds', 'update_status_based_on_dates'));

            add_action('admin_menu', array($this, 'add_admin_menu_pages'));

            add_action('admin_enqueue_scripts', array($this, 'enqueue'));

            // Hook to add reward
            add_action('woocommerce_before_calculate_totals', array('WCIRRewards', 'maybe_add_reward_to_cart'), 100, 1);
            add_action('woocommerce_before_calculate_totals', array('WCIRRewards', 'set_reward_prices'), 110, 1);
        }

        public function add_admin_menu_pages()
        {
            // Main reward list table page
            add_menu_page("WC Cart Item Rewards", "WC Cart Item Rewards", "manage_options", "wc-cart-item-rewards", array('WCIRRewards', 'display_rewards_page'));
            add_submenu_page("wc-cart-item-rewards", "All Cart Item Rewards", "All Rewards", "manage_options", "wc-cart-item-rewards", array('WCIRRewards', 'display_rewards_page'));

            // Add/edit reward page
            add_submenu_page("wc-cart-item-rewards", "Add Cart Item Reward", "Add", "manage_options", "wc-cart-item-rewards-add", array('WCIRRewardsAddEdit', 'display_add_edit_rewards_page'));
        }

        public function enqueue()
        {
            if (is_admin() && isset($_GET['page']) && ($_GET['page'] === 'wc-cart-item-rewards' || $_GET['page'] === 'wc-cart-item-rewards-add')) {
                // Plugin  styles
                wp_enqueue_style('wcir-styles', WCIR_STYLES, array(), false, 'all');

                // Product picker script
                wp_register_script('wcir-woo-select-script', WC()->plugin_url() . '/assets/js/selectWoo/selectWoo.js', array('jquery'), null, true);
                wp_enqueue_script('wcir-woo-select-script');

                // Product picker styles
                wp_register_style('wcir-woo-select-style', WC()->plugin_url() . '/assets/css/select2.css', array(), false, 'all');
                wp_enqueue_style('wcir-woo-select-style');

                // Product picker ajax script
                wp_register_script('wcir-product-picker-script', WCIR_SCRIPTS . "/wcir_product_picker_ajax.js", array('jquery'));
                wp_enqueue_script('wcir-product-picker-script');
            }
        }

        /**
         * Handles when plugin activates.
         * 
         * Maybe creates tables and schedules the cron job.
         */
        public function activate()
        {
            $this->maybe_create_reward_table();

            $this->schedule_reward_status_update_cron();
        }

        /**
         * Handles when plugin deactivates
         * 
         * Deletes tables and scheduled cron job.
         * TODO: Remove delete db and move into plugin delete hook.
         */
        public function deactivate()
        {
            $this->delete_db(); // for development

            $this->remove_reward_status_update_cron();
        }

        /**
         * Maybe create the reward table.
         */
        private function maybe_create_reward_table()
        {
            global $wpdb;

            $table_name = $wpdb->prefix . self::$rewards_table_name;

            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id                      INT(15)             NOT NULL AUTO_INCREMENT,
                reward_name             VARCHAR(255)    NOT NULL,
                display_name            VARCHAR(255)    NOT NULL,
                product_id              INT(15)        NOT NULL,
                status                  TINYINT(1)      NOT NULL DEFAULT 0,
                current_redemptions     INT(8)          NOT NULL DEFAULT 0,
                redemptions_per_user    INT(8),
                minimum_order           INT(6),
                stock                   INT(8),
                start_date              DATE,
                end_date                DATE,
                change_timestamp        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * Deletes all resources and tables.
         */
        private function delete_db()
        {
            global $wpdb;
            $table_name = $wpdb->prefix . self::$rewards_table_name;

            // Check if the table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
                // Drop the table
                $wpdb->query("DROP TABLE $table_name");
            }
        }

        /**
         * Creates a cron job to run next at 12am based on the sites timezone, then daily.
         */
        private function schedule_reward_status_update_cron()
        {
            if (!wp_next_scheduled(self::$cron_event_name)) {

                $timestamp = current_datetime()->modify('tomorrow midnight')->getTimestamp();

                wp_schedule_event($timestamp, 'daily', self::$cron_event_name);
            }
        }

        /**
         * Removes the scheduled cron job.
         */
        private function remove_reward_status_update_cron()
        {
            wp_clear_scheduled_hook(self::$cron_event_name);
        }
    }
}

function init_wcir_plugin()
{
    new WCIRPlugin();
}

init_wcir_plugin();
