# WC Cart Item Rewards

**WC Cart Item Rewards** is a user-friendly WordPress plugin designed to enhance your WooCommerce store's customer experience. With this plugin, you can easily incentivize your customers by offering free items based on their order total. Reward your loyal shoppers and boost sales with the following features:

## Features

### Flexible Reward Creation

Create custom rewards that suit your business needs. Define reward names, display names, associated products, and more.

### Order Total-Based Rewards

Automatically add free items to the customer's cart when their order total meets specific criteria. Encourage larger purchases and increase customer satisfaction.

### Redemption Control

Set the number of redemptions allowed per user to manage the distribution of rewards.

### Minimum Order Requirement

Define minimum order amounts that customers must reach to qualify for rewards.

### Stock Management

Keep track of reward stock to ensure you don't oversell free items.

### Time-Limited Rewards

Set start and end dates for rewards to run during specific periods, such as promotions and seasonal events.

### User-Friendly Admin Interface

Manage your rewards effortlessly through a user-friendly admin interface.

### Cron-Based Status Updates

Automatically update reward statuses based on start and end dates, ensuring accurate reward availability.

## Future Plans

- Add option to give rewards depending on amount of orders made
  - This will allow for things such as "first orders receive X"
- Add option to set discount on item
  - Instead of only free rewards, assign discounts to product rewards
- Add coupon support
  - If a coupon is added to the cart, attach a free gift

## To-Do List

- When order gets into processing/completed, increment redemption counter for reward
  - Either increment when it gets added to cart, in processing, or completed
  - Decrement when removed from cart, processing, or completed
- REFACTOR
- Create logs
- Add WooCommerce plugin requirement.

  ### Error Handling

- Handle errors when updating items with cron.
- Handle errors when submitting the form to add a reward.
  - End date should not be before start date
- Handle errors when there are issues deleting rewards from the database.
- Handle errors if there are form submission issues when editing.
