<?php
/**
 * Plugin Name: ShipStream Middleware
 * Description: A middleware plugin to handle webhooks from ShipStream WMS and synchronize inventory with WooCommerce.
 * Version: 1.0.0
 * Author: Praveen Kumar
 * Text Domain: shipstream-middleware
 * 
 * @package ShipStreamMiddleware
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add action to register REST API route
add_action('rest_api_init', function () {
    register_rest_route('shipstream/v1', '/webhook', array(
        'methods' => 'POST',
        'callback' => 'handle_shipstream_webhook',
        'permission_callback' => 'verify_shipstream_webhook',
    ));
});

// Verify webhook request
function verify_shipstream_webhook(WP_REST_Request $request)
{
    $secret = get_option('shipstream_middleware_secret');
    $token = $request->get_header('X-ShipStream-Token');

    if (hash_equals($secret, $token)) {
        return true;
    }

    return false;
}

// Handle webhook request
function handle_shipstream_webhook(WP_REST_Request $request)
{
    // Get the webhook payload
    $data = $request->get_json_params();

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('ShipStream Webhook received: ' . print_r($data, true));
    }

    // Process the webhook data
    if (isset($data['order_id']) && isset($data['status'])) {
        $order_id = intval($data['order_id']);
        $status = sanitize_text_field($data['status']);

        $order = wc_get_order($order_id);
        if ($order) {
            $order->update_status($status, 'Order status updated via ShipStream webhook.');

            // Handle additional status updates
            if ($status === 'Processing' || $status === 'Ready to Ship') {
                $order->add_meta_data('_shipstream_order_ids', $data['shipstream_order_id'], true);
            }

            return new WP_REST_Response('Order status updated successfully', 200);
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ShipStream Webhook Error: Order not found.');
            }
            return new WP_REST_Response('Order not found', 404);
        }
    }

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('ShipStream Webhook Error: Invalid payload.');
    }
    return new WP_REST_Response('Invalid payload', 400);
}

// Add menu item for settings
add_action('admin_menu', 'shipstream_middleware_menu');

function shipstream_middleware_menu()
{
    add_menu_page(
        'ShipStream Middleware Settings',
        'ShipStream Middleware',
        'manage_options',
        'shipstream-middleware',
        'shipstream_middleware_settings_page',
        'dashicons-admin-generic'
    );
}

// Settings page content
function shipstream_middleware_settings_page()
{
    ?>
    <div class="wrap">
        <h1>ShipStream Middleware Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('shipstream_middleware_settings');
            do_settings_sections('shipstream_middleware');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Initialize settings
add_action('admin_init', 'shipstream_middleware_settings_init');

function shipstream_middleware_settings_init()
{
    register_setting('shipstream_middleware_settings', 'shipstream_middleware_secret');

    add_settings_section(
        'shipstream_middleware_section',
        'Webhook Settings',
        null,
        'shipstream_middleware'
    );

    add_settings_field(
        'shipstream_middleware_secret',
        'Webhook Secret Token',
        'shipstream_middleware_secret_render',
        'shipstream_middleware',
        'shipstream_middleware_section'
    );
}

// Render secret token field
function shipstream_middleware_secret_render()
{
    $secret = get_option('shipstream_middleware_secret');
    ?>
    <input type="text" name="shipstream_middleware_secret" value="<?php echo esc_attr($secret); ?>" />
    <?php
}

// Schedule Cron Job on Plugin Activation
register_activation_hook(__FILE__, 'shipstream_schedule_inventory_pull');

function shipstream_schedule_inventory_pull()
{
    if (!wp_next_scheduled('shipstream_inventory_pull')) {
        wp_schedule_event(strtotime('02:00:00'), 'daily', 'shipstream_inventory_pull');
    }
}

// Unschedule Cron Job on Plugin Deactivation
register_deactivation_hook(__FILE__, 'shipstream_remove_inventory_pull');

function shipstream_remove_inventory_pull()
{
    $timestamp = wp_next_scheduled('shipstream_inventory_pull');
    wp_unschedule_event($timestamp, 'shipstream_inventory_pull');
}

// Cron Job Hook
add_action('shipstream_inventory_pull', 'shipstream_inventory_pull_function');

function shipstream_inventory_pull_function()
{
    sleep(rand(1, 300));
    // Add code here to pull inventory from ShipStream
}

// Sync inventory when product is saved
add_action('save_post_product', 'shipstream_sync_inventory', 10, 3);

function shipstream_sync_inventory($post_id, $post, $update)
{
    if ($post->post_type != 'product') {
        return;
    }
   
}

// Remove inventory when product is deleted
add_action('delete_post', 'shipstream_delete_inventory', 10, 1);

function shipstream_delete_inventory($post_id)
{
    $post_type = get_post_type($post_id);
    if ($post_type != 'product') {
        return;
    }
   
}

add_action('woocommerce_shipstream_shipment_completed', 'shipstream_update_order_status', 10, 1);

function shipstream_update_order_status($order_id)
{

}


add_action('woocommerce_shipstream_shipment_completed', 'shipstream_add_tracking_numbers', 10, 1);

function shipstream_add_tracking_numbers($order_id)
{
   
}


add_action('woocommerce_shipstream_shipment_completed', 'shipstream_update_order_line_items_meta', 10, 1);

function shipstream_update_order_line_items_meta($order_id)
{
   
}
