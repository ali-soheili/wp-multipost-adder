jQuery(document).ready(function ($) {
    const presets = Array.isArray(window.mpa_presets) ? window.mpa_presets : Object.values(window.mpa_presets || {});

    function createCustomFieldHTML(fieldName) {
        let options = '<option value="">-- Select Key --</option>';
        if (window.mpa_meta_keys) {
            mpa_meta_keys.forEach(key => {
                options += `<option value="${key}">${key}</option>`;
            });
        }
        return `
        <div class="mpa-meta-row">
            <select name="${fieldName}[key]">${options}</select>
            <input type="text" name="${fieldName}[custom_key]" placeholder="Or custom key">
            <input type="text" name="${fieldName}[value]" placeholder="Value">
        </div>`;
    }

    function createPostBlock(i) {
        return `
        <div class="mpa-post-block">
            <h3 class="mpa-post-number">${i + 1}</h3>
            <p class="mpa-field-row mpa-title-row">
                <label>Title:</label>
                <input type="text" name="mpa-posts[${i}][title]">
            </p>
            <p class="mpa-field-row">
                <label>Content:</label><br>
                <textarea id="editor-${i}" name="mpa-posts[${i}][content]"></textarea>
            </p>
            <p class="mpa-field-row">
                <label>Featured Image: 
                    <button type="button" class="button mpa-upload" data-index="${i}">Upload Image</button>
                    <input type="hidden" name="mpa-posts[${i}][image]" id="mpa-image-${i}">
                    <span class="mpa-image-preview" id="mpa-preview-${i}"></span>
                </label>
            </p>
            <p class="mpa-field-row">
                <label>Custom Fields: 
                    <button type="button" class="button mpa-add-meta" data-index="${i}">Add Custom Field</button>
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

        $('#mpa-posts-container textarea').each(function () {
            setEditorContent(this.id, content);
        });
    }

    $('#mpa-count').on('change', function () {
        const count = parseInt($(this).val());
        const container = $('#mpa-posts-container');
        container.empty();

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
            title: 'Select or Upload Image',
            button: { text: 'Use this image' },
            multiple: false
        });

        frame.on('select', function () {
            const attachment = frame.state().get('selection').first().toJSON();
            imageInput.val(attachment.id);
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
});
