<?php
/**
 * Plugin Name:       WordPress Post and User Manager
 * Plugin URI:        https://darrenk.uk
 * Description:       A plugin to export posts/users to CSV and delete posts/users by date or all.
 * Version:           1.5.0
 * Author:            Darren Kandekore
 * Author URI:        https://darrenk.uk
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

set_time_limit(0);
ini_set('memory_limit', '512M');

// --- WordPress Hooks ---
add_action('admin_menu', 'wpmd_register_admin_menu_pages');
add_action('admin_enqueue_scripts', 'wpmd_enqueue_admin_scripts');
add_action('admin_init', 'wpmd_handle_form_actions');

function wpmd_register_admin_menu_pages() {
    add_menu_page('WP Manager', 'WP Manager', 'manage_options', 'wpmd-manager', 'wpmd_main_dashboard_page_content', 'dashicons-admin-generic', 80);
    add_submenu_page('wpmd-manager', 'Manage Posts', 'Manage Posts', 'manage_options', 'wpmd-manage-posts', 'wpmd_posts_management_page_content');
    add_submenu_page('wpmd-manager', 'Manage Users', 'Manage Users', 'manage_options', 'wpmd-manage-users', 'wpmd_users_management_page_content');
}

function wpmd_enqueue_admin_scripts($hook_suffix) {
    if (strpos($hook_suffix, 'wpmd-manage-posts') !== false || strpos($hook_suffix, 'wpmd-manage-users') !== false) {
        wp_enqueue_script('wpmd-admin-script', plugin_dir_url(__FILE__) . 'wpmd-admin.js', [], '1.4.0', true);
    }
}

function wpmd_main_dashboard_page_content() {
    echo '<div class="wrap"><h1>WP Manager Dashboard</h1><p>Welcome! Use the submenus to manage your posts and users.</p></div>';
}

function wpmd_posts_management_page_content() {
    $post_types = get_post_types(['show_ui' => true], 'objects');
    $excluded = ['attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation'];
    foreach ($excluded as $key) unset($post_types[$key]);
    ?>
    <div class="wrap" id="wpmd-posts-page">
        <h1>Manage Posts</h1>
        <?php if (isset($_GET['message'])) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(urldecode($_GET['message'])) . '</p></div>'; ?>
        <div id="export-posts-container">
            <h2>Export Posts</h2>
            <form method="post" action=""><?php wpmd_display_form_elements('post', 'export', $post_types); ?></form>
        </div>
        <hr>
        <div id="delete-posts-container">
            <h2>Delete Posts</h2>
            <form method="post" action=""><?php wpmd_display_form_elements('post', 'delete', $post_types); ?></form>
        </div>
    </div>
    <?php
}

function wpmd_users_management_page_content() {
    global $wp_roles;
    $roles = $wp_roles->get_names();
    ?>
    <div class="wrap" id="wpmd-users-page">
        <h1>Manage Users</h1>
        <?php if (isset($_GET['message'])) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(urldecode($_GET['message'])) . '</p></div>'; ?>
        <div id="export-users-container">
            <h2>Export Users</h2>
            <form method="post" action=""><?php wpmd_display_form_elements('user', 'export', $roles); ?></form>
        </div>
        <hr>
        <div id="delete-users-container">
            <h2>Delete Users</h2>
            <form method="post" action=""><?php wpmd_display_form_elements('user', 'delete', $roles); ?></form>
        </div>
    </div>
    <?php
}

function wpmd_display_form_elements($type, $action, $items) {
    $is_post = ($type === 'post');
    $item_label = $is_post ? 'Post Type' : 'User Role';
    
    wp_nonce_field("wpmd_{$action}_{$type}s_action", "wpmd_{$action}_{$type}s_nonce");
    ?>
    <input type="hidden" name="wpmd_form_action" value="<?= esc_attr("{$action}_{$type}s") ?>">
    <table class="form-table">
        <tr>
            <th scope="row"><label for="wpmd_item_type_<?= $action ?>_<?= $type ?>"><?= $item_label ?></label></th>
            <td>
                <select name="wpmd_item_type" id="wpmd_item_type_<?= $action ?>_<?= $type ?>">
                    <?php foreach ($items as $key => $item) : ?>
                        <option value="<?= esc_attr($is_post ? $item->name : $key) ?>"><?= esc_html($is_post ? $item->labels->singular_name : $item) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row">Filter by Date</th>
            <td>
                <fieldset>
                    <label><input type="radio" name="wpmd_date_option" value="all" checked> All Dates</label><br>
                    <label><input type="radio" name="wpmd_date_option" value="single"> Before/After Specific Date</label>
                    <input type="date" name="wpmd_date_single" value="<?= esc_attr(current_time('Y-m-d')) ?>" disabled>
                    <select name="wpmd_date_condition" disabled><option value="before">Before</option><option value="after">After</option></select><br>
                    <label><input type="radio" name="wpmd_date_option" value="range"> Between Date Range</label>
                    <input type="date" name="wpmd_date_start" value="<?= esc_attr(current_time('Y-m-d')) ?>" disabled> to
                    <input type="date" name="wpmd_date_end" value="<?= esc_attr(current_time('Y-m-d')) ?>" disabled>
                </fieldset>
            </td>
        </tr>
        <?php if ($action === 'delete') : ?>
        <tr>
            <th scope="row">Or Delete All</th>
            <td>
                <label><input type="checkbox" name="wpmd_delete_all"> Delete ALL items, regardless of date.</label>
                <p class="description"><strong style="color: red;">WARNING: This cannot be undone!</strong></p>
            </td>
        </tr>
        <?php endif; ?>
    </table>
    <p class="submit"><input type="submit" class="button <?= $action === 'delete' ? 'button-danger' : 'button-primary' ?>" value="<?= ucfirst($action) ?> <?= ucfirst($type) ?>s"></p>
    <?php
}

function wpmd_handle_form_actions() {
    if (!isset($_POST['wpmd_form_action']) || !current_user_can('manage_options')) return;

    $action = sanitize_text_field($_POST['wpmd_form_action']);
    check_admin_referer("wpmd_{$action}_action", "wpmd_{$action}_nonce");

    $item_type = sanitize_text_field($_POST['wpmd_item_type']);
    $date_option = sanitize_text_field($_POST['wpmd_date_option']);
    $date_single = sanitize_text_field($_POST['wpmd_date_single']);
    $date_condition = sanitize_text_field($_POST['wpmd_date_condition']);
    $date_start = sanitize_text_field($_POST['wpmd_date_start']);
    $date_end = sanitize_text_field($_POST['wpmd_date_end']);
    $delete_all = isset($_POST['wpmd_delete_all']);

    switch ($action) {
        case 'export_posts':
            wpmd_export_posts_to_csv($item_type, $date_option, $date_single, $date_condition, $date_start, $date_end);
            exit;
        case 'export_users':
            wpmd_export_users_to_csv($item_type, $date_option, $date_single, $date_condition, $date_start, $date_end);
            exit;
        case 'delete_posts':
            $count = wpmd_delete_posts($item_type, $delete_all, $date_option, $date_single, $date_condition, $date_start, $date_end);
            $message = sprintf(_n('%s post deleted.', '%s posts deleted.', $count, 'wpmd'), $count);
            wp_safe_redirect(add_query_arg(['page' => 'wpmd-manage-posts', 'message' => urlencode($message)], admin_url('admin.php')));
            exit;
        case 'delete_users':
            $count = wpmd_delete_users($item_type, $delete_all, $date_option, $date_single, $date_condition, $date_start, $date_end);
            $message = sprintf(_n('%s user deleted.', '%s users deleted.', $count, 'wpmd'), $count);
            wp_safe_redirect(add_query_arg(['page' => 'wpmd-manage-users', 'message' => urlencode($message)], admin_url('admin.php')));
            exit;
    }
}

function wpmd_build_date_query($date_option, $date_single, $date_condition, $date_start, $date_end, $column = 'post_date') {
    if ($date_option === 'single' && !empty($date_single)) {
        return [['column' => $column, $date_condition => $date_single . ($date_condition === 'before' ? ' 23:59:59' : ' 00:00:00'), 'inclusive' => true]];
    }
    if ($date_option === 'range' && !empty($date_start) && !empty($date_end)) {
        return [['column' => $column, 'after' => $date_start . ' 00:00:00', 'before' => $date_end . ' 23:59:59', 'inclusive' => true]];
    }
    return [];
}

// --- DATA HANDLING FUNCTIONS ---

function wpmd_export_posts_to_csv($post_type, $date_option, $date_single, $date_condition, $date_start, $date_end) {
    $args = ['post_type' => $post_type, 'posts_per_page' => -1, 'post_status' => 'any'];
    $args['date_query'] = wpmd_build_date_query($date_option, $date_single, $date_condition, $date_start, $date_end);
    $posts = get_posts($args);

    if (empty($posts)) {
        wp_safe_redirect(add_query_arg(['page' => 'wpmd-manage-posts', 'message' => urlencode('No posts found to export.')], admin_url('admin.php')));
        exit;
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . sanitize_file_name($post_type) . '_export_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    
    $headers = ['ID', 'Title', 'Content', 'Status', 'Date', 'Author ID', 'Author Name', 'Permalink'];
    fputcsv($output, $headers);

    foreach ($posts as $post) {
        $author_info = get_userdata($post->post_author);
        $row = [
            $post->ID,
            $post->post_title,
            $post->post_content,
            $post->post_status,
            $post->post_date,
            $post->post_author,
            $author_info ? $author_info->display_name : '',
            get_permalink($post->ID)
        ];
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

function wpmd_export_users_to_csv($user_role, $date_option, $date_single, $date_condition, $date_start, $date_end) {
    $args = ['role' => $user_role];
    $args['date_query'] = wpmd_build_date_query($date_option, $date_single, $date_condition, $date_start, $date_end, 'user_registered');
    $users = get_users($args);

    if (empty($users)) {
        wp_safe_redirect(add_query_arg(['page' => 'wpmd-manage-users', 'message' => urlencode('No users found to export.')], admin_url('admin.php')));
        exit;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . sanitize_file_name($user_role) . '_export_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    
    $headers = ['ID', 'Username', 'Email', 'Display Name', 'Registered Date'];
    fputcsv($output, $headers);

    foreach ($users as $user) {
        $row = [$user->ID, $user->user_login, $user->user_email, $user->display_name, $user->user_registered];
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}


function wpmd_delete_posts($post_type, $delete_all, $date_option, $date_single, $date_condition, $date_start, $date_end) {
    $args = ['post_type' => $post_type, 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids'];
    if (!$delete_all) {
        $args['date_query'] = wpmd_build_date_query($date_option, $date_single, $date_condition, $date_start, $date_end);
        if (empty($args['date_query']) && $date_option !== 'all') return 0;
    }

    $posts_to_delete = get_posts($args);
    $deleted_count = 0;
    if (!empty($posts_to_delete)) {
        foreach ($posts_to_delete as $post_id) {
            if (wp_delete_post($post_id, true)) $deleted_count++;
        }
    }
    return $deleted_count;
}

function wpmd_delete_users($user_role, $delete_all, $date_option, $date_single, $date_condition, $date_start, $date_end) {
    $args = ['role' => $user_role, 'fields' => 'ids'];
     if (!$delete_all) {
        $args['date_query'] = wpmd_build_date_query($date_option, $date_single, $date_condition, $date_start, $date_end, 'user_registered');
        if (empty($args['date_query']) && $date_option !== 'all') return 0;
    }

    $users_to_delete = get_users($args);
    $deleted_count = 0;
    if (!empty($users_to_delete)) {
        foreach ($users_to_delete as $user_id) {
            if ($user_id != get_current_user_id() && wp_delete_user($user_id)) {
                $deleted_count++;
            }
        }
    }
    return $deleted_count;
}