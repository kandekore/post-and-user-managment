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

// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

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

    // Get all public post types
    $post_types = get_post_types( array( 'public' => true ), 'objects' );
    unset( $post_types['attachment'] ); // Exclude attachments

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Manage Posts', 'wpmd' ); ?></h1>

        <?php if ( isset( $_GET['message'] ) ) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html( sanitize_text_field( $_GET['message'] ) ); ?></p>
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
            <p class="submit">
                <input type="submit" name="wpmd_export_posts_submit" id="wpmd_export_posts_submit" class="button button-primary" value="<?php esc_attr_e( 'Export Posts to CSV', 'wpmd' ); ?>">
            </p>
        </form>
        <script>
            // JavaScript to toggle date input fields based on radio button selection
            document.addEventListener('DOMContentLoaded', function() {
                const exportDateOptionAll = document.getElementById('wpmd_export_date_option_all');
                const exportDateOptionSingle = document.getElementById('wpmd_export_date_option_single');
                const exportDateOptionRange = document.getElementById('wpmd_export_date_option_range');
                const exportDateSingle = document.getElementById('wpmd_export_date_single');
                const exportDateCondition = document.getElementById('wpmd_export_date_condition');
                const exportDateStart = document.getElementById('wpmd_export_date_start');
                const exportDateEnd = document.getElementById('wpmd_export_date_end');

                const deleteDateOptionAll = document.getElementById('wpmd_delete_date_option_all');
                const deleteDateOptionSingle = document.getElementById('wpmd_delete_date_option_single');
                const deleteDateOptionRange = document.getElementById('wpmd_delete_date_option_range');
                const deleteDateSingle = document.getElementById('wpmd_delete_date_single');
                const deleteDateCondition = document.getElementById('wpmd_delete_date_condition');
                const deleteDateStart = document.getElementById('wpmd_delete_date_start');
                const deleteDateEnd = document.getElementById('wpmd_delete_date_end');

                function toggleExportDateFields() {
                    if (exportDateOptionSingle.checked) {
                        exportDateSingle.disabled = false;
                        exportDateCondition.disabled = false;
                        exportDateStart.disabled = true;
                        exportDateEnd.disabled = true;
                    } else if (exportDateOptionRange.checked) {
                        exportDateSingle.disabled = true;
                        exportDateCondition.disabled = true;
                        exportDateStart.disabled = false;
                        exportDateEnd.disabled = false;
                    } else { // 'all' is checked
                        exportDateSingle.disabled = true;
                        exportDateCondition.disabled = true;
                        exportDateStart.disabled = true;
                        exportDateEnd.disabled = true;
                    }
                }

                function toggleDeleteDateFields() {
                    if (deleteDateOptionAll && deleteDateOptionSingle && deleteDateOptionRange) { // Check if elements exist
                        if (deleteDateOptionSingle.checked) {
                            deleteDateSingle.disabled = false;
                            deleteDateCondition.disabled = false;
                            deleteDateStart.disabled = true;
                            deleteDateEnd.disabled = true;
                            document.getElementById('wpmd_delete_all_posts').disabled = true; // Disable "Delete All" checkbox
                            document.getElementById('wpmd_delete_all_posts').checked = false;
                        } else if (deleteDateOptionRange.checked) {
                            deleteDateSingle.disabled = true;
                            deleteDateCondition.disabled = true;
                            deleteDateStart.disabled = false;
                            deleteDateEnd.disabled = false;
                            document.getElementById('wpmd_delete_all_posts').disabled = true; // Disable "Delete All" checkbox
                            document.getElementById('wpmd_delete_all_posts').checked = false;
                        } else { // 'all' is checked or no date option is selected (delete all is active)
                            deleteDateSingle.disabled = true;
                            deleteDateCondition.disabled = true;
                            deleteDateStart.disabled = true;
                            deleteDateEnd.disabled = true;
                            document.getElementById('wpmd_delete_all_posts').disabled = false; // Enable "Delete All" checkbox
                        }
                    }
                }

                // Initial state
                toggleExportDateFields();
                toggleDeleteDateFields();

                // Add event listeners
                exportDateOptionAll.addEventListener('change', toggleExportDateFields);
                exportDateOptionSingle.addEventListener('change', toggleExportDateFields);
                exportDateOptionRange.addEventListener('change', toggleExportDateFields);

                // Check if delete date options exist before adding listeners
                if (deleteDateOptionAll) {
                    deleteDateOptionAll.addEventListener('change', toggleDeleteDateFields);
                }
                if (deleteDateOptionSingle) {
                    deleteDateOptionSingle.addEventListener('change', toggleDeleteDateFields);
                }
                if (deleteDateOptionRange) {
                    deleteDateOptionRange.addEventListener('change', toggleDeleteDateFields);
                }

                // If "Delete All" checkbox is checked, disable date options
                const deleteAllPostsCheckbox = document.getElementById('wpmd_delete_all_posts');
                if (deleteAllPostsCheckbox) {
                    deleteAllPostsCheckbox.addEventListener('change', function() {
                        if (this.checked) {
                            if (deleteDateOptionAll) deleteDateOptionAll.disabled = true;
                            if (deleteDateOptionSingle) deleteDateOptionSingle.disabled = true;
                            if (deleteDateOptionRange) deleteDateOptionRange.disabled = true;
                            deleteDateSingle.disabled = true;
                            deleteDateCondition.disabled = true;
                            deleteDateStart.disabled = true;
                            deleteDateEnd.disabled = true;
                        } else {
                            if (deleteDateOptionAll) deleteDateOptionAll.disabled = false;
                            if (deleteDateOptionSingle) deleteDateOptionSingle.disabled = false;
                            if (deleteDateOptionRange) deleteDateOptionRange.disabled = false;
                            toggleDeleteDateFields(); // Re-evaluate based on date options
                        }
                    });
                     // Initial state for delete all checkbox
                    if (deleteAllPostsCheckbox.checked) {
                        if (deleteDateOptionAll) deleteDateOptionAll.disabled = true;
                        if (deleteDateOptionSingle) deleteDateOptionSingle.disabled = true;
                        if (deleteDateOptionRange) deleteDateOptionRange.disabled = true;
                    }
                }
            });
        </script>

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
            <p class="submit">
                <input type="submit" name="wpmd_export_users_submit" id="wpmd_export_users_submit" class="button button-primary" value="<?php esc_attr_e( 'Export Users to CSV', 'wpmd' ); ?>">
            </p>
        </form>
        <script>
            // JavaScript to toggle user date input fields based on radio button selection
            document.addEventListener('DOMContentLoaded', function() {
                const userExportDateOptionAll = document.getElementById('wpmd_user_export_date_option_all');
                const userExportDateOptionSingle = document.getElementById('wpmd_user_export_date_option_single');
                const userExportDateOptionRange = document.getElementById('wpmd_user_export_date_option_range');
                const userExportDateSingle = document.getElementById('wpmd_user_export_date_single');
                const userExportDateCondition = document.getElementById('wpmd_user_export_date_condition');
                const userExportDateStart = document.getElementById('wpmd_user_export_date_start');
                const userExportDateEnd = document.getElementById('wpmd_user_export_date_end');

                const userDeleteDateOptionAll = document.getElementById('wpmd_user_delete_date_option_all');
                const userDeleteDateOptionSingle = document.getElementById('wpmd_user_delete_date_option_single');
                const userDeleteDateOptionRange = document.getElementById('wpmd_user_delete_date_option_range');
                const userDeleteDateSingle = document.getElementById('wpmd_user_delete_date_single');
                const userDeleteDateCondition = document.getElementById('wpmd_user_delete_date_condition');
                const userDeleteDateStart = document.getElementById('wpmd_user_delete_date_start');
                const userDeleteDateEnd = document.getElementById('wpmd_user_delete_date_end');


                function toggleUserExportDateFields() {
                    if (userExportDateOptionSingle.checked) {
                        userExportDateSingle.disabled = false;
                        userExportDateCondition.disabled = false;
                        userExportDateStart.disabled = true;
                        userExportDateEnd.disabled = true;
                    } else if (userExportDateOptionRange.checked) {
                        userExportDateSingle.disabled = true;
                        userExportDateCondition.disabled = true;
                        userExportDateStart.disabled = false;
                        userExportDateEnd.disabled = false;
                    } else { // 'all' is checked
                        userExportDateSingle.disabled = true;
                        userExportDateCondition.disabled = true;
                        userExportDateStart.disabled = true;
                        userExportDateEnd.disabled = true;
                    }
                }

                function toggleUserDeleteDateFields() {
                     if (userDeleteDateOptionAll && userDeleteDateOptionSingle && userDeleteDateOptionRange) { // Check if elements exist
                        if (userDeleteDateOptionSingle.checked) {
                            userDeleteDateSingle.disabled = false;
                            userDeleteDateCondition.disabled = false;
                            userDeleteDateStart.disabled = true;
                            userDeleteDateEnd.disabled = true;
                            document.getElementById('wpmd_delete_all_users').disabled = true; // Disable "Delete All" checkbox
                            document.getElementById('wpmd_delete_all_users').checked = false;
                        } else if (userDeleteDateOptionRange.checked) {
                            userDeleteDateSingle.disabled = true;
                            userDeleteDateCondition.disabled = true;
                            userDeleteDateStart.disabled = false;
                            userDeleteDateEnd.disabled = false;
                            document.getElementById('wpmd_delete_all_users').disabled = true; // Disable "Delete All" checkbox
                            document.getElementById('wpmd_delete_all_users').checked = false;
                        } else { // 'all' is checked or no date option is selected (delete all is active)
                            userDeleteDateSingle.disabled = true;
                            userDeleteDateCondition.disabled = true;
                            userDeleteDateStart.disabled = true;
                            userDeleteDateEnd.disabled = true;
                            document.getElementById('wpmd_delete_all_users').disabled = false; // Enable "Delete All" checkbox
                        }
                    }
                }

                // Initial state
                toggleUserExportDateFields();
                toggleUserDeleteDateFields();

                // Add event listeners
                userExportDateOptionAll.addEventListener('change', toggleUserExportDateFields);
                userExportDateOptionSingle.addEventListener('change', toggleUserExportDateFields);
                userExportDateOptionRange.addEventListener('change', toggleUserExportDateFields);

                // Check if delete date options exist before adding listeners
                if (userDeleteDateOptionAll) {
                    userDeleteDateOptionAll.addEventListener('change', toggleUserDeleteDateFields);
                }
                if (userDeleteDateOptionSingle) {
                    userDeleteDateOptionSingle.addEventListener('change', toggleUserDeleteDateFields);
                }
                if (userDeleteDateOptionRange) {
                    userDeleteDateOptionRange.addEventListener('change', toggleUserDeleteDateFields);
                }

                // If "Delete All" checkbox is checked, disable date options
                const deleteAllUsersCheckbox = document.getElementById('wpmd_delete_all_users');
                if (deleteAllUsersCheckbox) {
                    deleteAllUsersCheckbox.addEventListener('change', function() {
                        if (this.checked) {
                            if (userDeleteDateOptionAll) userDeleteDateOptionAll.disabled = true;
                            if (userDeleteDateOptionSingle) userDeleteDateOptionSingle.disabled = true;
                            if (userDeleteDateOptionRange) userDeleteDateOptionRange.disabled = true;
                            userDeleteDateSingle.disabled = true;
                            userDeleteDateCondition.disabled = true;
                            userDeleteDateStart.disabled = true;
                            userDeleteDateEnd.disabled = true;
                        } else {
                            if (userDeleteDateOptionAll) userDeleteDateOptionAll.disabled = false;
                            if (userDeleteDateOptionSingle) userDeleteDateOptionSingle.disabled = false;
                            if (userDeleteDateOptionRange) userDeleteDateOptionRange.disabled = false;
                            toggleUserDeleteDateFields(); // Re-evaluate based on date options
                        }
                    });
                    // Initial state for delete all checkbox
                    if (deleteAllUsersCheckbox.checked) {
                        if (userDeleteDateOptionAll) userDeleteDateOptionAll.disabled = true;
                        if (userDeleteDateOptionSingle) userDeleteDateOptionSingle.disabled = true;
                        if (userDeleteDateOptionRange) userDeleteDateOptionRange.disabled = true;
                    }
                }
            });
        </script>


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
            $message = __( 'Error deleting posts.', 'wpmd' );
            wp_safe_redirect( add_query_arg( 'message', urlencode( $message ), admin_url( 'admin.php?page=wpmd-manage-posts' ) ) );
            exit;
        }
    }
}

/**
 * Handle user actions (export and delete).
 */
function wpmd_handle_users_action() {
    // Handle Export Users
    if ( isset( $_POST['wpmd_export_users_submit'] ) && current_user_can( 'manage_options' ) ) {
        if ( ! isset( $_POST['wpmd_export_users_nonce_field'] ) || ! wp_verify_nonce( $_POST['wpmd_export_users_nonce_field'], 'wpmd_export_users_nonce' ) ) {
            wp_die( __( 'Security check failed.', 'wpmd' ) );
        }

        $user_role = sanitize_text_field( $_POST['wpmd_user_role_export'] );
        $date_option = sanitize_text_field( $_POST['wpmd_user_export_date_option'] );
        $date_single = sanitize_text_field( $_POST['wpmd_user_export_date_single'] );
        $date_condition = sanitize_text_field( $_POST['wpmd_user_export_date_condition'] );
        $date_start = sanitize_text_field( $_POST['wpmd_user_export_date_start'] );
        $date_end = sanitize_text_field( $_POST['wpmd_user_export_date_end'] );

        wpmd_export_users_to_csv( $user_role, $date_option, $date_single, $date_condition, $date_start, $date_end );
    }

    // Handle Delete Users
    if ( isset( $_POST['wpmd_delete_users_submit'] ) && current_user_can( 'manage_options' ) ) {
        if ( ! isset( $_POST['wpmd_delete_users_nonce_field'] ) || ! wp_verify_nonce( $_POST['wpmd_delete_users_nonce_field'], 'wpmd_delete_users_nonce' ) ) {
            wp_die( __( 'Security check failed.', 'wpmd' ) );
        }

        $user_role        = sanitize_text_field( $_POST['wpmd_user_role_delete'] );
        $delete_all_users = isset( $_POST['wpmd_delete_all_users'] ) ? true : false;
        $date_option      = sanitize_text_field( $_POST['wpmd_user_delete_date_option'] );
        $date_single      = sanitize_text_field( $_POST['wpmd_user_delete_date_single'] );
        $date_condition   = sanitize_text_field( $_POST['wpmd_user_delete_date_condition'] );
        $date_start       = sanitize_text_field( $_POST['wpmd_user_delete_date_start'] );
        $date_end         = sanitize_text_field( $_POST['wpmd_user_delete_date_end'] );

        $deleted_count = wpmd_delete_users( $user_role, $delete_all_users, $date_option, $date_single, $date_condition, $date_start, $date_end );
        if ( $deleted_count !== false ) {
            $message = sprintf( _n( '%s user deleted successfully.', '%s users deleted successfully.', $deleted_count, 'wpmd' ), $deleted_count );
            wp_safe_redirect( add_query_arg( 'message', urlencode( $message ), admin_url( 'admin.php?page=wpmd-manage-users' ) ) );
            exit;
        } else {
            $message = __( 'Error deleting users.', 'wpmd' );
            wp_safe_redirect( add_query_arg( 'message', urlencode( $message ), admin_url( 'admin.php?page=wpmd-manage-users' ) ) );
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
        wp_safe_redirect( add_query_arg( 'message', urlencode( __( 'No posts found for the selected type and date filters to export.', 'wpmd' ) ), admin_url( 'admin.php?page=wpmd-manage-posts' ) ) );
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
    $args = array(
        'post_type'      => $post_type,
        'posts_per_page' => -1,
        'post_status'    => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash' ),
        'fields'         => 'ids',
    );

    if ( $delete_all ) {
        // No date filtering needed if deleting all
    } elseif ( 'single' === $date_option && ! empty( $date_single ) ) {
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
    } else {
        // If no delete_all, and no valid date filter chosen, return 0.
        return 0;
    }

    $posts_to_delete = get_posts( $args );
    $deleted_count   = 0;

    if ( $posts_to_delete ) {
        foreach ( $posts_to_delete as $post_id ) {
            // Delete post permanently (true) or move to trash (false)
            $result = wp_delete_post( $post_id, true );
            if ( $result && ! is_wp_error( $result ) ) {
                $deleted_count++;
            } else {
                // Log error or handle it as needed
                error_log( 'Error deleting post ID ' . $post_id . ': ' . ( is_wp_error( $result ) ? $result->get_error_message() : 'Unknown error' ) );
            }
        }
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
        wp_safe_redirect( add_query_arg( 'message', urlencode( __( 'No users found for the selected role and date filters to export.', 'wpmd' ) ), admin_url( 'admin.php?page=wpmd-manage-users' ) ) );
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
    $args = array(
        'role'    => $user_role,
        'fields'  => 'ID',
    );

    if ( $delete_all ) {
        // No date filtering needed if deleting all
    } elseif ( 'single' === $date_option && ! empty( $date_single ) ) {
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
    } else {
        // If no delete_all, and no valid date filter chosen, return 0.
        return 0;
    }

    $users_to_delete = get_users( $args );
    $deleted_count   = 0;

    if ( $users_to_delete ) {
        // Get the current user's ID to prevent self-deletion
        $current_user_id = get_current_user_id();

        foreach ( $users_to_delete as $user_id ) {
            // Prevent deleting the currently logged-in admin user
            if ( $user_id == $current_user_id ) {
                continue;
            }

            // wp_delete_user takes two arguments: user ID and reassign_to (optional).
            // Reassign to 1 (admin user) or null to delete content.
            // For now, setting to null to delete all content from deleted user.
            $result = wp_delete_user( $user_id, null ); // null means posts/links are deleted
            if ( $result && ! is_wp_error( $result ) ) {
                $deleted_count++;
            } else {
                // Log error or handle it as needed
                error_log( 'Error deleting user ID ' . $user_id . ': ' . ( is_wp_error( $result ) ? $result->get_error_message() : 'Unknown error' ) );
            }
        }
    }

    return $deleted_count;
}
