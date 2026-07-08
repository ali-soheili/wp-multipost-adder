<?php
/**
 * Plugin Name: Multi Post Adder
 * Description: Adds multiple posts at once from a custom admin page.
 * Version: 1.4
 * Author: A Soheili
 * Text Domain: multi-post-adder
 * Domain Path: /languages
 */
if (!defined('ABSPATH')) exit;

add_action('plugins_loaded', function() {
    load_plugin_textdomain('multi-post-adder', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php',
        __('Add Multiple Posts', 'multi-post-adder'),
        __('Add Multiple Posts', 'multi-post-adder'),
        'edit_posts',
        'add-multiple-posts',
        'mpa_admin_page'
    );
});

// Enqueue scripts and styles
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'posts_page_add-multiple-posts') return;

    wp_enqueue_media();
    wp_enqueue_editor(); //  Required for wp.editor
    wp_enqueue_script('mpa-script', plugin_dir_url(__FILE__) . 'mpa-script.js', ['jquery', 'wp-editor'], null, true);
    wp_enqueue_style('mpa-style', plugin_dir_url(__FILE__) . 'mpa-style.css');

    global $wpdb;
    $keys = $wpdb->get_col("SELECT DISTINCT meta_key FROM $wpdb->postmeta WHERE meta_key NOT LIKE '\\_%' LIMIT 50");
    wp_localize_script('mpa-script', 'mpa_meta_keys', $keys);
});


function mpa_admin_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Add Multiple Posts', 'multi-post-adder'); ?></h1>
        <form id="mpa-form" method="post">
            <section class="mpa-global-adjustments">
                <h2><?php _e('Global Adjustments', 'multi-post-adder'); ?></h2>

                <div class="mpa-global-fields">
                    <label>
                        <span><?php _e('Number of posts:', 'multi-post-adder'); ?></span>
                        <input type="number" id="mpa-count" min="1" value="1">
                    </label>

                    <label>
                        <span><?php _e('Category:', 'multi-post-adder'); ?></span>
                <?php
                wp_dropdown_categories([
                    'name' => 'mpa-category',
                    'hierarchical' => true,           // ✅ Enables hierarchy
                    'hide_empty' => false,
                    'orderby' => 'name',
                    'show_option_none' => __('Select a category', 'multi-post-adder'),
                    'depth' => 0,                      // ✅ Show all levels
                ]);
                ?>
                    </label>

                    <label>
                        <span><?php _e('Hashtags (comma-separated):', 'multi-post-adder'); ?></span>
                        <input type="text" name="mpa-hashtags" id="mpa-hashtags">
                    </label>
                </div>

                <div class="mpa-global-custom-fields">
                    <h3><?php _e('Global Custom Fields', 'multi-post-adder'); ?></h3>
                    <button type="button" class="button mpa-add-global-meta"><?php _e('Add Global Custom Field', 'multi-post-adder'); ?></button>
                    <div class="mpa-custom-fields" id="mpa-global-meta"></div>
                </div>
            </section>

            <div id="mpa-posts-container"></div>
            <br>
            <button type="submit" name="mpa-submit" class="button button-primary"><?php _e('Publish Posts', 'multi-post-adder'); ?></button>
            <button type="submit" name="mpa-submit-draft" class="button"><?php _e('Save as Draft', 'multi-post-adder'); ?></button>
        </form>
    </div>
    <?php
}

add_action('admin_init', function () {
    if (!isset($_POST['mpa-posts'])) return;

    $category_id = intval($_POST['mpa-category']);
    $hashtags = sanitize_text_field($_POST['mpa-hashtags']);
    $global_meta_data = !empty($_POST['mpa-global-meta']) ? $_POST['mpa-global-meta'] : [];
    $posts_data = $_POST['mpa-posts'];
    $status = isset($_POST['mpa-submit']) ? 'publish' : 'draft';

    foreach ($posts_data as $post_data) {
        $title = sanitize_text_field($post_data['title']);
        $content = wp_kses_post($post_data['content']);
        $image_id = intval($post_data['image']);

        $post_id = wp_insert_post([
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => $status,
            'post_category' => [$category_id],
        ]);
        if ($post_id && $image_id) set_post_thumbnail($post_id, $image_id);

        if (!empty($global_meta_data)) {
            foreach ($global_meta_data as $meta) {
                $key = sanitize_text_field($meta['key'] ?: $meta['custom_key']);
                $value = sanitize_text_field($meta['value']);
                if ($key) update_post_meta($post_id, $key, $value);
            }
        }

        if (!empty($post_data['meta'])) {
            foreach ($post_data['meta'] as $meta) {
                $key = sanitize_text_field($meta['key'] ?: $meta['custom_key']);
                $value = sanitize_text_field($meta['value']);
                if ($key) update_post_meta($post_id, $key, $value);
            }
        }

        if ($hashtags) wp_set_post_tags($post_id, $hashtags, true);
    }

    wp_redirect(admin_url('edit.php?message=posts_added'));
    exit;
});
