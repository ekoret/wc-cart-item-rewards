<?php

class WCIRRewardsAddEdit
{
    /**
     * Display the add and edit rewards page.
     */
    public function display_add_edit_rewards_page()
    {
        echo '<div class="wrap wc-cart-item-rewards-admin">';

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

        echo $page_title;

        // $reward_item is available in the template
        require_once(WCIR_VIEWS . "/reward-add-edit.php");

        echo '</div>';
    }

    function wcir_process_add_reward_form()
    {
        if (isset($_POST) && isset($_POST['wcir_delete_submit']) || isset($_POST['wcir_add_submit'])) {

            // handling deletion of reward
            if (isset($_POST['wcir_delete_submit'])) {
                $reward_id = intval($_GET['reward_id']);

                $delete_result = WCIRRewards::delete_reward($reward_id);

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

                    $result = WCIRRewards::update_reward($reward_data);
                } else {
                    $result = WCIRRewards::add_new_reward($reward_data);
                }

                if ($result) {
                    // if reward was added successfully, land on edit page with reward
                    wp_safe_redirect(admin_url("/admin.php?page=wc-cart-item-rewards"));
                    exit;
                }
            }
        }
    }
}
