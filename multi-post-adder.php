<?php
/**
 * Plugin Name: Multi Post Adder
 * Description: Adds multiple posts at once from a custom admin page.
 * Version: 1.13
 * Author: A Soheili
 * Text Domain: multi-post-adder
 * Domain Path: /languages
 * Requires at least: 5.8
 * Update URI: https://github.com/ali-soheili/wp-multipost-adder
 */
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/includes/class-mpa-github-updater.php';
new MPA_GitHub_Updater(__FILE__);

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
    wp_localize_script('mpa-script', 'mpa_i18n', [
        'selectKey' => __('-- Select Key --', 'multi-post-adder'),
        'customKey' => __('Or custom key', 'multi-post-adder'),
        'value' => __('Value', 'multi-post-adder'),
        'title' => __('Title:', 'multi-post-adder'),
        'content' => __('Content:', 'multi-post-adder'),
        'featuredImage' => __('Featured Image:', 'multi-post-adder'),
        'uploadImage' => __('Upload Image', 'multi-post-adder'),
        'customFields' => __('Custom Fields:', 'multi-post-adder'),
        'addCustomField' => __('Add Custom Field', 'multi-post-adder'),
        'selectOrUploadImage' => __('Select or Upload Image', 'multi-post-adder'),
        'useThisImage' => __('Use this image', 'multi-post-adder'),
        'automation' => __('Automation:', 'multi-post-adder'),
        'translateTitle' => __('Translate title', 'multi-post-adder'),
        'downloadLink' => __('Download link', 'multi-post-adder'),
        'fileName' => __('File name', 'multi-post-adder'),
        'customText' => __('Custom text', 'multi-post-adder'),
        'setupImported' => __('Setup imported.', 'multi-post-adder'),
        'setupInvalid' => __('This file is not a valid Multi Post Adder setup.', 'multi-post-adder'),
        /* translators: %s: category path, e.g. Software › Windows */
        'categoryMissing' => __('Category not found, skipped: %s', 'multi-post-adder'),
        /* translators: %s: preset name */
        'presetMissing' => __('Content preset not found, skipped: %s', 'multi-post-adder'),
        'confirmRebuild' => __('Importing changes the number of posts, which clears what you have typed in the posts below. Continue?', 'multi-post-adder'),
        'dismiss' => __('Dismiss this notice.', 'multi-post-adder'),
        'enterCustomText' => __('Enter custom text in the Automation box first.', 'multi-post-adder'),
        'runAll' => __('Run all', 'multi-post-adder'),
        'working' => __('Working…', 'multi-post-adder'),
        'done' => __('Updated.', 'multi-post-adder'),
        'enterTitle' => __('Enter a title first.', 'multi-post-adder'),
        'selectImage' => __('Select a featured image first.', 'multi-post-adder'),
        'enterPattern' => __('Enter a download link pattern in the Automation box first.', 'multi-post-adder'),
        /* translators: %s: placeholder such as {file_name} */
        'notFound' => __('%s was not found in the content.', 'multi-post-adder'),
        'translateFailed' => __('Translation failed.', 'multi-post-adder'),
        /* translators: %d: number of selected categories */
        'selectedCount' => __('%d selected', 'multi-post-adder'),
        'remove' => __('Remove', 'multi-post-adder'),
        'noPlaceholders' => __('No placeholders found in this post.', 'multi-post-adder'),
        'copied' => __('Copied!', 'multi-post-adder'),
        'publishPosts' => __('Publish Posts', 'multi-post-adder'),
        'schedulePosts' => __('Schedule Posts', 'multi-post-adder'),
        /* translators: %d: post number */
        'postN' => __('Post %d', 'multi-post-adder'),
        'chooseFuture' => __('Choose a date and time in the future.', 'multi-post-adder'),
        'chooseDate' => __('Choose a date and time for scheduling.', 'multi-post-adder'),
        /* translators: %s: date and time */
        'allAt' => __('All posts will be published on %s.', 'multi-post-adder'),
        /* translators: 1: first date and time, 2: last date and time */
        'oneByOne' => __('From %1$s to %2$s.', 'multi-post-adder'),
    ]);
    wp_localize_script('mpa-script', 'mpa_automation', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mpa_automation'),
        'sourceLang' => apply_filters('mpa_translate_source_lang', 'fa'),
        'targetLang' => apply_filters('mpa_translate_target_lang', 'en'),
        'settings' => mpa_get_automation_settings(),
    ]);
});

function mpa_get_automation_settings() {
    $saved = get_user_meta(get_current_user_id(), 'mpa_automation', true);
    return wp_parse_args(is_array($saved) ? $saved : [], [
        'enabled' => false,
        'download_pattern' => '',
        'custom_text' => '',
    ]);
}

// Translate a post title (Persian → English) with Google Translate's free endpoint.
add_action('wp_ajax_mpa_translate', function () {
    check_ajax_referer('mpa_automation', 'nonce');
    if (!current_user_can('edit_posts')) wp_send_json_error(['message' => __('Not allowed.', 'multi-post-adder')], 403);

    $text = isset($_POST['text']) ? trim(sanitize_text_field(wp_unslash($_POST['text']))) : '';
    if ($text === '') wp_send_json_error(['message' => __('Enter a title first.', 'multi-post-adder')]);

    $source = apply_filters('mpa_translate_source_lang', 'fa');
    $target = apply_filters('mpa_translate_target_lang', 'en');

    $cache_key = 'mpa_tr_' . md5($source . '|' . $target . '|' . $text);
    $cached = get_transient($cache_key);
    if ($cached !== false) wp_send_json_success(['text' => $cached]);

    $translated = mpa_google_translate($text, $source, $target);
    if (is_wp_error($translated)) wp_send_json_error(['message' => $translated->get_error_message()]);

    set_transient($cache_key, $translated, DAY_IN_SECONDS);
    wp_send_json_success(['text' => $translated]);
});

/**
 * Server-side fallback for translation. Tries several free Google endpoints,
 * because Google often rate-limits (HTTP 429) shared-hosting IP addresses.
 *
 * @return string|WP_Error
 */
function mpa_google_translate($text, $source, $target) {
    $endpoints = [
        'https://translate.googleapis.com/translate_a/single?' . http_build_query(['client' => 'gtx', 'sl' => $source, 'tl' => $target, 'dt' => 't', 'q' => $text]),
        'https://clients5.google.com/translate_a/t?' . http_build_query(['client' => 'dict-chrome-ex', 'sl' => $source, 'tl' => $target, 'q' => $text]),
        'https://translate.google.com/translate_a/single?' . http_build_query(['client' => 'gtx', 'sl' => $source, 'tl' => $target, 'dt' => 't', 'q' => $text]),
    ];

    $last_code = 0;
    foreach ($endpoints as $url) {
        $response = wp_remote_get($url, [
            'timeout' => 6,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
        ]);
        if (is_wp_error($response)) {
            // No connection at all (offline / intranet / blocked): trying other Google hosts won't help.
            return new WP_Error('mpa_translate_offline', __('Google Translate could not be reached. Translation needs internet access; everything else works without it.', 'multi-post-adder'));
        }

        $last_code = (int) wp_remote_retrieve_response_code($response);
        if ($last_code !== 200) continue;

        $translated = mpa_parse_google_translation(json_decode(wp_remote_retrieve_body($response), true));
        if ($translated !== '') return $translated;
    }

    if ($last_code === 429) {
        return new WP_Error('mpa_translate_429', __('Google Translate is limiting requests from this server right now (HTTP 429). Wait a few minutes and try again.', 'multi-post-adder'));
    }
    if ($last_code) {
        /* translators: %d: HTTP status code */
        return new WP_Error('mpa_translate_http', sprintf(__('Google Translate returned HTTP %d.', 'multi-post-adder'), $last_code));
    }
    return new WP_Error('mpa_translate_failed', __('Translation failed.', 'multi-post-adder'));
}

function mpa_parse_google_translation($data) {
    if (!is_array($data) || !isset($data[0])) return '';

    // translate_a/single: [[["Hello","سلام",...], ...], ...]
    if (is_array($data[0]) && isset($data[0][0]) && is_array($data[0][0])) {
        $out = '';
        foreach ($data[0] as $segment) {
            if (isset($segment[0]) && is_string($segment[0])) $out .= $segment[0];
        }
        return trim($out);
    }
    // translate_a/t: [["Hello","fa"]] or ["Hello"]
    if (is_array($data[0]) && isset($data[0][0]) && is_string($data[0][0])) return trim($data[0][0]);
    if (is_string($data[0])) return trim($data[0]);

    return '';
}

// Remember the Automation box (on/off + download pattern) per user.
add_action('wp_ajax_mpa_save_automation', function () {
    check_ajax_referer('mpa_automation', 'nonce');
    if (!current_user_can('edit_posts')) wp_send_json_error(null, 403);

    update_user_meta(get_current_user_id(), 'mpa_automation', [
        'enabled' => !empty($_POST['enabled']) && $_POST['enabled'] !== 'false',
        // Free text; HTML allowed in posts is kept.
        'download_pattern' => isset($_POST['download_pattern']) ? wp_kses_post(wp_unslash($_POST['download_pattern'])) : '',
        'custom_text' => isset($_POST['custom_text']) ? wp_kses_post(wp_unslash($_POST['custom_text'])) : '',
    ]);
    wp_send_json_success();
});

/* -------------------------------------------------------------------------
 * Scheduling
 * ---------------------------------------------------------------------- */

function mpa_schedule_intervals() {
    return [
        15   => __('15 minutes', 'multi-post-adder'),
        30   => __('30 minutes', 'multi-post-adder'),
        60   => __('1 hour', 'multi-post-adder'),
        120  => __('2 hours', 'multi-post-adder'),
        180  => __('3 hours', 'multi-post-adder'),
        360  => __('6 hours', 'multi-post-adder'),
        720  => __('12 hours', 'multi-post-adder'),
        1440 => __('24 hours', 'multi-post-adder'),
    ];
}

function mpa_get_schedule_settings() {
    $saved = get_user_meta(get_current_user_id(), 'mpa_schedule', true);
    $settings = wp_parse_args(is_array($saved) ? $saved : [], [
        'enabled' => false,
        'mode' => 'all',
        'interval' => 60,
    ]);
    if (!array_key_exists((int) $settings['interval'], mpa_schedule_intervals())) $settings['interval'] = 60;
    return $settings;
}

// Remember the Scheduling box (on/off, mode, interval) per user. The date itself is not remembered.
add_action('wp_ajax_mpa_save_schedule', function () {
    check_ajax_referer('mpa_automation', 'nonce');
    if (!current_user_can('edit_posts')) wp_send_json_error(null, 403);

    $interval = isset($_POST['interval']) ? (int) $_POST['interval'] : 60;
    update_user_meta(get_current_user_id(), 'mpa_schedule', [
        'enabled' => !empty($_POST['enabled']) && $_POST['enabled'] !== 'false',
        'mode' => (isset($_POST['mode']) && $_POST['mode'] === 'interval') ? 'interval' : 'all',
        'interval' => array_key_exists($interval, mpa_schedule_intervals()) ? $interval : 60,
    ]);
    wp_send_json_success();
});

/**
 * Publish times for $count posts, as DateTimeImmutable in the site timezone,
 * or null when scheduling is off or the input is invalid.
 */
function mpa_schedule_times($schedule, $count) {
    if (empty($schedule['enabled']) || empty($schedule['start'])) return null;

    $tz = wp_timezone();
    $start = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', sanitize_text_field(wp_unslash($schedule['start'])), $tz);
    if (!$start) return null;

    $interval = (isset($schedule['mode']) && $schedule['mode'] === 'interval') ? (int) ($schedule['interval'] ?? 0) : 0;
    if ($interval && !array_key_exists($interval, mpa_schedule_intervals())) $interval = 60;

    $times = [];
    for ($i = 0; $i < $count; $i++) {
        $times[] = $start->modify('+' . ($i * $interval) . ' minutes');
    }
    return $times;
}

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

        <?php if (!empty($_GET['mpa-message'])) :
            $messages = [
                'deleted'          => __('Preset removed.', 'multi-post-adder'),
                'saved'            => __('Preset saved.', 'multi-post-adder'),
                'updates-saved'    => __('Update settings saved.', 'multi-post-adder'),
                'updates-checked'  => __('Checked GitHub for updates.', 'multi-post-adder'),
            ];
            $message_key = sanitize_key($_GET['mpa-message']);
            if (isset($messages[$message_key])) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html($messages[$message_key]); ?></p>
            </div>
        <?php endif; endif; ?>

        <nav class="nav-tab-wrapper">
            <a href="<?php echo esc_url(admin_url('options-general.php?page=mpa-settings&tab=presets')); ?>" class="nav-tab <?php echo $active_tab === 'presets' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Presets', 'multi-post-adder'); ?>
            </a>
            <?php if (current_user_can('update_plugins')) : ?>
            <a href="<?php echo esc_url(admin_url('options-general.php?page=mpa-settings&tab=updates')); ?>" class="nav-tab <?php echo $active_tab === 'updates' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Updates', 'multi-post-adder'); ?>
            </a>
            <?php endif; ?>
        </nav>

        <?php if ($active_tab === 'updates' && current_user_can('update_plugins')) mpa_updates_tab(); ?>

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

                    <button style="margin-top: 1.5rem;" type="submit" class="button button-primary"><?php _e('Add Preset', 'multi-post-adder'); ?></button>
                </form>

                <hr class="mpa-presets-divider">

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

/**
 * Multi-select category picker: a dropdown with a checkbox beside each
 * category (children indented under parents) and the selection shown as chips.
 */
function mpa_category_picker() {
    $categories = get_categories([
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC',
    ]);

    $children = [];
    foreach ($categories as $category) {
        $children[(int) $category->parent][] = $category;
    }
    ?>
    <div class="mpa-cat-select" id="mpa-cat-select">
        <div class="mpa-cat-control" role="button" tabindex="0" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="mpa-cat-label">
            <span class="mpa-cat-chips" data-empty="<?php esc_attr_e('Select categories', 'multi-post-adder'); ?>"></span>
            <span class="mpa-cat-arrow" aria-hidden="true"></span>
        </div>
        <div class="mpa-cat-panel" hidden>
            <input type="search" class="mpa-cat-search" placeholder="<?php esc_attr_e('Search categories…', 'multi-post-adder'); ?>" aria-label="<?php esc_attr_e('Search categories', 'multi-post-adder'); ?>">
            <?php if (empty($categories)) : ?>
                <p class="mpa-cat-empty"><?php _e('No categories found.', 'multi-post-adder'); ?></p>
            <?php else : ?>
                <ul class="mpa-cat-list" role="listbox" aria-multiselectable="true">
                    <?php mpa_category_picker_items($children, 0, []); ?>
                </ul>
                <p class="mpa-cat-no-match" hidden><?php _e('No matching categories.', 'multi-post-adder'); ?></p>
            <?php endif; ?>
            <div class="mpa-cat-footer">
                <span class="mpa-cat-count"></span>
                <button type="button" class="button-link mpa-cat-clear"><?php _e('Clear', 'multi-post-adder'); ?></button>
            </div>
        </div>
    </div>
    <?php
}

function mpa_category_picker_items($children, $parent_id, $ancestors) {
    if (empty($children[$parent_id])) return;

    foreach ($children[$parent_id] as $category) {
        $id = (int) $category->term_id;
        $path = array_merge($ancestors, [$id]);
        ?>
        <li class="mpa-cat-item" data-id="<?php echo esc_attr($id); ?>">
            <label class="mpa-cat-option">
                <input type="checkbox" name="mpa-category[]" value="<?php echo esc_attr($id); ?>"
                       data-name="<?php echo esc_attr($category->name); ?>"
                       data-path="<?php echo esc_attr(implode(',', $path)); ?>">
                <span class="mpa-cat-name"><?php echo esc_html($category->name); ?></span>
            </label>
            <?php if (!empty($children[$id])) : ?>
                <ul class="mpa-cat-children">
                    <?php mpa_category_picker_items($children, $id, $path); ?>
                </ul>
            <?php endif; ?>
        </li>
        <?php
    }
}

function mpa_updates_tab() {
    $settings = MPA_GitHub_Updater::get_settings();
    $plugin   = get_file_data(__FILE__, ['Version' => 'Version']);
    $cached   = get_site_transient(MPA_GitHub_Updater::CACHE_KEY);
    $has_new  = is_array($cached) && !empty($cached['version']) && version_compare($cached['version'], $plugin['Version'], '>');
    $check_url = wp_nonce_url(admin_url('admin-post.php?action=mpa_check_updates&return=settings'), 'mpa_check_updates');
    ?>
    <div class="mpa-settings-panel">
        <h2><?php _e('Update status', 'multi-post-adder'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php _e('Installed version', 'multi-post-adder'); ?></th>
                <td><code><?php echo esc_html($plugin['Version']); ?></code></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Latest version on GitHub', 'multi-post-adder'); ?></th>
                <td>
                    <?php if (!MPA_GitHub_Updater::is_enabled()) : ?>
                        <em><?php _e('Update checks are turned off.', 'multi-post-adder'); ?></em>
                    <?php elseif (is_array($cached) && !empty($cached['error'])) : ?>
                        <span style="color:#b32d2e;"><?php echo esc_html($cached['error']); ?></span>
                    <?php elseif (is_array($cached) && !empty($cached['version'])) : ?>
                        <code><?php echo esc_html($cached['version']); ?></code>
                        <?php if ($has_new) : ?>
                            &nbsp;<a class="button button-primary" href="<?php echo esc_url(self_admin_url('update-core.php')); ?>"><?php _e('Update now', 'multi-post-adder'); ?></a>
                        <?php else : ?>
                            &nbsp;<span style="color:#008a20;"><?php _e('You are up to date.', 'multi-post-adder'); ?></span>
                        <?php endif; ?>
                    <?php else : ?>
                        <em><?php _e('Not checked yet.', 'multi-post-adder'); ?></em>
                    <?php endif; ?>
                    <?php if (is_array($cached) && !empty($cached['checked_at'])) : ?>
                        <p class="description">
                            <?php
                            /* translators: %s: human readable time difference, e.g. "5 mins" */
                            printf(esc_html__('Last checked %s ago.', 'multi-post-adder'), esc_html(human_time_diff($cached['checked_at'])));
                            ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php if (MPA_GitHub_Updater::is_enabled()) : ?>
        <p><a class="button" href="<?php echo esc_url($check_url); ?>"><?php _e('Check for updates now', 'multi-post-adder'); ?></a></p>
        <?php endif; ?>

        <hr class="mpa-presets-divider">

        <h2><?php _e('GitHub source', 'multi-post-adder'); ?></h2>
        <form method="post">
            <?php wp_nonce_field('mpa_save_updates', 'mpa_updates_nonce'); ?>
            <input type="hidden" name="mpa-settings-action" value="save_updates">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php _e('Update checks', 'multi-post-adder'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="mpa-update-enabled" value="1" <?php checked(!empty($settings['enabled'])); ?>>
                            <?php _e('Check GitHub for new versions', 'multi-post-adder'); ?>
                        </label>
                        <p class="description"><?php _e('Turn this off on sites without internet access (e.g. an intranet). The plugin works fully without it.', 'multi-post-adder'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mpa-update-repo"><?php _e('Repository', 'multi-post-adder'); ?></label></th>
                    <td>
                        <input type="text" id="mpa-update-repo" name="mpa-update-repo" class="regular-text" value="<?php echo esc_attr($settings['repo']); ?>" placeholder="owner/repository">
                        <p class="description"><?php _e('Format: owner/repository', 'multi-post-adder'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Update source', 'multi-post-adder'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="mpa-update-source" value="branch" <?php checked($settings['source'], 'branch'); ?>>
                                <?php _e('Branch: offer an update whenever the Version in the plugin file on the branch is higher', 'multi-post-adder'); ?>
                            </label><br>
                            <label>
                                <input type="radio" name="mpa-update-source" value="release" <?php checked($settings['source'], 'release'); ?>>
                                <?php _e('Releases: offer an update only when a new GitHub Release is published', 'multi-post-adder'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mpa-update-branch"><?php _e('Branch', 'multi-post-adder'); ?></label></th>
                    <td><input type="text" id="mpa-update-branch" name="mpa-update-branch" class="regular-text" value="<?php echo esc_attr($settings['branch']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="mpa-update-token"><?php _e('Access token', 'multi-post-adder'); ?></label></th>
                    <td>
                        <input type="password" id="mpa-update-token" name="mpa-update-token" class="regular-text" value="" autocomplete="new-password"
                               placeholder="<?php echo $settings['token'] ? esc_attr__('Saved — leave empty to keep', 'multi-post-adder') : ''; ?>">
                        <?php if ($settings['token']) : ?>
                            <br><label><input type="checkbox" name="mpa-update-token-remove" value="1"> <?php _e('Remove saved token', 'multi-post-adder'); ?></label>
                        <?php endif; ?>
                        <p class="description"><?php _e('Only needed if the repository is private. Use a fine-grained token with read-only “Contents” access to this repository.', 'multi-post-adder'); ?></p>
                    </td>
                </tr>
            </table>
            <button type="submit" class="button button-primary"><?php _e('Save Changes', 'multi-post-adder'); ?></button>
        </form>
    </div>
    <?php
}

function mpa_admin_page() {
    $presets = mpa_get_presets();
    $automation = mpa_get_automation_settings();
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php _e('Add Multiple Posts', 'multi-post-adder'); ?></h1>
        <button type="button" class="page-title-action mpa-import-setup">
            <span class="dashicons dashicons-upload" aria-hidden="true"></span><?php _e('Import setup', 'multi-post-adder'); ?>
        </button>
        <button type="button" class="page-title-action mpa-export-setup">
            <span class="dashicons dashicons-download" aria-hidden="true"></span><?php _e('Export setup', 'multi-post-adder'); ?>
        </button>
        <input type="file" id="mpa-setup-file" accept=".json,application/json" hidden>
        <hr class="wp-header-end">
        <div id="mpa-setup-notice" class="notice is-dismissible" hidden><p></p></div>

        <form id="mpa-form" method="post">
            <section class="mpa-global-adjustments">
                <h2><?php _e('Global Adjustments', 'multi-post-adder'); ?></h2>

                <div class="mpa-global-fields">
                    <label>
                        <span><?php _e('Number of posts:', 'multi-post-adder'); ?></span>
                        <input type="number" id="mpa-count" min="1" value="1">
                    </label>

                    <div class="mpa-field mpa-cat-field">
                        <span id="mpa-cat-label"><?php _e('Categories:', 'multi-post-adder'); ?></span>
                        <?php mpa_category_picker(); ?>
                    </div>

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
            </section>

            <section class="mpa-automation mpa-global-meta-box" id="mpa-global-meta-box">
                <label class="mpa-automation-header" for="mpa-global-meta-enabled">
                    <span class="mpa-automation-icon dashicons dashicons-list-view" aria-hidden="true"></span>
                    <span class="mpa-automation-titles">
                        <span class="mpa-automation-title"><?php _e('Global Custom Fields', 'multi-post-adder'); ?></span>
                        <span class="mpa-automation-subtitle"><?php _e('Custom fields added to every post', 'multi-post-adder'); ?></span>
                    </span>
                    <span class="mpa-box-count" aria-hidden="true"></span>
                    <input type="checkbox" class="mpa-switch" id="mpa-global-meta-enabled">
                </label>

                <div class="mpa-automation-body mpa-global-meta-body" hidden>
                    <div class="mpa-custom-fields" id="mpa-global-meta"></div>
                    <button type="button" class="button mpa-add-global-meta">
                        <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                        <?php _e('Add Field', 'multi-post-adder'); ?>
                    </button>
                </div>
            </section>

            <section class="mpa-automation<?php echo $automation['enabled'] ? ' is-active' : ''; ?>" id="mpa-automation">
                <label class="mpa-automation-header" for="mpa-automation-enabled">
                    <span class="mpa-automation-icon dashicons dashicons-controls-repeat" aria-hidden="true"></span>
                    <span class="mpa-automation-titles">
                        <span class="mpa-automation-title"><?php _e('Automation', 'multi-post-adder'); ?></span>
                        <span class="mpa-automation-subtitle"><?php _e('Fill placeholders in each post with one click', 'multi-post-adder'); ?></span>
                    </span>
                    <input type="checkbox" class="mpa-switch" id="mpa-automation-enabled" <?php checked($automation['enabled']); ?>>
                </label>

                <div class="mpa-automation-body mpa-automation-stack"<?php echo $automation['enabled'] ? '' : ' hidden'; ?>>
                    <div class="mpa-automation-block">
                        <h3 class="mpa-automation-heading"><?php _e('Placeholders', 'multi-post-adder'); ?></h3>
                        <p class="mpa-automation-hint"><?php _e('Put them in the post content or a preset. Click one to copy it.', 'multi-post-adder'); ?></p>
                        <ul class="mpa-placeholders">
                            <li>
                                <span class="dashicons dashicons-translation" aria-hidden="true"></span>
                                <button type="button" class="mpa-token" data-copy="{translated_title}">{translated_title}</button>
                                <span class="mpa-token-desc"><?php _e('Post title translated to English', 'multi-post-adder'); ?></span>
                            </li>
                            <li>
                                <span class="dashicons dashicons-download" aria-hidden="true"></span>
                                <button type="button" class="mpa-token" data-copy="{download_link}">{download_link}</button>
                                <span class="mpa-token-desc"><?php _e('Replaced with the download pattern', 'multi-post-adder'); ?></span>
                            </li>
                            <li>
                                <span class="dashicons dashicons-media-default" aria-hidden="true"></span>
                                <button type="button" class="mpa-token" data-copy="{file_name}">{file_name}</button>
                                <span class="mpa-token-desc"><?php _e('Featured image file name, without extension', 'multi-post-adder'); ?></span>
                            </li>
                            <li>
                                <span class="dashicons dashicons-editor-paste-text" aria-hidden="true"></span>
                                <button type="button" class="mpa-token" data-copy="{custom_text}">{custom_text}</button>
                                <span class="mpa-token-desc"><?php _e('Replaced with the custom text', 'multi-post-adder'); ?></span>
                            </li>
                        </ul>
                    </div>

                    <div class="mpa-automation-block">
                        <div class="mpa-automation-field">
                            <h3 class="mpa-automation-heading"><label for="mpa-download-pattern"><?php _e('Download link pattern', 'multi-post-adder'); ?></label></h3>
                            <p class="mpa-automation-hint"><?php _e('Any text. {file_name} is filled in for each post.', 'multi-post-adder'); ?></p>
                            <div class="mpa-input-group">
                                <input type="text" id="mpa-download-pattern" value="<?php echo esc_attr($automation['download_pattern']); ?>" placeholder="https://dl.example.com/{file_name}.zip" dir="ltr" spellcheck="false">
                                <button type="button" class="mpa-insert-token" data-token="{file_name}" title="<?php esc_attr_e('Insert at cursor', 'multi-post-adder'); ?>">+ {file_name}</button>
                            </div>
                        </div>

                        <div class="mpa-automation-field">
                            <h3 class="mpa-automation-heading"><label for="mpa-custom-text"><?php _e('Custom text', 'multi-post-adder'); ?></label></h3>
                            <p class="mpa-automation-hint"><?php _e('Any text or HTML. Inserted exactly as typed wherever {custom_text} appears.', 'multi-post-adder'); ?></p>
                            <textarea id="mpa-custom-text" rows="3" spellcheck="false"><?php echo esc_textarea($automation['custom_text']); ?></textarea>
                        </div>
                    </div>

                    <div class="mpa-automation-actions">
                        <button type="button" class="button mpa-run-all-posts">
                            <span class="dashicons dashicons-update" aria-hidden="true"></span>
                            <?php _e('Run on all posts', 'multi-post-adder'); ?>
                        </button>
                    </div>
                </div>
            </section>

            <div id="mpa-posts-container"></div>

            <?php
            $schedule = mpa_get_schedule_settings();
            $now = current_datetime();
            $default_start = $now->setTime((int) $now->format('H'), 0)->modify('+1 hour');
            ?>
            <section class="mpa-automation mpa-scheduling<?php echo $schedule['enabled'] ? ' is-active' : ''; ?>" id="mpa-scheduling">
                <label class="mpa-automation-header" for="mpa-schedule-enabled">
                    <span class="mpa-automation-icon dashicons dashicons-clock" aria-hidden="true"></span>
                    <span class="mpa-automation-titles">
                        <span class="mpa-automation-title"><?php _e('Scheduling', 'multi-post-adder'); ?></span>
                        <span class="mpa-automation-subtitle"><?php _e('Publish the posts later instead of right away', 'multi-post-adder'); ?></span>
                    </span>
                    <input type="checkbox" class="mpa-switch" id="mpa-schedule-enabled" name="mpa-schedule[enabled]" value="1" <?php checked($schedule['enabled']); ?>>
                </label>

                <div class="mpa-automation-body mpa-schedule-body"<?php echo $schedule['enabled'] ? '' : ' hidden'; ?>>
                    <div class="mpa-schedule-fields">
                        <div class="mpa-schedule-field">
                            <span class="mpa-automation-heading"><?php _e('Mode', 'multi-post-adder'); ?></span>
                            <div class="mpa-segmented" role="radiogroup">
                                <label>
                                    <input type="radio" name="mpa-schedule[mode]" value="all" <?php checked($schedule['mode'], 'all'); ?>>
                                    <span><span class="dashicons dashicons-images-alt2" aria-hidden="true"></span><?php _e('All at once', 'multi-post-adder'); ?></span>
                                </label>
                                <label>
                                    <input type="radio" name="mpa-schedule[mode]" value="interval" <?php checked($schedule['mode'], 'interval'); ?>>
                                    <span><span class="dashicons dashicons-backup" aria-hidden="true"></span><?php _e('One by one', 'multi-post-adder'); ?></span>
                                </label>
                            </div>
                        </div>

                        <div class="mpa-schedule-field">
                            <label class="mpa-automation-heading" for="mpa-schedule-start">
                                <span class="mpa-when-all"><?php _e('Publish at', 'multi-post-adder'); ?></span>
                                <span class="mpa-when-interval"><?php _e('First post at', 'multi-post-adder'); ?></span>
                            </label>
                            <input type="datetime-local" id="mpa-schedule-start" name="mpa-schedule[start]"
                                   value="<?php echo esc_attr($default_start->format('Y-m-d\TH:i')); ?>"
                                   data-now="<?php echo esc_attr($now->format('Y-m-d\TH:i')); ?>">
                        </div>

                        <div class="mpa-schedule-field mpa-when-interval">
                            <label class="mpa-automation-heading" for="mpa-schedule-interval"><?php _e('Then one post every', 'multi-post-adder'); ?></label>
                            <select id="mpa-schedule-interval" name="mpa-schedule[interval]">
                                <?php foreach (mpa_schedule_intervals() as $minutes => $label) : ?>
                                    <option value="<?php echo esc_attr($minutes); ?>" <?php selected((int) $schedule['interval'], $minutes); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mpa-schedule-summary">
                        <p class="mpa-schedule-preview" aria-live="polite"></p>
                        <p class="mpa-automation-hint">
                            <?php
                            /* translators: %s: timezone name, e.g. Europe/Amsterdam */
                            printf(esc_html__('Times are in the site timezone (%s). Save as Draft ignores scheduling.', 'multi-post-adder'), esc_html(wp_timezone_string()));
                            ?>
                        </p>
                    </div>
                </div>
            </section>

            <div class="mpa-submit-row">
            <button type="submit" name="mpa-submit" id="mpa-submit" class="button button-primary"><?php echo $schedule['enabled'] ? esc_html__('Schedule Posts', 'multi-post-adder') : esc_html__('Publish Posts', 'multi-post-adder'); ?></button>
            <button type="submit" name="mpa-submit-draft" class="button"><?php _e('Save as Draft', 'multi-post-adder'); ?></button>
            </div>
        </form>
    </div>
    <?php
}

// Save GitHub update settings.
add_action('admin_init', function () {
    if (empty($_POST['mpa-settings-action']) || $_POST['mpa-settings-action'] !== 'save_updates') return;
    if (!current_user_can('update_plugins') || !current_user_can('manage_options')) return;
    if (empty($_POST['mpa_updates_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mpa_updates_nonce'])), 'mpa_save_updates')) return;

    $current = MPA_GitHub_Updater::get_settings();

    $repo = isset($_POST['mpa-update-repo']) ? trim(sanitize_text_field(wp_unslash($_POST['mpa-update-repo']))) : '';
    $repo = preg_replace('#^https?://github\.com/#i', '', $repo);
    $repo = preg_replace('#\.git$#i', '', trim($repo, '/ '));
    if (!preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo)) $repo = MPA_GitHub_Updater::DEFAULT_REPO;

    $branch = isset($_POST['mpa-update-branch']) ? trim(sanitize_text_field(wp_unslash($_POST['mpa-update-branch']))) : '';
    $source = (isset($_POST['mpa-update-source']) && $_POST['mpa-update-source'] === 'release') ? 'release' : 'branch';

    $token = $current['token'];
    if (!empty($_POST['mpa-update-token-remove'])) $token = '';
    if (!empty($_POST['mpa-update-token'])) $token = trim(sanitize_text_field(wp_unslash($_POST['mpa-update-token'])));

    MPA_GitHub_Updater::save_settings([
        'repo'   => $repo,
        'branch' => $branch ?: MPA_GitHub_Updater::DEFAULT_BRANCH,
        'source' => $source,
        'token'  => $token,
        'enabled' => !empty($_POST['mpa-update-enabled']),
    ]);

    wp_redirect(admin_url('options-general.php?page=mpa-settings&tab=updates&mpa-message=updates-saved'));
    exit;
});

// "Check for updates now" (settings tab and Plugins screen link).
add_action('admin_post_mpa_check_updates', function () {
    if (!current_user_can('update_plugins')) wp_die(esc_html__('Sorry, you are not allowed to update plugins.', 'multi-post-adder'));
    check_admin_referer('mpa_check_updates');

    MPA_GitHub_Updater::clear_cache();
    wp_update_plugins();

    if (isset($_GET['return']) && $_GET['return'] === 'settings') {
        wp_redirect(admin_url('options-general.php?page=mpa-settings&tab=updates&mpa-message=updates-checked'));
    } else {
        wp_redirect(self_admin_url('plugins.php'));
    }
    exit;
});

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

    $category_ids = isset($_POST['mpa-category']) ? array_filter(array_map('intval', (array) $_POST['mpa-category']), function ($id) {
        return $id > 0;
    }) : [];
    $hashtags = sanitize_text_field($_POST['mpa-hashtags']);
    $global_meta_data = !empty($_POST['mpa-global-meta']) ? $_POST['mpa-global-meta'] : [];
    $posts_data = $_POST['mpa-posts'];
    $status = isset($_POST['mpa-submit']) ? 'publish' : 'draft';

    // Scheduling applies to publishing only; drafts are saved as usual.
    $schedule_times = ($status === 'publish' && !empty($_POST['mpa-schedule']) && is_array($_POST['mpa-schedule']))
        ? mpa_schedule_times($_POST['mpa-schedule'], count($posts_data))
        : null;
    $now_ts = time();
    $post_number = 0;

    foreach ($posts_data as $post_data) {
        $title = sanitize_text_field($post_data['title']);
        $content = wp_kses_post($post_data['content']);
        $image_id = intval($post_data['image']);

        $postarr = [
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => $status,
            'post_category' => array_values($category_ids),
        ];

        if ($schedule_times && isset($schedule_times[$post_number])) {
            $when = $schedule_times[$post_number];
            // A time that has already passed is published right away (no backdating).
            if ($when->getTimestamp() > $now_ts) {
                $postarr['post_status'] = 'future';
                $postarr['post_date'] = $when->format('Y-m-d H:i:s');
                $postarr['post_date_gmt'] = $when->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                $postarr['edit_date'] = true;
            }
        }
        $post_number++;

        $post_id = wp_insert_post($postarr);
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
