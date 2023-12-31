
<?php

class WCIRRewardsManager
{
    /**
     * Display list of all the rewards.
     */
    public function display_rewards_page()
    {
        $wcir_table_instance = new WCIRRewardsTable(WCIRPlugin::$rewards_table_name);

        require_once(WCIR_VIEWS . "/reward-list.php");
    }

    /**
     * Display list of all the rewards.
     */
    public function display_rewards_log_page()
    {
        $wcir_table_instance = new WCIRRewardsLogTable(WCIRPlugin::$rewards_logger_table_name);

        require_once(WCIR_VIEWS . "/reward-log.php");
    }

    /**
     * Display the add and edit rewards page.
     */
    public function display_rewards_editor_page()
    {

        $page_title = "<h1>Add WC Cart Item Reward</h1>";

        if (isset($_GET) && isset($_GET['edit'])) {
            global $wpdb;

            $reward_table = $wpdb->prefix . WCIRPlugin::$rewards_table_name;

            $wcir_reward_id = intval($_GET['reward_id']);

            $prepared_query = $wpdb->prepare("SELECT * FROM $reward_table
                                           WHERE id = %d", array($wcir_reward_id));

            $reward_item = $wpdb->get_row($prepared_query, ARRAY_A);

            $page_title = "<h1>Edit WC Cart Item Reward (#" . $wcir_reward_id . ")</h1>";
        }

        require_once(WCIR_VIEWS . "/reward-editor.php");
    }

    /**
     * Handles adding the reward to the cart.
     * 
     * Hooked into 'woocommerce_before_calculate_totals'
     */
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
            if ($this->is_reward_in_cart($cart->get_cart(), $reward_id)) {
                /**
                 * First we check if the minimum order is null. If it is, that
                 * means that the minimum order is not set in the rewards settings.
                 * So in that case, we can completely skip over checking if it eligible
                 * for the cart as it will always be eligible for the cart.
                 */
                if (!is_null($minimum_order)) {
                    // Checking if the order is still eligbile for the reward
                    if ($this->is_cart_over_minimum($minimum_order, $cart_total_after_discounts)) {
                        continue;
                    } else {
                        $this->remove_reward_from_cart($cart, $reward_id);
                    }
                }
                continue;
            }

            $product_id = $reward['product_id'];
            $product = wc_get_product($product_id);
            $current_redemptions = $reward['current_redemptions'];
            $stock = $reward['stock'];
            $stock_status = $product->get_stock_status();

            // Check if the cart total is eligible 
            if (!$this->is_cart_over_minimum($minimum_order, $cart_total_after_discounts)) {
                continue;
            }

            // Check if there is any stock or redemptions available
            if (!$this->check_stock($current_redemptions, $stock, $stock_status)) {
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

        if (is_null($stock)) {
            return true;
        }

        // if stock redemption has supassed, false
        if ($current_redemptions >= $stock) {
            return false;
        }


        return true;
    }

    /**
     * Finds if a reward is already in the cart.
     */
    public function is_reward_in_cart($cart_items, $reward_id)
    {
        foreach ($cart_items as $cart_item) {
            if (isset($cart_item['wcir_reward_id']) && $cart_item['wcir_reward_id'] == $reward_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if carts total is eligible for a reward.
     */
    public function is_cart_over_minimum($minimum_order, $cart_total)
    {
        if (is_null($minimum_order) || $cart_total >= $minimum_order) {
            return true;
        }

        return false;
    }

    /***
     * Remove a reward from cart.
     */
    public function remove_reward_from_cart($cart, $reward_id)
    {
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['wcir_reward_id']) && $cart_item['wcir_reward_id'] == $reward_id) {
                $cart->remove_cart_item($cart_item_key);
            }
        }
    }


    /**
     * Get all active rewards.
     */
    public function get_all_active_rewards()
    {
        global $wpdb;

        $table = $wpdb->prefix . WCIRPlugin::$rewards_table_name;

        $rewards = $wpdb->get_results("SELECT * FROM $table WHERE status = 1", ARRAY_A);

        return $rewards;
    }

    /**
     * Add a new reward.
     */
    public function add_new_reward($reward_data)
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

    /**
     * Update a reward.
     */
    public function update_reward($reward_data)
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

    /**
     * Deletes a reward.
     */
    public function delete_reward($reward_id)
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

    /**
     * The cron method to run to update statues depending on start and end dates.
     * 
     * Hooked into 'WCIRPlugin::$cron_event_name'
     */
    public function update_status_based_on_dates()
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

    /**
     * Handle the add/edit/delete form to add/edit/delete reward.
     * 
     * Hooked into 'init'
     */
    public function process_editor_form()
    {
        if (isset($_POST) && isset($_POST['wcir_delete_submit']) || isset($_POST['wcir_add_submit'])) {

            // handling deletion of reward
            if (isset($_POST['wcir_delete_submit'])) {
                $reward_id = intval($_GET['reward_id']);

                $delete_result = $this->delete_reward($reward_id);

                // if deletion was successful, return user to all rewards page
                if ($delete_result) {
                    wp_safe_redirect(admin_url('/admin.php?page=wc-cart-item-rewards'));
                    exit;
                }
            }

            // handling adding of reward
            if (isset($_POST['wcir_add_submit'])) {
                $status = isset($_POST['wcir_status']) ? 1 : 0;
                $reward_name = sanitize_text_field($_POST['wcir_reward_name']);
                $display_name = sanitize_text_field($_POST['wcir_display_name']);
                $inline_cart_display = sanitize_text_field($_POST['wcir_inline_cart_display']);
                $product_id = intval($_POST['wcir_product_id']);
                $minimum_order = empty($_POST['wcir_minimum_order']) ? null : floatval($_POST['wcir_minimum_order']);
                $stock = empty($_POST['wcir_stock']) ? null : intval($_POST['wcir_stock']);
                $current_redemptions = empty($_POST['wcir_current_redemptions']) ? 0 : intval($_POST['wcir_current_redemptions']);
                $redemptions_per_user = empty($_POST['wcir_redemptions_per_user']) ? null : intval($_POST['wcir_redemptions_per_user']);
                $start_date = empty($_POST['wcir_start_date']) ? null : date('Y-m-d', strtotime($_POST['wcir_start_date']));
                $end_date = empty($_POST['wcir_end_date']) ? null : date('Y-m-d', strtotime($_POST['wcir_end_date']));

                $reward_data = array(
                    'status' => $status,
                    'reward_name' => $reward_name,
                    'display_name' => $display_name,
                    'inline_cart_display' => $inline_cart_display,
                    'product_id' => $product_id,
                    'minimum_order' => $minimum_order,
                    'stock' => $stock,
                    'current_redemptions' => $current_redemptions,
                    'redemptions_per_user' => $redemptions_per_user,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                );

                if (isset($_GET['edit'])) {
                    $reward_id = intval($_GET['reward_id']);
                    $reward_data['id'] = $reward_id;

                    $result = $this->update_reward($reward_data);
                } else {
                    $result = $this->add_new_reward($reward_data);
                }

                if ($result) {
                    // if reward was added successfully, land on edit page with reward
                    wp_safe_redirect(admin_url("/admin.php?page=wc-cart-item-rewards"));
                    exit;
                }
            }
        }
    }

    /**
     * Disbles the quantity fields in the mini-cart and cart.
     * 
     * Hooked into 'woocommerce_cart_item_quantity', 'woocommerce_widget_cart_item_quantity'
     */
    public function disable_cart_item_qty($quantity_html, $cart_item_key, $cart_item)
    {
        if (isset($cart_item['wcir_reward'])) {
            return '<span style="font-size:0.85rem;">1</span>';
        }
        return $quantity_html;
    }

    /**
     * Returns the display name of the reward
     */
    private function get_reward_display_name($reward_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . WCIRPlugin::$rewards_table_name;

        $reward = $wpdb->get_var($wpdb->prepare("SELECT display_name FROM $table WHERE id = %d", array($reward_id)));

        return $reward;
    }

    /**
     * Returns the reward name for the reward.
     */
    public static function get_reward_name($reward_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . WCIRPlugin::$rewards_table_name;

        $reward = $wpdb->get_var($wpdb->prepare("SELECT reward_name FROM $table WHERE id = %d", array($reward_id)));

        return $reward;
    }

    /**
     * Change the cart item name to the reward display name
     */
    public function change_reward_display_name($product_name, $cart_item, $cart_item_key)
    {
        if (isset($cart_item['wcir_reward'])) {
            $permalink = $cart_item['data']->get_permalink();

            $product_name = $this->get_reward_display_name($cart_item['wcir_reward_id']);

            $product_name = "<a href='" . $permalink . "'>" . $product_name . "</a>";
        }

        return $product_name;
    }

    /**
     * Adds an item data to be displayed in mini-cart, cart, checkout, order details
     * 
     * Hooked into 'woocommerce_get_item_data'
     */
    public function add_item_data($item_data, $cart_item)
    {
        if (isset($cart_item['wcir_reward'])) {
            $reward_id = $cart_item['wcir_reward_id'];

            $reward_inline_cart_display = $this->get_inline_cart_display($reward_id);

            if (!is_null($reward_inline_cart_display)) {
                $item_data[] = array(
                    "key" => 'Promo',
                    "value" => $reward_inline_cart_display
                );
            }
        }

        return $item_data;
    }

    /**
     * Returns the inline cart display name for the reward.
     */
    private function get_inline_cart_display($reward_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . WCIRPlugin::$rewards_table_name;

        $reward = $wpdb->get_var($wpdb->prepare("SELECT inline_cart_display FROM $table WHERE id = %d", array($reward_id)));

        return $reward;
    }

    /**
     * Change the price of the reward in the mini-cart
     * 
     * Hooked into 'woocommerce_cart_item_product'
     */
    public function set_reward_prices_mini_cart($cart_item_data, $cart_item, $cart_item_key)
    {
        if (isset($cart_item['wcir_reward'])) {
            $cart_item_data->set_price(0);
        }

        return $cart_item_data;
    }

    /**
     * Adds the inline cart item to order details.
     * 
     * Hooked into 'woocommerce_checkout_create_order_line_item'
     * 
     * Here we are in the context of turning a cart item into an order item. Use
     * this to transfer custom data from the cart to the order.
     */
    public function add_line_item_to_order_details($item, $cart_item_key, $values, $order)
    {
        if (isset($values['wcir_reward'])) {
            $inline_cart_item_name = $this->get_inline_cart_display($values['wcir_reward_id']);

            if (!is_null($inline_cart_item_name)) {
                $item->update_meta_data('wcir_promo', $inline_cart_item_name);
            }

            $item->update_meta_data('_wcir_reward', 1);
            $item->update_meta_data('_wcir_reward_id', $values['wcir_reward_id']);
        }
    }

    /**
     * Changes the inline cart item key name on order details.
     * 
     * Hooked into 'woocommerce_order_item_display_meta_key'
     */
    public function change_line_item_order_details_key($display_value, $meta, $order)
    {
        if ($meta->key === 'wcir_promo') {
            $display_value = 'Promo';
        }

        return $display_value;
    }

    /**
     * Adds 1 to the current redemptions for the reward.
     */
    public function increment_reward_redemption($reward_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . WCIRPlugin::$rewards_table_name;

        $current_redemptions = $wpdb->get_var($wpdb->prepare("SELECT current_redemptions FROM $table_name WHERE id = %d", $reward_id));

        if ($current_redemptions !== null) {
            $new_redemptions = $current_redemptions + 1;

            $data = array('current_redemptions' => $new_redemptions);
            $where = array('id' => $reward_id);


            $result = $wpdb->update($table_name, $data, $where);
            if ($result === false) {
                $error_message = $wpdb->last_error;
                error_log('WCIR Increment Reward Database Error: ' . $error_message);
            }
        } else {
            error_log('WCIR Increment Reward Database Error: Unable to retrieve current redemptions for reward ID ' . $reward_id);
        }
    }

    /**
     * Handles incrementing every reward in the order. Also creates a log for each
     * reward in the order.
     * 
     * Hooked into 'woocommerce_new_order'
     */
    public function handle_reward_on_new_order($order_id, $order)
    {

        foreach ($order->get_items() as $item) {
            $wcir_reward = $item->get_meta('wcir_reward');
            $wcir_reward_id = $item->get_meta('wcir_reward_id');

            if (empty($wcir_reward)) continue;

            $product_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();

            $args = array(
                'reward_id' => $wcir_reward_id,
                'product_id' => $product_id,
                'user_id' => $order->get_user_id(),
                'order_number' => $order_id,
                'redeemed_timestamp' => current_datetime()->getTimestamp()
            );

            WCIRRewardsLogger::add_log($args);
            $this->increment_reward_redemption($wcir_reward_id);
        }
    }

    /**
     * Hide redundant order meta on admin order edit.
     * 
     * Hooked into 'woocommerce_hidden_order_itemmeta'
     */
    public function hide_meta_on_order_edit($hidden_meta_keys)
    {
        $hidden_meta_keys[] = '_wcir_reward';
        $hidden_meta_keys[] = '_wcir_reward_id';
        return $hidden_meta_keys;
    }
}
