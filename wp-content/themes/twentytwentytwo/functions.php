


<?php
/**
 * Twenty Twenty-Two functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_Two
 * @since Twenty Twenty-Two 1.0
 */


if ( ! function_exists( 'twentytwentytwo_support' ) ) :

	/**
	 * Sets up theme defaults and registers support for various WordPress features.
	 *
	 * @since Twenty Twenty-Two 1.0
	 *
	 * @return void
	 */
	function twentytwentytwo_support() {

		// Add support for block styles.
		add_theme_support( 'wp-block-styles' );

		// Enqueue editor styles.
		add_editor_style( 'style.css' );
	}

endif;

add_action( 'after_setup_theme', 'twentytwentytwo_support' );

if ( ! function_exists( 'twentytwentytwo_styles' ) ) :

	/**
	 * Enqueue styles.
	 *
	 * @since Twenty Twenty-Two 1.0
	 *
	 * @return void
	 */
	function twentytwentytwo_styles() {
		// Register theme stylesheet.
		$theme_version = wp_get_theme()->get( 'Version' );

		$version_string = is_string( $theme_version ) ? $theme_version : false;
		wp_register_style(
			'twentytwentytwo-style',
			get_template_directory_uri() . '/style.css',
			array(),
			$version_string
		);

		// Enqueue theme stylesheet.
		wp_enqueue_style( 'twentytwentytwo-style' );
	}

endif;

add_action( 'wp_enqueue_scripts', 'twentytwentytwo_styles' );

// Add block patterns
require get_template_directory() . '/inc/block-patterns.php';



// Register a custom REST API endpoint to receive data
add_action('rest_api_init', function () {
    register_rest_route('myplugin/v1', '/warehouse-data', array(
        'methods' => 'POST',
        'callback' => 'handle_warehouse_data',
    ));
});

// Callback function to process the incoming warehouse data
function handle_warehouse_data($request) {
    $data = $request->get_json_params(); // Get JSON data from request

    // Process the incoming data (e.g., update orders, inventory)
    // Example:
    // update_order($data['order_id'], $data['status']);

    return new WP_REST_Response('Data received', 200);
}

// Schedule an hourly event to fetch data from the warehouse API
if (!wp_next_scheduled('fetch_warehouse_data')) {
    wp_schedule_event(time(), 'hourly', 'fetch_warehouse_data');
}

// Hook the fetch function to the scheduled event
add_action('fetch_warehouse_data', 'fetch_warehouse_data_from_api');

// Function to fetch data from the warehouse API
function fetch_warehouse_data_from_api() {
    $api_url = 'https://fiverr-sandbox.shipstream.app/api/jsonrpc/';
    $api_user = 'qonotech';
    $api_password = '1ba374075c16ae20eb8dd2edfd7ade21';

    $response = wp_remote_post($api_url, array(
        'body'    => json_encode(array(
            'jsonrpc' => '2.0',
            'method'  => 'getData', // Replace with the actual method you want to call
            'params'  => array(), // Replace with the actual parameters required by the method
            'id'      => 1,
        )),
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode("$api_user:$api_password"),
            'Content-Type'  => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        error_log('Error fetching data from warehouse API: ' . $response->get_error_message());
        return;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);

    // Process the fetched data
    // Example:
    // update_order($data['result']['order_id'], $data['result']['status']);
}


