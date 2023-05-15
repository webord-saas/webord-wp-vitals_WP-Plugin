<?php 
/*
Plugin Name: Webord WP Vitals
Plugin URI: https://plugins.webord.de/Webord-WP-Vitals
Description: Ein Plugin zum erfassen wichtiger Vitaldaten der Wordpress Instanz. Verwalten der Daten erfolgt im Webord Dashboard.
Version: 1.0.0
Author: Webord
Author URI: https://webord.de
License: None &copy; by HeNoMedia / Webord
*/

if (!defined('ABSPATH')) {
    exit;
}

// Create the plugin's setup page
function health_data_collector_setup_page() {
    add_options_page(
        'Health Data Collector Settings',
        'Health Data Collector',
        'manage_options',
        'health-data-collector',
        'health_data_collector_render_setup_page'
    );
}
add_action('admin_menu', 'health_data_collector_setup_page');

// Render the plugin's setup page
function health_data_collector_render_setup_page() {
    ?>
    <div class="wrap">
        <h1>Health Data Collector Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('health_data_collector');
            do_settings_sections('health_data_collector');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings and fields for the setup page
function health_data_collector_register_settings() {
    register_setting('health_data_collector', 'workspace_key');
    register_setting('health_data_collector', 'api_key');
    register_setting('health_data_collector', 'website_key');

    add_settings_section(
        'health_data_collector_section',
        'API Keys',
        'health_data_collector_section_callback',
        'health_data_collector'
    );

    add_settings_field(
        'workspace_key',
        'Workspace Key',
        'health_data_collector_workspace_key_callback',
        'health_data_collector',
        'health_data_collector_section'
    );

    add_settings_field(
        'api_key',
        'API Key',
        'health_data_collector_api_key_callback',
        'health_data_collector',
        'health_data_collector_section'
    );

    add_settings_field(
        'website_key',
        'Website Key',
        'health_data_collector_website_key_callback',
        'health_data_collector',
        'health_data_collector_section'
    );
}
add_action('admin_init', 'health_data_collector_register_settings');

// Callback functions to render individual fields
function health_data_collector_workspace_key_callback() {
    $workspace_key = get_option('workspace_key');
    echo "<input type='text' name='workspace_key' value='$workspace_key' />";
}

function health_data_collector_api_key_callback() {
    $api_key = get_option('api_key');
    echo "<input type='text' name='api_key' value='$api_key' />";
}

function health_data_collector_website_key_callback() {
    $website_key = get_option('website_key');
    echo "<input type='text' name='website_key' value='$website_key' />";
}

function health_data_collector_get_latest_wordpress_version() {
    $response = wp_remote_get('https://api.wordpress.org/core/version-check/1.7/');

    if (is_wp_error($response)) {
        // Error occurred while retrieving the version
        error_log('Health Data Collector - Error retrieving WordPress version: ' . $response->get_error_message());
        return '';
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['offers'][0]['current'])) {
        return $data['offers'][0]['current'];
    }

    return '';
}

// Retrieve the list of plugins with updates
function health_data_collector_get_plugins_with_updates() {
    $updates = get_plugin_updates();

    $plugins_with_updates = array();
    foreach ($updates as $update) {
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $update->plugin);
        $plugins_with_updates[] = array(
            'name' => $plugin_data['Name'],
            'version' => $plugin_data['Version'],
        );
    }

    return $plugins_with_updates;
}

// Check if there are available plugin updates
function health_data_collector_check_plugin_updates() {
    $updates = get_plugin_updates();

    if (empty($updates)) {
        return false;
    }

    return true;
}

// Collect and send health data every hour
function health_data_collector_collect_data() {
    // Get the saved API keys
    $workspace_key = get_option('workspace_key');
    $api_key = get_option('api_key');
    $website_key = get_option('website_key');

    // Retrieve the latest WordPress version
    $latest_wordpress_version = health_data_collector_get_latest_wordpress_version();
  
    // Check if WordPress version is updateable
    $is_wordpress_updateable = (version_compare(get_bloginfo('version'), $latest_wordpress_version, '<')) ? true : false;


    // Retrieve the current PHP version
    $php_version = phpversion();

    // Check if there are available plugin updates
    $plugin_updates_available = health_data_collector_check_plugin_updates();

    // Retrieve the list of plugins with updates
    $plugins_with_updates = health_data_collector_get_plugins_with_updates();

    // Collect health data
    $health_data = array(
        'wordpress_version' => get_bloginfo('version'),
        'latest_wordpress_version' => $latest_wordpress_version,
        'php_version' => $php_version,
        'plugin_updates_available' => $plugin_updates_available,
        'installed_plugins' => get_plugins(),
        'plugins_with_updates' => $plugins_with_updates,
      'is_wordpress_updateable' => $is_wordpress_updateable,
        // Add more data as needed
    );

    // Convert data to JSON
    $json_data = json_encode($health_data );

    // Set the webhook URL (replace 'YOUR_WEBHOOK_URL' with the actual URL)
    $webhook_url = 'https://webhook.site/0abcd629-1f98-4997-a4c4-6b6e9d489eae';

    // Send data to the webhook using POST request
    $args = array(
        'body' => $json_data,
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
        'timeout' => 60,
    );

    $response = wp_remote_post($webhook_url, $args);

    // Handle the response
    if (is_wp_error($response)) {
        // Error occurred while sending data
        error_log('Health Data Collector - Error sending data: ' . $response->get_error_message());
    } else {
        // Data sent successfully
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        error_log('Health Data Collector - Data sent successfully. Response code: ' . $response_code);
        // You can handle the response as needed
    }
}


add_action('health_data_collector_cron', 'health_data_collector_collect_data');

// Schedule the data collection cron job on plugin activation
function health_data_collector_activate() {
    wp_schedule_event(time(), 'hourly', 'health_data_collector_cron');

}
register_activation_hook(__FILE__, 'health_data_collector_activate');

// Remove the data collection cron job on plugin deactivation
function health_data_collector_deactivate() {
    wp_clear_scheduled_hook('health_data_collector_cron');
}
register_deactivation_hook(__FILE__, 'health_data_collector_deactivate');


?>
