/**
 * Multi Post Adder – AI features.
 *  - Settings → MPA Settings → AI: "Check key & load models".
 *  - Posts → Add Posts with AI: images → setup file → prompt → generate,
 *    then the normal Add Multiple Posts page, filled in.
 */
jQuery(function ($) {

    /* ------------------------------------------------------------------
     * Settings tab: check a key and list its models
     * ---------------------------------------------------------------- */

    if (window.mpa_ai_admin) {
        const admin = window.mpa_ai_admin;

        $(document).on('click', '.mpa-ai-check', function () {
            const $btn = $(this);
            const provider = $btn.data('provider');
            const $status = $btn.siblings('.mpa-ai-check-status');
            const key = $(`#mpa-ai-${provider}-key`).val();

            $btn.prop('disabled', true);
            $status.removeClass('is-ok is-error').text(admin.i18n.checking);

            $.post(admin.ajaxUrl, { action: 'mpa_ai_models', nonce: admin.nonce, provider, key })
                .done(response => {
                    if (response && response.success) {
                        const models = response.data.models || [];
                        const $list = $(`#mpa-ai-${provider}-models`).empty();
                        models.forEach(m => $list.append($('<option>').attr('value', m)));
                        $status.addClass('is-ok').text(admin.i18n.keyOk.replace('%d', models.length));
                    } else {
                        $status.addClass('is-error').text((response && response.data && response.data.message) || admin.i18n.failed);
                    }
                })
                .fail(() => $status.addClass('is-error').text(admin.i18n.failed))
                .always(() => $btn.prop('disabled', false));
        });
    }

    /* ------------------------------------------------------------------
     * Add Posts with AI wizard
     * ---------------------------------------------------------------- */

    const $wizard = $('#mpa-wizard');
    if (!$wizard.length || !window.mpa_ai || !window.MPA) return;

    const ai = window.mpa_ai;
    const t = ai.i18n;
    const MPA = window.MPA;
    const automation = window.mpa_automation || {};

    const state = {
        step: 1,
        images: [],     // [{id, url, thumb, filename}]
        setup: null,
        setupName: '',
        results: {},    // id -> {status: 'waiting'|'working'|'done'|'error', data, error}
        running: false,
        stop: false
    };

    const $next = $wizard.find('.mpa-wz-next');
    const $back = $wizard.find('.mpa-wz-back');
    const $retry = $wizard.find('.mpa-wz-retry');
    const $hint = $wizard.find('.mpa-wz-hint');

    function esc(text) {
        return $('<span>').text(text == null ? '' : String(text)).html();
    }

    /* ---------- navigation ---------- */

    function canLeave(step) {
        if (step === 1 && !state.images.length) return t.needImages;
        if (step === 2 && !state.setup) return t.needSetup;
        if (step === 3 && !ai.hasKey) return t.needKey;
        return '';
    }

    function render() {
        const step = state.step;
        $wizard.find('.mpa-wizard-step').each(function () {
            $(this).prop('hidden', Number($(this).data('step')) !== step);
        });
        $wizard.find('.mpa-step').each(function () {
            const n = Number($(this).data('step'));
            $(this).toggleClass('is-current', n === step).toggleClass('is-done', n < step);
        });

        $back.prop('hidden', step === 1 || step === 4);
        $retry.prop('hidden', true);
        $hint.text('').removeClass('is-error');

        if (step < 3) {
            $next.text(t.next).prop('disabled', !!canLeave(step));
        } else if (step === 3) {
            $next.html(`<span class="dashicons dashicons-superhero-alt" aria-hidden="true"></span>${esc(t.generate)}`).prop('disabled', !ai.hasKey);
            if (!ai.hasKey) $hint.text(t.needKey);
        } else {
            renderGenerateFooter();
        }
    }

    function go(step) {
        state.step = step;
        render();
        $('html, body').animate({ scrollTop: $wizard.offset().top - 40 }, 120);
    }

    $next.on('click', function () {
        if (state.step === 4) {
            if (state.running) {
                state.stop = true;          // "Stop": finish the current image, skip the rest.
                $next.prop('disabled', true);
            } else {
                finish();
            }
            return;
        }
        const problem = canLeave(state.step);
        if (problem) {
            $hint.text(problem).addClass('is-error');
            return;
        }
        if (state.step < 3) return go(state.step + 1);
        go(4);
        generate(state.images.map(img => img.id));
    });

    $back.on('click', () => go(Math.max(1, state.step - 1)));

    /* ---------- step 1: images ---------- */

    function fileBase(attachment) {
        const name = attachment.originalImageName || attachment.filename || '';
        return name.replace(/\.[^.]+$/, '');
    }

    function renderImages() {
        const $list = $('#mpa-wz-images').empty();
        state.images.forEach((img, i) => {
            $list.append(`
                <li class="mpa-wz-image" data-id="${img.id}">
                    <img src="${esc(img.thumb)}" alt="">
                    <span class="mpa-wz-image-num">${i + 1}</span>
                    <button type="button" class="mpa-wz-image-remove" aria-label="${esc(t.remove)}" title="${esc(t.remove)}"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
                    <span class="mpa-wz-image-name" title="${esc(img.filename)}">${esc(img.filename)}</span>
                </li>`);
        });
        const n = state.images.length;
        $wizard.find('.mpa-wz-count').text(n === 0 ? '' : (n === 1 ? t.oneImage : t.imageCount.replace('%d', n)));
        if (state.step === 1) render();
    }

    let frame = null;
    $wizard.on('click', '.mpa-wz-add-images', function () {
        if (!frame) {
            frame = wp.media({
                title: t.selectImages,
                button: { text: t.useImages },
                library: { type: 'image' },
                multiple: 'add'
            });
            frame.on('select', function () {
                frame.state().get('selection').toJSON().forEach(att => {
                    if (state.images.some(img => img.id === att.id)) return;
                    const sizes = att.sizes || {};
                    state.images.push({
                        id: att.id,
                        url: att.url,
                        thumb: (sizes.medium || sizes.thumbnail || sizes.full || { url: att.url }).url,
                        filename: fileBase(att)
                    });
                });
                renderImages();
            });
        }
        frame.open();
    });

    $wizard.on('click', '.mpa-wz-image-remove', function () {
        const id = Number($(this).closest('.mpa-wz-image').data('id'));
        state.images = state.images.filter(img => img.id !== id);
        renderImages();
    });

    if ($.fn.sortable) {
        $('#mpa-wz-images').sortable({
            items: '> .mpa-wz-image',
            tolerance: 'pointer',
            placeholder: 'mpa-wz-image mpa-wz-image-placeholder',
            update() {
                const order = $(this).children('.mpa-wz-image').map((_, el) => Number($(el).data('id'))).get();
                state.images.sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));
                renderImages();
            }
        });
    }

    /* ---------- step 2: setup file ---------- */

    const $file = $('#mpa-wz-file');
    const $drop = $wizard.find('.mpa-wz-drop');

    function summaryRow(label, value) {
        return `<dt>${esc(label)}</dt><dd>${value}</dd>`;
    }

    function showSetup() {
        const setup = state.setup;
        const adj = setup.adjustments || {};
        const meta = setup.custom_fields || {};
        const auto = setup.automation || {};

        const cats = Array.isArray(adj.categories) && adj.categories.length
            ? adj.categories.map(c => `<span class="mpa-wz-chip">${esc(Array.isArray(c.path) ? c.path.join(' › ') : '#' + c.id)}</span>`).join(' ')
            : esc(t.none);
        const preset = adj.content_preset && adj.content_preset.name ? esc(adj.content_preset.name) : esc(t.none);
        const fields = Array.isArray(meta.fields) && meta.fields.length
            ? meta.fields.map(f => `<code>${esc(f.key)}</code>`).join(' ') + (meta.enabled === false ? ` <em>(${esc(t.off)})</em>` : '')
            : esc(t.none);
        const autoText = (auto.enabled ? t.on : t.off) + (auto.download_pattern ? ` · <code>${esc(auto.download_pattern)}</code>` : '');

        $wizard.find('.mpa-wz-summary-name').text(state.setupName);
        $wizard.find('.mpa-wz-summary-list').html(
            summaryRow(t.categories, cats) +
            summaryRow(t.hashtags, adj.hashtags ? esc(adj.hashtags) : esc(t.none)) +
            summaryRow(t.preset, preset) +
            summaryRow(t.customFields, fields) +
            summaryRow(t.automation, autoText)
        ).append(adj.post_count ? `<p class="mpa-wz-note">${esc(t.postCountIgnored)}</p>` : '');

        $wizard.find('.mpa-wz-summary').prop('hidden', false);
        $wizard.find('.mpa-wz-file-error').prop('hidden', true);
        $drop.prop('hidden', true);
        render();
    }

    function readSetupFile(file) {
        if (!file) return;
        const reader = new FileReader();
        reader.onload = () => {
            let data = null;
            try { data = JSON.parse(String(reader.result).replace(/^﻿/, '')); } catch (e) { data = null; }
            if (!data || typeof data !== 'object' || data.plugin !== 'multi-post-adder') {
                state.setup = null;
                $wizard.find('.mpa-wz-file-error').text(t.invalidSetup).prop('hidden', false);
                render();
                return;
            }
            state.setup = data;
            state.setupName = file.name;
            showSetup();
        };
        reader.readAsText(file);
    }

    $drop.on('click keydown', function (e) {
        if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
        e.preventDefault();
        $file.val('').trigger('click');
    });
    $drop.on('dragover dragenter', e => { e.preventDefault(); $drop.addClass('is-over'); });
    $drop.on('dragleave drop', () => $drop.removeClass('is-over'));
    $drop.on('drop', function (e) {
        e.preventDefault();
        const files = e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files;
        readSetupFile(files && files[0]);
    });
    $file.on('change', function () { readSetupFile(this.files && this.files[0]); });

    $wizard.on('click', '.mpa-wz-change-file', function () {
        state.setup = null;
        $wizard.find('.mpa-wz-summary').prop('hidden', true);
        $drop.prop('hidden', false);
        render();
        $file.val('').trigger('click');
    });

    /* ---------- step 4: generate ---------- */

    function renderResults() {
        const $list = $wizard.find('.mpa-wz-results').empty();
        state.images.forEach((img, i) => {
            const r = state.results[img.id] || { status: 'waiting' };
            let body = '';
            if (r.status === 'done') {
                body = `<strong class="mpa-wz-r-title" dir="auto">${esc(r.data.title)}</strong>
                        <span class="mpa-wz-r-alt"><span class="dashicons dashicons-format-image" aria-hidden="true"></span><span dir="auto">${esc(r.data.alt)}</span></span>
                        <span class="mpa-wz-r-desc" dir="auto">${esc(r.data.description)}</span>`;
            } else if (r.status === 'error') {
                body = `<span class="mpa-wz-r-error">${esc(r.error)}</span>
                        <button type="button" class="button-link mpa-wz-retry-one" data-id="${img.id}">${esc(t.retry)}</button>`;
            } else if (r.status === 'working') {
                body = `<span class="mpa-wz-r-muted"><span class="spinner is-active"></span>${esc(t.writing)}</span>`;
            } else {
                body = `<span class="mpa-wz-r-muted">${esc(r.status === 'stopped' ? t.stopped : t.waiting)}</span>`;
            }
            $list.append(`
                <li class="mpa-wz-result is-${r.status}">
                    <span class="mpa-wz-r-thumb"><img src="${esc(img.thumb)}" alt=""><span class="mpa-wz-image-num">${i + 1}</span></span>
                    <span class="mpa-wz-r-body">${body}</span>
                </li>`);
        });

        const total = state.images.length;
        const finished = state.images.filter(img => ['done', 'error', 'stopped'].includes((state.results[img.id] || {}).status)).length;
        const pct = total ? Math.round(finished / total * 100) : 0;
        $wizard.find('.mpa-wz-progress-bar').css('width', pct + '%');
        $wizard.find('.mpa-wz-progress').attr('aria-valuenow', pct);
        $wizard.find('.mpa-wz-progress-text').text(t.progress.replace('%1$d', finished).replace('%2$d', total));
    }

    function failedIds() {
        return state.images.filter(img => ['error', 'stopped'].includes((state.results[img.id] || {}).status)).map(img => img.id);
    }

    function renderGenerateFooter() {
        $back.prop('hidden', true);
        if (state.running) {
            $next.text(t.stop).prop('disabled', false).addClass('mpa-wz-stop').removeClass('button-primary');
            $retry.prop('hidden', true);
            $hint.text('');
            return;
        }
        $next.removeClass('mpa-wz-stop').addClass('button-primary');
        const failed = failedIds().length;
        $wizard.find('.mpa-wz-gen-title').text(failed === 1 ? t.doneOne : (failed ? t.doneSome.replace('%d', failed) : t.doneAll));
        $next.text(failed ? t.continueAnyway : t.continue).prop('disabled', false);
        $retry.prop('hidden', !failed);
    }

    function generateOne(id) {
        const img = state.images.find(x => x.id === id);
        state.results[id] = { status: 'working' };
        renderResults();
        return $.post(automation.ajaxUrl, {
            action: 'mpa_ai_generate',
            nonce: automation.nonce,
            attachment_id: id,
            prompt: $('#mpa-wz-prompt').val() || ''
        }).then(response => {
            if (response && response.success) {
                state.results[id] = { status: 'done', data: response.data };
            } else {
                state.results[id] = { status: 'error', error: (response && response.data && response.data.message) || t.failed };
            }
        }, xhr => {
            const msg = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
            state.results[id] = { status: 'error', error: msg || t.failed };
            return $.Deferred().resolve();
        }).always(() => {
            if (img) renderResults();
        });
    }

    // One image after another: kind to rate limits and to the server.
    function generate(ids) {
        state.running = true;
        state.stop = false;
        ids.forEach(id => { state.results[id] = { status: 'waiting' }; });
        $wizard.find('.mpa-wz-gen-title').text(t.writing);
        renderResults();
        renderGenerateFooter();

        let chain = $.Deferred().resolve().promise();
        ids.forEach(id => {
            chain = chain.then(() => {
                if (state.stop) {
                    state.results[id] = { status: 'stopped' };
                    return null;
                }
                return generateOne(id);
            });
        });
        chain.always(() => {
            state.running = false;
            renderResults();
            renderGenerateFooter();
        });
    }

    $retry.on('click', () => generate(failedIds()));
    $wizard.on('click', '.mpa-wz-retry-one', function () {
        if (!state.running) generate([Number($(this).data('id'))]);
    });

    /* ---------- finish: fill the normal page ---------- */

    function insertDescription(content, description) {
        const value = MPA.escapeHtml(description);
        if (MPA.placeholderRegExp('custom_text').test(content)) {
            return content.replace(MPA.placeholderRegExp('custom_text'), () => value);
        }
        // No {custom_text}: add the description at the top.
        return `<p>${value}</p>` + (content ? '\n' + content : '');
    }

    function fillPosts() {
        state.images.forEach((img, i) => {
            const r = state.results[img.id] || {};
            const data = r.status === 'done' ? r.data : null;

            $(`#mpa-image-${i}`).val(img.id).attr('data-filename', img.filename);
            $(`#mpa-preview-${i}`).html($('<img>').attr({ src: img.thumb, alt: '' }).css('max-width', '100px'));

            if (!data) return;
            $(`input[name="mpa-posts[${i}][title]"]`).val(data.title);
            $(`#mpa-image-alt-${i}`).val(data.alt);
            if (data.description) {
                const editorId = `editor-${i}`;
                MPA.setEditorContent(editorId, insertDescription(MPA.getEditorContent(editorId) || '', data.description));
            }
        });

        $wizard.prop('hidden', true);
        $wizard.closest('.wrap').removeClass('mpa-ai-pending');
        $('html, body').animate({ scrollTop: 0 }, 120);
    }

    function finish() {
        // The setup file decides everything except the number of posts: that is the number of images.
        const setup = JSON.parse(JSON.stringify(state.setup));
        setup.adjustments = Object.assign({}, setup.adjustments, { post_count: '' });
        MPA.importSetup(setup);

        $(document).one('mpa:posts-ready', () => setTimeout(fillPosts, 50));
        $('#mpa-count').val(state.images.length).trigger('change');
    }

    renderImages();
    render();
});
