<?php
/**
 * Plugin Name: OxyPods
 * Plugin URI:  https://github.com/QuirkyRobots/oxypods
 * Description: Exposes Pods custom fields in the Oxygen 6 Dynamic Data picker.
 * Version:     1.1.5
 * Requires PHP: 8.0
 * Author:      QuirkyRobots
 * Author URI:  https://github.com/QuirkyRobots
 * License:     GPL-2.0-or-later
 * Text Domain: oxypods
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * BOOTSTRAP TIMING RATIONALE
 * --------------------------
 * Oxygen 6 (Breakdance) fires 'breakdance_loaded' on 'plugins_loaded'.
 * We listen for 'breakdance_loaded' and then register our own 'wp_loaded'
 * callback. This guarantees:
 *   1. The Breakdance class hierarchy is fully declared before we extend it.
 *   2. Pods is fully initialised (pods_api() is callable) by wp_loaded.
 *   3. Our fields are registered before the 'template_include' filter where
 *      Breakdance fires its AJAX handler (breakdance_dynamic_data_get).
 */
add_action( 'breakdance_loaded', function () {
    add_action( 'wp_loaded', 'pods_oxygen6_bootstrap' );
} );

function pods_oxygen6_bootstrap(): void {
    // Guard: both plugins must be active.
    if ( ! function_exists( 'pods_api' ) ) {
        return;
    }
    if ( ! class_exists( 'Breakdance\\DynamicData\\DynamicDataController' ) ) {
        return;
    }

    // All field class definitions live here, loaded late so PHP doesn't try
    // to resolve the 'extends Breakdance\...' at parse time before Breakdance
    // has declared those abstract classes.
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-fields.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-admin-page.php';

    $controller = Breakdance\DynamicData\DynamicDataController::getInstance();
    $all_pods   = pods_api()->load_pods( [ 'type' => 'post_type' ] );

    foreach ( $all_pods as $pod_data ) {
        $pod_name  = (string) $pod_data['name'];
        $pod_label = (string) ( $pod_data['label'] ?: $pod_name );

        foreach ( $pod_data->get_fields() as $field ) {
            $field_type = (string) $field['type'];
            $field_name = (string) $field['name'];

            $meta = [
                'pod'       => $pod_name,
                'pod_label' => $pod_label,
                'name'      => $field_name,
                'label'     => (string) ( $field['label'] ?: $field_name ),
                'type'      => $field_type,
            ];

            switch ( $field_type ) {

                // NOTE: Pods 3.x has NO standalone 'image' field type.
                // All image/file uploads are 'file' type with file_type='images'.
                // The 'avatar' type exists but is restricted to 'user' pods,
                // so it will never appear in a post_type pod loop.

                case 'file':
                    // Options are stored FLAT on the field object (not nested).
                    // key format: {field_type}_{option_name}
                    //
                    // IMPORTANT: We intentionally do NOT gate on file_type here.
                    // file_type can be 'images', 'images-any', or 'other' (all files).
                    // A field set to 'other' may still contain image attachments — the
                    // user controls what they upload. We register purely on format_type:
                    //   multi  → GalleryField
                    //   single → ImageField
                    // This matches what the user intends when they set up the field.
                    $fmt = (string) ( $field['file_format_type'] ?? 'single' );

                    $meta['file_format_type'] = $fmt;

                    if ( 'multi' === $fmt ) {
                        $controller->registerField( new PodsOxygen6_GalleryField( $meta ) );
                    } else {
                        $controller->registerField( new PodsOxygen6_ImageField( $meta ) );
                    }
                    break;

                case 'pick':
                    // Relationship field – expose as a plain string (the related
                    // post title) so it appears in the picker.
                    $controller->registerField( new PodsOxygen6_StringField( $meta ) );
                    break;

                case 'boolean':
                    $controller->registerField( new PodsOxygen6_StringField( $meta ) );
                    break;

                default:
                    // text, paragraph, wysiwyg, number, currency, date, datetime,
                    // time, email, website, phone, color, code, slug, password, html
                    $controller->registerField( new PodsOxygen6_StringField( $meta ) );
                    break;
            }
        }
    }
}
