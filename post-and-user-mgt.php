<?php
/**
 * Plugin Name:       WordPress Post and User Manager
 * Plugin URI:        https://darrenk.uk
 * Description:       A plugin to export posts/users to CSV and delete posts/users by date or all.
 * Version:           1.1.0
 * Author:            Darren Kandekore
 * Author URI:        https://darrenk.uk
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

/// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Increase PHP limits for large operations
// Set maximum execution time to unlimited (0)
set_time_limit(0);
// Set memory limit to 512MB to handle large data exports/deletions
ini_set('memory_limit', '512M');


/**
 * Register admin menu pages.
 */
function wpmd_register_admin_menu_pages() {
    add_menu_page(
        __( 'WP Manager', 'wpmd' ),            // Page title
        __( 'WP Manager', 'wpmd' ),            // Menu title
        'manage_options',                      // Capability required to access
        'wpmd-manager',                        // Menu slug
        'wpmd_main_dashboard_page_content',    // Callback function to display content
        'dashicons-admin-generic',             // Icon URL or Dashicon class
        80                                     // Position in the menu
    );

    add_submenu_page(
        'wpmd-manager',                                // Parent slug
        __( 'Manage Posts', 'wpmd' ),                  // Page title
        __( 'Manage Posts', 'wpmd' ),                  // Menu title
        'manage_options',                              // Capability
        'wpmd-manage-posts',                           // Menu slug
        'wpmd_posts_management_page_content'           // Callback function
    );

    add_submenu_page(
        'wpmd-manager',                                // Parent slug
        __( 'Manage Users', 'wpmd' ),                  // Page title
        __( 'Manage Users', 'wpmd' ),                  // Menu title
        'manage_options',                              // Capability
        'wpmd-manage-users',                           // Menu slug
        'wpmd_users_management_page_content'           // Callback function
    );
}
add_action( 'admin_menu', 'wpmd_register_admin_menu_pages' );

/**
 * Enqueue admin scripts for dynamic counts and general functionality.
 */
function wpmd_enqueue_admin_scripts( $hook_suffix ) {
    // Only load on plugin's admin pages
    if ( strpos( $hook_suffix, 'wpmd-manager' ) !== false || strpos( $hook_suffix, 'wpmd-manage-posts' ) !== false || strpos( $hook_suffix, 'wpmd-manage-users' ) !== false ) {
        wp_enqueue_script( 'wpmd-admin-script', plugin_dir_url( __FILE__ ) . 'wpmd-admin.js', array( 'jquery' ), '1.0', true );
        wp_localize_script( 'wpmd-admin-script', 'wpmd_ajax_object', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wpmd_ajax_nonce' ),
        ) );
    }
}
add_action( 'admin_enqueue_scripts', 'wpmd_enqueue_admin_scripts' );


/**
 * Main dashboard page content.
 */
function wpmd_main_dashboard_page_content() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'WP Manager Dashboard', 'wpmd' ); ?></h1>
        <p><?php esc_html_e( 'Welcome to the WordPress Post and User Manager plugin. Use the submenus to manage your posts and users.', 'wpmd' ); ?></p>
        <p><?php esc_html_e( 'Please select "Manage Posts" or "Manage Users" from the sidebar menu to proceed.', 'wpmd' ); ?></p>
    </div>
    <?php
}

/**
 * Posts management page content.
 */
function wpmd_posts_management_page_content() {
    // Handle form submissions for posts
    wpmd_handle_posts_action();

    // Get all post types that are shown in the UI (admin area)
    $post_types = get_post_types( array( 'show_ui' => true ), 'objects' );

    // Exclude specific built-in post types that are typically not managed directly by users for export/deletion
    $excluded_post_types = array(
        'attachment',
        'revision',
        'nav_menu_item',
        'custom_css',
        'customize_changeset',
        'oembed_cache',
        'user_request', // GDPR requests
        'wp_block', // Reusable blocks
        'wp_template', // FSE templates
        'wp_template_part', // FSE template parts
        'wp_global_styles', // FSE global styles
        'wp_navigation', // FSE navigation
    );

    foreach ( $excluded_post_types as $exclude_key ) {
        if ( isset( $post_types[ $exclude_key ] ) ) {
            unset( $post_types[ $exclude_key ] );
        }
    }

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Manage Posts', 'wpmd' ); ?></h1>

        <?php if ( isset( $_GET['message'] ) ) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html( sanitize_text_field( $_GET['message'] ) ); ?></p>
            </div>
        <?php endif; ?>

        <?php if ( isset( $_GET['error_message'] ) ) : ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html( sanitize_text_field( $_GET['error_message'] ) ); ?></p>
            </div>
        <?php endif; ?>

        <h2><?php esc_html_e( 'Export Posts', 'wpmd' ); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'wpmd_export_posts_nonce', 'wpmd_export_posts_nonce_field' ); ?>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="wpmd_post_type_export"><?php esc_html_e( 'Select Post Type to Export', 'wpmd' ); ?></label></th>
                        <td>
                            <select name="wpmd_post_type_export" id="wpmd_post_type_export">
                                <?php foreach ( $post_types as $post_type ) : ?>
                                    <option value="<?php echo esc_attr( $post_type->name ); ?>"><?php echo esc_html( $post_type->labels->singular_name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Filter by Date', 'wpmd' ); ?></th>
                        <td>
                            <label for="wpmd_export_date_option_all">
                                <input type="radio" name="wpmd_export_date_option" id="wpmd_export_date_option_all" value="all" checked>
                                <?php esc_html_e( 'All Dates', 'wpmd' ); ?>
                            </label><br>
                            <label for="wpmd_export_date_option_single">
                                <input type="radio" name="wpmd_export_date_option" id="wpmd_export_date_option_single" value="single">
                                <?php esc_html_e( 'Before/After Specific Date', 'wpmd' ); ?>
                            </label>
                            <input type="date" name="wpmd_export_date_single" id="wpmd_export_date_single" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" disabled>
                            <select name="wpmd_export_date_condition" id="wpmd_export_date_condition" disabled>
                                <option value="before"><?php esc_html_e( 'Before', 'wpmd' ); ?></option>
                                <option value="after"><?php esc_html_e( 'After', 'wpmd' ); ?></option>
                            </select><br>
                            <label for="wpmd_export_date_option_range">
                                <input type="radio" name="wpmd_export_date_option" id="wpmd_export_date_option_range" value="range">
                                <?php esc_html_e( 'Between Date Range', 'wpmd' ); ?>
                            </label>
                            <input type="date" name="wpmd_export_date_start" id="wpmd_export_date_start" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" disabled>
                            <?php esc_html_e( 'to', 'wpmd' ); ?>
                            <input type="date" name="wpmd_export_date_end" id="wpmd_export_date_end" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" disabled>
                            <p class="description"><?php esc_html_e( 'Choose to export posts from all dates, before/after a specific date, or within a date range.', 'wpmd' ); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p id="wpmd-export-post-count-info" class="description"></p>
            <p class="submit">
                <input type="submit" name="wpmd_export_posts_submit" id="wpmd_export_posts_submit" class="button button-primary" value="<?php esc_attr_e( 'Export Posts to CSV', 'wpmd' ); ?>">
            </p>
        </form>


        <hr>

        <h2><?php esc_html_e( 'Delete Posts', 'wpmd' ); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'wpmd_delete_posts_nonce', 'wpmd_delete_posts_nonce_field' ); ?>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="wpmd_post_type_delete"><?php esc_html_e( 'Select Post Type to Delete', 'wpmd' ); ?></label></th>
                        <td>
                            <select name="wpmd_post_type_delete" id="wpmd_post_type_delete">
                                <?php foreach ( $post_types as $post_type ) : ?>
                                    <option value="<?php echo esc_attr( $post_type->name ); ?>"><?php echo esc_html( $post_type->labels->singular_name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Delete by Date', 'wpmd' ); ?></th>
                        <td>
                            <label for="wpmd_delete_date_option_all">
                                <input type="radio" name="wpmd_delete_date_option" id="wpmd_delete_date_option_all" value="all" checked>
                                <?php esc_html_e( 'No Date Filter (use "Delete All" checkbox)', 'wpmd' ); ?>
                            </label><br>
                            <label for="wpmd_delete_date_option_single">
                                <input type="radio" name="wpmd_delete_date_option" id="wpmd_delete_date_option_single" value="single">
                                <?php esc_html_e( 'Before/After Specific Date', 'wpmd' ); ?>
                            </label>
                            <input type="date" name="wpmd_delete_date_single" id="wpmd_delete_date_single" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" disabled>
                            <select name="wpmd_delete_date_condition" id="wpmd_delete_date_condition" disabled>
                                <option value="before"><?php esc_html_e( 'Before', 'wpmd' ); ?></option>
                                <option value="after"><?php esc_html_e( 'After', 'wpmd' ); ?></option>
                            </select><br>
                            <label for="wpmd_delete_date_option_range">
                                <input type="radio" name="wpmd_delete_date_option" id="wpmd_delete_date_option_range" value="range">
                                <?php esc_html_e( 'Between Date Range', 'wpmd' ); ?>
                            </label>
                            <input type="date" name="wpmd_delete_date_start" id="wpmd_delete_date_start" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" disabled>
                            <?php esc_html_e( 'to', 'wpmd' ); ?>
                            <input type="date" name="wpmd_delete_date_end" id="wpmd_delete_date_end" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" disabled>
                            <p class="description"><?php esc_html_e( 'Choose to delete posts before/after a specific date, or within a date range.', 'wpmd' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Or Delete All', 'wpmd' ); ?></th>
                        <td>
                            <label for="wpmd_delete_all_posts">
                                <input type="checkbox" name="wpmd_delete_all_posts" id="wpmd_delete_all_posts" value="1">
                                <?php esc_html_e( 'Check this to delete ALL posts of the selected type, regardless of date filters.', 'wpmd' ); ?>
                            </label>
                            <p class="description"><strong style="color: red;"><?php esc_html_e( 'WARNING: This action cannot be undone!', 'wpmd' ); ?></strong></p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p id="wpmd-delete-post-count-info" class="description"></p>
            <p class="submit">
                <input type="submit" name="wpmd_delete_posts_submit" id="wpmd_delete_posts_submit" class="button button-danger" value="<?php esc_attr_e( 'Delete Posts', 'wpmd' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete these posts? This action cannot be undone.', 'wpmd' ); ?>');">
            </p>
        </form>
    </div>
    <?php
}

/**
 * Users management page content.
 */
function wpmd_users_management_page_content() {
    // Handle form submissions for users
    wpmd_handle_users_action();

    // Get all user roles
    global $wp_roles;
    $roles = $wp_roles->get_names();

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Manage Users', 'wpmd' ); ?></h1>

        <?php if ( isset( $_GET['message'] ) ) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html( sanitize_text_field( $_GET['message'] ) ); ?></p>
            </div>
        <?php endif; ?>

        <?php if ( isset( $_GET['error_message'] ) ) : ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html( sanitize_text_field( $_GET['error_message'] ) ); ?></p>
            </div>
        <?php endif; ?>

        <h2><?php esc_html_e( 'Export Users', 'wpmd' ); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'wpmd_export_users_nonce', 'wpmd_export_users_nonce_field' ); ?>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="wpmd_user_role_export"><?php esc_html_e( 'Select User Role to Export', 'wpmd' ); ?></label></th>
                        <td>
                            <select name="wpmd_user_role_export" id="wpmd_user_role_export">
                                <?php foreach ( $roles as $role_key => $role_name ) : ?>
                                    <option value="<?php echo esc_attr( $role_key ); ?>"><?php echo esc_html( $role_name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Filter by Registration Date', 'wpmd' ); ?></th>
                        <td>
                            <label for="wpmd_user_export_date_option_all">
                                <input type="radio" name="wpmd_user_export_date_option" id="wpmd_user_export_date_option_all" value="all" checked>
                                <?php esc_html_e( 'All Dates', 'wpmd' ); ?>
                            </label><br>
                            <label for="wpmd_user_export_date_option_single">
                                <input type="radio" name="wpmd_user_export_date_option" id="wpmd_user_export_date_option_single" value="single">
                                <?php esc_html_e( 'Before/After Specific Date', 'wpmd' ); ?>
                            </label>
                            <input type="date" name="wpmd_user_export_date_single" id="wpmd_user_export_date_single" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" disabled>
                            <select name="wpmd_user_export_date_condition" id="wpmd_user_export_date_condition" disabled>
                                <option value="before"><?php esc_html_e( 'Before', 'wpmd' ); ?></option>
                                <option value="after"><?php esc_html_e( 'After', 'wpmd' ); ?></option>
                            </select><br>
                            <label for="wpmd_user_export_date_option_range">
                                <input type="radio" name="wpmd_user_export_date_option" id="wpmd_user_export_date_option_range" value="range">
                                <?php esc_html_e( 'Between Date Range', 'wpmd' ); ?>
                            </label>
                            <input type="date" name="wpmd_user_export_date_start" id="wpmd_user_export_date_start" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" disabled>
                            <?php esc_html_e( 'to', 'wpmd' ); ?>
                            <input type="date" name="wpmd_user_export_date_end" id="wpmd_user_export_date_end" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" disabled>
                            <p class="description"><?php esc_html_e( 'Choose to export users from all dates, before/after a specific date, or within a date range.', 'wpmd' ); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p id="wpmd-export-user-count-info" class="description"></p>
            <p class="submit">
                <input type="submit" name="wpmd_export_users_submit" id="wpmd_export_users_submit" class="button button-primary" value="<?php esc_attr_e( 'Export Users to CSV', 'wpmd' ); ?>">
            </p>
        </form>

        <hr>

        <h2><?php esc_html_e( 'Delete Users', 'wpmd' ); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'wpmd_delete_users_nonce', 'wpmd_delete_users_nonce_field' ); ?>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="wpmd_user_role_delete"><?php esc_html_e( 'Select User Role to Delete', 'wpmd' ); ?></label></th>
                        <td>
                            <select name="wpmd_user_role_delete" id="wpmd_user_role_delete">
                                <?php foreach ( $roles as $role_key => $role_name ) : ?>
                                    <option value="<?php echo esc_attr( $role_key ); ?>"><?php echo esc_html( $role_name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Delete by Registration Date', 'wpmd' ); ?></th>
                        <td>
                            <label for="wpmd_user_delete_date_option_all">
                                <input type="radio" name="wpmd_user_delete_date_option" id="wpmd_user_delete_date_option_all" value="all" checked>
                                <?php esc_html_e( 'No Date Filter (use "Delete All" checkbox)', 'wpmd' ); ?>
                            </label><br>
                            <label for="wpmd_user_delete_date_option_single">
                                <input type="radio" name="wpmd_user_delete_date_option" id="wpmd_user_delete_date_option_single" value="single">
                                <?php esc_html_e( 'Before/After Specific Date', 'wpmd' ); ?>
                            </label>
                            <input type="date" name="wpmd_user_delete_date_single" id="wpmd_user_delete_date_single" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" disabled>
                            <select name="wpmd_user_delete_date_condition" id="wpmd_user_delete_date_condition" disabled>
                                <option value="before"><?php esc_html_e( 'Before', 'wpmd' ); ?></option>
                                <option value="after"><?php esc_html_e( 'After', 'wpmd' ); ?></option>
                            </select><br>
                            <label for="wpmd_user_delete_date_option_range">
                                <input type="radio" name="wpmd_user_delete_date_option" id="wpmd_user_delete_date_option_range" value="range">
                                <?php esc_html_e( 'Between Date Range', 'wpmd' ); ?>
                            </label>
                            <input type="date" name="wpmd_user_delete_date_start" id="wpmd_user_delete_date_start" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" disabled>
                            <?php esc_html_e( 'to', 'wpmd' ); ?>
                            <input type="date" name="wpmd_user_delete_date_end" id="wpmd_user_delete_date_end" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" disabled>
                            <p class="description"><?php esc_html_e( 'Choose to delete users before/after a specific date, or within a date range.', 'wpmd' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Or Delete All', 'wpmd' ); ?></th>
                        <td>
                            <label for="wpmd_delete_all_users">
                                <input type="checkbox" name="wpmd_delete_all_users" id="wpmd_delete_all_users" value="1">
                                <?php esc_html_e( 'Check this to delete ALL users of the selected role, regardless of date filters.', 'wpmd' ); ?>
                            </label>
                            <p class="description"><strong style="color: red;"><?php esc_html_e( 'WARNING: This action cannot be undone!', 'wpmd' ); ?></strong></p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p id="wpmd-delete-user-count-info" class="description"></p>
            <p class="submit">
                <input type="submit" name="wpmd_delete_users_submit" id="wpmd_delete_users_submit" class="button button-danger" value="<?php esc_attr_e( 'Delete Users', 'wpmd' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete these users? This action cannot be undone.', 'wpmd' ); ?>');">
            </p>
        </form>
    </div>
    <?php
}

/**
 * Handle post actions (export and delete).
 */
function wpmd_handle_posts_action() {
    // Handle Export Posts
    if ( isset( $_POST['wpmd_export_posts_submit'] ) && current_user_can( 'manage_options' ) ) {
        if ( ! isset( $_POST['wpmd_export_posts_nonce_field'] ) || ! wp_verify_nonce( $_POST['wpmd_export_posts_nonce_field'], 'wpmd_export_posts_nonce' ) ) {
            wp_die( __( 'Security check failed.', 'wpmd' ) );
        }

        $post_type = sanitize_text_field( $_POST['wpmd_post_type_export'] );
        $date_option = sanitize_text_field( $_POST['wpmd_export_date_option'] );
        $date_single = sanitize_text_field( $_POST['wpmd_export_date_single'] );
        $date_condition = sanitize_text_field( $_POST['wpmd_export_date_condition'] );
        $date_start = sanitize_text_field( $_POST['wpmd_export_date_start'] );
        $date_end = sanitize_text_field( $_POST['wpmd_export_date_end'] );

        wpmd_export_posts_to_csv( $post_type, $date_option, $date_single, $date_condition, $date_start, $date_end );
    }

    // Handle Delete Posts
    if ( isset( $_POST['wpmd_delete_posts_submit'] ) && current_user_can( 'manage_options' ) ) {
        if ( ! isset( $_POST['wpmd_delete_posts_nonce_field'] ) || ! wp_verify_nonce( $_POST['wpmd_delete_posts_nonce_field'], 'wpmd_delete_posts_nonce' ) ) {
            wp_die( __( 'Security check failed.', 'wpmd' ) );
        }

        $post_type        = sanitize_text_field( $_POST['wpmd_post_type_delete'] );
        $delete_all_posts = isset( $_POST['wpmd_delete_all_posts'] ) ? true : false;
        $date_option      = sanitize_text_field( $_POST['wpmd_delete_date_option'] );
        $date_single      = sanitize_text_field( $_POST['wpmd_delete_date_single'] );
        $date_condition   = sanitize_text_field( $_POST['wpmd_delete_date_condition'] );
        $date_start       = sanitize_text_field( $_POST['wpmd_delete_date_start'] );
        $date_end         = sanitize_text_field( $_POST['wpmd_delete_date_end'] );

        $deleted_count = wpmd_delete_posts( $post_type, $delete_all_posts, $date_option, $date_single, $date_condition, $date_start, $date_end );
        if ( $deleted_count !== false ) {
            $message = sprintf( _n( '%s post deleted successfully.', '%s posts deleted successfully.', $deleted_count, 'wpmd' ), $deleted_count );
            wp_safe_redirect( add_query_arg( 'message', urlencode( $message ), admin_url( 'admin.php?page=wpmd-manage-posts' ) ) );
            exit;
        } else {
            $error_message = __( 'Error deleting posts. Check your debug log for details.', 'wpmd' );
            wp_safe_redirect( add_query_arg( 'error_message', urlencode( $error_message ), admin_url( 'admin.php?page=wpmd-manage-posts' ) ) );
            exit;
        }
    }
}

/**
 * Exports posts of a selected type to a CSV file, including custom fields.
 *
 * @param string $post_type The post type to export.
 * @param string $date_option 'all', 'single', or 'range'.
 * @param string $date_single Date for single date filter.
 * @param string $date_condition 'before' or 'after' for single date filter.
 * @param string $date_start Start date for range filter.
 * @param string $date_end End date for range filter.
 */
function wpmd_export_posts_to_csv( $post_type, $date_option, $date_single, $date_condition, $date_start, $date_end ) {
    $args = array(
        'post_type'      => $post_type,
        'posts_per_page' => -1, // Get all posts of this type
        'post_status'    => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash' ), // Include all statuses
        'orderby'        => 'ID',
        'order'          => 'ASC',
        // 'fields'         => 'ids', // No longer using 'ids' because we need post meta
    );

    // Add date query based on selected option
    if ( 'single' === $date_option && ! empty( $date_single ) ) {
        if ( 'before' === $date_condition ) {
            $args['date_query'] = array(
                array(
                    'before'    => $date_single . ' 23:59:59',
                    'inclusive' => true,
                ),
            );
        } elseif ( 'after' === $date_condition ) {
            $args['date_query'] = array(
                array(
                    'after'     => $date_single . ' 00:00:00',
                    'inclusive' => true,
                ),
            );
        }
    } elseif ( 'range' === $date_option && ! empty( $date_start ) && ! empty( $date_end ) ) {
        $args['date_query'] = array(
            array(
                'after'     => $date_start . ' 00:00:00',
                'before'    => $date_end . ' 23:59:59',
                'inclusive' => true,
            ),
        );
    }

    $posts = get_posts( $args );

    if ( empty( $posts ) ) {
        // Redirect back with a message if no posts found
        wp_safe_redirect( add_query_arg( 'message', urlencode( __( 'No posts found for the selected type and date filters to export. Try adjusting your filters.', 'wpmd' ) ), admin_url( 'admin.php?page=wpmd-manage-posts' ) ) );
        exit;
    }

    // Set headers for CSV download
    header( 'Content-Type: text/csv; charset=' . get_option( 'blog_charset' ) );
    header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $post_type ) . '_posts_export_' . date( 'Y-m-d' ) . '.csv"' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    $output = fopen( 'php://output', 'w' );

    // Define initial CSV headers
    $headers = array(
        __( 'ID', 'wpmd' ),
        __( 'Title', 'wpmd' ),
        __( 'Content', 'wpmd' ),
        __( 'Excerpt', 'wpmd' ),
        __( 'Status', 'wpmd' ),
        __( 'Date Published', 'wpmd' ),
        __( 'Last Modified', 'wpmd' ),
        __( 'Author ID', 'wpmd' ),
        __( 'Author Name', 'wpmd' ),
        __( 'Categories', 'wpmd' ),
        __( 'Tags', 'wpmd' ),
        __( 'Permalink', 'wpmd' ),
        __( 'Featured Image URL', 'wpmd' ),
    );

    // Collect all unique custom meta keys for the selected post type
    $custom_meta_keys = array();
    foreach ( $posts as $post ) {
        $post_custom = get_post_custom( $post->ID );
        foreach ( $post_custom as $key => $values ) {
            // Exclude WordPress internal meta keys (start with '_')
            if ( ! empty( $values ) && ! in_array( $key, $headers ) && strpos( $key, '_' ) !== 0 ) {
                $custom_meta_keys[ $key ] = $key; // Use key as value for header
            }
        }
    }
    // Sort custom meta keys alphabetically
    ksort( $custom_meta_keys );
    $headers = array_merge( $headers, array_values( $custom_meta_keys ) );

    fputcsv( $output, $headers );

    foreach ( $posts as $post ) {
        $author_id   = $post->post_author;
        $author_info = get_userdata( $author_id );
        $author_name = $author_info ? $author_info->display_name : '';

        $categories_list = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
        $categories_str  = ! empty( $categories_list ) ? implode( ', ', $categories_list ) : '';

        $tags_list = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );
        $tags_str  = ! empty( $tags_list ) ? implode( ', ', $tags_list ) : '';

        $featured_image_url = '';
        if ( has_post_thumbnail( $post->ID ) ) {
            $featured_image_url = get_the_post_thumbnail_url( $post->ID, 'full' );
        }

        $row = array(
            $post->ID,
            $post->post_title,
            wp_strip_all_tags( $post->post_content ), // Strip HTML tags from content
            wp_strip_all_tags( $post->post_excerpt ), // Strip HTML tags from excerpt
            $post->post_status,
            $post->post_date,
            $post->post_modified,
            $author_id,
            $author_name,
            $categories_str,
            $tags_str,
            get_permalink( $post->ID ),
            $featured_image_url,
        );

        // Add custom field values
        foreach ( $custom_meta_keys as $meta_key ) {
            $meta_value = get_post_meta( $post->ID, $meta_key, true );
            if ( is_array( $meta_value ) ) {
                $row[] = wp_json_encode( $meta_value ); // Encode arrays to JSON string
            } else {
                $row[] = $meta_value;
            }
        }
        fputcsv( $output, $row );
    }

    fclose( $output );
    exit; // Important to exit after file download
}

/**
 * Deletes posts of a selected type based on date or all.
 *
 * @param string $post_type The post type to delete.
 * @param bool   $delete_all If true, delete all posts of the type.
 * @param string $date_option 'all', 'single', or 'range'.
 * @param string $date_single Date for single date filter.
 * @param string $date_condition 'before' or 'after' for single date filter.
 * @param string $date_start Start date for range filter.
 * @param string $date_end End date for range filter.
 * @return int|false Number of deleted posts on success, false on error.
 */
function wpmd_delete_posts( $post_type, $delete_all, $date_option, $date_single, $date_condition, $date_start, $date_end ) {
    error_log( 'wpmd_delete_posts called for post type: ' . $post_type );
    error_log( 'Delete all: ' . ( $delete_all ? 'true' : 'false' ) );
    error_log( 'Date option: ' . $date_option );
    error_log( 'Date single: ' . $date_single );
    error_log( 'Date condition: ' . $date_condition );
    error_log( 'Date start: ' . $date_start );
    error_log( 'Date end: ' . $date_end );

    $args = array(
        'post_type'      => $post_type,
        'posts_per_page' => -1,
        'post_status'    => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash' ),
        'fields'         => 'ids',
    );

    if ( $delete_all ) {
        // No date filtering needed if deleting all
        error_log( 'Deleting all posts of type: ' . $post_type );
    } elseif ( 'single' === $date_option && ! empty( $date_single ) ) {
        if ( 'before' === $date_condition ) {
            $args['date_query'] = array(
                array(
                    'before'    => $date_single . ' 23:59:59',
                    'inclusive' => true,
                ),
            );
            error_log( 'Deleting posts before: ' . $date_single );
        } elseif ( 'after' === $date_condition ) {
            $args['date_query'] = array(
                array(
                    'after'     => $date_single . ' 00:00:00',
                    'inclusive' => true,
                ),
            );
            error_log( 'Deleting posts after: ' . $date_single );
        }
    } elseif ( 'range' === $date_option && ! empty( $date_start ) && ! empty( $date_end ) ) {
        $args['date_query'] = array(
            array(
                'after'     => $date_start . ' 00:00:00',
                'before'    => $date_end . ' 23:59:59',
                'inclusive' => true,
            ),
        );
        error_log( 'Deleting posts between: ' . $date_start . ' and ' . $date_end );
    } else {
        error_log( 'No valid deletion criteria for posts. Returning 0.' );
        return 0;
    }

    $posts_to_delete = get_posts( $args );
    $deleted_count   = 0;

    error_log( 'Found ' . count( $posts_to_delete ) . ' posts to delete.' );

    if ( $posts_to_delete ) {
        foreach ( $posts_to_delete as $post_id ) {
            // Delete post permanently (true) or move to trash (false)
            $result = wp_delete_post( $post_id, true );
            if ( $result && ! is_wp_error( $result ) ) {
                $deleted_count++;
                error_log( 'Successfully deleted post ID: ' . $post_id );
            } else {
                // Log error or handle it as needed
                $error_message = is_wp_error( $result ) ? $result->get_error_message() : 'Unknown error';
                error_log( 'Error deleting post ID ' . $post_id . ': ' . $error_message );
            }
        }
    } else {
        error_log( 'No posts found matching the criteria for deletion.' );
    }

    return $deleted_count;
}

/**
 * Exports users of a selected role to a CSV file.
 *
 * @param string $user_role The user role to export.
 * @param string $date_option 'all', 'single', or 'range'.
 * @param string $date_single Date for single date filter.
 * @param string $date_condition 'before' or 'after' for single date filter.
 * @param string $date_start Start date for range filter.
 * @param string $date_end End date for range filter.
 */
function wpmd_export_users_to_csv( $user_role, $date_option, $date_single, $date_condition, $date_start, $date_end ) {
    $args = array(
        'role'    => $user_role,
        'fields'  => 'ID', // Still using IDs for initial query
        'orderby' => 'ID',
        'order'   => 'ASC',
    );

    // Add date query based on selected option
    if ( 'single' === $date_option && ! empty( $date_single ) ) {
        if ( 'before' === $date_condition ) {
            $args['date_query'] = array(
                array(
                    'before'    => $date_single . ' 23:59:59',
                    'inclusive' => true,
                    'column'    => 'user_registered',
                ),
            );
        } elseif ( 'after' === $date_condition ) {
            $args['date_query'] = array(
                array(
                    'after'     => $date_single . ' 00:00:00',
                    'inclusive' => true,
                    'column'    => 'user_registered',
                ),
            );
        }
    } elseif ( 'range' === $date_option && ! empty( $date_start ) && ! empty( $date_end ) ) {
        $args['date_query'] = array(
            array(
                'after'     => $date_start . ' 00:00:00',
                'before'    => $date_end . ' 23:59:59',
                'inclusive' => true,
                'column'    => 'user_registered',
            ),
        );
    }

    $user_ids = get_users( $args );

    if ( empty( $user_ids ) ) {
        // Redirect back with a message if no users found
        wp_safe_redirect( add_query_arg( 'message', urlencode( __( 'No users found for the selected role and date filters to export. Try adjusting your filters.', 'wpmd' ) ), admin_url( 'admin.php?page=wpmd-manage-users' ) ) );
        exit;
    }

    // Set headers for CSV download
    header( 'Content-Type: text/csv; charset=' . get_option( 'blog_charset' ) );
    header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $user_role ) . '_users_export_' . date( 'Y-m-d' ) . '.csv"' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    $output = fopen( 'php://output', 'w' );

    // Define CSV headers
    $headers = array(
        __( 'ID', 'wpmd' ),
        __( 'Username', 'wpmd' ),
        __( 'Email', 'wpmd' ),
        __( 'First Name', 'wpmd' ),
        __( 'Last Name', 'wpmd' ),
        __( 'Display Name', 'wpmd' ),
        __( 'Roles', 'wpmd' ),
        __( 'Registered Date', 'wpmd' ),
        __( 'User URL', 'wpmd' ),
        __( 'Description', 'wpmd' ),
        // Potentially add more user meta fields here if needed in the future
    );
    fputcsv( $output, $headers );

    foreach ( $user_ids as $user_id ) {
        $user_data = get_userdata( $user_id );
        if ( ! $user_data ) {
            continue;
        }

        $user_roles_list = ! empty( $user_data->roles ) ? implode( ', ', $user_data->roles ) : '';

        $row = array(
            $user_data->ID,
            $user_data->user_login,
            $user_data->user_email,
            $user_data->first_name,
            $user_data->last_name,
            $user_data->display_name,
            $user_roles_list,
            $user_data->user_registered,
            $user_data->user_url,
            $user_data->description,
        );
        fputcsv( $output, $row );
    }

    fclose( $output );
    exit; // Important to exit after file download
}

/**
 * Deletes users of a selected role based on date or all.
 *
 * @param string $user_role The user role to delete.
 * @param bool   $delete_all If true, delete all users of the role.
 * @param string $date_option 'all', 'single', or 'range'.
 * @param string $date_single Date for single date filter.
 * @param string $date_condition 'before' or 'after' for single date filter.
 * @param string $date_start Start date for range filter.
 * @param string $date_end End date for range filter.
 * @return int|false Number of deleted users on success, false on error.
 */
function wpmd_delete_users( $user_role, $delete_all, $date_option, $date_single, $date_condition, $date_start, $date_end ) {
    error_log( 'wpmd_delete_users called for user role: ' . $user_role );
    error_log( 'Delete all: ' . ( $delete_all ? 'true' : 'false' ) );
    error_log( 'Date option: ' . $date_option );
    error_log( 'Date single: ' . $date_single );
    error_log( 'Date condition: ' . $date_condition );
    error_log( 'Date start: ' . $date_start );
    error_log( 'Date end: ' . $date_end );

    $args = array(
        'role'    => $user_role,
        'fields'  => 'ID',
    );

    if ( $delete_all ) {
        // No date filtering needed if deleting all
        error_log( 'Deleting all users of role: ' . $user_role );
    } elseif ( 'single' === $date_option && ! empty( $date_single ) ) {
        if ( 'before' === $date_condition ) {
            $args['date_query'] = array(
                array(
                    'before'    => $date_single . ' 23:59:59',
                    'inclusive' => true,
                    'column'    => 'user_registered',
                ),
            );
            error_log( 'Deleting users registered before: ' . $date_single );
        } elseif ( 'after' === $date_condition ) {
            $args['date_query'] = array(
                array(
                    'after'     => $date_single . ' 00:00:00',
                    'inclusive' => true,
                    'column'    => 'user_registered',
                ),
            );
            error_log( 'Deleting users registered after: ' . $date_single );
        }
    } elseif ( 'range' === $date_option && ! empty( $date_start ) && ! empty( $date_end ) ) {
        $args['date_query'] = array(
            array(
                'after'     => $date_start . ' 00:00:00',
                'before'    => $date_end . ' 23:59:59',
                'inclusive' => true,
                'column'    => 'user_registered',
            ),
        );
        error_log( 'Deleting users registered between: ' . $date_start . ' and ' . $date_end );
    } else {
        error_log( 'No valid deletion criteria for users. Returning 0.' );
        return 0;
    }

    $users_to_delete = get_users( $args );
    $deleted_count   = 0;

    error_log( 'Found ' . count( $users_to_delete ) . ' users to delete.' );

    if ( $users_to_delete ) {
        // Get the current user's ID to prevent self-deletion
        $current_user_id = get_current_user_id();
        error_log( 'Current user ID: ' . $current_user_id );

        foreach ( $users_to_delete as $user_id ) {
            // Prevent deleting the currently logged-in admin user
            if ( $user_id == $current_user_id ) {
                error_log( 'Skipping deletion of current user ID: ' . $user_id );
                continue;
            }

            // wp_delete_user takes two arguments: user ID and reassign_to (optional).
            // Reassign to 1 (admin user) or null to delete content.
            // For now, setting to null to delete all content from deleted user.
            $result = wp_delete_user( $user_id, null ); // null means posts/links are deleted
            if ( $result && ! is_wp_error( $result ) ) {
                $deleted_count++;
                error_log( 'Successfully deleted user ID: ' . $user_id );
            } else {
                // Log error or handle it as needed
                $error_message = is_wp_error( $result ) ? $result->get_error_message() : 'Unknown error';
                error_log( 'Error deleting user ID ' . $user_id . ': ' . $error_message );
            }
        }
    } else {
        error_log( 'No users found matching the criteria for deletion.' );
    }

    return $deleted_count;
}

/**
 * AJAX handler to get post count based on filters.
 */
function wpmd_ajax_get_post_count() {
    check_ajax_referer( 'wpmd_ajax_nonce', 'nonce' );

    $post_type      = sanitize_text_field( $_POST['post_type'] );
    $date_option    = sanitize_text_field( $_POST['date_option'] );
    $date_single    = sanitize_text_field( $_POST['date_single'] );
    $date_condition = sanitize_text_field( $_POST['date_condition'] );
    $date_start     = sanitize_text_field( $_POST['date_start'] );
    $date_end       = sanitize_text_field( $_POST['date_end'] );

    $args = array(
        'post_type'      => $post_type,
        'posts_per_page' => -1,
        'post_status'    => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash' ),
        'fields'         => 'ids', // Only fetch IDs for counting
        'no_found_rows'  => true, // Optimize for counting
        'update_post_term_cache' => false, // Don't fetch terms
        'update_post_meta_cache' => false, // Don't fetch meta
    );

    // Apply date filters
    if ( 'single' === $date_option && ! empty( $date_single ) ) {
        if ( 'before' === $date_condition ) {
            $args['date_query'] = array(
                array(
                    'before'    => $date_single . ' 23:59:59',
                    'inclusive' => true,
                ),
            );
        } elseif ( 'after' === $date_condition ) {
            $args['date_query'] = array(
                array(
                    'after'     => $date_single . ' 00:00:00',
                    'inclusive' => true,
                ),
            );
        }
    } elseif ( 'range' === $date_option && ! empty( $date_start ) && ! empty( $date_end ) ) {
        $args['date_query'] = array(
            array(
                'after'     => $date_start . ' 00:00:00',
                'before'    => $date_end . ' 23:59:59',
                'inclusive' => true,
            ),
        );
    }

    $query = new WP_Query( $args );
    wp_send_json_success( array( 'count' => $query->post_count ) );
}
add_action( 'wp_ajax_wpmd_get_post_count', 'wpmd_ajax_get_post_count' );

/**
 * AJAX handler to get user count based on filters.
 */
function wpmd_ajax_get_user_count() {
    check_ajax_referer( 'wpmd_ajax_nonce', 'nonce' );

    $user_role      = sanitize_text_field( $_POST['user_role'] );
    $date_option    = sanitize_text_field( $_POST['date_option'] );
    $date_single    = sanitize_text_field( $_POST['date_single'] );
    $date_condition = sanitize_text_field( $_POST['date_condition'] );
    $date_start     = sanitize_text_field( $_POST['date_start'] );
    $date_end       = sanitize_text_field( $_POST['date_end'] );

    $args = array(
        'role'    => $user_role,
        'fields'  => 'ID', // Only fetch IDs for counting
        'number'  => -1,   // Get all users
    );

    // Apply date filters
    if ( 'single' === $date_option && ! empty( $date_single ) ) {
        if ( 'before' === $date_condition ) {
            $args['date_query'] = array(
                array(
                    'before'    => $date_single . ' 23:59:59',
                    'inclusive' => true,
                    'column'    => 'user_registered',
                ),
            );
        } elseif ( 'after' === $date_condition ) {
            $args['date_query'] = array(
                array(
                    'after'     => $date_single . ' 00:00:00',
                    'inclusive' => true,
                    'column'    => 'user_registered',
                ),
            );
        }
    } elseif ( 'range' === $date_option && ! empty( $date_start ) && ! empty( $date_end ) ) {
        $args['date_query'] = array(
            array(
                'after'     => $date_start . ' 00:00:00',
                'before'    => $date_end . ' 23:59:59',
                'inclusive' => true,
                'column'    => 'user_registered',
            ),
        );
    }

    $user_query = new WP_User_Query( $args );
    wp_send_json_success( array( 'count' => $user_query->total_users ) );
}
add_action( 'wp_ajax_wpmd_get_user_count', 'wpmd_ajax_get_user_count' );

// Start of the JavaScript for dynamic counts and form toggling
?>
<script>
    // This script will be output directly into the admin page.
    // It uses jQuery, which is typically available in WordPress admin.

    document.addEventListener('DOMContentLoaded', function() {
        // --- Post Management Form Elements ---
        const postExportTypeSelect = document.getElementById('wpmd_post_type_export');
        const postExportDateOptionAll = document.getElementById('wpmd_export_date_option_all');
        const postExportDateOptionSingle = document.getElementById('wpmd_export_date_option_single');
        const postExportDateOptionRange = document.getElementById('wpmd_export_date_option_range');
        const postExportDateSingle = document.getElementById('wpmd_export_date_single');
        const postExportDateCondition = document.getElementById('wpmd_export_date_condition');
        const postExportDateStart = document.getElementById('wpmd_export_date_start');
        const postExportDateEnd = document.getElementById('wpmd_export_date_end');
        const postExportCountInfo = document.getElementById('wpmd-export-post-count-info');

        const postDeleteTypeSelect = document.getElementById('wpmd_post_type_delete');
        const postDeleteDateOptionAll = document.getElementById('wpmd_delete_date_option_all');
        const postDeleteDateOptionSingle = document.getElementById('wpmd_delete_date_option_single');
        const postDeleteDateOptionRange = document.getElementById('wpmd_delete_date_option_range');
        const postDeleteDateSingle = document.getElementById('wpmd_delete_date_single');
        const postDeleteDateCondition = document.getElementById('wpmd_delete_date_condition');
        const postDeleteDateStart = document.getElementById('wpmd_delete_date_start');
        const postDeleteDateEnd = document.getElementById('wpmd_delete_date_end');
        const postDeleteAllCheckbox = document.getElementById('wpmd_delete_all_posts');
        const postDeleteCountInfo = document.getElementById('wpmd-delete-post-count-info');

        // --- User Management Form Elements ---
        const userExportRoleSelect = document.getElementById('wpmd_user_role_export');
        const userExportDateOptionAll = document.getElementById('wpmd_user_export_date_option_all');
        const userExportDateOptionSingle = document.getElementById('wpmd_user_export_date_option_single');
        const userExportDateOptionRange = document.getElementById('wpmd_user_export_date_option_range');
        const userExportDateSingle = document.getElementById('wpmd_user_export_date_single');
        const userExportDateCondition = document.getElementById('wpmd_user_export_date_condition');
        const userExportDateStart = document.getElementById('wpmd_user_export_date_start');
        const userExportDateEnd = document.getElementById('wpmd_user_export_date_end');
        const userExportCountInfo = document.getElementById('wpmd-export-user-count-info');

        const userDeleteRoleSelect = document.getElementById('wpmd_user_role_delete');
        const userDeleteDateOptionAll = document.getElementById('wpmd_user_delete_date_option_all');
        const userDeleteDateOptionSingle = document.getElementById('wpmd_user_delete_date_option_single');
        const userDeleteDateOptionRange = document.getElementById('wpmd_user_delete_date_option_range');
        const userDeleteDateSingle = document.getElementById('wpmd_user_delete_date_single');
        const userDeleteDateCondition = document.getElementById('wpmd_user_delete_date_condition');
        const userDeleteDateStart = document.getElementById('wpmd_user_delete_date_start');
        const userDeleteDateEnd = document.getElementById('wpmd_user_delete_date_end');
        const userDeleteAllCheckbox = document.getElementById('wpmd_delete_all_users');
        const userDeleteCountInfo = document.getElementById('wpmd-delete-user-count-info');

        // --- Helper Function to Toggle Date Fields ---
        function toggleDateFields(optionAll, optionSingle, optionRange, dateSingle, dateCondition, dateStart, dateEnd, allCheckbox = null) {
            if (optionSingle.checked) {
                dateSingle.disabled = false;
                dateCondition.disabled = false;
                dateStart.disabled = true;
                dateEnd.disabled = true;
                if (allCheckbox) allCheckbox.disabled = true;
            } else if (optionRange.checked) {
                dateSingle.disabled = true;
                dateCondition.disabled = true;
                dateStart.disabled = false;
                dateEnd.disabled = false;
                if (allCheckbox) allCheckbox.disabled = true;
            } else { // 'all' is checked
                dateSingle.disabled = true;
                dateCondition.disabled = true;
                dateStart.disabled = true;
                dateEnd.disabled = true;
                if (allCheckbox) allCheckbox.disabled = false;
            }
        }

        // --- Functions to Fetch and Display Counts via AJAX ---
        function updatePostCount(formType) { // 'export' or 'delete'
            let postType, dateOption, dateSingle, dateCondition, dateStart, dateEnd, countInfoElement;

            if (formType === 'export') {
                postType = postExportTypeSelect.value;
                dateOption = document.querySelector('input[name="wpmd_export_date_option"]:checked').value;
                dateSingle = postExportDateSingle.value;
                dateCondition = postExportDateCondition.value;
                dateStart = postExportDateStart.value;
                dateEnd = postExportDateEnd.value;
                countInfoElement = postExportCountInfo;
            } else { // formType === 'delete'
                postType = postDeleteTypeSelect.value;
                // If "delete all" is checked, override date options
                if (postDeleteAllCheckbox && postDeleteAllCheckbox.checked) {
                    dateOption = 'all'; // Treat as all for deletion count logic
                } else {
                    dateOption = document.querySelector('input[name="wpmd_delete_date_option"]:checked').value;
                }
                dateSingle = postDeleteDateSingle.value;
                dateCondition = postDeleteDateCondition.value;
                dateStart = postDeleteDateStart.value;
                dateEnd = postDeleteDateEnd.value;
                countInfoElement = postDeleteCountInfo;
            }

            countInfoElement.innerHTML = '<?php esc_html_e( 'Calculating...', 'wpmd' ); ?>';

            // Ensure not to fetch count if 'delete all' is checked for delete form and no date filter applies
            if (formType === 'delete' && postDeleteAllCheckbox.checked) {
                countInfoElement.innerHTML = '<?php esc_html_e( 'All posts of the selected type will be deleted.', 'wpmd' ); ?>';
                return;
            }


            jQuery.post(wpmd_ajax_object.ajax_url, {
                action: 'wpmd_get_post_count',
                nonce: wpmd_ajax_object.nonce,
                post_type: postType,
                date_option: dateOption,
                date_single: dateSingle,
                date_condition: dateCondition,
                date_start: dateStart,
                date_end: dateEnd
            }, function(response) {
                if (response.success) {
                    let message = '';
                    if (formType === 'export') {
                        message = `<?php esc_html_e( 'Found %s posts to export.', 'wpmd' ); ?>`;
                    } else {
                        message = `<?php esc_html_e( 'Found %s posts matching criteria to delete.', 'wpmd' ); ?>`;
                    }
                    countInfoElement.innerHTML = message.replace('%s', `<strong>${response.data.count}</strong>`);
                    if (response.data.count === 0) {
                        countInfoElement.innerHTML += ' <?php esc_html_e( 'Consider adjusting your filters.', 'wpmd' ); ?>';
                    }
                } else {
                    countInfoElement.innerHTML = '<?php esc_html_e( 'Could not retrieve post count.', 'wpmd' ); ?>';
                    console.error('AJAX Error:', response);
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                countInfoElement.innerHTML = '<?php esc_html_e( 'Error connecting to server for post count.', 'wpmd' ); ?>';
                console.error('AJAX Fail:', textStatus, errorThrown, jqXHR);
            });
        }

        function updateUserCount(formType) { // 'export' or 'delete'
            let userRole, dateOption, dateSingle, dateCondition, dateStart, dateEnd, countInfoElement;

            if (formType === 'export') {
                userRole = userExportRoleSelect.value;
                dateOption = document.querySelector('input[name="wpmd_user_export_date_option"]:checked').value;
                dateSingle = userExportDateSingle.value;
                dateCondition = userExportDateCondition.value;
                dateStart = userExportDateStart.value;
                dateEnd = userExportDateEnd.value;
                countInfoElement = userExportCountInfo;
            } else { // formType === 'delete'
                userRole = userDeleteRoleSelect.value;
                 // If "delete all" is checked, override date options
                if (userDeleteAllCheckbox && userDeleteAllCheckbox.checked) {
                    dateOption = 'all'; // Treat as all for deletion count logic
                } else {
                    dateOption = document.querySelector('input[name="wpmd_user_delete_date_option"]:checked').value;
                }
                dateSingle = userDeleteDateSingle.value;
                dateCondition = userDeleteDateCondition.value;
                dateStart = userDeleteDateStart.value;
                dateEnd = userDeleteDateEnd.value;
                countInfoElement = userDeleteCountInfo;
            }

            countInfoElement.innerHTML = '<?php esc_html_e( 'Calculating...', 'wpmd' ); ?>';

            // Ensure not to fetch count if 'delete all' is checked for delete form and no date filter applies
            if (formType === 'delete' && userDeleteAllCheckbox.checked) {
                countInfoElement.innerHTML = '<?php esc_html_e( 'All users of the selected role will be deleted (excluding current admin).', 'wpmd' ); ?>';
                return;
            }

            jQuery.post(wpmd_ajax_object.ajax_url, {
                action: 'wpmd_get_user_count',
                nonce: wpmd_ajax_object.nonce,
                user_role: userRole,
                date_option: dateOption,
                date_single: dateSingle,
                date_condition: dateCondition,
                date_start: dateStart,
                date_end: dateEnd
            }, function(response) {
                if (response.success) {
                    let message = '';
                    if (formType === 'export') {
                        message = `<?php esc_html_e( 'Found %s users to export.', 'wpmd' ); ?>`;
                    } else {
                        message = `<?php esc_html_e( 'Found %s users matching criteria to delete (excluding current admin).', 'wpmd' ); ?>`;
                    }
                    countInfoElement.innerHTML = message.replace('%s', `<strong>${response.data.count}</strong>`);
                    if (response.data.count === 0) {
                        countInfoElement.innerHTML += ' <?php esc_html_e( 'Consider adjusting your filters.', 'wpmd' ); ?>';
                    }
                } else {
                    countInfoElement.innerHTML = '<?php esc_html_e( 'Could not retrieve user count.', 'wpmd' ); ?>';
                    console.error('AJAX Error:', response);
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                countInfoElement.innerHTML = '<?php esc_html_e( 'Error connecting to server for user count.', 'wpmd' ); ?>';
                console.error('AJAX Fail:', textStatus, errorThrown, jqXHR);
            });
        }

        // --- Event Listeners for Post Management ---
        if (postExportTypeSelect) {
            postExportTypeSelect.addEventListener('change', () => updatePostCount('export'));
            postExportDateOptionAll.addEventListener('change', () => { toggleDateFields(postExportDateOptionAll, postExportDateOptionSingle, postExportDateOptionRange, postExportDateSingle, postExportDateCondition, postExportDateStart, postExportDateEnd); updatePostCount('export'); });
            postExportDateOptionSingle.addEventListener('change', () => { toggleDateFields(postExportDateOptionAll, postExportDateOptionSingle, postExportDateOptionRange, postExportDateSingle, postExportDateCondition, postExportDateStart, postExportDateEnd); updatePostCount('export'); });
            postExportDateOptionRange.addEventListener('change', () => { toggleDateFields(postExportDateOptionAll, postExportDateOptionSingle, postExportDateOptionRange, postExportDateSingle, postExportDateCondition, postExportDateStart, postExportDateEnd); updatePostCount('export'); });
            postExportDateSingle.addEventListener('change', () => updatePostCount('export'));
            postExportDateCondition.addEventListener('change', () => updatePostCount('export'));
            postExportDateStart.addEventListener('change', () => updatePostCount('export'));
            postExportDateEnd.addEventListener('change', () => updatePostCount('export'));

            // Initial call for export count
            updatePostCount('export');
        }

        if (postDeleteTypeSelect) {
            postDeleteTypeSelect.addEventListener('change', () => updatePostCount('delete'));
            postDeleteDateOptionAll.addEventListener('change', () => { toggleDateFields(postDeleteDateOptionAll, postDeleteDateOptionSingle, postDeleteDateOptionRange, postDeleteDateSingle, postDeleteDateCondition, postDeleteDateStart, postDeleteDateEnd, postDeleteAllCheckbox); updatePostCount('delete'); });
            postDeleteDateOptionSingle.addEventListener('change', () => { toggleDateFields(postDeleteDateOptionAll, postDeleteDateOptionSingle, postDeleteDateOptionRange, postDeleteDateSingle, postDeleteDateCondition, postDeleteDateStart, postDeleteDateEnd, postDeleteAllCheckbox); updatePostCount('delete'); });
            postDeleteDateOptionRange.addEventListener('change', () => { toggleDateFields(postDeleteDateOptionAll, postDeleteDateOptionSingle, postDeleteDateOptionRange, postDeleteDateSingle, postDeleteDateCondition, postDeleteDateStart, postDeleteDateEnd, postDeleteAllCheckbox); updatePostCount('delete'); });
            postDeleteDateSingle.addEventListener('change', () => updatePostCount('delete'));
            postDeleteDateCondition.addEventListener('change', () => updatePostCount('delete'));
            postDeleteDateStart.addEventListener('change', () => updatePostCount('delete'));
            postDeleteDateEnd.addEventListener('change', () => updatePostCount('delete'));
            if (postDeleteAllCheckbox) {
                postDeleteAllCheckbox.addEventListener('change', () => { toggleDateFields(postDeleteDateOptionAll, postDeleteDateOptionSingle, postDeleteDateOptionRange, postDeleteDateSingle, postDeleteDateCondition, postDeleteDateStart, postDeleteDateEnd, postDeleteAllCheckbox); updatePostCount('delete'); });
            }

            // Initial state for delete all checkbox and date fields
            if (postDeleteAllCheckbox && postDeleteAllCheckbox.checked) {
                postDeleteDateOptionAll.disabled = true;
                postDeleteDateOptionSingle.disabled = true;
                postDeleteDateOptionRange.disabled = true;
            }

            // Initial call for delete count
            updatePostCount('delete');
        }

        // --- Event Listeners for User Management ---
        if (userExportRoleSelect) {
            userExportRoleSelect.addEventListener('change', () => updateUserCount('export'));
            userExportDateOptionAll.addEventListener('change', () => { toggleDateFields(userExportDateOptionAll, userExportDateOptionSingle, userExportDateOptionRange, userExportDateSingle, userExportDateCondition, userExportDateStart, userExportDateEnd); updateUserCount('export'); });
            userExportDateOptionSingle.addEventListener('change', () => { toggleDateFields(userExportDateOptionAll, userExportDateOptionSingle, userExportDateOptionRange, userExportDateSingle, userExportDateCondition, userExportDateStart, userExportDateEnd); updateUserCount('export'); });
            userExportDateOptionRange.addEventListener('change', () => { toggleDateFields(userExportDateOptionAll, userExportDateOptionSingle, userExportDateOptionRange, userExportDateSingle, userExportDateCondition, userExportDateStart, userExportDateEnd); updateUserCount('export'); });
            userExportDateSingle.addEventListener('change', () => updateUserCount('export'));
            userExportDateCondition.addEventListener('change', () => updateUserCount('export'));
            userExportDateStart.addEventListener('change', () => updateUserCount('export'));
            userExportDateEnd.addEventListener('change', () => updateUserCount('export'));

            // Initial call for export count
            updateUserCount('export');
        }

        if (userDeleteRoleSelect) {
            userDeleteRoleSelect.addEventListener('change', () => updateUserCount('delete'));
            userDeleteDateOptionAll.addEventListener('change', () => { toggleDateFields(userDeleteDateOptionAll, userDeleteDateOptionSingle, userDeleteDateOptionRange, userDeleteDateSingle, userDeleteDateCondition, userDeleteDateStart, userDeleteDateEnd, userDeleteAllCheckbox); updateUserCount('delete'); });
            userDeleteDateOptionSingle.addEventListener('change', () => { toggleDateFields(userDeleteDateOptionAll, userDeleteDateOptionSingle, userDeleteDateOptionRange, userDeleteDateSingle, userDeleteDateCondition, userDeleteDateStart, userDeleteDateEnd, userDeleteAllCheckbox); updateUserCount('delete'); });
            userDeleteDateOptionRange.addEventListener('change', () => { toggleDateFields(userDeleteDateOptionAll, userDeleteDateOptionSingle, userDeleteDateOptionRange, userDeleteDateSingle, userDeleteDateCondition, userDeleteDateStart, userDeleteDateEnd, userDeleteAllCheckbox); updateUserCount('delete'); });
            userDeleteDateSingle.addEventListener('change', () => updateUserCount('delete'));
            userDeleteDateCondition.addEventListener('change', () => updateUserCount('delete'));
            userDeleteDateStart.addEventListener('change', () => updateUserCount('delete'));
            userDeleteDateEnd.addEventListener('change', () => updateUserCount('delete'));
            if (userDeleteAllCheckbox) {
                userDeleteAllCheckbox.addEventListener('change', () => { toggleDateFields(userDeleteDateOptionAll, userDeleteDateOptionSingle, userDeleteDateOptionRange, userDeleteDateSingle, userDeleteDateCondition, userDeleteDateStart, userDeleteDateEnd, userDeleteAllCheckbox); updateUserCount('delete'); });
            }

            // Initial state for delete all checkbox and date fields
            if (userDeleteAllCheckbox && userDeleteAllCheckbox.checked) {
                userDeleteDateOptionAll.disabled = true;
                userDeleteDateOptionSingle.disabled = true;
                userDeleteDateOptionRange.disabled = true;
            }

            // Initial call for delete count
            updateUserCount('delete');
        }
    });
</script>
