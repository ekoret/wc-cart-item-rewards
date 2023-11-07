<?php

require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');

class WCCartItemRewardsTable extends WP_List_Table
{
    public function prepare_items()
    {
        /**
         * Handling query param sorting
         */
        $orderby = isset($_GET['orderby']) ? trim($_GET['orderby']) : "";
        $order = isset($_GET['order']) ? trim($_GET['order']) : "";


        $this->items = $this->get_items($orderby, $order);


        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);
    }

    /**
     * Get the items from the database and set initial order.
     */
    private function get_items($orderby = '', $order = '')
    {
        global $wpdb;

        $table_name = $wpdb->prefix . WCCartItemRewards::$rewards_table_name;

        // By default sort by change date in descending order
        $orderby = !isset($_GET['orderby']) || empty($_GET['orderby']) ? "change_date" : trim($_GET['orderby']);
        $order = !isset($_GET['order']) || empty($_GET['order']) ? "DESC" : trim($_GET['order']);

        // Here we are ensuring that the orderby value is allowed
        $orderby_options = array(
            'product_id' => 'product_id',
            'change_timestamp' => 'change_timestamp'
        );
        $order_options = array(
            'asc' => 'ASC',
            'desc' => 'DESC'
        );

        $orderby = $orderby_options[$orderby] ?? 'change_timestamp';
        $order = $order_options[$order] ?? 'DESC';

        $result = $wpdb->get_results("SELECT * FROM $table_name ORDER BY $orderby $order", ARRAY_A);

        return $result;
    }

    /**
     * Make columns sortable
     */
    protected function get_sortable_columns()
    {
        return array(
            'product_id' => array('product_id', false),
            'change_timestamp' => array('change_timestamp', false),
            'reward_name' => array('reward_name', false),
            'stock' => array('stock', false),
            'status' => array('status', false),
            'start_date' => array('start_date', false),
            'end_date' => array('end_date', false),
        );
    }

    /**
     * Define the order and header names of the columns.
     */
    public function get_columns()
    {
        $columns = array(
            'reward_name' => '<span title="The reward name.">Reward Name</span>',
            'status' => '<span title="If the reward is enabled or disabled.">Status</span>',
            'product_id' => '<span title="Product used for the reward.">Reward Item</span>',
            'current_redemptions' => '<span title="The current stock of redemptions for this reward.">Redemptions</span>',
            'minimum_order' => '<span title="The customer must order more than or equal to this stock.">Minimum Order Amount</span>',
            'start_date' => '<span title="The start date of the reward.">Start Date</span>',
            'end_date' => '<span title="The end date of the reward.">End Date</span>',
            'change_timestamp' => '<span title="The date the reward was created or last modified.">Date Modified</span>',
        );

        return $columns;
    }

    /**
     * Filter the output before it gets displayed here dpending on the header key.
     */
    protected function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'minimum_order':
            case 'stock':
            case 'start_date':
            case 'end_date':
            case 'change_timestamp':
                return $item[$column_name];

            case 'reward_name':
                return "<a href='" . admin_url("/admin.php?page=wc-cart-item-rewards-add&edit&reward_id=" . $item['id'] . "") . "'>" . $item[$column_name] . "</a>";

            case 'current_redemptions':
                return $item[$column_name] . "/" . (!empty($item['stock']) ? $item['stock'] : '&infin;');

            case 'status':
                return $item[$column_name] == 0 ? "&#10006;" : "&#10004;";

            case 'product_id': // Reward Item

                $product_id = $item[$column_name];
                $product = wc_get_product($product_id);
                $name = $product->get_name();
                $permalink = $product->get_permalink();

                return "<a href='$permalink'>$name</a>";

            default:
                return "No Value";
        }
    }
}


function wcir_display_table()
{
    $wcir_instance = new WCCartItemRewardsTable();

    $add_reward_url = admin_url('/admin.php?page=wc-cart-item-rewards-add');
    $wcir_instance->prepare_items();
    echo '<a href="' . $add_reward_url . '" class="page-title-action">Add New Reward</a>';
    $wcir_instance->display();
}

wcir_display_table();
