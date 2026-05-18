(function ($) {
    'use strict';

    function announceCustomFieldAction(message) {
        const $status = $('#cf-a11y-status');

        if (!$status.length) {
            return;
        }

        $status.text('');

        window.setTimeout(function () {
            $status.text(message);
        }, 10);
    }

    function nextRepeaterIndex($repeater) {
        return $repeater.find('> .cf-repeater-items > .cf-repeater-item').length;
    }

    function refreshRepeaterIndexes($repeater) {
        const prefix = $repeater.find('.cf-add-repeater-row').data('prefix');

        $repeater.find('> .cf-repeater-items > .cf-repeater-item').each(function (rowIndex) {
            $(this).find(':input[name]').each(function () {
                const $input = $(this);
                const name = $input.attr('name');

                if (!name) {
                    return;
                }

                const updatedName = name.replace(
                    new RegExp(prefix + '\\[([^\\]]+)\\]\\[\\d+\\]'),
                    function (match, fieldName) {
                        return prefix + '[' + fieldName + '][' + rowIndex + ']';
                    }
                );

                $input.attr('name', updatedName);
            });
        });
    }

    function parseImageValue($input) {
        try {
            const value = $input.val();

            if (!value) {
                return {};
            }

            const parsed = JSON.parse(value);

            return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function saveImageValue($input, item) {
        $input.val(JSON.stringify(item || {}));
    }

    function renderImagePreview($field, item) {
        const $preview = $field.find('.cf-image-preview');

        $preview.empty();

        if (!item || !item.url) {
            return;
        }

        const url = item.url;
        const name = item.name || url.split('/').pop();
        const mime = item.mime || '';

        $preview.html(`
        <div class="cf-image-item" data-url="${url}" data-name="${name}" data-mime="${mime}">
            <button type="button" class="cf-image-preview-button" aria-label="Preview image ${name}">
                <img src="${url}" alt="${name}" loading="lazy">
            </button>
            <button type="button" class="btn btn-xs btn-danger cf-image-remove" aria-label="Remove image ${name}">
                Remove
            </button>
            <small>${name}</small>
        </div>
    `);
    }

    function uploadImageFile($field, file) {
        const $input = $field.find('.cf-image-value');
        const uploadUrl = window.DevflowCustomFields?.imageUploadUrl;

        if (!uploadUrl) {
            alert('Missing image upload URL.');
            return;
        }

        if (!file || !file.type || !file.type.startsWith('image/')) {
            alert('Please upload a valid image file.');
            return;
        }

        const formData = new FormData();
        formData.append('image', file);

        const $dropzone = $field.find('.cf-image-dropzone');

        $dropzone.addClass('is-uploading');

        $.ajax({
            url: uploadUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (response) {
            if (!response.success || !response.image) {
                alert(response.message || 'Unable to upload image.');
                return;
            }

            saveImageValue($input, response.image);
            renderImagePreview($field, response.image);

            announceCustomFieldAction('Image uploaded.');
        }).fail(function (xhr) {
            console.log('Image upload failed:', xhr.status, xhr.responseText);
            alert('Unable to upload image.');
        }).always(function () {
            $dropzone.removeClass('is-uploading is-dragover');
        });
    }

    function getFieldValue(fieldName, $scope) {
        const $fields = $scope.find(':input').filter(function () {
            const name = $(this).attr('name') || '';

            return name === fieldName || name.endsWith('[' + fieldName + ']');
        });

        if (!$fields.length) {
            return '';
        }

        const $first = $fields.first();

        if ($first.is(':checkbox')) {
            return $fields.filter(':checked').map(function () {
                return $(this).val();
            }).get();
        }

        if ($first.is(':radio')) {
            return $fields.filter(':checked').val() || '';
        }

        return $first.val();
    }

    function conditionMatches(rule, $scope) {
        const value = getFieldValue(rule.field, $scope);
        const expected = rule.value || '';

        switch (rule.operator) {
            case 'equals':
                return Array.isArray(value)
                    ? value.includes(expected)
                    : String(value) === String(expected);

            case 'not_equals':
                return Array.isArray(value)
                    ? !value.includes(expected)
                    : String(value) !== String(expected);

            case 'empty':
                return Array.isArray(value) ? value.length === 0 : !value;

            case 'not_empty':
                return Array.isArray(value) ? value.length > 0 : !!value;

            case 'contains':
                return Array.isArray(value)
                    ? value.includes(expected)
                    : String(value).includes(expected);

            default:
                return false;
        }
    }

    function evaluateConditionalField($field) {
        const $scope = getConditionalScope($field);

        let conditional = getConditionalData($field);

        if (!conditional || !conditional.enabled) {
            $field.show();
            return;
        }

        const rules = conditional.rules || [];

        if (!rules.length) {
            $field.show();
            return;
        }

        const matches = rules.map(function (rule) {
            return conditionMatches(rule, $scope);
        });

        const passed = conditional.logic === 'any'
            ? matches.includes(true)
            : matches.every(Boolean);

        const shouldShow = conditional.action === 'show'
            ? passed
            : !passed;

        $field.toggle(shouldShow);
    }

    function evaluateConditionalLogic(context) {
        const $scope = context ? $(context) : $(document);

        $scope
            .filter('.cf-runtime-field')
            .add($scope.find('.cf-runtime-field'))
            .each(function () {
                evaluateConditionalField($(this));
            });
    }

    function validateCustomFields($form) {
        const errors = [];

        $form.find('.cf-runtime-field:visible').each(function () {
            const $field = $(this);
            const label = $field.find('label').first().text().replace('*', '').trim();
            const $required = $field.find(':input[required]').first();

            if (!$required.length) {
                return;
            }

            const value = $required.val();

            if (!value) {
                errors.push({
                    field: $field,
                    message: (label || 'This field') + ' is required.'
                });
            }

            if (!fieldHasRequiredValue($field)) {
                errors.push({
                    field: $field,
                    message: (label || 'This field') + ' is required.'
                });
            }
        });

        return errors;
    }

    function showCustomFieldErrors(errors) {
        const $summary = $('#cf-error-summary');
        const $list = $summary.find('ul');

        $list.empty();

        errors.forEach(function (error) {
            const fieldId = error.field.find(':input').first().attr('id');

            $list.append(`
            <li>
                <a href="#${fieldId}">${error.message}</a>
            </li>
        `);
        });

        $summary.removeClass('hidden').focus();
    }

    function clearCustomFieldErrors() {
        $('#cf-error-summary').addClass('hidden').find('ul').empty();
        $('.cf-runtime-field :input').removeAttr('aria-invalid');

        $('.cf-runtime-field')
            .removeClass('has-error')
            .find('.cf-field-error')
            .remove();
    }

    function fieldHasRequiredValue($field) {
        const $required = $field.find(':input[required]').first();

        if (!$required.length) {
            return true;
        }

        if ($required.is(':radio')) {
            const name = $required.attr('name');
            return $field.find(':radio[name="' + name + '"]:checked').length > 0;
        }

        if ($required.is(':checkbox')) {
            return $field.find(':checkbox[required]:checked').length > 0;
        }

        return !!$required.val();
    }

    function getConditionalData($field) {
        const raw = $field.attr('data-conditional');

        if (!raw) {
            return {};
        }

        if (typeof raw === 'object') {
            return raw;
        }

        try {
            return JSON.parse(raw);
        } catch (e) {
            return {};
        }
    }

    function initFlexibleSortable(context) {
        const $context = context ? $(context) : $(document);

        $context.find('.cf-flexible-items').each(function () {
            const $items = $(this);

            if ($items.data('sortable-initialized')) {
                return;
            }

            $items.sortable({
                items: '> .cf-flexible-item',
                handle: '.cf-flexible-toolbar',
                placeholder: 'cf-flexible-placeholder',
                tolerance: 'pointer',
                forcePlaceholderSize: true,
                cursor: 'move',
                update: function () {
                    const $flexible = $items.closest('.cf-flexible-render');

                    refreshFlexibleIndexes($flexible);
                    updateNestedRepeaterPrefixes($flexible);

                    announceCustomFieldAction('Flexible content layouts reordered.');
                }
            });

            $items.data('sortable-initialized', true);
        });
    }

    $(function () {
        initFlexibleSortable(document);
    });

    function resetElfinderModal() {
        const $modal = $('#cf-elfinder-modal');

        if ($modal.length) {
            $modal.modal('hide');
            $modal.removeData('bs.modal');
            $modal.remove();
        }

        cleanupCustomFieldModalState('#cf-elfinder-modal');
    }

    function updateNestedRepeaterPrefixes($flexible) {
        const flexibleField = $flexible.find('> .cf-flexible-add-wrapper .cf-add-flexible-row').data('field');
        const rootPrefix = $flexible.find('> .cf-flexible-add-wrapper .cf-add-flexible-row').data('prefix');

        $flexible.find('> .cf-flexible-items > .cf-flexible-item').each(function (index) {
            const $row = $(this);

            $row.find('.cf-repeater-render').each(function () {
                const $repeater = $(this);
                const repeaterField = $repeater.find('> .cf-add-repeater-row').data('field');

                $repeater.find('> .cf-add-repeater-row')
                    .attr('data-prefix', rootPrefix + '[' + flexibleField + '][' + index + ']')
                    .data('prefix', rootPrefix + '[' + flexibleField + '][' + index + ']')
                    .attr('data-field', repeaterField)
                    .data('field', repeaterField);
            });
        });
    }

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape' && $('#cf-gallery-preview-modal').is(':visible')) {
            $('#cf-gallery-preview-modal').modal('hide');
        }
    });

    $(document).on('submit', 'form', function (event) {
        const $form = $(this);

        clearCustomFieldErrors();

        const errors = validateCustomFields($form);

        if (errors.length === 0) {
            return;
        }

        event.preventDefault();

        errors.forEach(function (error) {
            error.field.addClass('has-error');

            error.field.append(`
            <p class="text-danger cf-field-error" role="alert">
                ${error.message}
            </p>
        `);
        });

        showCustomFieldErrors(errors);
    });

    $(document).on('input change', '.cf-runtime-field :input', function () {
        const $field = $(this).closest('.cf-runtime-field');
        const $scope = getConditionalScope($field);

        evaluateConditionalLogic($scope);
    });

    $(function () {
        evaluateConditionalLogic(document);
    });

    $(document).on('click keydown', '.cf-image-dropzone', function (event) {
        if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        event.preventDefault();

        $(this)
            .closest('.cf-image-field')
            .find('.cf-image-upload-input')
            .first()
            .trigger('click');
    });

    $(document).on('change', '.cf-image-upload-input', function () {
        const file = this.files && this.files[0] ? this.files[0] : null;
        const $field = $(this).closest('.cf-image-field');

        if (file) {
            uploadImageFile($field, file);
        }

        $(this).val('');
    });

    $(document).on('dragenter dragover', '.cf-image-dropzone', function (event) {
        event.preventDefault();
        event.stopPropagation();

        $(this).addClass('is-dragover');
    });

    $(document).on('dragleave dragend', '.cf-image-dropzone', function (event) {
        event.preventDefault();
        event.stopPropagation();

        $(this).removeClass('is-dragover');
    });

    $(document).on('drop', '.cf-image-dropzone', function (event) {
        event.preventDefault();
        event.stopPropagation();

        const files = event.originalEvent.dataTransfer.files;
        const file = files && files[0] ? files[0] : null;

        const $field = $(this).closest('.cf-image-field');

        if (file) {
            uploadImageFile($field, file);
        }
    });

    $(document).on('click', '.cf-open-elfinder-image', function () {
        resetElfinderModal();
        const $button = $(this);
        const $field = $button.closest('.cf-image-field');
        const $input = $field.find('.cf-image-value').first();

        const connectorUrl = window.DevflowCustomFields?.elfinderConnectorUrl;

        if (!connectorUrl) {
            alert('Missing elFinder connector URL.');
            return;
        }

        let $modal = $('#cf-elfinder-modal');

        if (!$modal.length) {
            $('body').append(`
            <div id="cf-elfinder-modal" class="modal fade" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-lg" style="width:90%;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title">Select Image</h4>
                        </div>
                        <div class="modal-body">
                            <div id="cf-elfinder-browser" style="height:500px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        `);

            $modal = $('#cf-elfinder-modal');
        }

        $modal.modal('show');
        $modal.data('return-focus', $button);

        const $browser = $('#cf-elfinder-browser');
        $browser.empty();

        $browser.elfinder({
            url: connectorUrl,
            lang: 'en',
            commandsOptions: {
                getfile: {
                    multiple: false,
                    folders: false,
                    oncomplete: 'destroy'
                }
            },
            getFileCallback: function (file) {
                const selected = Array.isArray(file) ? file[0] : file;

                if (!selected || !selected.url) {
                    return;
                }

                const item = {
                    url: selected.url,
                    name: selected.name || selected.url.split('/').pop(),
                    mime: selected.mime || ''
                };

                saveImageValue($input, item);
                renderImagePreview($field, item);

                announceCustomFieldAction('Image selected.');

                $modal.modal('hide');
            }
        });
    });

    $(document).on('dragenter dragover', '.cf-image-dropzone', function (e) {
        e.preventDefault();
        e.stopPropagation();

        $(this).addClass('is-dragover');
    });

    $(document).on('dragleave dragend drop', '.cf-image-dropzone', function (e) {
        e.preventDefault();
        e.stopPropagation();

        $(this).removeClass('is-dragover');
    });

    $(document).on('click', '.cf-image-dropzone', function () {
        $(this).closest('.cf-image-field').find('.cf-image-upload-input').trigger('click');
    });

    $(document).on('click', '.cf-image-remove', function () {
        const $field = $(this).closest('.cf-image-field');
        const $input = $field.find('.cf-image-value');

        saveImageValue($input, {});
        renderImagePreview($field, {});

        announceCustomFieldAction('Image removed.');
    });

    $(document).on('click', '.cf-add-repeater-row', function () {
        const $button = $(this);
        const $repeater = $button.closest('.cf-repeater-render');
        const $items = $repeater.find('> .cf-repeater-items');
        const $template = $repeater.find('> template.cf-repeater-template').first();

        const field = $button.data('field');
        const prefix = $button.data('prefix');
        const index = $items.children('.cf-repeater-item').length;

        let html = $template.html();

        html = html
            .replaceAll('__PREFIX__', prefix)
            .replaceAll('__FIELD__', field)
            .replaceAll('__INDEX__', index)
            .replaceAll('__ROW_INDEX__', index);

        const $row = $(html);

        $items.append($row);

        initGallerySortable($row);

        if (typeof initializeTinyMce === 'function') {
            initializeTinyMce($row);
        }

        evaluateConditionalLogic($row);

        announceCustomFieldAction('Repeater row added.');
    });

    $(document).on('click', '.cf-remove-row', function () {
        const $row = $(this).closest('.cf-repeater-item');

        $row.find('textarea.tinymce').each(function () {
            const id = $(this).attr('id');

            if (id && typeof tinymce !== 'undefined' && tinymce.get(id)) {
                tinymce.get(id).remove();
            }
        });

        $row.remove();

        announceCustomFieldAction('Repeater row removed.');
    });

    function parseGalleryValue($input) {
        try {
            const value = $input.val();

            if (!value) {
                return [];
            }

            const parsed = JSON.parse(value);

            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function saveGalleryValue($input, items) {
        $input.val(JSON.stringify(items));
    }

    function updateGalleryValue($field) {
        const $input = $field.find('.cf-gallery-value');

        const items = [];

        $field.find('.cf-gallery-item').each(function () {
            items.push({
                url: $(this).data('url'),
                name: $(this).data('name') || '',
                mime: $(this).data('mime') || ''
            });
        });

        $input.val(JSON.stringify(items));
    }

    function initGallerySortable(context) {
        const $context = context ? $(context) : $(document);

        $context.find('.cf-gallery-preview').sortable({
            items: '.cf-gallery-item',
            tolerance: 'pointer',
            cursor: 'move',
            placeholder: 'cf-gallery-sort-placeholder',

            update: function () {
                const $field = $(this).closest('.cf-gallery-field');

                updateGalleryValue($field);
            }
        });
    }

    $(function () {
        initGallerySortable(document);
    });

    function renderGalleryPreview($field, items) {
        const $preview = $field.find('.cf-gallery-preview');

        $preview.empty();

        items.forEach(function (item) {
            const url = item.url || '';
            const name = item.name || url.split('/').pop();
            const mime = item.mime || '';

            if (!url) {
                return;
            }

            $preview.append(`
            <div 
                class="cf-gallery-item" 
                tabindex="0"
                data-url="${url}"
                data-name="${name}"
                data-mime="${mime}"
            >
                <button 
                    type="button" 
                    class="cf-gallery-preview-button"
                    aria-label="Preview image ${name}"
                >
                    <img 
                        src="${url}" 
                        alt="${name}"
                        loading="lazy"
                    >
                </button>

                <div class="cf-gallery-actions">
                    <button type="button" class="btn btn-xs btn-default cf-gallery-move-left" aria-label="Move ${name} left">Left</button>
                    <button type="button" class="btn btn-xs btn-default cf-gallery-move-right" aria-label="Move ${name} right">Right</button>
                    <button type="button" class="btn btn-xs btn-danger cf-gallery-remove" aria-label="Remove image ${name}">Remove</button>
                </div>

                <small>${name}</small>
            </div>
        `);
        });
    }

    function ensureGalleryPreviewModal() {
        let $modal = $('#cf-gallery-preview-modal');

        if ($modal.length) {
            return $modal;
        }

        $('body').append(`
        <div id="cf-gallery-preview-modal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="cf-gallery-preview-title">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close cf-gallery-preview-close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 id="cf-gallery-preview-title" class="modal-title">Image Preview</h4>
                    </div>

                    <div class="modal-body text-center">
                        <img id="cf-gallery-preview-image" src="" alt="" class="img-responsive center-block">
                    </div>
                </div>
            </div>
        </div>
    `);

        return $('#cf-gallery-preview-modal');
    }

    function loadExistingOembedPreviews() {
        $('.cf-oembed-field').each(function () {
            const $field = $(this);
            const $input = $field.find('.cf-oembed-url');
            const $button = $field.find('.cf-oembed-preview-button');

            if ($input.val()) {
                $button.trigger('click');
            }
        });
    }

    $(function () {
        loadExistingOembedPreviews();
    });

    function nextFlexibleIndex($flexible) {
        return $flexible.find('> .cf-flexible-items > .cf-flexible-item').length;
    }

    function refreshFlexibleIndexes($flexible) {
        const prefix = $flexible.find('.cf-add-flexible-row').data('prefix');

        $flexible.find('> .cf-flexible-items > .cf-flexible-item').each(function (rowIndex) {
            $(this).find(':input[name]').each(function () {
                const $input = $(this);
                const name = $input.attr('name');

                if (!name) {
                    return;
                }

                const updatedName = name.replace(
                    new RegExp(prefix + '\\[([^\\]]+)\\]\\[\\d+\\]'),
                    function (match, fieldName) {
                        return prefix + '[' + fieldName + '][' + rowIndex + ']';
                    }
                );

                $input.attr('name', updatedName);
            });
        });
    }

    function uniqueFlexibleUuid() {
        return 'row_' + Date.now() + '_' + Math.floor(Math.random() * 100000);
    }

    function initializeTinyMce(context) {
        if (typeof tinymce === 'undefined') {
            return;
        }

        const $context = context ? $(context) : $(document);

        $context.find('textarea.cf-richtext, textarea.tinymce').each(function () {
            const $textarea = $(this);

            if (!$textarea.attr('id')) {
                $textarea.attr(
                    'id',
                    'cf_tinymce_' + Date.now() + '_' + Math.floor(Math.random() * 100000)
                );
            }

            const id = $textarea.attr('id');

            if (tinymce.get(id)) {
                return;
            }

            tinymce.init({
                selector: '#' + id,
                convert_urls: false,
                relative_urls: false,
                remove_script_host: false,
                document_base_url: window.location.origin + '/',
                menubar: false,
                branding: false,
                height: 250,
                plugins: 'link lists image media table code',
                toolbar: 'undo redo | bold italic underline | bullist numlist | link image media | table | code',

                file_picker_callback: function (callback, value, meta) {
                    openTinyMceElfinder(callback, meta);
                }
            });
        });
    }

    function openTinyMceElfinder(callback, meta) {
        const connectorUrl = window.DevflowCustomFields?.elfinderConnectorUrl;

        if (!connectorUrl) {
            alert('Missing elFinder connector URL.');
            return;
        }

        let $modal = $('#cf-elfinder-modal');

        if (!$modal.length) {
            $('body').append(`
            <div id="cf-elfinder-modal" class="modal fade" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-lg" style="width:90%;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title">Select File</h4>
                        </div>
                        <div class="modal-body">
                            <div id="cf-elfinder-browser" style="height:500px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        `);

            $modal = $('#cf-elfinder-modal');
        }

        $modal
            .css('z-index', 200000)
            .modal({
                backdrop: true,
                keyboard: true,
                show: true
            });

        setTimeout(function () {
            $('.modal-backdrop')
                .last()
                .addClass('cf-custom-fields-backdrop')
                .css('z-index', 199990);
        }, 10);

        const $browser = $('#cf-elfinder-browser');
        $browser.empty();

        $browser.elfinder({
            url: connectorUrl,
            lang: 'en',
            commandsOptions: {
                getfile: {
                    multiple: false,
                    folders: false,
                    oncomplete: 'destroy'
                }
            },
            onlyMimes: meta.filetype === 'image'
                ? ['image']
                : meta.filetype === 'media'
                    ? ['video', 'audio']
                    : [],
            getFileCallback: function (file) {
                const selected = Array.isArray(file) ? file[0] : file;

                if (!selected || !selected.url) {
                    return;
                }

                callback(selected.url, {
                    text: selected.name || selected.url.split('/').pop(),
                    title: selected.name || '',
                    alt: selected.name || ''
                });

                $modal.modal('hide');
            }
        });
    }

    $(function () {
        initializeTinyMce(document);
    });

    function cleanupCustomFieldModalState(modalSelector) {
        const hasOtherOpenModals = $('.modal.in, .modal:visible')
            .not(modalSelector)
            .length > 0;

        $('.modal-backdrop.cf-custom-fields-backdrop').remove();

        if (!hasOtherOpenModals) {
            $('body').removeClass('modal-open').css('padding-right', '');
        }
    }

    function getTemplateHtml($template) {
        const template = $template.get(0);

        if (!template) {
            return '';
        }

        if (template.content) {
            return template.innerHTML || $(template.content).html() || '';
        }

        return $template.html() || '';
    }

    $(document).on('click', '.cf-add-flexible-row', function () {
        const $button = $(this);
        const $flexible = $button.closest('.cf-flexible-render');
        const $select = $flexible.find('.cf-flexible-layout-select').first();
        const layout = $select.val();

        if (!layout) {
            announceCustomFieldAction('Please select a layout first.');
            return;
        }

        const $template = $flexible.find(
            '.cf-flexible-templates template[data-layout="' + layout + '"]'
        ).first();

        if (!$template.length) {
            console.warn('Flexible template not found for layout:', layout);
            announceCustomFieldAction('Selected layout template was not found.');
            return;
        }

        const prefix = $button.data('prefix');
        const index = nextFlexibleIndex($flexible);
        const uuid = uniqueFlexibleUuid();

        let html = getTemplateHtml($template);

        if (!html.trim()) {
            console.warn('Flexible template HTML is empty for layout:', layout, $template);
            announceCustomFieldAction('Selected layout template is empty.');
            return;
        }

        html = html
            .replaceAll('__PREFIX__', prefix)
            .replaceAll('__INDEX__', index)
            .replaceAll('__UUID__', uuid);

        const $row = $(html);

        $flexible.find('> .cf-flexible-items').append($row);

        initFlexibleSortable($flexible);
        refreshFlexibleIndexes($flexible);
        updateNestedRepeaterPrefixes($flexible);
        initGallerySortable($row);

        if (typeof initializeTinyMce === 'function') {
            initializeTinyMce($row);
        }

        evaluateConditionalLogic($row);

        $select.val('');

        announceCustomFieldAction('Flexible content layout added.');
    });

    function getConditionalScope($field) {
        const $flexRow = $field.closest('.cf-flexible-item');

        if ($flexRow.length) {
            return $flexRow;
        }

        const $repeaterRow = $field.closest('.cf-repeater-item');

        if ($repeaterRow.length) {
            return $repeaterRow;
        }

        return $field.closest('form');
    }

    $(document).on('click', '.cf-remove-flexible-row', function () {
        const $flexible = $(this).closest('.cf-flexible-render');
        const $layout = $(this).closest('.cf-flexible-item');

        $layout.find('textarea.tinymce').each(function () {
            const id = $(this).attr('id');

            if (
                id &&
                typeof tinymce !== 'undefined' &&
                tinymce.get(id)
            ) {
                tinymce.get(id).remove();
            }
        });

        $layout.remove();

        refreshFlexibleIndexes($flexible);

        announceCustomFieldAction('Flexible content layout removed.');
    });

    $(document).on('click', '.cf-gallery-preview-button', function () {
        const $button = $(this);
        const $item = $button.closest('.cf-gallery-item');

        const url = $item.data('url');
        const name = $item.data('name') || 'Image preview';

        const $modal = ensureGalleryPreviewModal();

        $modal.data('return-focus', $button);

        $('#cf-gallery-preview-title').text(name);
        $('#cf-gallery-preview-image')
            .attr('src', url)
            .attr('alt', name);

        $modal
            .css('z-index', 210000)
            .modal({
                backdrop: true,
                keyboard: true,
                show: true
            });

        setTimeout(function () {
            $('.modal-backdrop')
                .last()
                .addClass('cf-custom-fields-backdrop')
                .css('z-index', 209990);
            $modal.find('.cf-gallery-preview-close').focus();
        }, 10);
    });

    $(document).on('hidden.bs.modal', '#cf-gallery-preview-modal', function () {
        const $returnFocus = $(this).data('return-focus');

        if ($returnFocus && $returnFocus.length) {
            $returnFocus.focus();
        }
    });

    $(document).on('keydown', '.cf-gallery-item', function (event) {
        const $item = $(this);
        const $field = $item.closest('.cf-gallery-field');

        if (event.key === 'ArrowLeft') {
            const $prev = $item.prev('.cf-gallery-item');

            if ($prev.length) {
                event.preventDefault();
                $item.insertBefore($prev);
                updateGalleryValue($field);
                $item.focus();
                announceCustomFieldAction('Gallery image moved left.');
            }
        }

        if (event.key === 'ArrowRight') {
            const $next = $item.next('.cf-gallery-item');

            if ($next.length) {
                event.preventDefault();
                $item.insertAfter($next);
                updateGalleryValue($field);
                $item.focus();
                announceCustomFieldAction('Gallery image moved right.');
            }
        }
    });

    $(document).on('click', '.cf-gallery-move-left', function () {
        const $item = $(this).closest('.cf-gallery-item');
        const $field = $(this).closest('.cf-gallery-field');
        const $prev = $item.prev('.cf-gallery-item');

        if ($prev.length) {
            $item.insertBefore($prev);
            updateGalleryValue($field);
            $(this).focus();
        }

        announceCustomFieldAction('Gallery image moved left.');
    });

    $(document).on('click', '.cf-gallery-move-right', function () {
        const $item = $(this).closest('.cf-gallery-item');
        const $field = $(this).closest('.cf-gallery-field');
        const $next = $item.next('.cf-gallery-item');

        if ($next.length) {
            $item.insertAfter($next);
            updateGalleryValue($field);
            $(this).focus();
        }

        announceCustomFieldAction('Gallery image moved right.');
    });

    $(document).on('click', '.cf-open-elfinder', function () {
        const $button = $(this);

        const target = $button.data('target');
        const $input = $('#' + target);
        const $field = $input.closest('.cf-gallery-field');

        if (typeof $('<div>').elfinder !== 'function') {
            alert('elFinder is not available on this page.');
            return;
        }

        let $modal = $('#cf-elfinder-modal');

        if (!$modal.length) {
            $('body').append(`
                <div id="cf-elfinder-modal" class="modal fade" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-lg" style="width:90%;">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                <h4 class="modal-title">Select Images</h4>
                            </div>
                            <div class="modal-body">
                                <div id="cf-elfinder-browser" style="height:500px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `);

            $modal = $('#cf-elfinder-modal');
        }

        $modal.modal('show');

        $modal.data('return-focus', $button);

        const $browser = $('#cf-elfinder-browser');
        $browser.empty();

        $browser.elfinder({
            url: window.DevflowCustomFields?.elfinderConnectorUrl || '/admin/connector/',
            lang: 'en',
            commandsOptions: {
                getfile: {
                    multiple: true,
                    folders: false,
                    oncomplete: 'destroy'
                }
            },
            getFileCallback: function (files) {
                const current = parseGalleryValue($input);
                const selectedFiles = Array.isArray(files) ? files : [files];

                selectedFiles.forEach(function (file) {
                    const url = file.url || '';
                    const mime = file.mime || '';
                    const name = file.name || url.split('/').pop();

                    if (!url) {
                        return;
                    }

                    const exists = current.some(function (item) {
                        return item.url === url;
                    });

                    if (!exists) {
                        current.push({
                            url: url,
                            name: name,
                            mime: mime
                        });
                    }
                });

                saveGalleryValue($input, current);
                renderGalleryPreview($field, current);

                $modal.modal('hide');
            }
        });
    });

    $(document).on('hidden.bs.modal', '#cf-elfinder-modal', function () {
        const $browser = $('#cf-elfinder-browser');

        try {
            const elf = $browser.elfinder('instance');

            if (elf) {
                elf.destroy();
            }
        } catch (e) {
            // ignore
        }

        $(this).remove();
        cleanupCustomFieldModalState('#cf-elfinder-modal');
    });

    $(document).on('click', '.cf-gallery-remove', function () {
        const $item = $(this).closest('.cf-gallery-item');
        const $field = $(this).closest('.cf-gallery-field');
        const $input = $field.find('.cf-gallery-value');

        const removeUrl = $item.data('url');

        const items = parseGalleryValue($input).filter(function (item) {
            return item.url !== removeUrl;
        });

        saveGalleryValue($input, items);
        renderGalleryPreview($field, items);
        announceCustomFieldAction('Gallery image removed.');
    });

    $(document).on('click', '.cf-oembed-preview-button', function () {
        const $button = $(this);
        const target = $button.data('target');
        const $input = $('#' + target);
        const $field = $input.closest('.cf-oembed-field');
        const $preview = $field.find('.cf-oembed-preview');

        const url = $input.val();

        if (!url) {
            $preview.html('<div class="alert alert-warning">Please enter a URL.</div>');
            announceCustomFieldAction('Please enter a URL before previewing.');
            return;
        }

        $button.prop('disabled', true).text('Loading...');
        $preview.html('<div class="text-muted">Loading preview...</div>');

        $.ajax({
            url: window.DevflowCustomFields?.oembedPreviewUrl || '/admin/plugin/custom-fields/ajax/oembed-preview/',
            method: 'POST',
            dataType: 'json',
            data: {
                url: url
            }
        }).done(function (response) {
            if (response.success) {
                $preview.html(response.html);
                announceCustomFieldAction('oEmbed preview loaded.');
                return;
            }

            $preview.html('<div class="alert alert-danger">' + response.message + '</div>');
            announceCustomFieldAction(response.message || 'Unable to load oEmbed preview.');
        }).fail(function () {
            $preview.html('<div class="alert alert-danger">Unable to load preview.</div>');
            announceCustomFieldAction('Unable to load oEmbed preview.');
        }).always(function () {
            $button.prop('disabled', false).text('Preview');
        });
    });
})(jQuery);
