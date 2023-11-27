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


define('WCIR_BASENAME', plugin_basename(__FILE__));
define('WCIR_STYLES', plugins_url('/assets/css/style.css', __FILE__));
define('WCIR_SCRIPTS', plugins_url('/assets/js', __FILE__));
define('WCIR_VIEWS', plugin_dir_path(__FILE__) . 'views');

if (!class_exists('WCIRPlugin')) {

    class WCIRPlugin
    {

        protected static $instance;
        public static $rewards_table_name = "wcir_cart_item_rewards";
        public static $rewards_logger_table_name = "wcir_cart_item_rewards_log";
        public static $cron_event_name = "wcir_reward_status_update";
        public $manager;


        public function __construct()
        {
            require_once(plugin_dir_path(__FILE__) . 'inc/wcir-class-rewards-logger.php');
            require_once(plugin_dir_path(__FILE__) . 'inc/wcir-class-rewards-manager.php');
            require_once(plugin_dir_path(__FILE__) . 'inc/wcir-class-rewards-log-table.php');
            require_once(plugin_dir_path(__FILE__) . 'inc/wcir-class-rewards-table.php');
            require_once(plugin_dir_path(__FILE__) . 'inc/wcir-functions.php');


            $this->manager = new WCIRRewardsManager();
            $this->register();
        }

        public static function init()
        {
            if (is_null(self::$instance)) {
                self::$instance = new self;
            }

            return self::$instance;
        }

        /**
         * Register hooks
         */
        private function register()
        {
            add_action('admin_menu', array($this, 'add_admin_menu_pages'));

            add_action('admin_enqueue_scripts', array($this, 'enqueue'));

            // Register cron hook
            add_action(self::$cron_event_name, array($this->manager, 'update_status_based_on_dates'));

            // Hook to add reward
            add_action('woocommerce_before_calculate_totals', array($this->manager, 'set_reward_prices'), 10, 1);
            add_action('woocommerce_before_calculate_totals', array($this->manager, 'maybe_add_reward_to_cart'), 10, 1);

            // Hook to remove reward item quantity input on cart page
            add_filter('woocommerce_cart_item_quantity', array($this->manager, 'disable_cart_item_qty'), 10, 3);

            // Hook to remove reward item quantity input on mini-cart
            add_filter('woocommerce_widget_cart_item_quantity', array($this->manager, 'disable_cart_item_qty'), 10, 3);

            // Hook to handle the price in the mini-cart for the reward
            add_filter('woocommerce_cart_item_product', array($this->manager, 'set_reward_prices_mini_cart'), 10, 3);

            // Hook to change reward display name
            add_filter('woocommerce_cart_item_name', array($this->manager, 'change_reward_display_name'), 10, 3);

            // Hook to add item data to be displayed 
            add_filter('woocommerce_get_item_data', array($this->manager, 'add_item_data'), 10, 2);

            // Hook to add cart item data on order details
            add_action('woocommerce_checkout_create_order_line_item', array($this->manager, 'add_line_item_to_order_details'), 10, 4);

            // Hook to change the cart item display key on order details
            add_action('woocommerce_order_item_display_meta_key', array($this->manager, 'change_line_item_order_details_key'), 10, 3);

            // Hook to create a log and manage reward redemption
            add_action('woocommerce_new_order', array($this->manager, 'handle_reward_on_new_order'), 10, 2);

            // Hook to hide meta on admin order edit screen
            add_filter('woocommerce_hidden_order_itemmeta', array($this->manager, 'hide_meta_on_order_edit'), 10, 1);

            add_action('init', array($this->manager, 'process_editor_form'));
        }

        /**
         * Adding the admin menu pages and submenus
         */
        public function add_admin_menu_pages()
        {
            // Main reward list table page
            add_menu_page("WC Cart Item Rewards", "WC Cart Item Rewards", "manage_options", "wc-cart-item-rewards", array($this->manager, 'display_rewards_page'));
            add_submenu_page("wc-cart-item-rewards", "All Cart Item Rewards", "All Rewards", "manage_options", "wc-cart-item-rewards", array($this->manager, 'display_rewards_page'));

            // Add/edit reward page
            add_submenu_page("wc-cart-item-rewards", "Add Cart Item Reward", "Add", "manage_options", "wc-cart-item-rewards-editor", array($this->manager, 'display_rewards_editor_page'));

            // Log list table page
            add_submenu_page("wc-cart-item-rewards", "Cart Item Rewards Log", "Log", "manage_options", "wc-cart-item-rewards-log", array($this->manager, 'display_rewards_log_page'));
        }

        /**
         * Enqueue plugins styles and scripts
         */
        public function enqueue()
        {
            $whitelisted_pages = array('wc-cart-item-rewards', 'wc-cart-item-rewards-editor', 'wc-cart-item-rewards-log');
            if (is_admin() && isset($_GET['page']) && in_array($_GET['page'], $whitelisted_pages)) {
                // Plugin styles
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
        public static function activate()
        {
            if (!class_exists('WooCommerce')) {
                deactivate_plugins(WCIR_BASENAME);
                wp_die('Sorry, but this WC Cart Item Rewards requires WooCommerce to be installed and active.', 'Error', array('back_link' => true));
            }

            self::maybe_create_reward_table();

            self::maybe_create_reward_log_table();

            self::schedule_reward_status_update_cron();
        }

        /**
         * Handles when plugin deactivates
         * 
         * Deletes tables and scheduled cron job.
         * TODO: Remove delete db and move into plugin delete hook.
         */
        public static function deactivate()
        {
            self::delete_dbs(); // for development

            self::remove_reward_status_update_cron();
        }

        /**
         * Maybe create the reward table.
         */
        public static function maybe_create_reward_table()
        {
            global $wpdb;

            $table_name = $wpdb->prefix . self::$rewards_table_name;

            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id                      INT(15)             NOT NULL AUTO_INCREMENT,
                reward_name             VARCHAR(255)    NOT NULL,
                display_name            VARCHAR(255)    NOT NULL,
                product_id              INT(15)        NOT NULL,
                inline_cart_display     VARCHAR(255),
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
         * Maybe create the rewards log table.
         */
        public static function maybe_create_reward_log_table()
        {
            global $wpdb;

            $rewards_table_name = $wpdb->prefix . self::$rewards_table_name;
            $logger_table_name = $wpdb->prefix . self::$rewards_logger_table_name;

            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE IF NOT EXISTS $logger_table_name (
                        id                  INT(15)     NOT NULL AUTO_INCREMENT,
                        reward_id           INT(15)     NOT NULL,
                        product_id          INT(15)     NOT NULL,
                        user_id             INT(12)     NOT NULL,
                        order_number        INT(12)     NOT NULL,
                        redeemed_timestamp  TIMESTAMP   NOT NULL,
                        created_timestamp   TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY  (id),
                        FOREIGN KEY (reward_id) REFERENCES $rewards_table_name (id)
                    ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * Deletes all resources and tables.
         */
        public static function delete_dbs()
        {
            global $wpdb;
            $rewards_table_name = $wpdb->prefix . self::$rewards_table_name;
            $rewards_log_table_name = $wpdb->prefix . self::$rewards_logger_table_name;

            // Drop logger if it exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$rewards_log_table_name'") === $rewards_log_table_name) {
                $wpdb->query("DROP TABLE $rewards_log_table_name");
            }

            // Drop rewards table if it exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$rewards_table_name'") === $rewards_table_name) {
                $wpdb->query("DROP TABLE $rewards_table_name");
            }
        }

        /**
         * Creates a cron job to run next at 12am based on the sites timezone, then daily.
         */
        public static function schedule_reward_status_update_cron()
        {
            if (!wp_next_scheduled(self::$cron_event_name)) {

                $timestamp = current_datetime()->modify('tomorrow midnight')->getTimestamp();

                wp_schedule_event($timestamp, 'daily', self::$cron_event_name);
            }
        }

        /**
         * Removes the scheduled cron job.
         */
        public static function remove_reward_status_update_cron()
        {
            wp_clear_scheduled_hook(self::$cron_event_name);
        }
    }
}


register_activation_hook(__FILE__, array('WCIRPlugin', 'activate'));
register_deactivation_hook(__FILE__, array('WCIRPlugin', 'deactivate'));
add_action('plugins_loaded', array('WCIRPlugin', 'init'));
