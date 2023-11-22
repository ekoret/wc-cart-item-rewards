<?php

require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');

class WCIRRewardsLogTable extends WP_List_Table
{
    public $table_name;

    public function __construct($table_name)
    {
        parent::__construct();

        $this->table_name = $table_name;
    }


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

        $table_name = $wpdb->prefix . $this->table_name;

        // By default sort by change date in descending order
        $orderby = !isset($_GET['orderby']) || empty($_GET['orderby']) ? "change_date" : trim($_GET['orderby']);
        $order = !isset($_GET['order']) || empty($_GET['order']) ? "DESC" : trim($_GET['order']);

        // Here we are ensuring that the orderby value is allowed
        $orderby_options = array(
            'product_id' => 'product_id',
            'redeemed_timestamp' => 'redeemed_timestamp',
            'created_timestamp' => 'created_timestamp',
        );
        $order_options = array(
            'asc' => 'ASC',
            'desc' => 'DESC'
        );

        $orderby = $orderby_options[$orderby] ?? 'created_timestamp';
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
            'created_timestamp' => array('created_timestamp', false),
            'redeemed_timestamp' => array('redeemed_timestamp', false),
        );
    }

    /**
     * Define the order and header names of the columns.
     */
    public function get_columns()
    {
        $columns = array(
            'reward_id' => '<span title="The reward name.">Reward Name</span>',
            'product_id' => '<span title="Product used for the reward.">Reward Item</span>',
            'user_id' => '<span title="The user that redeemed the reward.">User</span>',
            'order_number' => '<span title="Order number that redeemed the reward.">Order Number</span>',
            'redeemed_timestamp' => '<span title="When the reward was redeemed.">Redeemed Date</span>',
            'created_timestamp' => '<span title="When the log was created.">Date Logged</span>',
        );

        return $columns;
    }

    /**
     * Filter the output before it gets displayed here dpending on the header key.
     */
    protected function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'redeemed_timestamp': // Redeemed Date
                return $item[$column_name];
            case 'created_timestamp': // Date logged
                return date('Y-m-d h:i:s A', strtotime($item[$column_name]));

            case 'reward_id': // Reward name
                $reward_name = WCIRRewardsManager::get_reward_name($item[$column_name]);
                return "<a href='" . admin_url("/admin.php?page=wc-cart-item-rewards-editor&edit&reward_id=" . $item[$column_name] . "") . "'>" . $reward_name . "</a>";

            case 'product_id':  // Reward Item
                $product = wc_get_product($item[$column_name]);
                $name = $product->get_name();
                $permalink = $product->get_permalink();

                return "<a href='" . $permalink . "'>" . $name . "</a>";

            case 'user_id': // User name
                $user = get_user_by('id', $item[$column_name]);
                $user_name = $user->display_name;
                return  $user_name;

            case 'order_number':
                return "<a href='" . admin_url("/post.php?post=" . $item[$column_name] . "&action=edit") . "'>" . $item[$column_name] . "</a>";

            default:
                return "No Value";
        }
    }
}
