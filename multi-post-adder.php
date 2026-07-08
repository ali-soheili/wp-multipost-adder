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

    add_options_page(
        __('MPA Settings', 'multi-post-adder'),
        __('MPA Settings', 'multi-post-adder'),
        'manage_options',
        'mpa-settings',
        'mpa_settings_page'
    );
});

// Enqueue scripts and styles
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook === 'settings_page_mpa-settings') {
        wp_enqueue_media();
        wp_enqueue_editor();
        wp_enqueue_style('mpa-style', plugin_dir_url(__FILE__) . 'mpa-style.css');
        return;
    }

    if ($hook !== 'posts_page_add-multiple-posts') return;

    wp_enqueue_media();
    wp_enqueue_editor(); //  Required for wp.editor
    wp_enqueue_script('mpa-script', plugin_dir_url(__FILE__) . 'mpa-script.js', ['jquery', 'wp-editor'], null, true);
    wp_enqueue_style('mpa-style', plugin_dir_url(__FILE__) . 'mpa-style.css');

    global $wpdb;
    $keys = $wpdb->get_col("SELECT DISTINCT meta_key FROM $wpdb->postmeta WHERE meta_key NOT LIKE '\\_%' LIMIT 50");
    wp_localize_script('mpa-script', 'mpa_meta_keys', $keys);
    wp_localize_script('mpa-script', 'mpa_presets', mpa_get_presets());
});

function mpa_get_presets() {
    $presets = get_option('mpa_content_presets', []);
    return is_array($presets) ? $presets : [];
}

function mpa_preset_editor($content, $editor_id) {
    wp_editor($content, $editor_id, [
        'textarea_name' => 'mpa-preset-content',
        'textarea_rows' => 12,
        'media_buttons' => true,
        'teeny' => false,
        'quicktags' => true,
    ]);
}

function mpa_settings_page() {
    if (!current_user_can('manage_options')) return;

    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'presets';
    $presets = mpa_get_presets();
    ?>
    <div class="wrap mpa-settings-page">
        <h1><?php _e('MPA Settings', 'multi-post-adder'); ?></h1>

        <?php if (!empty($_GET['mpa-message'])) : ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <?php
                    echo sanitize_key($_GET['mpa-message']) === 'deleted'
                        ? esc_html__('Preset removed.', 'multi-post-adder')
                        : esc_html__('Preset saved.', 'multi-post-adder');
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <nav class="nav-tab-wrapper">
            <a href="<?php echo esc_url(admin_url('options-general.php?page=mpa-settings&tab=presets')); ?>" class="nav-tab <?php echo $active_tab === 'presets' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Presets', 'multi-post-adder'); ?>
            </a>
        </nav>

        <?php if ($active_tab === 'presets') : ?>
            <div class="mpa-settings-panel">
                <h2><?php _e('Add New Preset', 'multi-post-adder'); ?></h2>
                <form method="post" class="mpa-preset-form">
                    <?php wp_nonce_field('mpa_save_preset', 'mpa_preset_nonce'); ?>
                    <input type="hidden" name="mpa-settings-action" value="save_preset">

                    <label>
                        <span><?php _e('Preset name', 'multi-post-adder'); ?></span>
                        <input type="text" name="mpa-preset-name" class="mpa-preset-title-input" required>
                    </label>

                    <div class="mpa-preset-editor">
                        <span><?php _e('Post content', 'multi-post-adder'); ?></span>
                        <?php mpa_preset_editor('', 'mpa_preset_content_new'); ?>
                    </div>

                    <button type="submit" class="button button-primary"><?php _e('Add Preset', 'multi-post-adder'); ?></button>
                </form>

                <h2><?php _e('Saved Presets', 'multi-post-adder'); ?></h2>
                <?php if (empty($presets)) : ?>
                    <p><?php _e('No presets saved yet.', 'multi-post-adder'); ?></p>
                <?php else : ?>
                    <div class="mpa-preset-list">
                        <?php foreach ($presets as $preset) : ?>
                            <details class="mpa-preset-details">
                                <summary><?php echo esc_html($preset['name']); ?></summary>
                                <form method="post" class="mpa-preset-form mpa-saved-preset">
                                    <?php wp_nonce_field('mpa_save_preset', 'mpa_preset_nonce'); ?>
                                    <input type="hidden" name="mpa-settings-action" value="save_preset">
                                    <input type="hidden" name="mpa-preset-id" value="<?php echo esc_attr($preset['id']); ?>">

                                    <label>
                                        <span><?php _e('Preset name', 'multi-post-adder'); ?></span>
                                        <input type="text" name="mpa-preset-name" class="mpa-preset-title-input" value="<?php echo esc_attr($preset['name']); ?>" required>
                                    </label>

                                    <div class="mpa-preset-editor">
                                        <span><?php _e('Post content', 'multi-post-adder'); ?></span>
                                        <?php mpa_preset_editor($preset['content'], 'mpa_preset_content_' . sanitize_key($preset['id'])); ?>
                                    </div>

                                    <div class="mpa-preset-actions">
                                        <button type="submit" class="button button-primary"><?php _e('Save Changes', 'multi-post-adder'); ?></button>
                                        <button type="submit" name="mpa-settings-action" value="delete_preset" class="button button-link-delete" formnovalidate onclick="return confirm('<?php echo esc_js(__('Delete this preset?', 'multi-post-adder')); ?>');">
                                            <?php _e('Remove', 'multi-post-adder'); ?>
                                        </button>
                                    </div>
                                </form>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

function mpa_admin_page() {
    $presets = mpa_get_presets();
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

                    <label>
                        <span><?php _e('Content preset:', 'multi-post-adder'); ?></span>
                        <select id="mpa-content-preset">
                            <option value=""><?php _e('Select a preset', 'multi-post-adder'); ?></option>
                            <?php foreach ($presets as $preset) : ?>
                                <option value="<?php echo esc_attr($preset['id']); ?>"><?php echo esc_html($preset['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
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
    if (empty($_POST['mpa-settings-action'])) return;
    if (!current_user_can('manage_options')) return;
    if (empty($_POST['mpa_preset_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mpa_preset_nonce'])), 'mpa_save_preset')) return;

    $action = sanitize_key(wp_unslash($_POST['mpa-settings-action']));
    $presets = mpa_get_presets();
    $preset_id = !empty($_POST['mpa-preset-id']) ? sanitize_key(wp_unslash($_POST['mpa-preset-id'])) : '';

    if ($action === 'delete_preset' && $preset_id) {
        unset($presets[$preset_id]);
        update_option('mpa_content_presets', $presets);
        wp_redirect(admin_url('options-general.php?page=mpa-settings&tab=presets&mpa-message=deleted'));
        exit;
    }

    if ($action === 'save_preset') {
        $preset_name = !empty($_POST['mpa-preset-name']) ? sanitize_text_field(wp_unslash($_POST['mpa-preset-name'])) : '';
        $preset_content = !empty($_POST['mpa-preset-content']) ? wp_kses_post(wp_unslash($_POST['mpa-preset-content'])) : '';

        if ($preset_name && $preset_content) {
            if (!$preset_id) {
                $preset_id = 'preset_' . str_replace('.', '_', uniqid('', true));
            }

            $presets[$preset_id] = [
                'id' => $preset_id,
                'name' => $preset_name,
                'content' => $preset_content,
            ];

            update_option('mpa_content_presets', $presets);
            wp_redirect(admin_url('options-general.php?page=mpa-settings&tab=presets&mpa-message=saved'));
            exit;
        }
    }
});

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
