(function ($) {
    'use strict';

    let fieldIndex = 0;

    const fieldDefaults = {
        text: { label: 'Text Field', type: 'text' },
        textarea: { label: 'Textarea', type: 'textarea' },
        richtext: { label: 'Rich Text', type: 'richtext' },
        email: { label: 'Email', type: 'email' },
        password: { label: 'Password', type: 'password' },
        tel: { label: 'Telephone', type: 'tel' },
        select: { label: 'Select Dropdown', type: 'select', options: ['Option 1', 'Option 2'] },
        checkbox: { label: 'Checklist', type: 'checkbox', options: ['Option 1', 'Option 2'] },
        radio: { label: 'Radio Buttons', type: 'radio', options: ['Option 1', 'Option 2'] },
        gallery: { label: 'Gallery', type: 'gallery' },
        image: { label: 'Image', type: 'image' },
        oembed: { label: 'oEmbed', type: 'oembed' },
        help: { label: 'Help Text', type: 'help' },

        repeater: {
            label: 'Repeater',
            type: 'repeater',
            children: { default: [] }
        },

        flexible_content: {
            label: 'Flexible Content',
            type: 'flexible_content',
            layouts: []
        },

        layout_2col: {
            label: '2 Column Container',
            type: 'layout_2col',
            children: { left: [], right: [] }
        },

        layout_3col: {
            label: '3 Column Container',
            type: 'layout_3col',
            children: { left: [], middle: [], right: [] }
        }
    };

    function slugify(value) {
        return value.toLowerCase()
            .replace(/[^a-z0-9_]+/g, '_')
            .replace(/^_+|_+$/g, '');
    }

    function createField(type) {
        const defaults = fieldDefaults[type] || fieldDefaults.text;
        const id = 'field_' + Date.now() + '_' + fieldIndex++;

        const galleryPreviewSizeStyle = type === 'gallery'
            ? ''
            : 'style="display:none;"';

        let nestedHtml = '';

        if (type === 'gallery') {
            nestedHtml = `
            <div class="form-group cf-gallery-preview-size-wrapper" ${galleryPreviewSizeStyle}>
                <label>Gallery Preview Size</label>
                <select class="form-control cf-gallery-preview-size">
                    <option value="">Use default</option>
                    <option value="small">Small</option>
                    <option value="medium">Medium</option>
                    <option value="large">Large</option>
                </select>
            </div>
        `;
        }

        if (type === 'layout_2col') {
            nestedHtml = `
            <div class="cf-layout-grid cf-layout-2col">
                <div class="cf-layout-column">
                    <strong>Left Column</strong>
                    <div class="cf-nested-drop-zone" data-zone="left"></div>
                </div>
                <div class="cf-layout-column">
                    <strong>Right Column</strong>
                    <div class="cf-nested-drop-zone" data-zone="right"></div>
                </div>
            </div>
        `;
        }

        if (type === 'layout_3col') {
            nestedHtml = `
            <div class="cf-layout-grid cf-layout-3col">
                <div class="cf-layout-column">
                    <strong>Left Column</strong>
                    <div class="cf-nested-drop-zone" data-zone="left"></div>
                </div>
                <div class="cf-layout-column">
                    <strong>Middle Column</strong>
                    <div class="cf-nested-drop-zone" data-zone="middle"></div>
                </div>
                <div class="cf-layout-column">
                    <strong>Right Column</strong>
                    <div class="cf-nested-drop-zone" data-zone="right"></div>
                </div>
            </div>
        `;
        }

        if (type === 'repeater') {
            nestedHtml = `
            <div class="cf-repeater-builder">
                <strong>Repeater Fields</strong>
                <p class="text-muted">Drop fields here. These fields will repeat as rows.</p>
                <div class="cf-nested-drop-zone" data-zone="default"></div>
            </div>
        `;
        }

        if (type === 'flexible_content') {
            nestedHtml = `
            <div class="cf-flexible-builder">
                <strong>Flexible Content Layouts</strong>
                <p class="text-muted">Create layouts, then drop fields into each layout.</p>
    
                <button type="button" class="btn btn-xs btn-primary cf-add-flex-layout">
                    Add Layout
                </button>
    
                <div class="cf-flex-layouts"></div>
            </div>
        `;
        }

        return `
        <div class="cf-field-panel" data-field-id="${id}" data-type="${type}">
            <div class="cf-field-header">
                <span class="cf-handle"><i class="fa fa-arrows"></i></span>
                <strong class="cf-field-title">${defaults.label}</strong>
                <span class="label label-default">${type}</span>

                <div class="pull-right">
                    <button type="button" class="btn btn-xs btn-default cf-toggle" aria-expanded="true">
                        <i class="fa fa-chevron-down"></i>
                    </button>
                    <button type="button" class="btn btn-xs btn-default cf-clone">Clone</button>
                    <button type="button" class="btn btn-xs btn-danger cf-remove">Remove</button>
                </div>
            </div>

            <div class="cf-field-body">
                <div class="row">
                    <div class="col-md-6">
                        <label>Field Name</label>
                        <input type="text" class="form-control cf-name" value="${slugify(defaults.label)}">
                    </div>

                    <div class="col-md-6">
                        <label>Label</label>
                        <input type="text" class="form-control cf-label" value="${defaults.label}">
                    </div>
                </div>

                <div class="cf-standard-settings ${type.startsWith('layout_') ? 'hidden' : ''}">
                    <div class="row" style="margin-top:10px;">
                        <div class="col-md-6">
                            <label>Placeholder</label>
                            <input type="text" class="form-control cf-placeholder">
                        </div>

                        <div class="col-md-6">
                            <label>Default Value</label>
                            <input type="text" class="form-control cf-default">
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:10px;">
                        <label>Help Text</label>
                        <textarea class="form-control cf-help" rows="2"></textarea>
                    </div>

                    <div class="cf-field-flags">
                        <label><input type="checkbox" class="cf-required"> Required</label>
                        <label><input type="checkbox" class="cf-hidden"> Hidden</label>
                        <label><input type="checkbox" class="cf-readonly"> Readonly</label>
                        <label><input type="checkbox" class="cf-disabled"> Disabled</label>
                    </div>
                </div>

                <div class="cf-options-wrapper ${['select', 'checkbox', 'radio'].includes(type) ? '' : 'hidden'}">
                    <hr>
                    <label>Options</label>
                    <textarea class="form-control cf-options" rows="4">Option 1
Option 2</textarea>
                </div>
                
                <div class="cf-appearance-wrapper">
                    <hr>
                
                    <h5>Appearance</h5>
                
                    <div class="row">
                        <div class="col-md-4">
                            <label>Width</label>
                            <select class="form-control cf-style-width">
                                <option value="">Default</option>
                                <option value="25">25%</option>
                                <option value="33">33%</option>
                                <option value="50">50%</option>
                                <option value="66">66%</option>
                                <option value="75">75%</option>
                                <option value="100">100%</option>
                            </select>
                        </div>
                
                        <div class="col-md-4">
                            <label>Label Color</label>
                            <input type="color" class="form-control cf-style-label-color" placeholder="#949494">
                        </div>
                
                        <div class="col-md-4">
                            <label>Input Field Background</label>
                            <input type="color" class="form-control cf-style-input-background" value="#FFFFFF">
                        </div>
                    </div>
                
                    <div class="row" style="margin-top:10px;">
                        <div class="col-md-4">
                            <label>Text Color</label>
                            <input type="color" class="form-control cf-style-text-color" placeholder="#949494">
                        </div>
                
                        <div class="col-md-4">
                            <label>Border Radius</label>
                            <input type="text" class="form-control cf-style-border-radius" placeholder="4px">
                        </div>
                
                        <div class="col-md-4">
                            <label>Font Size</label>
                            <input type="text" class="form-control cf-style-font-size" placeholder="16px">
                        </div>
                    </div>
                
                    <div class="row" style="margin-top:10px;">
                        <div class="col-md-6">
                            <label>Google Font</label>
                            <select class="form-control cf-style-google-font">
                                <option value="">Default</option>
                                <option value="Inter">Inter</option>
                                <option value="Roboto">Roboto</option>
                                <option value="Open Sans">Open Sans</option>
                                <option value="Lato">Lato</option>
                                <option value="Montserrat">Montserrat</option>
                                <option value="Poppins">Poppins</option>
                                <option value="Nunito">Nunito</option>
                                <option value="Raleway">Raleway</option>
                                <option value="Merriweather">Merriweather</option>
                                <option value="Playfair Display">Playfair Display</option>
                                <option value="Source Sans 3">Source Sans 3</option>
                                <option value="Oswald">Oswald</option>
                                <option value="Rubik">Rubik</option>
                                <option value="DM Sans">DM Sans</option>
                                <option value="Fira Sans">Fira Sans</option>
                            </select>
                        </div>
                
                        <div class="col-md-6">
                            <label>CSS Class</label>
                            <input type="text" class="form-control cf-style-class" placeholder="custom-field-class">
                        </div>
                    </div>
                </div>
                
                <hr>

                <div class="cf-conditional-wrapper">
                    <h5>Conditional Logic</h5>

                    <label>
                        <input type="checkbox" class="cf-conditional-enabled">
                        Enable conditional logic
                    </label>

                    <div class="cf-conditional-settings hidden">
                        <div class="row">
                            <div class="col-md-4">
                                <label>Action</label>
                                <select class="form-control cf-conditional-action">
                                    <option value="show">Show this field if</option>
                                    <option value="hide">Hide this field if</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label>Logic</label>
                                <select class="form-control cf-conditional-logic">
                                    <option value="all">All rules match</option>
                                    <option value="any">Any rule matches</option>
                                </select>
                            </div>
                        </div>

                        <div class="cf-conditional-rules"></div>

                        <button type="button" class="btn btn-xs btn-default cf-add-condition-rule">
                            Add Rule
                        </button>
                    </div>
                </div>

                ${nestedHtml}
            </div>
        </div>
    `;
    }

    function uniqueFlexLayoutId() {
        return 'layout_' + Date.now() + '_' + Math.floor(Math.random() * 100000);
    }

    function createFlexLayout(layout) {
        layout = layout || {};

        const id = layout.id || uniqueFlexLayoutId();
        const name = layout.name || '';
        const label = layout.label || 'New Layout';
        const fields = layout.fields || [];

        const $layout = $(`
        <div class="cf-flex-layout" data-layout-id="${id}">
            <div class="cf-flex-layout-header">
                <span class="cf-flex-layout-handle">
                    <i class="fa fa-arrows"></i>
                </span>

                <strong class="cf-flex-layout-title">${label}</strong>

                <div class="pull-right">
                    <button type="button" class="btn btn-xs btn-default cf-flex-layout-toggle">
                        <i class="fa fa-chevron-down"></i>
                    </button>
                    <button type="button" class="btn btn-xs btn-default cf-flex-layout-clone">Clone</button>
                    <button type="button" class="btn btn-xs btn-danger cf-flex-layout-remove">Remove</button>
                </div>
            </div>

            <div class="cf-flex-layout-body">
                <div class="row">
                    <div class="col-md-6">
                        <label>Layout Name</label>
                        <input type="text" class="form-control cf-flex-layout-name" value="${name}">
                    </div>

                    <div class="col-md-6">
                        <label>Layout Label</label>
                        <input type="text" class="form-control cf-flex-layout-label" value="${label}">
                    </div>
                </div>

                <hr>

                <p class="text-muted">Drop fields into this layout.</p>
                <div class="cf-nested-drop-zone cf-flex-layout-drop-zone" data-zone="flex_layout_fields"></div>
            </div>
        </div>
    `);

        const $zone = $layout.find('.cf-flex-layout-drop-zone');

        fields.forEach(function (savedField) {
            $zone.append(renderSavedField(savedField));
        });

        initDropZones($layout);

        return $layout;
    }

    function initDropZones(context) {
        const $context = context ? $(context) : $(document);

        $context.find('.cf-nested-drop-zone').droppable({
            accept: '.cf-field-type, .cf-field-panel',
            greedy: true,
            drop: function (event, ui) {
                if (ui.draggable.hasClass('cf-field-type')) {
                    const type = ui.draggable.data('type');
                    const $dropZone = $(this);
                    const isFlexLayoutDropZone = $dropZone.hasClass('cf-flex-layout-drop-zone');

                    if (type && type.startsWith('layout_') && !isFlexLayoutDropZone) {
                        alert('Column containers can only be dropped into the root builder or inside Flexible Content layouts.');
                        return;
                    }

                    $(this).append(createField(type));
                    initDropZones(this);
                    serializeFields();
                }
            }
        }).sortable({
            connectWith: '.cf-nested-drop-zone, #cf-drop-zone',
            handle: '.cf-handle',
            update: serializeFields
        });
    }

    function serializeFieldPanel($field, index) {
        const type = $field.data('type');

        const field = {
            id: $field.data('field-id'),
            type: type,
            name: $field.find('> .cf-field-body .cf-name').first().val(),
            label: $field.find('> .cf-field-body .cf-label').first().val(),
            placeholder: $field.find('> .cf-field-body .cf-placeholder').first().val(),
            help: $field.find('> .cf-field-body .cf-help').first().val(),
            default: $field.find('> .cf-field-body .cf-default').first().val(),

            required: $field.find('> .cf-field-body .cf-required').first().is(':checked'),
            hidden: $field.find('> .cf-field-body .cf-hidden').first().is(':checked'),
            readonly: $field.find('> .cf-field-body .cf-readonly').first().is(':checked'),
            disabled: $field.find('> .cf-field-body .cf-disabled').first().is(':checked'),
            gallery_preview_size: $field.find('> .cf-field-body .cf-gallery-preview-size').first().val() || '',

            options: $field.find('> .cf-field-body .cf-options')
                .first()
                .val()
                ?.split('\n')
                .map(option => option.trim())
                .filter(Boolean) ?? [],

            validation: {
                min: $field.find('> .cf-field-body .cf-validation-min').first().val(),
                max: $field.find('> .cf-field-body .cf-validation-max').first().val(),
                pattern: $field.find('> .cf-field-body .cf-validation-pattern').first().val()
            },

            styles: {
                width: $field.find('> .cf-field-body .cf-style-width').first().val(),
                label_color: $field.find('> .cf-field-body .cf-style-label-color').first().val(),
                input_background: $field.find('> .cf-field-body .cf-style-input-background').first().val(),
                text_color: $field.find('> .cf-field-body .cf-style-text-color').first().val(),
                border_radius: $field.find('> .cf-field-body .cf-style-border-radius').first().val(),
                css_class: $field.find('> .cf-field-body .cf-style-class').first().val(),
                google_font: $field.find('> .cf-field-body .cf-style-google-font').first().val(),
                font_size: $field.find('> .cf-field-body .cf-style-font-size').first().val()
            },

            sort_order: index
        };

        const $zones = $field.find('> .cf-field-body .cf-nested-drop-zone');

        if ($zones.length > 0) {
            field.children = {};

            $zones.each(function () {
                const zone = $(this).data('zone') || 'default';

                field.children[zone] = [];

                $(this).children('.cf-field-panel').each(function (childIndex) {
                    field.children[zone].push(serializeFieldPanel($(this), childIndex));
                });
            });
        }

        if (type === 'flexible_content') {
            field.layouts = [];

            $field.find('> .cf-field-body .cf-flex-layouts > .cf-flex-layout').each(function (layoutIndex) {
                field.layouts.push(serializeFlexLayout($(this), layoutIndex));
            });
        }

        const $conditional = $field.find('> .cf-field-body .cf-conditional-wrapper').first();

        const conditional = {
            enabled: $conditional.find('.cf-conditional-enabled').is(':checked'),
            action: $conditional.find('.cf-conditional-action').val() || 'show',
            logic: $conditional.find('.cf-conditional-logic').val() || 'all',
            rules: []
        };

        $conditional.find('.cf-condition-rule').each(function () {
            conditional.rules.push({
                field: $(this).find('.cf-condition-field').val(),
                operator: $(this).find('.cf-condition-operator').val(),
                value: $(this).find('.cf-condition-value').val()
            });
        });

        field.conditional_logic = conditional;

        return field;
    }

    function serializeFields() {
        const fields = [];

        $('#cf-drop-zone > .cf-field-panel').each(function (index) {
            fields.push(serializeFieldPanel($(this), index));
        });

        $('#fields-json').val(JSON.stringify(fields));
    }

    function serializeFlexLayout($layout, index) {
        const fields = [];

        $layout.find('> .cf-flex-layout-body > .cf-flex-layout-drop-zone > .cf-field-panel').each(function (fieldIndex) {
            fields.push(serializeFieldPanel($(this), fieldIndex));
        });

        return {
            id: $layout.data('layout-id'),
            name: $layout.find('.cf-flex-layout-name').val(),
            label: $layout.find('.cf-flex-layout-label').val(),
            fields: fields,
            sort_order: index
        };
    }

    function renderSavedField(field) {
        const type = field.type || field.field_type || 'text';

        const $field = $(createField(type));

        $field.attr('data-field-id', field.id || uniqueFieldId());
        $field.attr('data-type', type);

        $field.find('> .cf-field-body .cf-name').first().val(field.name || field.field_name || '');
        $field.find('> .cf-field-body .cf-label').first().val(field.label || field.field_label || '');
        $field.find('> .cf-field-body .cf-placeholder').first().val(field.placeholder || field.field_placeholder || '');
        $field.find('> .cf-field-body .cf-help').first().val(field.help || field.field_help || '');
        $field.find('> .cf-field-body .cf-default').first().val(field.default || field.field_default || '');

        const settings = field.settings || field.field_settings || {};

        $field.find('> .cf-field-body .cf-required').first().prop('checked', !!settings.required);
        $field.find('> .cf-field-body .cf-hidden').first().prop('checked', !!settings.hidden);
        $field.find('> .cf-field-body .cf-readonly').first().prop('checked', !!settings.readonly);
        $field.find('> .cf-field-body .cf-disabled').first().prop('checked', !!settings.disabled);
        $field.find('> .cf-field-body .cf-gallery-preview-size')
            .first()
            .val(settings.gallery_preview_size || '');
        $field.find('> .cf-gallery-preview-size-wrapper')
            .toggle(type === 'gallery');

        const options = field.options || field.field_options || [];

        if (Array.isArray(options)) {
            $field.find('> .cf-field-body .cf-options').first().val(options.join('\n'));
        }

        const validation = field.validation || field.validation_rules || {};

        $field.find('> .cf-field-body .cf-validation-min').first().val(validation.min || '');
        $field.find('> .cf-field-body .cf-validation-max').first().val(validation.max || '');
        $field.find('> .cf-field-body .cf-validation-pattern').first().val(validation.pattern || '');

        const styles = field.styles || field.style_settings || {};

        $field.find('> .cf-field-body .cf-style-width').first().val(styles.width || '');
        $field.find('> .cf-field-body .cf-style-label-color').first().val(styles.label_color || '');
        $field.find('> .cf-field-body .cf-style-input-background').first().val(styles.input_background || '');
        $field.find('> .cf-field-body .cf-style-text-color').first().val(styles.text_color || '');
        $field.find('> .cf-field-body .cf-style-border-radius').first().val(styles.border_radius || '');
        $field.find('> .cf-field-body .cf-style-class').first().val(styles.css_class || styles.class || '');
        $field.find('> .cf-field-body .cf-style-google-font').first().val(styles.google_font || '');
        $field.find('> .cf-field-body .cf-style-font-size').first().val(styles.font_size || '');

        const children = field.children || {};

        Object.keys(children).forEach(function (zone) {
            const $zone = $field.find('> .cf-field-body .cf-nested-drop-zone[data-zone="' + zone + '"]').first();

            if (!$zone.length) {
                return;
            }

            children[zone].forEach(function (childField) {
                $zone.append(renderSavedField(childField));
            });
        });

        const fieldSettings = field.settings || field.field_settings || {};
        const layouts = field.layouts || fieldSettings.layouts || [];

        if (type === 'flexible_content' && Array.isArray(layouts)) {
            const $layouts = $field.find('> .cf-field-body .cf-flex-layouts').first();

            $layouts.empty();

            layouts.forEach(function (layout) {
                $layouts.append(createFlexLayout(layout));
            });

            $layouts.sortable({
                handle: '.cf-flex-layout-handle',
                update: serializeFields
            });
        }

        initDropZones($field);

        const conditional = field.conditional_logic || field.field_settings?.conditional_logic || {};

        if (conditional.enabled) {
            $field.find('> .cf-field-body .cf-conditional-enabled').first().prop('checked', true);
            $field.find('> .cf-field-body .cf-conditional-settings').first().removeClass('hidden');
        }

        $field.find('> .cf-field-body .cf-conditional-action').first().val(conditional.action || 'show');
        $field.find('> .cf-field-body .cf-conditional-logic').first().val(conditional.logic || 'all');

        const $rules = $field.find('> .cf-field-body .cf-conditional-rules').first();
        $rules.empty();

        (conditional.rules || []).forEach(function (rule) {
            $rules.append(createConditionRule(rule));
        });

        return $field;
    }

    function hydrateSavedFields() {
        const savedFields = window.DevflowCustomFields?.savedFields || [];

        if (!Array.isArray(savedFields) || savedFields.length === 0) {
            return;
        }

        const $dropZone = $('#cf-drop-zone');

        $dropZone.find('.cf-empty-state').remove();
        $dropZone.empty();

        savedFields.forEach(function (field) {
            $dropZone.append(renderSavedField(field));
        });

        initDropZones($dropZone);
        serializeFields();
    }

    $(function () {
        hydrateSavedFields();
    });

    function uniqueFieldId() {
        return 'field_' + Date.now() + '_' + Math.floor(Math.random() * 100000);
    }

    function createConditionRule(rule) {
        rule = rule || {};

        return `
        <div class="cf-condition-rule">
            <div class="row">
                <div class="col-md-4">
                    <label>Field</label>
                    <input type="text" class="form-control cf-condition-field" value="${rule.field || ''}" placeholder="field_name">
                </div>

                <div class="col-md-3">
                    <label>Operator</label>
                    <select class="form-control cf-condition-operator">
                        <option value="equals" ${rule.operator === 'equals' ? 'selected' : ''}>Equals</option>
                        <option value="not_equals" ${rule.operator === 'not_equals' ? 'selected' : ''}>Does not equal</option>
                        <option value="empty" ${rule.operator === 'empty' ? 'selected' : ''}>Is empty</option>
                        <option value="not_empty" ${rule.operator === 'not_empty' ? 'selected' : ''}>Is not empty</option>
                        <option value="contains" ${rule.operator === 'contains' ? 'selected' : ''}>Contains</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label>Value</label>
                    <input type="text" class="form-control cf-condition-value" value="${rule.value || ''}">
                </div>

                <div class="col-md-1">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-danger btn-block cf-remove-condition-rule">
                        <i class="fa fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    }

    function getFieldScopeKey($field) {
        const $flexLayout = $field.closest('.cf-flex-layout');

        if ($flexLayout.length) {
            return 'flex:' + ($flexLayout.data('layout-id') || $flexLayout.find('.cf-flex-layout-name').val() || 'layout');
        }

        const $repeater = $field.closest('.cf-field-panel[data-type="repeater"]');

        if ($repeater.length && !$field.is($repeater)) {
            return 'repeater:' + ($repeater.data('field-id') || 'repeater');
        }

        const $containerZone = $field.closest('.cf-nested-drop-zone');

        if ($containerZone.length) {
            const $container = $containerZone.closest('.cf-field-panel');

            return 'container:' +
                ($container.data('field-id') || 'container') +
                ':' +
                ($containerZone.data('zone') || 'default');
        }

        return 'root';
    }

    function validateUniqueFieldNames() {
        const scopes = {};
        const errors = [];

        $('.cf-field-panel').removeClass('has-error cf-duplicate-name');
        $('.cf-duplicate-name-message').remove();

        $('.cf-field-panel').each(function () {
            const $field = $(this);
            const type = $field.data('type');

            if (type === 'help' || type === 'layout_2col' || type === 'layout_3col') {
                return;
            }

            const name = ($field.find('> .cf-field-body .cf-name').first().val() || '').trim();

            if (!name) {
                return;
            }

            const scope = getFieldScopeKey($field);
            const key = scope + ':' + name;

            scopes[key] = scopes[key] || [];
            scopes[key].push($field);
        });

        Object.keys(scopes).forEach(function (key) {
            const matches = scopes[key];

            if (matches.length <= 1) {
                return;
            }

            matches.forEach(function ($field) {
                $field.addClass('has-error cf-duplicate-name');

                const $nameInput = $field.find('> .cf-field-body .cf-name').first();

                $nameInput.after(`
                <p class="text-danger cf-duplicate-name-message">
                    This field name is already used in this same scope.
                </p>
            `);
            });

            errors.push(key);
        });

        return errors;
    }

    $(function () {
        $('.cf-field-type').draggable({
            helper: 'clone',
            revert: 'invalid',
            appendTo: 'body',
            zIndex: 10000
        });

        $('#cf-drop-zone').droppable({
            accept: '.cf-field-type',
            drop: function (event, ui) {
                const type = ui.draggable.data('type');

                $(this).find('.cf-empty-state').remove();
                $(this).append(createField(type));

                initDropZones(this);
                serializeFields();
            }
        }).sortable({
            connectWith: '.cf-nested-drop-zone',
            handle: '.cf-handle',
            update: serializeFields
        });

        initDropZones(document);

        $(document).on('click', '.cf-remove', function () {
            $(this).closest('.cf-field-panel').remove();
            serializeFields();
        });

        $(document).on('click', '.cf-clone', function () {
            const clone = $(this).closest('.cf-field-panel').clone();
            clone.attr('data-field-id', 'field_' + Date.now());
            $(this).closest('.cf-field-panel').after(clone);
            serializeFields();
        });

        $(document).on('input change', '#cf-drop-zone input, #cf-drop-zone textarea, #cf-drop-zone select', serializeFields);

        $('#custom-field-group-form').on('submit', function (e) {
            const duplicateErrors = validateUniqueFieldNames();

            if (duplicateErrors.length > 0) {
                e.preventDefault();

                alert('Please fix duplicate field names before saving.');

                const $firstError = $('.cf-duplicate-name').first();

                if ($firstError.length) {
                    $('html, body').animate({
                        scrollTop: $firstError.offset().top - 100
                    }, 250);

                    $firstError.find('.cf-name').first().focus();
                }

                return false;
            }
        });

        $('#custom-field-group-form').on('submit', function () {
            serializeFields();
        });
    });

    function fieldCollapseKey($panel) {
        return 'cf_field_collapsed_' + $panel.data('field-id');
    }

    function setFieldCollapsed($panel, collapsed) {
        const $body = $panel.children('.cf-field-body');
        const $button = $panel.find('> .cf-field-header .cf-toggle').first();
        const $icon = $button.find('i');

        $body.toggle(!collapsed);
        $panel.toggleClass('is-collapsed', collapsed);
        $button.attr('aria-expanded', collapsed ? 'false' : 'true');

        $icon
            .toggleClass('fa-chevron-down', !collapsed)
            .toggleClass('fa-chevron-right', collapsed);

        localStorage.setItem(fieldCollapseKey($panel), collapsed ? '1' : '0');
    }

    function fieldGroupCollapseKey() {
        const groupId = $('#cf-field-group-id').val() || 'new';

        return 'cf_field_group_collapse_mode_' + groupId;
    }

    function initStickyFieldSidebar() {
        const $sidebar = $('.cf-field-sidebar');
        const $wrap = $('.cf-field-sidebar-wrap');

        if (!$sidebar.length || !$wrap.length) {
            return;
        }

        const topOffset = 70;

        function resetSidebar() {
            $wrap.removeClass('is-sticky-active');

            $sidebar
                .removeClass('is-sticky is-sticky-bottom')
                .css({
                    width: '',
                    left: ''
                });
        }

        function updateStickySidebar() {
            const scrollTop = $(window).scrollTop();
            const wrapTop = $wrap.offset().top;
            const wrapLeft = $wrap.closest('.cf-builder-sidebar-col').offset().left;
            const wrapWidth = $wrap.closest('.cf-builder-sidebar-col').outerWidth();
            const sidebarHeight = $sidebar.outerHeight();
            const wrapHeight = $wrap.outerHeight();

            const start = wrapTop - topOffset;
            const end = wrapTop + wrapHeight - sidebarHeight - topOffset;

            if (scrollTop < start) {
                resetSidebar();
                return;
            }

            if (scrollTop > end) {
                $wrap.addClass('is-sticky-active');

                $sidebar
                    .removeClass('is-sticky')
                    .addClass('is-sticky-bottom')
                    .css({
                        width: '',
                        left: ''
                    });

                return;
            }

            $wrap.addClass('is-sticky-active');

            $sidebar
                .removeClass('is-sticky-bottom')
                .addClass('is-sticky')
                .css({
                    width: wrapWidth,
                    left: wrapLeft
                });
        }

        $(window).on('scroll resize', updateStickySidebar);

        updateStickySidebar();
    }

    $(function () {
        initStickyFieldSidebar();
    });

    $(document).on('input', '#cf-field-type-search', function () {
        const query = ($(this).val() || '').toLowerCase().trim();
        let visibleCount = 0;

        $('.cf-field-category-panel').each(function () {
            const $panel = $(this);
            let panelHasVisibleFields = false;

            $panel.find('.cf-field-type').each(function () {
                const $fieldType = $(this);
                const label = ($fieldType.data('label') || $fieldType.text() || '').toLowerCase();
                const category = ($fieldType.data('category') || '').toLowerCase();

                const matches = query === '' ||
                    label.includes(query) ||
                    category.includes(query);

                $fieldType.toggle(matches);

                if (matches) {
                    panelHasVisibleFields = true;
                    visibleCount++;
                }
            });

            $panel.toggle(panelHasVisibleFields);

            if (query !== '' && panelHasVisibleFields) {
                $panel.find('.panel-collapse').collapse('show');
            }
        });

        $('.cf-field-type-empty').toggleClass('hidden', visibleCount > 0);
    });

    $(document).on('input change', '.cf-name, .cf-flex-layout-name', function () {
        validateUniqueFieldNames();
    });

    $(document).on('click', '.cf-toggle', function () {
        const $panel = $(this).closest('.cf-field-panel');
        const collapsed = !$panel.hasClass('is-collapsed');

        localStorage.removeItem(fieldGroupCollapseKey());

        setFieldCollapsed($panel, collapsed);
    });

    $('#cf-collapse-all').on('click', function () {
        localStorage.setItem(fieldGroupCollapseKey(), 'collapsed');

        $('#cf-drop-zone .cf-field-panel').each(function () {
            setFieldCollapsed($(this), true);
        });
    });

    $('#cf-expand-all').on('click', function () {
        localStorage.setItem(fieldGroupCollapseKey(), 'expanded');

        $('#cf-drop-zone .cf-field-panel').each(function () {
            setFieldCollapsed($(this), false);
        });
    });

    $(function () {
        const mode = localStorage.getItem(fieldGroupCollapseKey());

        $('#cf-drop-zone .cf-field-panel').each(function () {
            const $panel = $(this);

            if (mode === 'expanded') {
                setFieldCollapsed($panel, false);
                return;
            }

            if (mode === 'collapsed') {
                setFieldCollapsed($panel, true);
                return;
            }

            const saved = localStorage.getItem(fieldCollapseKey($panel));

            setFieldCollapsed($panel, saved === null ? true : saved === '1');
        });
    });

    $(function () {
        $('#cf-drop-zone .cf-field-panel').each(function () {
            const $panel = $(this);
            const saved = localStorage.getItem(fieldCollapseKey($panel));

            setFieldCollapsed($panel, saved === null ? true : saved === '1');
        });
    });

    $(document).on('change', '.cf-conditional-enabled', function () {
        const $settings = $(this)
            .closest('.cf-conditional-wrapper')
            .find('.cf-conditional-settings');

        $settings.toggleClass('hidden', !$(this).is(':checked'));

        serializeFields();
    });

    $(document).on('click', '.cf-add-condition-rule', function () {
        const $rules = $(this)
            .closest('.cf-conditional-settings')
            .find('.cf-conditional-rules');

        $rules.append(createConditionRule());

        serializeFields();
    });

    $(document).on('click', '.cf-remove-condition-rule', function () {
        $(this).closest('.cf-condition-rule').remove();

        serializeFields();
    });

    $(document).on('click', '.cf-add-flex-layout', function () {
        const $flexField = $(this).closest('.cf-field-panel');
        const $layouts = $flexField.find('> .cf-field-body .cf-flex-layouts').first();

        $layouts.append(createFlexLayout());

        $layouts.sortable({
            handle: '.cf-flex-layout-handle',
            update: serializeFields
        });

        serializeFields();
    });

    $(document).on('click', '.cf-flex-layout-remove', function () {
        $(this).closest('.cf-flex-layout').remove();
        serializeFields();
    });

    $(document).on('click', '.cf-flex-layout-clone', function () {
        const $layout = $(this).closest('.cf-flex-layout');
        const layoutData = serializeFlexLayout($layout, $layout.index());

        layoutData.id = uniqueFlexLayoutId();
        layoutData.name = layoutData.name + '_copy';
        layoutData.label = layoutData.label + ' Copy';

        $layout.after(createFlexLayout(layoutData));

        serializeFields();
    });

    $(document).on('input change', '.cf-flex-layout input, .cf-flex-layout textarea, .cf-flex-layout select', function () {
        const $layout = $(this).closest('.cf-flex-layout');
        const label = $layout.find('.cf-flex-layout-label').val();

        $layout.find('.cf-flex-layout-title').text(label || 'Untitled Layout');

        serializeFields();
    });

    $(document).on('click', '.cf-flex-layout-toggle', function () {
        $(this).closest('.cf-flex-layout').find('> .cf-flex-layout-body').slideToggle(150);
    });
})(jQuery);
