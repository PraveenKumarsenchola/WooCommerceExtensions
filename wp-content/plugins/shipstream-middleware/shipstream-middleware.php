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


// Retrieve settings
$options = get_option('shipstream_settings');


// Define ShipStream API credentials
define('SHIPSTREAM_API_URL', isset($options['shipstream_api_url']) ? $options['shipstream_api_url'] : '');
define('SHIPSTREAM_API_USERNAME', isset($options['shipstream_username']) ? $options['shipstream_username'] : '');
define('SHIPSTREAM_API_PASSWORD', isset($options['shipstream_password']) ? $options['shipstream_password'] : '');




// Register REST API route
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

    return hash_equals($secret, $token);
}






// Handle webhook request
function handle_shipstream_webhook(WP_REST_Request $request)
{
    $data = $request->get_json_params();

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('ShipStream Webhook received: ' . print_r($data, true));
    }

    if (isset($data['order_id']) && isset($data['status'])) {
        $order_id = intval($data['order_id']);
        $status = sanitize_text_field($data['status']);
        $shipstream_order_id = sanitize_text_field($data['shipstream_order_id']);

        $order = wc_get_order($order_id);
        if ($order) {
            $order->update_status($status, 'Order status updated via ShipStream webhook.');

            if ($status === 'processing' || $status === 'ready-to-ship') {
                $order->add_meta_data('_shipstream_order_ids', $shipstream_order_id, true);
                notify_shipstream_order($order_id);
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










// Send callback to ShipStream Plugin
function notify_shipstream_order($order_id)
{
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $order_data = array(
        'order_id' => $order->get_id(),
        'status' => $order->get_status(),
        'items' => array()
    );

    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();
        $order_data['items'][] = array(
            'sku' => $product->get_sku(),
            'quantity' => $item->get_quantity()
        );
    }

    $response = wp_remote_post(SHIPSTREAM_API_URL . 'orders', array(
        'body' => json_encode($order_data),
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(SHIPSTREAM_API_USERNAME . ':' . SHIPSTREAM_API_PASSWORD)
        )
    ));

    if (is_wp_error($response)) {
        $order->update_status('failed-to-submit', 'Failed to submit order to ShipStream.');
    } else {
        $order->update_status('submitted', 'Order submitted to ShipStream.');
    }
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
    sync_inventory_from_shipstream();
}




// Sync inventory from ShipStream
function sync_inventory_from_shipstream()
{
    $response = wp_remote_get(SHIPSTREAM_API_URL . 'inventory', array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode(SHIPSTREAM_API_USERNAME . ':' . SHIPSTREAM_API_PASSWORD)
        )
    ));

    if (is_wp_error($response)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Failed to fetch inventory from ShipStream: ' . $response->get_error_message());
        }
        return;
    }

    $inventory_data = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($inventory_data)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('No inventory data received from ShipStream.');
        }
        return;
    }

    foreach ($inventory_data as $item) {
        $product_id = wc_get_product_id_by_sku($item['sku']);
        if ($product_id) {
            update_post_meta($product_id, '_stock', $item['quantity']);
            wc_update_product_stock_status($product_id);
        }
    }
}











// Sync inventory when product is saved
add_action('save_post_product', 'shipstream_sync_inventory', 10, 3);


function shipstream_sync_inventory($post_id, $post, $update)
{
    if ($post->post_type != 'product') {
        return;
    }
    sync_inventory_from_shipstream();
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





// Add tracking numbers and update order meta when shipment is completed
add_action('woocommerce_shipstream_shipment_completed', 'shipstream_add_tracking_numbers', 10, 1);

function shipstream_add_tracking_numbers($order_id)
{
    // Fetch tracking info from ShipStream API and add to WooCommerce order
    $response = wp_remote_get(SHIPSTREAM_API_URL . 'shipments/' . $order_id, array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode(SHIPSTREAM_API_USERNAME . ':' . SHIPSTREAM_API_PASSWORD)
        )
    ));

    if (is_wp_error($response)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Failed to fetch shipment info from ShipStream: ' . $response->get_error_message());
        }
        return;
    }

    $shipment_info = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($shipment_info)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('No shipment info received from ShipStream.');
        }
        return;
    }

    foreach ($shipment_info as $shipment) {
        if (isset($shipment['tracking_number'])) {
            update_post_meta($order_id, '_tracking_number', $shipment['tracking_number']);
        }
    }
}






// Update order line items meta when shipment is completed
add_action('woocommerce_shipstream_shipment_completed', 'shipstream_update_order_line_items_meta', 10, 1);

function shipstream_update_order_line_items_meta($order_id)
{
    // Fetch shipment info from ShipStream API and update WooCommerce order line items meta
    $response = wp_remote_get(SHIPSTREAM_API_URL . 'shipments/' . $order_id, array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode(SHIPSTREAM_API_USERNAME . ':' . SHIPSTREAM_API_PASSWORD)
        )
    ));

    if (is_wp_error($response)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Failed to fetch shipment info from ShipStream: ' . $response->get_error_message());
        }
        return;
    }

    $shipment_info = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($shipment_info)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('No shipment info received from ShipStream.');
        }
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    foreach ($order->get_items() as $item_id => $item) {
        foreach ($shipment_info['items'] as $shipment_item) {
            if ($item->get_sku() === $shipment_item['sku']) {
                $item->add_meta_data('_shipstream_shipment_quantity', $shipment_item['quantity'], true);
                $item->save();
            }
        }
    }
}











// Update order status when shipment is completed
add_action('woocommerce_shipstream_shipment_completed', 'shipstream_update_order_status', 10, 1);

function shipstream_update_order_status($order_id)
{
    $order = wc_get_order($order_id);
    if ($order && $order->get_status() !== 'completed') {
        $order->update_status('completed', 'Order completed via ShipStream.');
    }
}
