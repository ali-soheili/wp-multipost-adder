<?php
/**
 * AI-assisted posts for Multi Post Adder.
 *
 * - Settings tab "AI": provider (Claude or Gemini), API keys, models, language,
 *   lengths and a default prompt.
 * - AJAX endpoint that sends one image (plus the author's notes) to the chosen
 *   provider and returns a post title, image ALT text and a short description.
 *
 * API keys stay on the server; the browser only ever talks to admin-ajax.php.
 */
if (!defined('ABSPATH')) exit;

class MPA_AI {

    const OPTION = 'mpa_ai_settings';

    const DEFAULT_CLAUDE_MODEL = 'claude-haiku-5-5';
    const DEFAULT_GEMINI_MODEL = 'gemini-flash-latest';

    public static function init() {
        add_action('admin_init', [__CLASS__, 'handle_save']);
        add_action('wp_ajax_mpa_ai_models', [__CLASS__, 'ajax_models']);
        add_action('wp_ajax_mpa_ai_generate', [__CLASS__, 'ajax_generate']);
    }

    /* ---------------------------------------------------------------------
     * Settings
     * ------------------------------------------------------------------- */

    public static function languages() {
        return [
            'fa' => __('Persian', 'multi-post-adder'),
            'en' => __('English', 'multi-post-adder'),
            'ar' => __('Arabic', 'multi-post-adder'),
            'tr' => __('Turkish', 'multi-post-adder'),
            'de' => __('German', 'multi-post-adder'),
            'fr' => __('French', 'multi-post-adder'),
            'es' => __('Spanish', 'multi-post-adder'),
            'it' => __('Italian', 'multi-post-adder'),
            'nl' => __('Dutch', 'multi-post-adder'),
            'pt' => __('Portuguese', 'multi-post-adder'),
            'ru' => __('Russian', 'multi-post-adder'),
            'zh' => __('Chinese', 'multi-post-adder'),
            'ja' => __('Japanese', 'multi-post-adder'),
            'hi' => __('Hindi', 'multi-post-adder'),
            'ur' => __('Urdu', 'multi-post-adder'),
        ];
    }

    /** Language names the model is told to write in (always in English). */
    private static function language_name_for_prompt($code) {
        $names = [
            'fa' => 'Persian (Farsi)', 'en' => 'English', 'ar' => 'Arabic', 'tr' => 'Turkish',
            'de' => 'German', 'fr' => 'French', 'es' => 'Spanish', 'it' => 'Italian', 'nl' => 'Dutch',
            'pt' => 'Portuguese', 'ru' => 'Russian', 'zh' => 'Simplified Chinese', 'ja' => 'Japanese',
            'hi' => 'Hindi', 'ur' => 'Urdu',
        ];
        return $names[$code] ?? 'Persian (Farsi)';
    }

    public static function get_settings() {
        $saved = get_option(self::OPTION, []);
        $s = wp_parse_args(is_array($saved) ? $saved : [], [
            'provider' => 'claude',
            'claude_key' => '',
            'gemini_key' => '',
            'claude_model' => self::DEFAULT_CLAUDE_MODEL,
            'gemini_model' => self::DEFAULT_GEMINI_MODEL,
            'language' => 'fa',
            'title_length' => 60,
            'description_sentences' => 2,
            'default_prompt' => '',
        ]);
        $s['provider'] = $s['provider'] === 'gemini' ? 'gemini' : 'claude';
        $s['title_length'] = max(30, min(120, (int) $s['title_length']));
        $s['description_sentences'] = max(1, min(4, (int) $s['description_sentences']));
        if (!array_key_exists($s['language'], self::languages())) $s['language'] = 'fa';
        if ($s['claude_model'] === '') $s['claude_model'] = self::DEFAULT_CLAUDE_MODEL;
        if ($s['gemini_model'] === '') $s['gemini_model'] = self::DEFAULT_GEMINI_MODEL;
        return $s;
    }

    /** What the browser may know: never the keys themselves. */
    public static function public_settings() {
        $s = self::get_settings();
        $key = $s[$s['provider'] . '_key'];
        return [
            'provider' => $s['provider'],
            'providerLabel' => $s['provider'] === 'gemini' ? 'Gemini' : 'Claude',
            'model' => $s[$s['provider'] . '_model'],
            'hasKey' => $key !== '',
            'language' => self::languages()[$s['language']],
            'defaultPrompt' => $s['default_prompt'],
            'settingsUrl' => admin_url('options-general.php?page=mpa-settings&tab=ai'),
        ];
    }

    private static function key_hint($key) {
        return $key === '' ? '' : '…' . substr($key, -4);
    }

    public static function handle_save() {
        if (empty($_POST['mpa-settings-action']) || $_POST['mpa-settings-action'] !== 'save_ai') return;
        if (!current_user_can('manage_options')) return;
        if (empty($_POST['mpa_ai_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mpa_ai_nonce'])), 'mpa_save_ai')) return;

        $current = self::get_settings();
        $text = function ($name) {
            return isset($_POST[$name]) ? trim(sanitize_text_field(wp_unslash($_POST[$name]))) : '';
        };

        $new = $current;
        $new['provider'] = $text('mpa-ai-provider') === 'gemini' ? 'gemini' : 'claude';

        foreach (['claude', 'gemini'] as $p) {
            if (!empty($_POST["mpa-ai-{$p}-key-remove"])) $new["{$p}_key"] = '';
            $typed = $text("mpa-ai-{$p}-key");
            if ($typed !== '') $new["{$p}_key"] = $typed;
            $model = preg_replace('/[^A-Za-z0-9._\-\/:]/', '', $text("mpa-ai-{$p}-model"));
            $new["{$p}_model"] = $model;
        }

        $new['language'] = $text('mpa-ai-language');
        $new['title_length'] = (int) $text('mpa-ai-title-length');
        $new['description_sentences'] = (int) $text('mpa-ai-description-sentences');
        $new['default_prompt'] = isset($_POST['mpa-ai-default-prompt']) ? sanitize_textarea_field(wp_unslash($_POST['mpa-ai-default-prompt'])) : '';

        update_option(self::OPTION, $new, false);
        wp_redirect(admin_url('options-general.php?page=mpa-settings&tab=ai&mpa-message=ai-saved'));
        exit;
    }

    public static function render_tab() {
        $s = self::get_settings();
        ?>
        <div class="mpa-settings-panel mpa-ai-settings">
            <form method="post">
                <?php wp_nonce_field('mpa_save_ai', 'mpa_ai_nonce'); ?>
                <input type="hidden" name="mpa-settings-action" value="save_ai">

                <h2><?php _e('Provider', 'multi-post-adder'); ?></h2>
                <p class="description"><?php _e('Used by Posts → Add Posts with AI to write a post title, image ALT text and a short description for each image.', 'multi-post-adder'); ?></p>
                <div class="mpa-provider-cards">
                    <?php foreach (['claude' => 'Claude', 'gemini' => 'Gemini'] as $value => $label) : ?>
                        <label class="mpa-provider-card">
                            <input type="radio" name="mpa-ai-provider" value="<?php echo esc_attr($value); ?>" <?php checked($s['provider'], $value); ?>>
                            <span class="mpa-provider-card-body">
                                <strong><?php echo esc_html($label); ?></strong>
                                <span class="mpa-provider-card-status <?php echo $s[$value . '_key'] ? 'is-ok' : ''; ?>">
                                    <?php echo $s[$value . '_key'] ? esc_html__('Key saved', 'multi-post-adder') : esc_html__('No key yet', 'multi-post-adder'); ?>
                                </span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <h2><?php _e('API keys and models', 'multi-post-adder'); ?></h2>
                <table class="form-table" role="presentation">
                    <?php
                    $providers = [
                        'claude' => ['Claude', 'https://console.anthropic.com/settings/keys', __('Create a key in the Claude Console.', 'multi-post-adder')],
                        'gemini' => ['Gemini', 'https://aistudio.google.com/apikey', __('Create a key in Google AI Studio.', 'multi-post-adder')],
                    ];
                    foreach ($providers as $p => [$label, $url, $help]) :
                        $key = $s[$p . '_key'];
                        ?>
                        <tr class="mpa-ai-provider-row" data-provider="<?php echo esc_attr($p); ?>">
                            <th scope="row"><label for="mpa-ai-<?php echo esc_attr($p); ?>-key"><?php echo esc_html(sprintf(__('%s API key', 'multi-post-adder'), $label)); ?></label></th>
                            <td>
                                <input type="password" id="mpa-ai-<?php echo esc_attr($p); ?>-key" name="mpa-ai-<?php echo esc_attr($p); ?>-key" class="regular-text mpa-ai-key" value="" autocomplete="new-password" spellcheck="false"
                                       placeholder="<?php echo $key ? esc_attr(sprintf(__('Saved (%s) — leave empty to keep', 'multi-post-adder'), self::key_hint($key))) : ''; ?>">
                                <button type="button" class="button mpa-ai-check" data-provider="<?php echo esc_attr($p); ?>"><?php _e('Check key & load models', 'multi-post-adder'); ?></button>
                                <span class="mpa-ai-check-status" aria-live="polite"></span>
                                <?php if ($key) : ?>
                                    <br><label><input type="checkbox" name="mpa-ai-<?php echo esc_attr($p); ?>-key-remove" value="1"> <?php _e('Remove saved key', 'multi-post-adder'); ?></label>
                                <?php endif; ?>
                                <p class="description"><?php echo esc_html($help); ?> <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php echo esc_html(wp_parse_url($url, PHP_URL_HOST)); ?></a></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mpa-ai-<?php echo esc_attr($p); ?>-model"><?php echo esc_html(sprintf(__('%s model', 'multi-post-adder'), $label)); ?></label></th>
                            <td>
                                <input type="text" id="mpa-ai-<?php echo esc_attr($p); ?>-model" name="mpa-ai-<?php echo esc_attr($p); ?>-model" class="regular-text code" list="mpa-ai-<?php echo esc_attr($p); ?>-models" value="<?php echo esc_attr($s[$p . '_model']); ?>" spellcheck="false">
                                <datalist id="mpa-ai-<?php echo esc_attr($p); ?>-models"></datalist>
                                <p class="description"><?php _e('The model must accept images. “Check key & load models” lists the models available to your key.', 'multi-post-adder'); ?></p>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <h2><?php _e('Writing', 'multi-post-adder'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="mpa-ai-language"><?php _e('Language', 'multi-post-adder'); ?></label></th>
                        <td>
                            <select id="mpa-ai-language" name="mpa-ai-language">
                                <?php foreach (self::languages() as $code => $name) : ?>
                                    <option value="<?php echo esc_attr($code); ?>" <?php selected($s['language'], $code); ?>><?php echo esc_html($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Language of the post title, the image ALT text and the description.', 'multi-post-adder'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mpa-ai-title-length"><?php _e('Post title length', 'multi-post-adder'); ?></label></th>
                        <td>
                            <input type="number" id="mpa-ai-title-length" name="mpa-ai-title-length" class="small-text" min="30" max="120" value="<?php echo esc_attr($s['title_length']); ?>">
                            <?php _e('characters at most', 'multi-post-adder'); ?>
                            <p class="description"><?php _e('About 50–60 characters shows in full in search results.', 'multi-post-adder'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mpa-ai-description-sentences"><?php _e('Description length', 'multi-post-adder'); ?></label></th>
                        <td>
                            <select id="mpa-ai-description-sentences" name="mpa-ai-description-sentences">
                                <?php for ($n = 1; $n <= 4; $n++) : ?>
                                    <option value="<?php echo $n; ?>" <?php selected($s['description_sentences'], $n); ?>><?php echo esc_html(sprintf(_n('%d sentence', '%d sentences', $n, 'multi-post-adder'), $n)); ?></option>
                                <?php endfor; ?>
                            </select>
                            <p class="description"><?php _e('Image ALT text is always kept under 125 characters.', 'multi-post-adder'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mpa-ai-default-prompt"><?php _e('Default prompt', 'multi-post-adder'); ?></label></th>
                        <td>
                            <textarea id="mpa-ai-default-prompt" name="mpa-ai-default-prompt" class="large-text" rows="4" placeholder="<?php esc_attr_e('e.g. These are screenshots of Windows software. Mention the app name if it is visible.', 'multi-post-adder'); ?>"><?php echo esc_textarea($s['default_prompt']); ?></textarea>
                            <p class="description"><?php _e('Pre-filled in the prompt step; you can change it each time.', 'multi-post-adder'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Changes', 'multi-post-adder')); ?>
            </form>
        </div>
        <?php
    }

    /* ---------------------------------------------------------------------
     * Wizard on Posts → Add Posts with AI
     * ------------------------------------------------------------------- */

    public static function render_wizard() {
        $s = self::public_settings();
        ?>
        <div class="mpa-wizard" id="mpa-wizard">
            <ol class="mpa-steps" aria-label="<?php esc_attr_e('Steps', 'multi-post-adder'); ?>">
                <li class="mpa-step is-current" data-step="1"><span class="mpa-step-num">1</span><span class="mpa-step-label"><?php _e('Images', 'multi-post-adder'); ?></span></li>
                <li class="mpa-step" data-step="2"><span class="mpa-step-num">2</span><span class="mpa-step-label"><?php _e('Setup file', 'multi-post-adder'); ?></span></li>
                <li class="mpa-step" data-step="3"><span class="mpa-step-num">3</span><span class="mpa-step-label"><?php _e('Prompt', 'multi-post-adder'); ?></span></li>
                <li class="mpa-step" data-step="4"><span class="mpa-step-num">4</span><span class="mpa-step-label"><?php _e('Generate', 'multi-post-adder'); ?></span></li>
            </ol>

            <div class="mpa-wizard-card">
                <section class="mpa-wizard-step" data-step="1">
                    <h2><?php _e('Choose the images', 'multi-post-adder'); ?></h2>
                    <p class="description"><?php _e('One post is created for each image, in this order. Drag to reorder.', 'multi-post-adder'); ?></p>
                    <ul class="mpa-wz-images" id="mpa-wz-images"></ul>
                    <div class="mpa-wz-images-actions">
                        <button type="button" class="button mpa-wz-add-images">
                            <span class="dashicons dashicons-format-gallery" aria-hidden="true"></span><?php _e('Select or upload images', 'multi-post-adder'); ?>
                        </button>
                        <span class="mpa-wz-count"></span>
                    </div>
                </section>

                <section class="mpa-wizard-step" data-step="2" hidden>
                    <h2><?php _e('Load a setup file', 'multi-post-adder'); ?></h2>
                    <p class="description"><?php _e('The JSON file made with “Export setup” on Add Multiple Posts. It sets categories, hashtags, content preset, custom fields and automation.', 'multi-post-adder'); ?></p>
                    <div class="mpa-wz-drop" tabindex="0" role="button">
                        <span class="dashicons dashicons-media-code" aria-hidden="true"></span>
                        <span class="mpa-wz-drop-text"><?php _e('Drop the .json file here, or click to choose it', 'multi-post-adder'); ?></span>
                    </div>
                    <input type="file" id="mpa-wz-file" accept=".json,application/json" hidden>
                    <p class="mpa-wz-file-error" hidden></p>
                    <div class="mpa-wz-summary" hidden>
                        <div class="mpa-wz-summary-head">
                            <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                            <strong class="mpa-wz-summary-name"></strong>
                            <button type="button" class="button-link mpa-wz-change-file"><?php _e('Change', 'multi-post-adder'); ?></button>
                        </div>
                        <dl class="mpa-wz-summary-list"></dl>
                    </div>
                </section>

                <section class="mpa-wizard-step" data-step="3" hidden>
                    <h2><?php _e('Tell the AI about these images', 'multi-post-adder'); ?></h2>
                    <p class="description"><?php _e('What are they, what should it focus on, any names or terms to use? The better the notes, the better the titles.', 'multi-post-adder'); ?></p>
                    <textarea id="mpa-wz-prompt" rows="5" class="large-text"><?php echo esc_textarea($s['defaultPrompt']); ?></textarea>
                    <?php if ($s['hasKey']) : ?>
                        <p class="mpa-wz-provider">
                            <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
                            <?php
                            /* translators: 1: provider, 2: model, 3: language */
                            printf(esc_html__('Writing with %1$s (%2$s) in %3$s.', 'multi-post-adder'), esc_html($s['providerLabel']), '<code>' . esc_html($s['model']) . '</code>', esc_html($s['language']));
                            ?>
                            <?php if (current_user_can('manage_options')) : ?>
                                <a href="<?php echo esc_url($s['settingsUrl']); ?>"><?php _e('Change', 'multi-post-adder'); ?></a>
                            <?php endif; ?>
                        </p>
                    <?php else : ?>
                        <div class="notice notice-warning inline"><p>
                            <?php
                            /* translators: %s: provider name */
                            printf(esc_html__('No %s API key saved yet.', 'multi-post-adder'), esc_html($s['providerLabel']));
                            ?>
                            <?php if (current_user_can('manage_options')) : ?>
                                <a href="<?php echo esc_url($s['settingsUrl']); ?>"><?php _e('Add it in the AI settings', 'multi-post-adder'); ?></a>
                            <?php else : ?>
                                <?php _e('Ask an administrator to add one.', 'multi-post-adder'); ?>
                            <?php endif; ?>
                        </p></div>
                    <?php endif; ?>
                </section>

                <section class="mpa-wizard-step" data-step="4" hidden>
                    <h2 class="mpa-wz-gen-title"><?php _e('Writing titles and descriptions…', 'multi-post-adder'); ?></h2>
                    <div class="mpa-wz-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100"><span class="mpa-wz-progress-bar"></span></div>
                    <p class="mpa-wz-progress-text"></p>
                    <ul class="mpa-wz-results"></ul>
                </section>

                <footer class="mpa-wizard-footer">
                    <button type="button" class="button mpa-wz-back" hidden><?php _e('Back', 'multi-post-adder'); ?></button>
                    <span class="mpa-wz-hint" aria-live="polite"></span>
                    <button type="button" class="button mpa-wz-retry" hidden><?php _e('Retry failed', 'multi-post-adder'); ?></button>
                    <button type="button" class="button button-primary mpa-wz-next"><?php _e('Next', 'multi-post-adder'); ?></button>
                </footer>
            </div>
        </div>
        <?php
    }

    /* ---------------------------------------------------------------------
     * HTTP helpers
     * ------------------------------------------------------------------- */

    private static function request($provider, $method, $url, $key, $body = null, $timeout = 60) {
        $headers = ['content-type' => 'application/json'];
        if ($provider === 'claude') {
            $headers['x-api-key'] = $key;
            $headers['anthropic-version'] = '2023-06-01';
        } else {
            $headers['x-goog-api-key'] = $key;
        }

        $args = ['method' => $method, 'timeout' => $timeout, 'headers' => $headers];
        if ($body !== null) $args['body'] = wp_json_encode($body);

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            return new WP_Error('mpa_ai_offline', sprintf(
                /* translators: %s: provider name */
                __('%s could not be reached. AI writing needs internet access.', 'multi-post-adder'),
                $provider === 'gemini' ? 'Gemini' : 'Claude'
            ));
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 200 && $code < 300) return is_array($data) ? $data : [];

        $detail = is_array($data) && isset($data['error']['message']) ? $data['error']['message'] : '';
        if ($code === 401 || $code === 403 || ($code === 400 && stripos($detail, 'api key') !== false)) {
            $message = __('The API key was rejected. Check it in Settings → MPA Settings → AI.', 'multi-post-adder');
        } elseif ($code === 404) {
            $message = __('Model not found. Pick another model in Settings → MPA Settings → AI.', 'multi-post-adder');
        } elseif ($code === 429) {
            $message = __('Too many requests or quota used up. Wait a moment and retry.', 'multi-post-adder');
        } elseif ($code >= 500) {
            $message = __('The AI service is busy or having problems. Retry in a moment.', 'multi-post-adder');
        } else {
            /* translators: %d: HTTP status code */
            $message = sprintf(__('The AI service returned HTTP %d.', 'multi-post-adder'), $code);
        }
        if ($detail !== '') $message .= ' (' . wp_strip_all_tags($detail) . ')';

        return new WP_Error('mpa_ai_http', $message);
    }

    /* ---------------------------------------------------------------------
     * AJAX: check key + list models
     * ------------------------------------------------------------------- */

    public static function ajax_models() {
        check_ajax_referer('mpa_ai_settings', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('Not allowed.', 'multi-post-adder')], 403);

        $provider = (isset($_POST['provider']) && $_POST['provider'] === 'gemini') ? 'gemini' : 'claude';
        $typed = isset($_POST['key']) ? trim(sanitize_text_field(wp_unslash($_POST['key']))) : '';
        $key = $typed !== '' ? $typed : self::get_settings()[$provider . '_key'];
        if ($key === '') wp_send_json_error(['message' => __('Enter an API key first.', 'multi-post-adder')]);

        $models = [];
        if ($provider === 'claude') {
            $data = self::request('claude', 'GET', 'https://api.anthropic.com/v1/models?limit=100', $key, null, 20);
            if (is_wp_error($data)) wp_send_json_error(['message' => $data->get_error_message()]);
            foreach (($data['data'] ?? []) as $m) {
                if (!empty($m['id'])) $models[] = $m['id'];
            }
        } else {
            $data = self::request('gemini', 'GET', 'https://generativelanguage.googleapis.com/v1beta/models?pageSize=200', $key, null, 20);
            if (is_wp_error($data)) wp_send_json_error(['message' => $data->get_error_message()]);
            foreach (($data['models'] ?? []) as $m) {
                $methods = $m['supportedGenerationMethods'] ?? [];
                $name = preg_replace('#^models/#', '', $m['name'] ?? '');
                if ($name !== '' && in_array('generateContent', $methods, true) && stripos($name, 'gemini') !== false
                    && !preg_match('/(embedding|tts|image-generation|live|audio)/i', $name)) {
                    $models[] = $name;
                }
            }
        }

        wp_send_json_success(['models' => $models]);
    }

    /* ---------------------------------------------------------------------
     * AJAX: write title / ALT / description for one image
     * ------------------------------------------------------------------- */

    public static function ajax_generate() {
        check_ajax_referer('mpa_automation', 'nonce');
        if (!current_user_can('edit_posts') || !current_user_can('upload_files')) {
            wp_send_json_error(['message' => __('Not allowed.', 'multi-post-adder')], 403);
        }
        if (function_exists('set_time_limit')) @set_time_limit(120);

        $attachment_id = isset($_POST['attachment_id']) ? (int) $_POST['attachment_id'] : 0;
        $notes = isset($_POST['prompt']) ? sanitize_textarea_field(wp_unslash($_POST['prompt'])) : '';

        if (!$attachment_id || get_post_type($attachment_id) !== 'attachment' || !wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(['message' => __('This file is not an image.', 'multi-post-adder')]);
        }

        $settings = self::get_settings();
        $provider = $settings['provider'];
        $key = $settings[$provider . '_key'];
        if ($key === '') {
            wp_send_json_error(['message' => __('No API key saved. Add one in Settings → MPA Settings → AI.', 'multi-post-adder')]);
        }

        $image = self::image_payload($attachment_id);
        if (is_wp_error($image)) wp_send_json_error(['message' => $image->get_error_message()]);

        $file_name = pathinfo((string) get_attached_file($attachment_id), PATHINFO_FILENAME);
        $file_name = preg_replace('/-(scaled|rotated|\d+x\d+)$/', '', $file_name);

        [$system, $user] = self::build_prompt($settings, $notes, $file_name);

        $result = $provider === 'gemini'
            ? self::call_gemini($key, $settings['gemini_model'], $system, $user, $image)
            : self::call_claude($key, $settings['claude_model'], $system, $user, $image);

        if (is_wp_error($result)) wp_send_json_error(['message' => $result->get_error_message()]);

        wp_send_json_success($result);
    }

    /** Prompt text. Public for testing. */
    public static function build_prompt($settings, $notes, $file_name) {
        $language = self::language_name_for_prompt($settings['language']);
        $max_title = (int) $settings['title_length'];
        $sentences = (int) $settings['description_sentences'];

        $system = "You write metadata for WordPress blog posts, based on one photo per post.\n"
            . "Reply with a single JSON object and nothing else, with exactly these keys:\n"
            . "- \"title\": a descriptive, SEO-friendly post title of at most {$max_title} characters. No quotes, emoji, hashtags or trailing period.\n"
            . "- \"alt\": alt text for the image that describes what is visible, at most 125 characters. Do not start with \"image of\" or \"photo of\".\n"
            . "- \"description\": " . ($sentences === 1 ? 'one short sentence' : "{$sentences} short sentences") . " introducing the post. Plain text, no markdown, no hashtags.\n"
            . "Write all three values in {$language}. Do not invent facts that are not visible in the photo or given in the author's notes.";

        $user = '';
        if (trim($notes) !== '') {
            $user .= "Notes from the author about these images:\n" . trim($notes) . "\n\n";
        }
        $user .= 'Image file name: ' . $file_name;

        return [$system, $user];
    }

    /**
     * The image as base64, scaled down to at most 1568px (enough for both
     * providers, keeps requests small and cheap).
     *
     * @return array{mime:string,data:string}|WP_Error
     */
    private static function image_payload($attachment_id) {
        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) {
            return new WP_Error('mpa_ai_file', __('The image file could not be found on the server.', 'multi-post-adder'));
        }

        $editor = wp_get_image_editor($file);
        if (!is_wp_error($editor)) {
            $size = $editor->get_size();
            if (!empty($size['width']) && ($size['width'] > 1568 || $size['height'] > 1568)) {
                $editor->resize(1568, 1568, false);
            }
            $editor->set_quality(85);
            $tmp = wp_tempnam('mpa-ai.jpg');
            $saved = $editor->save($tmp, 'image/jpeg');
            if (!is_wp_error($saved) && !empty($saved['path']) && file_exists($saved['path'])) {
                $data = file_get_contents($saved['path']);
                @unlink($saved['path']);
                if ($tmp !== $saved['path']) @unlink($tmp);
                if ($data) return ['mime' => 'image/jpeg', 'data' => base64_encode($data)];
            }
            @unlink($tmp);
        }

        // Fallback: send the original if it is a supported type and not too big.
        $mime = get_post_mime_type($attachment_id);
        if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true) && filesize($file) <= 4 * MB_IN_BYTES) {
            return ['mime' => $mime, 'data' => base64_encode(file_get_contents($file))];
        }

        return new WP_Error('mpa_ai_file', __('The image could not be prepared for the AI (unsupported format or too large).', 'multi-post-adder'));
    }

    /** Request body for Claude. Public for testing. */
    public static function claude_body($model, $system, $user, $image) {
        return [
            'model' => $model,
            'max_tokens' => 1024,
            'system' => $system,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $image['mime'], 'data' => $image['data']]],
                    ['type' => 'text', 'text' => $user],
                ],
            ]],
        ];
    }

    /** Request body for Gemini. Public for testing. */
    public static function gemini_body($system, $user, $image) {
        return [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [[
                'role' => 'user',
                'parts' => [
                    ['inline_data' => ['mime_type' => $image['mime'], 'data' => $image['data']]],
                    ['text' => $user],
                ],
            ]],
            'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.4],
        ];
    }

    private static function call_claude($key, $model, $system, $user, $image) {
        $data = self::request('claude', 'POST', 'https://api.anthropic.com/v1/messages', $key, self::claude_body($model, $system, $user, $image));
        if (is_wp_error($data)) return $data;

        $text = '';
        foreach (($data['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') $text .= $block['text'];
        }
        return self::parse_result($text);
    }

    private static function call_gemini($key, $model, $system, $user, $image) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
        $data = self::request('gemini', 'POST', $url, $key, self::gemini_body($system, $user, $image));
        if (is_wp_error($data)) return $data;

        $text = '';
        foreach (($data['candidates'][0]['content']['parts'] ?? []) as $part) {
            if (isset($part['text']) && empty($part['thought'])) $text .= $part['text'];
        }
        if ($text === '' && !empty($data['promptFeedback']['blockReason'])) {
            return new WP_Error('mpa_ai_blocked', __('The AI refused to describe this image.', 'multi-post-adder'));
        }
        return self::parse_result($text);
    }

    /** Pulls {title, alt, description} out of the model's reply. Public for testing. */
    public static function parse_result($text) {
        $text = trim((string) $text);
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        $json = ($start !== false && $end !== false && $end > $start) ? json_decode(substr($text, $start, $end - $start + 1), true) : null;

        if (!is_array($json)) {
            return new WP_Error('mpa_ai_parse', __('The AI reply could not be read. Retry this image.', 'multi-post-adder'));
        }

        $clean = function ($value) {
            $value = is_string($value) ? $value : '';
            $value = wp_strip_all_tags($value);
            $value = preg_replace('/\s+/u', ' ', $value);
            return trim($value, " \t\n\r\0\x0B\"'«»“”");
        };

        $alt = $clean($json['alt'] ?? '');
        if (mb_strlen($alt) > 125) {
            $cut = mb_substr($alt, 0, 125);
            $space = mb_strrpos($cut, ' ');
            $alt = rtrim($space > 60 ? mb_substr($cut, 0, $space) : $cut, ' ,.;:');
        }

        $result = [
            'title' => rtrim($clean($json['title'] ?? ''), '.'),
            'alt' => $alt,
            'description' => $clean($json['description'] ?? ''),
        ];

        if ($result['title'] === '' && $result['alt'] === '' && $result['description'] === '') {
            return new WP_Error('mpa_ai_parse', __('The AI reply was empty. Retry this image.', 'multi-post-adder'));
        }
        return $result;
    }
}

MPA_AI::init();
