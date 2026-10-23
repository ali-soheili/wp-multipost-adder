jQuery(document).ready(function ($) {
    const presets = Array.isArray(window.mpa_presets) ? window.mpa_presets : Object.values(window.mpa_presets || {});
    const labels = Object.assign({
        selectKey: '-- Select Key --',
        customKey: 'Or custom key',
        value: 'Value',
        title: 'Title:',
        content: 'Content:',
        featuredImage: 'Featured Image:',
        uploadImage: 'Upload Image',
        customFields: 'Custom Fields:',
        addCustomField: 'Add Custom Field',
        selectOrUploadImage: 'Select or Upload Image',
        useThisImage: 'Use this image',
        automation: 'Automation:',
        translateTitle: 'Translate title',
        downloadLink: 'Download link',
        fileName: 'File name',
        runAll: 'Run all',
        working: 'Working…',
        done: 'Updated.',
        enterTitle: 'Enter a title first.',
        selectImage: 'Select a featured image first.',
        enterPattern: 'Enter a download link pattern in the Automation box first.',
        notFound: '%s was not found in the content.',
        translateFailed: 'Translation failed.',
        selectedCount: '%d selected',
        remove: 'Remove',
        noPlaceholders: 'No placeholders found in this post.'
    }, window.mpa_i18n || {});
    const automation = window.mpa_automation || {};

    // Automation: values inserted per post, so clicking a button again can update them.
    // lastValues[postIndex][placeholderKey] = 'inserted text'
    let lastValues = {};

    function createCustomFieldHTML(fieldName) {
        let options = `<option value="">${labels.selectKey}</option>`;
        if (window.mpa_meta_keys) {
            mpa_meta_keys.forEach(key => {
                options += `<option value="${key}">${key}</option>`;
            });
        }
        return `
        <div class="mpa-meta-row">
            <select name="${fieldName}[key]">${options}</select>
            <input type="text" name="${fieldName}[custom_key]" placeholder="${labels.customKey}">
            <input type="text" name="${fieldName}[value]" placeholder="${labels.value}">
        </div>`;
    }

    function createPostBlock(i) {
        return `
        <div class="mpa-post-block">
            <h3 class="mpa-post-number">${i + 1}</h3>
            <div class="mpa-post-automation" data-index="${i}">
                <span class="mpa-post-automation-label">${labels.automation}</span>
                <button type="button" class="button mpa-auto-btn mpa-icon-btn" data-action="translate" data-index="${i}" title="${labels.translateTitle}" aria-label="${labels.translateTitle}">
                    <span class="dashicons dashicons-translation" aria-hidden="true"></span>
                </button>
                <button type="button" class="button mpa-auto-btn mpa-icon-btn" data-action="download" data-index="${i}" title="${labels.downloadLink}" aria-label="${labels.downloadLink}">
                    <span class="dashicons dashicons-download" aria-hidden="true"></span>
                </button>
                <button type="button" class="button mpa-auto-btn mpa-icon-btn" data-action="filename" data-index="${i}" title="${labels.fileName}" aria-label="${labels.fileName}">
                    <span class="dashicons dashicons-media-default" aria-hidden="true"></span>
                </button>
                <span class="mpa-auto-divider" aria-hidden="true"></span>
                <button type="button" class="button button-primary mpa-auto-btn mpa-icon-btn" data-action="all" data-index="${i}" title="${labels.runAll}" aria-label="${labels.runAll}">
                    <span class="dashicons dashicons-update" aria-hidden="true"></span>
                </button>
                <span class="mpa-auto-status" id="mpa-auto-status-${i}" aria-live="polite"></span>
            </div>
            <p class="mpa-field-row mpa-title-row">
                <label>${labels.title}</label>
                <input Style="width:stretch;" type="text" name="mpa-posts[${i}][title]">
            </p>
            <p class="mpa-field-row">
                <label>${labels.content}</label><br>
                <textarea id="editor-${i}" name="mpa-posts[${i}][content]"></textarea>
            </p>
            <p class="mpa-field-row">
                <label>${labels.featuredImage}
                    <button type="button" class="button mpa-upload" data-index="${i}">${labels.uploadImage}</button>
                    <input type="hidden" name="mpa-posts[${i}][image]" id="mpa-image-${i}">
                    <span class="mpa-image-preview" id="mpa-preview-${i}"></span>
                </label>
            </p>
            <p class="mpa-field-row">
                <label>${labels.customFields}
                    <button type="button" class="button mpa-add-meta" data-index="${i}">${labels.addCustomField}</button>
                    <div class="mpa-custom-fields" id="mpa-meta-${i}"></div>
                </label>
            </p>
        </div>`;
    }

    function initEditor(id) {
        if (typeof wp.editor !== 'undefined') {
            wp.editor.remove(id);
            wp.editor.initialize(id, {
                tinymce: { wpautop: true, plugins: 'lists,textcolor,wordpress' },
                quicktags: true,
                mediaButtons: true
            });
        }
    }

    function getSelectedPresetContent() {
        const presetId = $('#mpa-content-preset').val();
        const preset = presets.find(item => item.id === presetId);
        return preset ? preset.content : '';
    }

    function setEditorContent(id, content) {
        const textarea = $(`#${id}`);
        textarea.val(content);

        if (typeof tinyMCE !== 'undefined') {
            const editor = tinyMCE.get(id);
            if (editor) {
                editor.setContent(content);
                editor.save();
            }
        }
    }

    function applySelectedPreset() {
        const content = getSelectedPresetContent();
        if (!content) return;
        lastValues = {};

        $('#mpa-posts-container textarea').each(function () {
            setEditorContent(this.id, content);
        });
    }

    $('#mpa-count').on('change', function () {
        const count = parseInt($(this).val());
        const container = $('#mpa-posts-container');
        container.empty();
        lastValues = {};

        for (let i = 0; i < count; i++) {
            container.append(createPostBlock(i));
        }

        setTimeout(() => {
            for (let i = 0; i < count; i++) {
                initEditor(`editor-${i}`);
            }
            applySelectedPreset();
        }, 200); // Delay to allow DOM to render
    }).trigger('change');

    $('#mpa-content-preset').on('change', applySelectedPreset);

    $(document).on('click', '.mpa-upload', function (e) {
        e.preventDefault();
        const index = $(this).data('index');
        const imageInput = $(`#mpa-image-${index}`);
        const preview = $(`#mpa-preview-${index}`);

        const frame = wp.media({
            title: labels.selectOrUploadImage,
            button: { text: labels.useThisImage },
            multiple: false
        });

        frame.on('select', function () {
            const attachment = frame.state().get('selection').first().toJSON();
            imageInput.val(attachment.id);
            // Original file name (before WordPress adds "-scaled"), without extension.
            const file = attachment.originalImageName || attachment.filename || '';
            imageInput.attr('data-filename', file.replace(/\.[^.]+$/, ''));
            preview.html(`<img src="${attachment.url}" style="max-width:100px;">`);
        });

        frame.open();
    });

    $(document).on('click', '.mpa-add-meta', function () {
        const index = $(this).data('index');
        const container = $(`#mpa-meta-${index}`);
        const metaIndex = container.children().length;
        container.append(createCustomFieldHTML(`mpa-posts[${index}][meta][${metaIndex}]`));
    });

    $(document).on('click', '.mpa-add-global-meta', function () {
        const container = $('#mpa-global-meta');
        const metaIndex = container.children().length;
        container.append(createCustomFieldHTML(`mpa-global-meta[${metaIndex}]`));
    });

    /* ------------------------------------------------------------------
     * Automation
     * ---------------------------------------------------------------- */

    const $form = $('#mpa-form');
    const $automationBox = $('#mpa-automation');
    const $automationToggle = $('#mpa-automation-enabled');
    const $pattern = $('#mpa-download-pattern');

    function setAutomationActive(active) {
        $automationBox.toggleClass('is-active', active);
        $automationBox.find('.mpa-automation-body').prop('hidden', !active);
        $form.toggleClass('mpa-automation-on', active);
    }

    let saveTimer = null;
    function saveAutomationSettings() {
        if (!automation.ajaxUrl) return;
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function () {
            $.post(automation.ajaxUrl, {
                action: 'mpa_save_automation',
                nonce: automation.nonce,
                enabled: $automationToggle.is(':checked') ? 1 : 0,
                download_pattern: $pattern.val()
            });
        }, 400);
    }

    setAutomationActive($automationToggle.is(':checked'));
    $automationToggle.on('change', function () {
        setAutomationActive(this.checked);
        saveAutomationSettings();
    });
    $pattern.on('change input', saveAutomationSettings);

    // Insert {file_name} at the cursor position in the pattern field.
    $(document).on('click', '.mpa-insert-token', function () {
        const token = $(this).data('token');
        const input = $pattern.get(0);
        const start = input.selectionStart ?? input.value.length;
        const end = input.selectionEnd ?? input.value.length;
        input.value = input.value.slice(0, start) + token + input.value.slice(end);
        input.focus();
        input.setSelectionRange(start + token.length, start + token.length);
        saveAutomationSettings();
    });

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getEditorContent(id) {
        if (typeof tinyMCE !== 'undefined') {
            const editor = tinyMCE.get(id);
            if (editor && !editor.isHidden()) return editor.getContent();
        }
        return $(`#${id}`).val();
    }

    function escapeRegExp(text) {
        return String(text).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    // {key}, or %7Bkey%7D if the visual editor encoded the braces inside a link.
    function placeholderRegExp(key) {
        const k = escapeRegExp(key);
        return new RegExp(`\\{${k}\\}|%7B${k}%7D`, 'gi');
    }

    /**
     * Replace {key} in the post content with value. If the placeholder is
     * gone because it was filled before, replace the previously inserted value.
     * With asIs = true the value is inserted exactly as given (may contain HTML).
     */
    function fillPlaceholder(index, key, rawValue, asIs) {
        const editorId = `editor-${index}`;
        const value = asIs ? String(rawValue) : escapeHtml(rawValue);
        let content = getEditorContent(editorId) || '';
        const previous = (lastValues[index] || {})[key];

        let found = placeholderRegExp(key).test(content);
        if (found) {
            content = content.replace(placeholderRegExp(key), () => value);
        } else if (previous && previous !== value && content.includes(previous)) {
            content = content.split(previous).join(value);
            found = true;
        } else if (content.includes(value)) {
            // Already up to date (e.g. updated together with another value).
            lastValues[index] = Object.assign({}, lastValues[index], { [key]: value });
            return true;
        }
        if (!found) return false;

        setEditorContent(editorId, content);
        lastValues[index] = Object.assign({}, lastValues[index], { [key]: value });
        return true;
    }

    function getFileName(index) {
        return $(`#mpa-image-${index}`).attr('data-filename') || '';
    }

    function setStatus(index, message, type) {
        const $status = $(`#mpa-auto-status-${index}`);
        $status.removeClass('is-error is-success is-working').addClass(type ? `is-${type}` : '').text(message || '');
        clearTimeout($status.data('timer'));
        if (type === 'success') {
            $status.data('timer', setTimeout(() => $status.text('').removeClass('is-success'), 4000));
        }
    }

    function notFound(key) {
        return labels.notFound.replace('%s', `{${key}}`);
    }

    // Reads Google's free-endpoint response: [[["Hello","سلام",...], ...], ...]
    function parseGoogleResponse(data) {
        if (!Array.isArray(data)) return '';
        if (Array.isArray(data[0]) && Array.isArray(data[0][0])) {
            return data[0].map(seg => (typeof seg[0] === 'string' ? seg[0] : '')).join('').trim();
        }
        if (Array.isArray(data[0]) && typeof data[0][0] === 'string') return data[0][0].trim();
        if (typeof data[0] === 'string') return data[0].trim();
        return '';
    }

    const translationCache = {};

    // 1) Ask Google straight from the browser (uses the author's own IP, which
    //    Google rarely rate-limits). 2) If that fails, ask our server, which
    //    tries several Google endpoints.
    function requestTranslation(text) {
        const source = automation.sourceLang || 'fa';
        const target = automation.targetLang || 'en';
        const cacheKey = `${source}|${target}|${text}`;
        if (translationCache[cacheKey]) return $.Deferred().resolve(translationCache[cacheKey]).promise();

        const deferred = $.Deferred();
        const url = 'https://translate.googleapis.com/translate_a/single?client=gtx&dt=t'
            + `&sl=${encodeURIComponent(source)}&tl=${encodeURIComponent(target)}&q=${encodeURIComponent(text)}`;

        const viaServer = () => {
            $.post(automation.ajaxUrl, { action: 'mpa_translate', nonce: automation.nonce, text: text })
                .done(response => {
                    if (response && response.success && response.data.text) {
                        translationCache[cacheKey] = response.data.text;
                        deferred.resolve(response.data.text);
                    } else {
                        deferred.reject((response && response.data && response.data.message) || labels.translateFailed);
                    }
                })
                .fail(() => deferred.reject(labels.translateFailed));
        };

        if (typeof window.fetch !== 'function') {
            viaServer();
        } else {
            fetch(url, { credentials: 'omit' })
                .then(r => (r.ok ? r.json() : Promise.reject(r.status)))
                .then(data => {
                    const translated = parseGoogleResponse(data);
                    if (!translated) return Promise.reject('empty');
                    translationCache[cacheKey] = translated;
                    deferred.resolve(translated);
                })
                .catch(viaServer);
        }

        return deferred.promise();
    }

    function translateTitle(index) {
        const title = $(`input[name="mpa-posts[${index}][title]"]`).val().trim();
        if (!title) return $.Deferred().reject(labels.enterTitle).promise();

        return requestTranslation(title).then(function (translated) {
            if (!fillPlaceholder(index, 'translated_title', translated)) {
                return $.Deferred().reject(notFound('translated_title'));
            }
            return translated;
        });
    }

    function applyDownloadLink(index) {
        const pattern = $pattern.val() || '';
        if (!pattern.trim()) return labels.enterPattern;

        let link = pattern;
        if (pattern.includes('{file_name}')) {
            const fileName = getFileName(index);
            if (!fileName) return labels.selectImage;
            link = pattern.split('{file_name}').join(fileName);
        }
        // The pattern is free text: inserted exactly as typed, only {file_name} is filled in.
        return fillPlaceholder(index, 'download_link', link, true) ? true : notFound('download_link');
    }

    function applyFileName(index) {
        const fileName = getFileName(index);
        if (!fileName) return labels.selectImage;
        return fillPlaceholder(index, 'file_name', fileName) ? true : notFound('file_name');
    }

    // Runs one action on one post. Resolves with nothing, rejects with an error message.
    function runAction(index, action) {
        if (action === 'translate') return translateTitle(index);

        const result = action === 'download' ? applyDownloadLink(index) : applyFileName(index);
        return result === true ? $.Deferred().resolve().promise() : $.Deferred().reject(result).promise();
    }

    const stepPlaceholders = { download: 'download_link', filename: 'file_name', translate: 'translated_title' };

    // A step is needed when its placeholder (or a value it inserted before) is in the content.
    function postUses(index, key) {
        const content = getEditorContent(`editor-${index}`) || '';
        const previous = (lastValues[index] || {})[key];
        return placeholderRegExp(key).test(content) || Boolean(previous && content.includes(previous));
    }

    function runAllOnPost(index) {
        const errors = [];
        // Download link first (it may itself contain {file_name}), then file name, then translation.
        const steps = ['download', 'filename', 'translate'].filter(step => postUses(index, stepPlaceholders[step]));
        if (!steps.length) return $.Deferred().resolve([labels.noPlaceholders]).promise();
        let chain = $.Deferred().resolve().promise();

        steps.forEach(step => {
            chain = chain.then(() => runAction(index, step).then(null, msg => {
                errors.push(msg);
                return $.Deferred().resolve().promise();
            }));
        });

        return chain.then(() => errors);
    }

    function runButton($btn) {
        const index = $btn.data('index');
        const action = $btn.data('action');
        const $buttons = $btn.closest('.mpa-post-automation').find('.mpa-auto-btn');

        $buttons.prop('disabled', true);
        $btn.addClass('is-busy');
        setStatus(index, labels.working, 'working');

        const job = action === 'all'
            ? runAllOnPost(index).then(errors => errors.length ? $.Deferred().reject(errors.join(' ')) : null)
            : runAction(index, action);

        return job.then(
            () => setStatus(index, labels.done, 'success'),
            message => setStatus(index, message, 'error')
        ).always(() => {
            $buttons.prop('disabled', false);
            $btn.removeClass('is-busy');
        });
    }

    $(document).on('click', '.mpa-auto-btn', function () {
        runButton($(this));
    });

    // One post after another, with a short pause, so Google isn't flooded.
    $(document).on('click', '.mpa-run-all-posts', function () {
        const $all = $(this).prop('disabled', true);
        const buttons = $('.mpa-post-automation .mpa-auto-btn[data-action="all"]').get();
        let chain = $.Deferred().resolve().promise();

        buttons.forEach((btn, i) => {
            chain = chain.then(() => {
                const pause = $.Deferred();
                setTimeout(() => runButton($(btn)).always(() => pause.resolve()), i ? 400 : 0);
                return pause.promise();
            });
        });
        chain.always(() => $all.prop('disabled', false));
    });
    /* ------------------------------------------------------------------
     * Category picker (multi-select with checkboxes)
     * ---------------------------------------------------------------- */

    const $catSelect = $('#mpa-cat-select');
    if ($catSelect.length) {
        const $control = $catSelect.find('.mpa-cat-control');
        const $panel = $catSelect.find('.mpa-cat-panel');
        const $search = $catSelect.find('.mpa-cat-search');
        const $chips = $catSelect.find('.mpa-cat-chips');
        const $boxes = $catSelect.find('input[type="checkbox"]');
        const names = {};
        $boxes.each(function () { names[this.value] = $(this).data('name'); });

        function openPanel() {
            $panel.prop('hidden', false);
            $control.attr('aria-expanded', 'true');
            $catSelect.addClass('is-open');
            setTimeout(() => $search.trigger('focus'), 0);
        }

        function closePanel() {
            $panel.prop('hidden', true);
            $control.attr('aria-expanded', 'false');
            $catSelect.removeClass('is-open');
        }

        function pathOf(box) {
            return String($(box).data('path')).split(',');
        }

        // Selected categories as chips. A selected parent with a selected
        // descendant is shown inside that descendant's chip: Parent › Child.
        function renderChips() {
            const selected = $boxes.filter(':checked').get();
            const selectedIds = new Set(selected.map(b => b.value));
            const covered = new Set();
            selected.forEach(box => pathOf(box).slice(0, -1).forEach(id => covered.add(id)));

            $chips.empty();
            selected.filter(box => !covered.has(box.value)).forEach(box => {
                const $chip = $('<span class="mpa-cat-chip"></span>');
                pathOf(box).forEach((id, i, arr) => {
                    if (i > 0) $chip.append('<span class="mpa-cat-sep" aria-hidden="true">›</span>');
                    $('<span></span>')
                        .addClass(selectedIds.has(id) ? 'mpa-cat-part' : 'mpa-cat-part is-muted')
                        .text(names[id] || '')
                        .appendTo($chip);
                });
                $('<button type="button" class="mpa-cat-remove">×</button>')
                    .attr('data-id', box.value)
                    .attr('aria-label', `${labels.remove}: ${names[box.value] || ''}`)
                    .appendTo($chip);
                $chips.append($chip);
            });

            $catSelect.toggleClass('has-value', selected.length > 0);
            $catSelect.find('.mpa-cat-count').text(selected.length ? labels.selectedCount.replace('%d', selected.length) : '');
            $catSelect.find('.mpa-cat-option').each(function () {
                $(this).toggleClass('is-checked', $(this).find('input').is(':checked'));
            });
        }

        $control.on('click', function (e) {
            if ($(e.target).closest('.mpa-cat-remove').length) return;
            $panel.prop('hidden') ? openPanel() : closePanel();
        });

        $control.on('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
                e.preventDefault();
                openPanel();
            }
        });

        $catSelect.on('click', '.mpa-cat-remove', function (e) {
            e.stopPropagation();
            // Removing a "Parent › Child" chip unticks the child; the parent then gets its own chip.
            $boxes.filter(`[value="${$(this).data('id')}"]`).prop('checked', false);
            renderChips();
        });

        $boxes.on('change', renderChips);

        $catSelect.find('.mpa-cat-clear').on('click', function () {
            $boxes.prop('checked', false);
            renderChips();
            $search.trigger('focus');
        });

        // Filter: keep matches and their ancestors visible.
        $search.on('input', function () {
            const term = this.value.trim().toLowerCase();
            const $items = $catSelect.find('.mpa-cat-item');

            if (!term) {
                $items.prop('hidden', false);
                $catSelect.find('.mpa-cat-no-match').prop('hidden', true);
                return;
            }

            $items.prop('hidden', true);
            let matches = 0;
            $items.each(function () {
                const name = String($(this).children('.mpa-cat-option').find('input').data('name')).toLowerCase();
                if (name.includes(term)) {
                    matches++;
                    $(this).prop('hidden', false).parents('.mpa-cat-item').prop('hidden', false);
                }
            });
            $catSelect.find('.mpa-cat-no-match').prop('hidden', matches > 0);
        });

        $search.on('keydown', function (e) {
            if (e.key === 'Enter') e.preventDefault(); // Don't submit the form.
        });

        $(document).on('mousedown', function (e) {
            if (!$catSelect.is(e.target) && !$catSelect.has(e.target).length) closePanel();
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && !$panel.prop('hidden')) {
                closePanel();
                $control.trigger('focus');
            }
        });

        renderChips();
    }
});
