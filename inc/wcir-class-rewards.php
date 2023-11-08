
<?php

class WCIRRewards
{
    /**
     * Display list of all the rewards.
     */
    public function display_rewards_page()
    {
        echo '<div class="wrap wc-cart-item-rewards-admin">';
        echo '<h1>All WC Cart Item Rewards</h1>';

        require_once(WCIR_VIEWS . "/reward-list.php");

        echo '</div>';
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

            // Check if reward is already in the cart
            if ($this->check_if_reward_in_cart($cart->get_cart(), $reward_id)) {
                // Check if the order is still eligbile for the reward
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
            $stock = $reward['stock'];

            // Check if there is any stock or redemptions available
            if (!$this->check_stock($current_redemptions, $stock, $product->get_stock_status())) {
                continue;
            }

            // Check if the cart total is eligible 
            if (!$this->check_minimum_order_total($minimum_order, $cart_total_after_discounts)) {
                continue;
            }

            // Adding custom data to handle in other hooks
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

        $table = $wpdb->prefix . WCIRPlugin::$rewards_table_name;

        $rewards = $wpdb->get_results("SELECT * FROM $table WHERE status = 1", ARRAY_A);

        return $rewards;
    }

    public static function add_new_reward($reward_data)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . WCIRPlugin::$rewards_table_name;

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
        $table_name = $wpdb->prefix . WCIRPlugin::$rewards_table_name;


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
        $table_name = $wpdb->prefix . WCIRPlugin::$rewards_table_name;

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

        $table = $wpdb->prefix . WCIRPlugin::$rewards_table_name;
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
