<?php
/**
 * Admin settings page for OxyPods...
 *
 * Adds Settings → OxyPods.
 * Shows: status, field list, and a Diagnostics table with raw meta values.
 *
 * @package OxyPods
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', 'oxypods_register_admin_page' );
add_filter( 'plugin_action_links_' . plugin_basename( dirname( __DIR__ ) . '/oxypods.php' ), 'oxypods_plugin_action_links' );

function oxypods_register_admin_page(): void {
    add_options_page(
        'OxyPods',
        'OxyPods',
        'manage_options',
        'oxypods',
        'oxypods_render_admin_page'
    );
}

function oxypods_plugin_action_links( array $links ): array {
    $settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=oxypods' ) ) . '">Settings</a>';
    array_unshift( $links, $settings_link );
    return $links;
}

function oxypods_render_admin_page(): void {
    // SECURITY: explicit capability check as defence-in-depth.
    // add_options_page() already gates menu access, but this protects
    // against direct callback invocation outside the normal menu flow.
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to view this page.', 'oxypods' ) );
    }

    $pods_ok       = function_exists( 'pods_api' );
    $breakdance_ok = class_exists( 'Breakdance\\DynamicData\\DynamicDataController' );
    ?>
    <div class="wrap">
        <h1>OxyPods</h1>

        <!-- Status banner -->
        <div class="notice <?php echo ( $pods_ok && $breakdance_ok ) ? 'notice-success' : 'notice-error'; ?>" style="padding:10px 15px;">
            <?php if ( $pods_ok && $breakdance_ok ) : ?>
                <strong>✅ Both <a href="https://wordpress.org/plugins/pods/" target="_blank">Pods</a> and <a href="https://oxygenbuilder.com/" target="_blank">Oxygen 6</a> are active — OxyPods is running.</strong>
            <?php elseif ( ! $pods_ok ) : ?>
                <strong>❌ The Pods plugin is not active. Install and activate Pods to use OxyPods.</strong>
            <?php else : ?>
                <strong>❌ Oxygen 6 is not active. Install and activate Oxygen 6 to use OxyPods.</strong>
            <?php endif; ?>
        </div>

        <p>OxyPods exposes every Pods post-type field in the <strong>Oxygen 6 Dynamic Data</strong> picker under the <em>Pods</em> category, grouped by pod name. Learn more at <a href="https://github.com/QuirkyRobots/oxypods" target="_blank">github.com/QuirkyRobots/oxypods</a>.</p>

        <?php if ( $pods_ok && $breakdance_ok ) : ?>

        <!-- Registered fields table -->
        <h2>Detected Pods &amp; Fields</h2>
        <?php
        $all_pods = pods_api()->load_pods( [ 'type' => 'post_type' ] );
        if ( empty( $all_pods ) ) :
        ?>
            <p>No post-type pods found. Create a pod in the Pods admin to get started.</p>
        <?php else : ?>
            <table class="widefat striped" style="max-width:900px">
                <thead>
                    <tr>
                        <th>Pod</th>
                        <th>Field name</th>
                        <th>Field label</th>
                        <th>Pods type</th>
                        <th>Registered as</th>
                        <th>Slug</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $all_pods as $pod_data ) :
                    $pod_name  = (string) $pod_data['name'];
                    $pod_label = (string) ( $pod_data['label'] ?: $pod_name );
                    foreach ( $pod_data->get_fields() as $field ) :
                        $fname  = (string) $field['name'];
                        $ftype  = (string) $field['type'];
                        $flabel = (string) ( $field['label'] ?: $fname );
                        $fmt    = (string) ( $field['file_format_type'] ?? '' );

                        if ( 'password' === $ftype ) {
                            $registered_as = '— excluded (security)';
                            $slug          = '';
                        } elseif ( 'file' === $ftype ) {
                            if ( 'multi' === $fmt ) {
                                $registered_as = 'GalleryField';
                                $slug          = 'pods_gallery_' . $pod_name . '_' . $fname;
                            } else {
                                $registered_as = 'ImageField';
                                $slug          = 'pods_image_' . $pod_name . '_' . $fname;
                            }
                        } else {
                            $registered_as = 'StringField';
                            $slug          = 'pods_field_' . $pod_name . '_' . $fname;
                        }
                    ?>
                    <tr>
                        <td><?php echo esc_html( $pod_label ); ?></td>
                        <td><code><?php echo esc_html( $fname ); ?></code></td>
                        <td><?php echo esc_html( $flabel ); ?></td>
                        <td><code><?php echo esc_html( $ftype );
                            if ( 'file' === $ftype ) echo ' / ' . esc_html( $fmt );
                        ?></code></td>
                        <td><?php echo esc_html( $registered_as ); ?></td>
                        <td><code style="font-size:11px"><?php echo esc_html( $slug ); ?></code></td>
                    </tr>
                    <?php endforeach; endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Diagnostics -->
        <h2>Diagnostics</h2>
        <p>For each pod type, this shows raw meta values for the <strong>most recently published post</strong> of that type.
           If <code>_pods_{field}</code> is empty, the post has not been saved through Pods or meta storage is disabled.</p>
        <?php
        foreach ( $all_pods as $pod_data ) :
            $pod_name  = (string) $pod_data['name'];
            $pod_label = (string) ( $pod_data['label'] ?: $pod_name );

            // SECURITY: 'publish' only — never expose draft/private/trash post content.
            $sample_posts = get_posts( [
                'post_type'      => $pod_name,
                'posts_per_page' => 1,
                'orderby'        => 'modified',
                'order'          => 'DESC',
                'post_status'    => 'publish',
            ] );

            if ( empty( $sample_posts ) ) {
                echo '<p><strong>' . esc_html( $pod_label ) . '</strong>: no published posts found.</p>';
                continue;
            }

            $sample_id = $sample_posts[0]->ID;
        ?>
        <h3><?php echo esc_html( $pod_label ); ?> (sample post ID: <?php echo (int) $sample_id; ?>)</h3>
        <table class="widefat striped" style="max-width:900px;margin-bottom:20px">
            <thead>
                <tr>
                    <th>Field name</th>
                    <th>Pods type</th>
                    <th><code>_pods_{field}</code> value</th>
                    <th>Plain meta value</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $pod_data->get_fields() as $field ) :
                $fname = (string) $field['name'];
                $ftype = (string) $field['type'];

                // SECURITY: never display password field values in the UI.
                if ( 'password' === $ftype ) :
            ?>
            <tr>
                <td><code><?php echo esc_html( $fname ); ?></code></td>
                <td><code><?php echo esc_html( $ftype ); ?></code></td>
                <td colspan="3"><em>— excluded for security</em></td>
            </tr>
            <?php
                    continue;
                endif;

                $pods_meta  = get_post_meta( $sample_id, '_pods_' . $fname, true );
                $plain_meta = get_post_meta( $sample_id, $fname, false );
                $has_data   = ! empty( $pods_meta ) || ! empty( $plain_meta );
            ?>
            <tr>
                <td><code><?php echo esc_html( $fname ); ?></code></td>
                <td><code><?php echo esc_html( $ftype ); ?></code></td>
                <td><pre style="white-space:pre-wrap;margin:0;font-size:11px"><?php echo esc_html( print_r( $pods_meta, true ) ); ?></pre></td>
                <td><pre style="white-space:pre-wrap;margin:0;font-size:11px"><?php echo esc_html( print_r( $plain_meta, true ) ); ?></pre></td>
                <td><?php echo $has_data ? '✅' : '⚠️ empty'; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endforeach; ?>

        <h2>Troubleshooting</h2>
        <ol>
            <li>If the <code>_pods_{field}</code> column shows empty for an image field, <strong>re-save the post</strong> through the WordPress edit screen with Pods active, then reload this page.</li>
            <li>Run this SQL to verify directly (replace IDs):
                <pre style="background:#f0f0f0;padding:10px">SELECT meta_key, meta_value FROM wp_postmeta
WHERE post_id = YOUR_POST_ID
  AND (meta_key = 'your_field' OR meta_key = '_pods_your_field');</pre>
            </li>
            <li>To debug image IDs, temporarily add to your theme's <code>functions.php</code>:
                <pre style="background:#f0f0f0;padding:10px">add_action('wp_loaded', function() {
    $ids = oxypods_get_attachment_ids( YOUR_POST_ID, 'YOUR_FIELD_NAME' );
    error_log( 'OxyPods attachment IDs: ' . wp_json_encode( $ids ) );
});</pre>
                Then check <code>wp-content/debug.log</code>.
            </li>
            <li>If images resolve as empty, confirm attachments have <code>post_status = 'inherit'</code> in <code>wp_posts</code>.</li>
            <li>Check for a filter disabling Pods meta storage:
                <pre style="background:#f0f0f0;padding:10px">grep -r 'pods_relationship_meta_storage_enabled' /path/to/wp-content/</pre>
            </li>
        </ol>

        <?php endif; ?>
    </div>
    <?php
}
