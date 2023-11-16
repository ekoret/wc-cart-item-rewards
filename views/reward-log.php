<?php

$reward_log_url = admin_url('/admin.php?page=wc-cart-item-rewards-log');
$add_reward_url = admin_url('/admin.php?page=wc-cart-item-rewards-editor');
$wcir_table_instance->prepare_items();

?>

<div class="wrap wc-cart-item-rewards-admin">
    <h1>WC Cart Item Rewards Log</h1>
    <a href="<?php echo $reward_log_url ?>" class="page-title-action">View All Rewards</a>
    <a href="<?php echo $add_reward_url ?>" class="page-title-action">Add New Reward</a>

    <?php $wcir_table_instance->display(); ?>

</div>