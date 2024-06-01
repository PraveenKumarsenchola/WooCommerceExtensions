<?php
// WooCommerce REST API credentials
define('WC_API_URL', 'http://localhost/woocomerce/wp-json/wc/v3/orders/');
define('WC_CONSUMER_KEY', 'ck_your_consumer_key');
define('WC_CONSUMER_SECRET', 'cs_your_consumer_secret');

// Shipstream WMS API URL and token
define('SHIPSTREAM_API_URL', 'https://your-shipstream-wms-endpoint.com/jsonrpc');
define('SHIPSTREAM_API_TOKEN', 'be1c13ed4e03f0ed7f1e4053dfff9658');
define('STORE_CODE', 'mystorecode');

function sendToShipstream($order_data) {
    $jsonrpc_data = [
        "jsonrpc" => "2.0",
        "id" => 1234,
        "method" => "call",
        "params" => [
            SHIPSTREAM_API_TOKEN,
            "order.create",
            [
                STORE_CODE,
                $order_data['items'],
                $order_data['address'],
                $order_data['details']
            ]
        ]
    ];

    $ch = curl_init(SHIPSTREAM_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonrpc_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('Error sending data to Shipstream WMS');
    } else {
        error_log('Order sent to Shipstream WMS successfully: ' . $response);
    }
}

function handleWooCommerceWebhook() {
    // Get the raw POST data from WooCommerce
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['id'])) {
        error_log('Invalid data received from WooCommerce');
        return;
    }

    // Fetch detailed order information from WooCommerce API
    $order_id = $data['id'];
    $ch = curl_init(WC_API_URL . $order_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, WC_CONSUMER_KEY . ":" . WC_CONSUMER_SECRET);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('Error fetching order: ' . curl_error($ch));
        return;
    }

    $order = json_decode($response, true);
    curl_close($ch);

    // Prepare data for Shipstream WMS
    $order_data = [
        'items' => [],
        'address' => [
            'firstname' => $order['billing']['first_name'],
            'lastname' => $order['billing']['last_name'],
            'company' => $order['billing']['company'],
            'street1' => $order['billing']['address_1'],
            'city' => $order['billing']['city'],
            'region' => $order['billing']['state'],
            'postcode' => $order['billing']['postcode'],
            'country' => $order['billing']['country'],
            'telephone' => $order['billing']['phone']
        ],
        'details' => [
            'order_ref' => $order['id'],
            'shipping_method' => 'ups_03',
            'custom_greeting' => 'Greeting text here',
            'note' => $order['customer_note'],
            'signature_required' => 'none',
            'saturday_delivery' => false,
            'declared_value_service' => false,
            'overbox' => false,
            'delayed_ship_date' => '2022-07-28',
            'duties_payor' => 'third_party',
            'duties_tpb_group_id' => '1',
            'custom_fields' => ['colors' => [['id' => 6]]]
        ]
    ];

    foreach ($order['line_items'] as $item) {
        $order_data['items'][] = [
            'sku' => $item['sku'],
            'qty' => $item['quantity'],
            'order_item_ref' => 'ref_' . $order['id'] . '-' . $item['id']
        ];
    }

    // Send data to Shipstream WMS
    sendToShipstream($order_data);
}

// Execute the webhook handler
handleWooCommerceWebhook();
?>
