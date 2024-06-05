<?php
/**
 * Plugin Name: ShipStream Integration WooCommerce
 * Plugin URI: 
 * Description: Integration with ShipStream for WooCommerce.
 * Version: 1.0.0
 * Author: Praveen Kumar
 * Author URI: 
 * Text Domain: shipstream-integration
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Include class-shipstream-api.php
include_once 'includes/class-shipstream-api.php';

// Hook into WooCommerce order completion
add_action('woocommerce_thankyou', 'send_order_to_wms', 10, 1);

// Add settings menu
add_action('admin_menu', 'shipstream_add_admin_menu');
add_action('admin_init', 'shipstream_settings_init');

function shipstream_add_admin_menu() {
    add_options_page('ShipStream Settings', 'ShipStream', 'manage_options', 'shipstream', 'shipstream_options_page');
}

function shipstream_settings_init() {
    register_setting('shipstream', 'shipstream_settings');

    add_settings_section(
        'shipstream_section',
        __('API Settings', 'shipstream-integration'),
        'shipstream_settings_section_callback',
        'shipstream'
    );

    add_settings_field(
        'shipstream_username',
        __('Username', 'shipstream-integration'),
        'shipstream_username_render',
        'shipstream',
        'shipstream_section'
    );

    add_settings_field(
        'shipstream_password',
        __('Password', 'shipstream-integration'),
        'shipstream_password_render',
        'shipstream',
        'shipstream_section'
    );

    add_settings_field(
        'shipstream_api_url',
        __('Would you like to use the Live URL or the Sandbox URL?', 'shipstream-integration'),
        'shipstream_api_url_render',
        'shipstream',
        'shipstream_section'
    );
}

function shipstream_username_render() {
    $options = get_option('shipstream_settings');
    ?>
    <input type='text' name='shipstream_settings[shipstream_username]' value='<?php echo isset($options['shipstream_username']) ? esc_attr($options['shipstream_username']) : ''; ?>'>
    <?php
}

function shipstream_password_render() {
    $options = get_option('shipstream_settings');
    ?>
    <input type='password' name='shipstream_settings[shipstream_password]' value='<?php echo isset($options['shipstream_password']) ? esc_attr($options['shipstream_password']) : ''; ?>'>
    <?php
}

function shipstream_api_url_render() {
    $options = get_option('shipstream_settings');
    ?>
    <input type="radio" name="shipstream_settings[shipstream_api_url]" value="live" <?php checked('live', isset($options['shipstream_api_url']) ? $options['shipstream_api_url'] : ''); ?> /> Live URL &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    <input type="radio" name="shipstream_settings[shipstream_api_url]" value="sandbox" <?php checked('sandbox', isset($options['shipstream_api_url']) ? $options['shipstream_api_url'] : ''); ?> /> Sandbox URL
    <?php
}

function shipstream_settings_section_callback() {
    // echo __('Enter your ShipStream API credentials here.', 'shipstream-integration');
}

function shipstream_options_page() {
    ?>
    <div class="wrap">
        <h2>ShipStream Settings</h2>
        <form action='options.php' method='post'>
            <?php
            settings_fields('shipstream');
            do_settings_sections('shipstream');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function send_order_to_wms($order_id) {
    try {
        if (!$order_id) {
            throw new Exception('Invalid order ID.');
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            throw new Exception('Order not found.');
        }

        $items = [];
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $items[] = [
                'sku' => $product->get_sku(),
                'qty' => $item->get_quantity(),
                'order_item_ref' => $item_id,
            ];
        }
        $billing = $order->get_address('billing');

        // Retrieve settings
        $options = get_option('shipstream_settings');

        $api_url = isset($options['shipstream_api_url']) && $options['shipstream_api_url'] === 'live' 
                    ? 'https://live-url.shipstream.app/api/jsonrpc/' 
                    : 'https://fiverr-sandbox.shipstream.app/api/jsonrpc/';
        $username = isset($options['shipstream_username']) ? $options['shipstream_username'] : '';
        $password = isset($options['shipstream_password']) ? $options['shipstream_password'] : '';

        if (empty($api_url) || empty($username) || empty($password)) {
            throw new Exception('API credentials are not set.');
        }

        // REST API credentials
        $login_data = [
            'jsonrpc' => '2.0',
            'id' => 1234,
            'method' => 'login',
            'params' => [
                $username,
                $password
            ]
        ];

        $session_token = make_json_rpc_call($api_url, $login_data);

        if (!$session_token) {
            throw new Exception('Login failed.');
        }

        $data = [
            'jsonrpc' => '2.0',
            'id' => 1234,
            'method' => 'call',
            'params' => [
                $session_token,
                "order.create",
                [
                    "",
                    $items,
                    [
                        'firstname' => $billing['first_name'],
                        'lastname' => $billing['last_name'],
                        'company' => $billing['company'],
                        'street1' => $billing['address_1'],
                        'city' => $billing['city'],
                        'region' => $billing['state'],
                        'postcode' => $billing['postcode'],
                        'country' => $billing['country'],
                        'telephone' => $billing['phone'],
                    ],
                    [
                        'order_ref' => $order->get_order_number(),
                        'shipping_method' => "ups_03",
                        'custom_greeting' => get_post_meta($order_id, '_custom_greeting', true),
                        'note' => $order->get_customer_note(),
                        'signature_required' => "none",
                        'saturday_delivery' => false,
                        'declared_value_service' => false,
                        'overbox' => false,
                        'delayed_ship_date' => get_post_meta($order_id, '_delayed_ship_date', true),
                        'duties_payor' => 'third_party',
                        'duties_tpb_group_id' => '1',
                        'custom_fields' => ['colors' => [['id' => 6]]]
                    ]
                ]
            ]
        ];

        $response = wp_remote_post($api_url, [
            'method' => 'POST',
            'body' => json_encode($data),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Error sending order to WMS: ' . $response->get_error_message());
        } else {
            $response_body = wp_remote_retrieve_body($response);
            $decoded_response = json_decode($response_body, true);

            if (isset($decoded_response['error'])) {
                throw new Exception('Error from WMS: ' . print_r($decoded_response['error'], true));
            } else {
                error_log('Response from WMS: ' . print_r($decoded_response, true));
                update_post_meta($order_id, '_wms_error', '');
            }
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        error_log($error_message);
        update_post_meta($order_id, '_wms_error', $error_message);
    }
}

function make_json_rpc_call($url, $data) {
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
        ],
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $response = json_decode($result, true);
    
    if (isset($response['result'])) {
        return $response['result'];
    }

    return false;
}
?>
