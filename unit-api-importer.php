<?php
/**
 * Plugin Name: Unit API Importer
 * Description: A simple plugin to import units from an API and manage them using a custom post type.
 * Version: 1.0
 * Author: Derek Straga
 * Author URI: https://yourwebsite.com
 */

// Register the custom post type 'unit'
function unit_api_importer_register_post_type() {
    $labels = array(
        'name' => __('Units'),
        'singular_name' => __('Unit'),
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => false,
        'supports' => array('title', 'custom-fields'),
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-admin-multisite',
        'menu_position' => 25,
    );

    register_post_type('unit', $args);
}
add_action('init', 'unit_api_importer_register_post_type');

// Add the custom admin page
function unit_api_importer_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=unit',
        'Import Units',
        'Import Units',
        'manage_options',
        'unit-api-importer',
        'unit_api_importer_admin_page'
    );
}
add_action('admin_menu', 'unit_api_importer_admin_menu');

// Render the custom admin page
function unit_api_importer_admin_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Import Units', 'unit-api-importer'); ?></h1>
        <button id="unit-api-importer-btn" class="button button-primary"><?php _e('Import Units', 'unit-api-importer'); ?></button>
    </div>

    <script>
        document.getElementById('unit-api-importer-btn').addEventListener('click', function () {
            this.disabled = true;
            this.innerText = '<?php _e('Importing...', 'unit-api-importer'); ?>';

            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'unit_api_importer_import_units',
                    _wpnonce: '<?php echo wp_create_nonce('unit_api_importer_import_units'); ?>',
                }),
            })
            .then(response => response.json())
            .then(data => {
                this.disabled = false;
                this.innerText = '<?php _e('Import Units', 'unit-api-importer'); ?>';
                alert(data.message);
            });
        });
    </script>
    <?php
}

// Import the units using the API
function unit_api_importer_import_units() {
    check_ajax_referer('unit_api_importer_import_units');

    $api_endpoint = 'https://api.sightmap.com/v1/assets/1273/multifamily/units?per-page=250';
    $api_key = '7d64ca3869544c469c3e7a586921ba37';

    $response = wp_remote_get($api_endpoint, array(
        'headers' => array(
            'API-Key' => $api_key,
        ),
    ));

    if (is_wp_error($response)) {
        wp_send
        wp_send_json_error(array(
            'message' => __('Error fetching data from the API.', 'unit-api-importer'),
        ));
    }

    $units = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($units)) {
        wp_send_json_error(array(
            'message' => __('No units found in the API response.', 'unit-api-importer'),
        ));
    }

    foreach ($units as $unit) {
        // Check if the unit already exists
        $existing_unit = get_posts(array(
            'post_type' => 'unit',
            'meta_key' => 'asset_id',
            'meta_value' => $unit['asset_id'],
            'posts_per_page' => 1,
        ));

        if (!empty($existing_unit)) {
            continue;
        }

        // Insert a new unit post
        $post_id = wp_insert_post(array(
            'post_title' => $unit['unit_number'],
            'post_type' => 'unit',
            'post_status' => 'publish',
        ));

        // Add custom fields to the unit post
        update_post_meta($post_id, 'asset_id', $unit['asset_id']);
        update_post_meta($post_id, 'building_id', $unit['building_id']);
        update_post_meta($post_id, 'floor_id', $unit['floor_id']);
        update_post_meta($post_id, 'floor_plan_id', $unit['floor_plan_id']);
        update_post_meta($post_id, 'area', $unit['area']);
    }

    wp_send_json_success(array(
        'message' => __('Units imported successfully.', 'unit-api-importer'),
    ));
}
add_action('wp_ajax_unit_api_importer_import_units', 'unit_api_importer_import_units');

// Add the custom column to the unit admin post list
function unit_api_importer_manage_columns($columns) {
    $columns['floor_plan_id'] = __('Floor Plan ID', 'unit-api-importer');
    return $columns;
}
add_filter('manage_unit_posts_columns', 'unit_api_importer_manage_columns');

// Display the custom column data
function unit_api_importer_manage_custom_column($column, $post_id) {
    if ($column === 'floor_plan_id') {
        echo get_post_meta($post_id, 'floor_plan_id', true);
    }
}
add_action('manage_unit_posts_custom_column', 'unit_api_importer_manage_custom_column', 10, 2);
function unit_api_importer_enqueue_styles() {
    wp_enqueue_style('unit-api-importer-style', plugins_url('style.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'unit_api_importer_enqueue_styles');
function unit_api_importer_shortcode($atts) {
    $args = array(
        'post_type' => 'unit',
        'posts_per_page' => -1,
    );
    $units = get_posts($args);

    $output = '<div class="unit-api-importer-wrap">';
    $output .= '<h2>Units with an area greater than 1</h2>';
    $output .= '<ul class="unit-api-importer-unit-list">';
    foreach ($units as $unit) {
        $area = get_post_meta($unit->ID, 'area', true);
        if ($area > 1) {
            $output .= '<li>' . $unit->post_title . ' - Floor Plan ID: ' . get_post_meta($unit->ID, 'floor_plan_id', true) . '</li>';
        }
    }
    $output .= '</ul>';

    $output .= '<h2>Units with an area of 1</h2>';
    $output .= '<ul class="unit-api-importer-unit-list">';
    foreach ($units as $unit) {
        $area = get_post_meta($unit->ID, 'area', true);
        if ($area == 1) {
            $output .= '<li>' . $unit->post_title . ' - Floor Plan ID: ' . get_post_meta($unit->ID, 'floor_plan_id', true) . '</li>';
        }
    }
    $output .= '</ul>';
    $output .= '</div>';

    return $output;
}
add_shortcode('unit_api_importer', 'unit_api_importer_shortcode');
