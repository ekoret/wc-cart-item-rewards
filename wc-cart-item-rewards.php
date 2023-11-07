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


if (!class_exists('WCCartItemRewards')) {

    class WCCartItemRewards
    {

        public static $identifier = "wcir";
        public static $rewards_table_name;
        // public static $rewards_log_table_name;
        public static $cron_event_name = "wcir_reward_status_update";


        public function __construct()
        {
            // Initialize static properties
            self::$rewards_table_name = self::$identifier . "_cart_item_rewards";
            // self::$rewards_log_table_name = self::$identifier . "_cart_item_rewards_log";

            $this->register();

            // Require main plugin functions
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
            add_action(self::$cron_event_name, array($this, 'update_status_based_on_dates'));

            add_action('admin_menu', array($this, 'add_admin_menu_pages'));

            add_action('admin_enqueue_scripts', array($this, 'enqueue'));

            // Hook to add reward
            add_action('woocommerce_before_calculate_totals', array($this, 'maybe_add_reward_to_cart'), 100, 1);
            add_action('woocommerce_before_calculate_totals', array($this, 'set_reward_prices'), 110, 1);
        }

        public function add_admin_menu_pages()
        {
            // Main reward list table page
            add_menu_page("WC Cart Item Rewards", "WC Cart Item Rewards", "manage_options", "wc-cart-item-rewards", array($this, 'all_rewards_page'));
            add_submenu_page("wc-cart-item-rewards", "All Cart Item Rewards", "All Rewards", "manage_options", "wc-cart-item-rewards", array($this, 'all_rewards_page'));

            // Add reward page
            add_submenu_page("wc-cart-item-rewards", "Add Cart Item Reward", "Add", "manage_options", "wc-cart-item-rewards-add", array($this, 'add_edit_rewards_page'));
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

        public function activate()
        {
            // create rewards table if it doesnt exist
            $this->maybe_create_reward_table();

            // schedule reward status update cron job
            $this->schedule_reward_status_update_cron();
        }

        public function deactivate()
        {
            $this->delete_db(); // for development

            $this->remove_reward_status_update_cron();
        }

        private function maybe_create_reward_table()
        {
            global $wpdb;

            $table_name = $wpdb->prefix . WCCartItemRewards::$rewards_table_name;

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

        // private function maybe_create_reward_log_table()
        // {
        //     global $wpdb;

        //     $table_name = $wpdb->prefix . WCCartItemRewards::$rewards_log_table_name;
        //     $rewards_table_name = $wpdb->prefix . WCCartItemRewards::$rewards_table_name;

        //     $charset_collate = $wpdb->get_charset_collate();

        //     $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        //                 id                  INT(15)         NOT NULL AUTO_INCREMENT,
        //                 product_id          INT(15)         NOT NULL,
        //                 user_id             INT(12)        NOT NULL,
        //                 order_number        INT(12)     NOT NULL,
        //                 reward_id           INT(15)      NOT NULL,
        //                 change_timestamp    TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
        //                 PRIMARY KEY  (id),
        //                 FOREIGN KEY (reward_id) REFERENCES $rewards_table_name (id)
        //             ) $charset_collate;";

        //     require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        //     dbDelta($sql);
        // }

        public function all_rewards_page()
        {
            echo '<div class="wrap wc-cart-item-rewards-admin">';
            echo '<h1>All WC Cart Item Rewards</h1>';

            // Include the HTML content from the /views folder
            require_once(WCIR_VIEWS . "/reward-list.php");

            echo '</div>';
        }

        public function add_edit_rewards_page()
        {
            echo '<div class="wrap wc-cart-item-rewards-admin">';

            $page_title = "<h1>Add WC Cart Item Reward</h1>";

            if (isset($_GET) && isset($_GET['edit'])) {
                global $wpdb;


                $reward_table = $wpdb->prefix . WCCartItemRewards::$rewards_table_name;

                $wcir_reward_id = intval($_GET['reward_id']);

                $prepared_query = $wpdb->prepare("SELECT * FROM $reward_table
                                           WHERE id = %d", array($wcir_reward_id));

                $reward_item = $wpdb->get_row($prepared_query, ARRAY_A);

                $page_title = "<h1>Edit WC Cart Item Reward (#" . $wcir_reward_id . ")</h1>";
            }

            echo $page_title;

            // $reward_item is available in the template
            require_once(WCIR_VIEWS . "/reward-add-edit.php");

            echo '</div>';
        }

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

        private function schedule_reward_status_update_cron()
        {
            if (!wp_next_scheduled(self::$cron_event_name)) {

                $timestamp = current_datetime()->modify('tomorrow midnight')->getTimestamp();

                wp_schedule_event($timestamp, 'daily', self::$cron_event_name);
            }
        }

        private function remove_reward_status_update_cron()
        {
            wp_clear_scheduled_hook(self::$cron_event_name);
        }

        public function maybe_add_reward_to_cart($cart)
        {
            $rewards = $this->get_all_active_rewards();

            // Get the total of the cart with discounts
            $cart_total_after_discounts = 0;
            foreach ($cart->get_cart() as $cart_item) {
                $cart_total_after_discounts += $cart_item['data']->get_price() * $cart_item['quantity'];
            }

            // Check all rewards if any are eligible for the cart
            foreach ($rewards as $reward) {
                $reward_id = $reward['id'];
                $minimum_order = $reward['minimum_order'];

                // Skip the reward if its already in the cart
                if ($this->check_if_reward_in_cart($cart->get_cart(), $reward_id)) {
                    // But before we do that, lets check if the order is still eligbile for the reward
                    if ($this->check_minimum_order_total($minimum_order, $cart_total_after_discounts)) {
                        continue;
                    } else {
                        $this->remove_reward_from_cart($cart, $reward_id);
                    }
                    continue;
                }

                $product_id = $reward['product_id'];
                $product = wc_get_product($product_id);
                $current_redemptions = $reward['current_redemptions'];
                // $redemptions_per_user = $reward['redemptions_per_user'];
                $stock = $reward['stock'];

                // check that there is stock left
                if (!$this->check_stock($current_redemptions, $stock, $product->get_stock_status())) {
                    continue;
                }

                // checking the cart amount after discounts
                if (!$this->check_minimum_order_total($minimum_order, $cart_total_after_discounts)) {
                    continue;
                }

                // if we're here we can add the reward, we will add custom data to track it later
                $cart_item_data = array(
                    'wcir_reward' => 1,
                    'wcir_reward_id' => $reward_id
                );
                $cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);
            }
        }

        /**
         * Set the reward prices to 0. This is called right after maybe_add_reward_to_cart
         */
        public function set_reward_prices($cart)
        {
            foreach ($cart->get_cart() as $cart_item) {
                if (isset($cart_item['wcir_reward'])) {
                    $cart_item['data']->set_price(0);
                }
            }
        }

        /**
         * Check if there is stock left for the reward and the product itself.
         */
        public function check_stock($current_redemptions, $stock, $stock_status)
        {
            // if the product is out of stock, false
            if ($stock_status === 'outofstock') {
                return false;
            }

            // if stock redemption has supassed, false
            if ($current_redemptions >= $stock) {
                return false;
            }


            return true;
        }

        public function check_if_reward_in_cart($cart_items, $reward_id)
        {
            foreach ($cart_items as $cart_item) {
                if (isset($cart_item['wcir_reward_id']) && $cart_item['wcir_reward_id'] == $reward_id) {
                    return true;
                }
            }

            return false;
        }

        public function check_minimum_order_total($minimum_order, $order_total)
        {
            if ($order_total >= $minimum_order) {
                return true;
            }

            return false;
        }

        public function remove_reward_from_cart($cart, $reward_id)
        {
            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                if (isset($cart_item['wcir_reward_id']) && $cart_item['wcir_reward_id'] == $reward_id) {
                    $cart->remove_cart_item($cart_item_key);
                }
            }
        }


        public function get_all_active_rewards()
        {
            global $wpdb;

            $table = $wpdb->prefix . self::$rewards_table_name;

            $rewards = $wpdb->get_results("SELECT * FROM $table WHERE status = 1", ARRAY_A);

            return $rewards;
        }

        public static function add_new_reward($reward_data)
        {
            global $wpdb;
            $table_name = $wpdb->prefix . self::$rewards_table_name;

            $result = $wpdb->insert($table_name, $reward_data);

            if ($result) {
                return true;
            } else {
                $error_message = $wpdb->last_error;
                error_log('WCIR Add Reward Database Error: ' . $error_message);

                return false;
            }
        }

        public static function update_reward($reward_data)
        {
            global $wpdb;
            $table_name = $wpdb->prefix . self::$rewards_table_name;


            $where = array('id' => $reward_data['id']);
            $result = $wpdb->update($table_name, $reward_data, $where);

            if ($result) {
                return true;
            } else {
                $error_message = $wpdb->last_error;
                error_log('WCIR Update Reward Database Error: ' . $error_message);

                return false;
            }
        }

        public static function delete_reward($reward_id)
        {
            global $wpdb;
            $table_name = $wpdb->prefix . self::$rewards_table_name;

            $result = $wpdb->delete($table_name, array('id' => $reward_id));

            if ($result) {
                return true;
            } else {
                $error_message = $wpdb->last_error;
                error_log('WCIR Delete Reward Database Error: ' . $error_message);

                return false;
            }
        }

        public static function update_status_based_on_dates()
        {
            global $wpdb;

            $table = $wpdb->prefix . self::$rewards_table_name;
            $current_date = current_time('mysql');
            $current_timestamp = strtotime($current_date);

            $rows_to_update = $wpdb->get_results("SELECT * FROM $table WHERE start_date IS NOT NULL OR end_date IS NOT NULL", ARRAY_A);

            foreach ($rows_to_update as $row) {
                $start_date = strtotime($row['start_date'] . ' 00:00:00');
                $end_date = strtotime($row['end_date'] . ' 23:59:59');

                if ($row['status'] === 1 && !is_null($end_date) && $current_timestamp > $end_date) {
                    $wpdb->update(
                        $table,
                        array('status' => 0),
                        array('id' => $row['id'])
                    );
                } else if ($row['status'] === 0 && !is_null($start_date) && $current_timestamp > $start_date) {

                    $wpdb->update(
                        $table,
                        array('status' => 1),
                        array('id' => $row['id'])
                    );
                }
            }
        }
    }
}





new WCCartItemRewards();
