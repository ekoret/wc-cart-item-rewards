<div class="wrap wc-cart-item-rewards-admin">
    <?php echo $page_title; ?>
    <div style="display:flex;">
        <a href="<?php echo admin_url('/admin.php?page=wc-cart-item-rewards'); ?>" class="page-title-action">Cancel</a>
        <?php if (isset($_GET['edit'])) : ?>
            <form style="margin-left:10px;" method="post" action="">
                <input type="submit" name="wcir_delete_submit" id="wcir_delete_submit" class="page-title-action" value="Delete">
            </form>
        <?php endif; ?>
    </div>


    <form id="wcir_form" method="post" action="">

        <table class="form-table">
            <tbody>
                <tr>
                    <th>
                        <label for="wcir_status">Enabled</label>
                    </th>
                    <td>
                        <input id="wcir_status" name="wcir_status" type="checkbox" <?php echo isset($reward_item['status']) && $reward_item['status'] == 1 ? 'checked="checked"' : ''; ?>>
                        <label for="wcir_status">Toggle reward status.</label>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="wcir_reward_name">Reward Name</label>
                    </th>
                    <td>
                        <input required id="wcir_reward_name" name="wcir_reward_name" class="regular-text" type="text" value="<?php echo isset($reward_item['reward_name']) ? esc_attr($reward_item['reward_name']) : ''; ?>">
                        <p class="description">The reward name is for admin display in the backend.</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="wcir_display_name">Display Name</label>
                    </th>
                    <td>
                        <input id="wcir_display_name" required name="wcir_display_name" class="regular-text" type="text" value="<?php echo isset($reward_item['display_name']) ? esc_attr($reward_item['display_name']) : ''; ?>">
                        <p class="description">The name that will show for customers, order details, and packing slips.</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="wcir_inline_cart_display">Inline Cart Display Name</label>
                    </th>
                    <td>
                        <input id="wcir_inline_cart_display" required name="wcir_inline_cart_display" class="regular-text" type="text" value="<?php echo isset($reward_item['inline_cart_display']) ? esc_attr($reward_item['inline_cart_display']) : ''; ?>">
                        <p class="description">The name that will show under the products name in mini-cart, cart, checkout, order details, and packing slips.</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="wcir_product_id">Product</label>
                    </th>
                    <td>

                        <select data-security="<?php echo wp_create_nonce('search-products'); ?>" style="width: 300px;" id="wcir_product_id_select">
                            <?php if (isset($_GET['edit'])) : ?>
                                <?php
                                $product = wc_get_product($reward_item['product_id']);
                                $product_name = $product->get_name();
                                $product_sku = $product->get_sku();
                                $product_text = $product_name . " (" . $product_sku . ")";
                                ?>
                                <option value="<?php echo $reward_item['product_id']; ?>"><?php echo $product_text; ?></option>
                            <?php endif; ?>
                        </select>

                        <p class="description">The product that will be added to the customers order.</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="wcir_minimum_order">Minimum Order Amount</label>
                    </th>
                    <td>
                        <input id="wcir_minimum_order" name="wcir_minimum_order" max="9999" class="small-text" type="number" <?php echo isset($reward_item['minimum_order']) ? 'value="' . esc_attr($reward_item['minimum_order']) . '"' : ''; ?>>
                        <p class="description">The minimum order amount AFTER discounts.</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="wcir_stock">Stock</label>
                    </th>
                    <td>
                        <input id="wcir_stock" name="wcir_stock" max="999" class="small-text" type="number" <?php echo isset($reward_item['stock']) ? 'value="' . esc_attr($reward_item['stock']) . '"' : ''; ?>>
                        <p class="description">The amount of rewards available.</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="wcir_current_redemptions">Current Redemptions</label>
                    </th>
                    <td>
                        <input id="wcir_current_redemptions" name="wcir_current_redemptions" max="999" class="small-text" type="number" <?php echo isset($reward_item['current_redemptions'])  ? 'value="' . esc_attr($reward_item['current_redemptions']) . '"' : ''; ?>>
                        <p class="description">The amount of current redemptions. Use this to reset a reward.</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="wcir_redemptions_per_user">Redemptions per User</label>
                    </th>
                    <td>
                        <input id="wcir_redemptions_per_user" name="wcir_redemptions_per_user" max="10" class="tiny-text" type="number" <?php echo isset($reward_item['redemptions_per_user']) ? 'value="' . esc_attr($reward_item['redemptions_per_user']) . '"' : ''; ?>>
                        <p class="description">The amount of times the user can redeem the reward. Leave empty for unlimited.</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label>Scheduling</label>
                    </th>
                    <td>
                        <div>
                            <label for="wcir_start_date">Start Date</label>
                            <input id="wcir_start_date" name="wcir_start_date" class="regular-text" type="date" value="<?php echo isset($reward_item['start_date']) ? esc_attr($reward_item['start_date']) : ''; ?>">
                            <p class="description">If the start date is not set, the reward will begin immediately.</p>
                        </div>
                        <div style="margin-top:10px;">
                            <label for="wcir_end_date">End Date</label>
                            <input id="wcir_end_date" name="wcir_end_date" class="regular-text" type="date" value="<?php echo isset($reward_item['end_date']) ? esc_attr($reward_item['end_date']) : ''; ?>">
                            <p class="description">If there is no end date, the reward will not end unless disabled or there is no stock left.</p>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <input type="submit" name="wcir_add_submit" id="wcir_add_submit" class="button button-primary" value="Save Changes">
    </form>

</div>