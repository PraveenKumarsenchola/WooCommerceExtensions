ShipStream Integration WooCommerce
Version: 1.0.0
Author: Praveen Kumar



Description
This WordPress plugin integrates WooCommerce with ShipStream, a warehouse management system (WMS). It sends order information from WooCommerce to ShipStream upon order completion.



Features
Automatic order export to ShipStream upon WooCommerce order completion.
Configurable API settings through the WordPress admin panel.
Supports live and sandbox environments for testing and production use.
Installation



Download the Plugin:

Download the plugin files and upload them to your WordPress site's /wp-content/plugins/ directory.


Activate the Plugin:

Go to the WordPress admin dashboard.
Navigate to the "Plugins" menu and click "Add New".
Click "Upload Plugin" and select the plugin zip file.
Click "Install Now" and then activate the plugin.

Configure the Plugin:

Go to Settings -> ShipStream in the WordPress admin menu.
Enter your ShipStream API credentials (username and password).
Select the API URL environment (live or sandbox).
Click "Save Changes" to store your settings.


Usage

Once the plugin is installed and configured:

When a WooCommerce order is completed, it will automatically be sent to ShipStream.
If there is an error during the order export, it will be logged and saved as post meta on the order.


Plugin Settings
Username: Your ShipStream API username.
Password: Your ShipStream API password.
API URL: Choose between the live URL and the sandbox URL for testing.


Development

File Structure

includes/class-shipstream-api.php: Contains the API interaction logic.
shipstream-integration.php: Main plugin file which sets up the hooks and settings.

Hooks

woocommerce_thankyou: Triggered upon order completion to send order data to ShipStream.
admin_menu: Adds the ShipStream settings menu to the WordPress admin.
admin_init: Initializes the settings for the plugin.


Error Handling
If an error occurs during the order export, it will be logged using error_log and stored as post meta (_wms_error) on the WooCommerce order.


Support
For support or issues, please contact the plugin author or submit an issue on the GitHub repository (if applicable).

License
This plugin is licensed under the GPLv2 or later.