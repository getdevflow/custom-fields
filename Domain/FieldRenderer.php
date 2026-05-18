<?php

declare(strict_types=1);

namespace Plugin\CustomFields\Domain;

use JsonException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\ValueObjects\Identity\Uuid;
use ReflectionException;

use function App\Shared\Helpers\get_content_attribute;
use function App\Shared\Helpers\get_option;
use function App\Shared\Helpers\get_product_attribute;
use function App\Shared\Helpers\get_user_attribute;
use function array_filter;
use function basename;
use function implode;
use function is_array;
use function is_string;
use function json_decode;
use function Qubus\Security\Helpers\esc_attr;
use function Qubus\Security\Helpers\esc_attr__;
use function Qubus\Security\Helpers\esc_html;
use function Qubus\Security\Helpers\esc_html__;
use function sprintf;

use const JSON_UNESCAPED_SLASHES;

final class FieldRenderer
{
    private array $loadedGoogleFonts = [];

    public static function make(): self
    {
        return new self();
    }

    /**
     * @param string $context
     * @param string|null $objectId
     * @param string $fieldNamePrefix
     * @param string|null $type
     * @param string $position
     * @return string
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function renderFor(
        string $context,
        string $fieldNamePrefix,
        ?string $objectId = null,
        ?string $type = null,
        string $position = 'extended'
    ): string {
        $groups = FieldGroupRepository::make()->activeFor($context, $type, $position);

        $html = '';

        foreach ($groups as $group) {
            $settings = $group['settings'] ?? [];
            $style = $settings['style'] ?? 'default';

            $html .= sprintf(
                '<div class="box box-primary custom-field-group cf-group-style-%s cf-group-position-%s">',
                esc_attr($style),
                esc_attr($position)
            );
            $html .= '<div class="box-header with-border"><h3 class="box-title">' . esc_html($group['title']) . '</h3></div>';
            $html .= '<div class="box-body">';

            foreach ($group['fields'] as $field) {
                $html .= $this->renderField($field, $context, $fieldNamePrefix, $objectId);
            }

            $html .= '</div></div>';
        }

        return $html;
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    private function renderField(array $field, string $context, string $prefix, ?string $objectId = null): string
    {
        $name = $this->fieldName($field);
        $value = $this->value($context, $name, $objectId);
        $inputName = sprintf('%s[%s]', $prefix, $name);

        return match ($this->fieldType($field)) {
            'textarea' => $this->textarea($field, $inputName, $value),
            'richtext' => $this->richtext($field, $inputName, $value),
            'select' => $this->select($field, $inputName, $value),
            'checkbox' => $this->checkboxList($field, $inputName, $value),
            'radio' => $this->radioList($field, $inputName, $value),
            'gallery' => $this->gallery($field, $inputName, $value),
            'oembed' => $this->oembed($field, $inputName, $value),
            'image' => $this->image($field, $inputName, $value),
            'help' => $this->help($field),
            'layout_2col' => $this->layout($field, ['left', 'right'], 'cf-render-layout-2col', $context, $prefix, $objectId),
            'layout_3col' => $this->layout($field, ['left', 'middle', 'right'], 'cf-render-layout-3col', $context, $prefix, $objectId),
            'repeater' => $this->repeater($field, $context, $prefix, $objectId),
            'flexible_content' => $this->flexibleContent(
                field: $field,
                context: $context,
                prefix: $prefix,
                objectId: $objectId
            ),
            default => $this->input($field, $inputName, $value, $this->fieldType($field)),
        };
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeException
     */
    private function value(string $context, string $name, ?string $objectId = null): mixed
    {
        if ($objectId === null) {
            return '';
        }

        return match ($context) {
            'product' => get_product_attribute($objectId, $name, ''),
            'content' => get_content_attribute($objectId, $name, ''),
            'user' => get_user_attribute($objectId, $name, null),
            default => '',
        };
    }

    /**
     * @throws Exception
     */
    private function input(array $field, string $name, mixed $value, string $type = 'text'): string
    {
        $id = $this->fieldId($name);

        return sprintf(
            $this->openFieldWrapper($field) . '
                %s
                <input
                    id="%s"
                    type="%s"
                    name="%s"
                    value="%s"
                    placeholder="%s"
                    class="form-control"
                    %s%s%s%s%s
                >
                %s
                %s
            </div>',
            $this->label($field, $id),
            esc_attr($id),
            esc_attr($type),
            esc_attr($name),
            esc_attr((string) $value),
            esc_attr($field['field_placeholder'] ?? ''),
            $this->attributes($field),
            $this->requiredAttrs($field),
            $this->invalidAttrs($field),
            $this->describedBy($field, $id),
            $this->inputStyle($field),
            $this->helpText($field, $id),
            $this->errorText($field, $id)
        );
    }

    /**
     * @throws Exception
     */
    private function textarea(array $field, string $name, mixed $value): string
    {
        $id = $this->fieldId($name);

        return sprintf(
            $this->openFieldWrapper($field) . '
                %s
                <textarea
                    id="%s"
                    name="%s"
                    placeholder="%s"
                    class="form-control"
                    %s%s%s%s%s
                >%s</textarea>
                %s
                %s
            </div>',
            $this->label($field, $id),
            esc_attr($id),
            esc_attr($name),
            esc_attr($field['field_placeholder'] ?? ''),
            $this->attributes($field),
            $this->requiredAttrs($field),
            $this->invalidAttrs($field),
            $this->describedBy($field, $id),
            $this->inputStyle($field),
            esc_html((string) $value),
            $this->helpText($field, $id),
            $this->errorText($field, $id)
        );
    }

    /**
     * @throws Exception
     */
    private function richtext(array $field, string $name, mixed $value): string
    {
        $id = $this->fieldId($name . '_' . Uuid::generateAsString());

        return sprintf(
            $this->openFieldWrapper($field) . '
                %s
                <textarea
                    id="%s"
                    name="%s"
                    class="form-control tinymce cf-richtext"
                    %s%s%s%s%s
                >%s</textarea>
                %s
                %s
            </div>',
            $this->label($field, $id),
            esc_attr($id),
            esc_attr($name),
            $this->attributes($field),
            $this->requiredAttrs($field),
            $this->invalidAttrs($field),
            $this->describedBy($field, $id),
            $this->inputStyle($field),
            esc_html((string) $value),
            $this->helpText($field, $id),
            $this->errorText($field, $id)
        );
    }

    /**
     * @throws Exception
     */
    private function select(array $field, string $name, mixed $value): string
    {
        $id = $this->fieldId($this->fieldName($field));

        $options = $this->fieldOptions($field) ?? [];
        $options = is_array($options) ? $options : [];

        $html = $this->openFieldWrapper($field);
        $html .= '<label>' . htmlspecialchars($this->fieldLabel($field)) . '</label>';
        $html .= '<select name="' . htmlspecialchars($name) . '" class="form-control select2" ' . $this->attributes($field) . '>';

        foreach ($options as $optionValue => $optionLabel) {
            if (is_int($optionValue)) {
                $optionValue = $optionLabel;
            }

            $selected = (string) $value === (string) $optionValue
            ? ' selected'
            : '';

            $html .= sprintf(
                '<option value="%s"%s>%s</option>',
                esc_attr((string) $optionValue),
                $selected,
                esc_html((string) $optionLabel)
            );
        }

        $html .= '</select>';
        $html .= $this->helpText($field, $id);
        $html .= '</div>';

        return $html;
    }

    /**
     * @throws Exception
     */
    private function checkboxList(array $field, string $name, mixed $value): string
    {
        $id = $this->fieldId($this->fieldName($field));

        $options = $this->fieldOptions($field) ?? [];
        $options = is_array($options) ? $options : [];

        $values = is_array($value) ? $value : [];

        $html = $this->openFieldWrapper($field);
        $html .= '<label>' . htmlspecialchars($this->fieldLabel($field)) . '</label>';

        foreach ($options as $optionValue => $optionLabel) {
            if (is_int($optionValue)) {
                $optionValue = $optionLabel;
            }

            $checked = in_array((string) $optionValue, $values, true)
            ? ' checked'
            : '';

            $html .= sprintf(
                '<div class="checkbox">
                    <label>
                        <input type="checkbox" name="%s[]" value="%s"%s>
                        %s
                    </label>
                </div>',
                esc_attr($name),
                esc_attr((string) $optionValue),
                $checked,
                esc_html((string) $optionLabel)
            );
        }

        $html .= $this->helpText($field, $id);
        $html .= '</div>';

        return $html;
    }

    /**
     * @throws Exception
     */
    private function radioList(array $field, string $name, mixed $value): string
    {
        $id = $this->fieldId($this->fieldName($field));

        $options = $this->fieldOptions($field) ?? [];
        $options = is_array($options) ? $options : [];

        $html = $this->openFieldWrapper($field);
        $html .= '<label>' . htmlspecialchars($this->fieldLabel($field)) . '</label>';

        foreach ($options as $optionValue => $optionLabel) {
            if (is_int($optionValue)) {
                $optionValue = $optionLabel;
            }

            $checked = (string) $value === (string) $optionValue
            ? ' checked'
            : '';

            $html .= sprintf(
                '<div class="radio">
                    <label>
                        <input type="radio" name="%s" value="%s"%s>
                        %s
                    </label>
                </div>',
                esc_attr($name),
                esc_attr((string) $optionValue),
                $checked,
                esc_html((string) $optionLabel)
            );
        }

        $html .= $this->helpText($field, $id);
        $html .= '</div>';

        return $html;
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    private function gallery(array $field, string $name, mixed $value): string
    {
        $id = $this->fieldId($this->fieldName($field));

        $fieldId = 'cf_gallery_' . md5($name);

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $items = is_array($decoded) ? $decoded : [];
        } else {
            $items = is_array($value) ? $value : [];
        }

        $html = $this->openFieldWrapper($field, 'form-group cf-gallery-field', [
            'data-gallery-id' => $fieldId,
        ]);

        $html .= '<label>' . esc_html($this->fieldLabel($field)) . '</label>';

        $html .= sprintf(
            '<input type="hidden" name="%s" id="%s" class="cf-gallery-value" value="%s">',
            esc_attr($name),
            esc_attr($fieldId),
            esc_attr(json_encode($items, JSON_UNESCAPED_SLASHES))
        );

        $settings = $this->fieldSettings($field);
        $previewSize = $settings['gallery_preview_size'] ?? '';

        if ($previewSize === '') {
            $previewSize = (string) get_option('custom_fields_gallery_preview_size', 'medium');
        }

        if (! in_array($previewSize, ['small', 'medium', 'large'], true)) {
            $previewSize = 'medium';
        }

        $html .= sprintf(
            '<div class="cf-gallery-preview cf-gallery-size-%s">',
            esc_attr($previewSize)
        );

        foreach ($items as $item) {
            $url = $item['url'] ?? $item['URL'] ?? $item['path'] ?? '';
            $nameValue = $item['name'] ?? basename($url);

            if ($url === '') {
                continue;
            }

            $html .= sprintf(
                '<div 
                    class="cf-gallery-item"
                    tabindex="0"
                    data-url="%s"
                    data-name="%s"
                    data-mime="%s"
                >
                    <button 
                        type="button" 
                        class="cf-gallery-preview-button"
                        aria-label="Preview image %s"
                    >
                        <img 
                            src="%s" 
                            alt="%s"
                            loading="lazy"
                        >
                    </button>
            
                    <div class="cf-gallery-actions">
                        <button type="button" class="btn btn-xs btn-default cf-gallery-move-left" aria-label="Move %s left">Left</button>
                        <button type="button" class="btn btn-xs btn-default cf-gallery-move-right" aria-label="Move %s right">Right</button>
                        <button type="button" class="btn btn-xs btn-danger cf-gallery-remove" aria-label="Remove image %s">Remove</button>
                    </div>
            
                    <small>%s</small>
                </div>',
                esc_attr($url),
                esc_attr($nameValue),
                esc_attr($item['mime'] ?? ''),
                esc_attr($nameValue),
                esc_attr($url),
                esc_attr($nameValue),
                esc_attr($nameValue),
                esc_attr($nameValue),
                esc_attr($nameValue),
                esc_html($nameValue)
            );
        }

        $html .= '</div>';

        $html .= sprintf(
            '<button type="button" class="btn btn-primary cf-open-elfinder" data-target="%s">
            Select Images
        </button>',
            esc_attr($fieldId)
        );

        $html .= $this->helpText($field, $id);
        $html .= '</div>';

        return $html;
    }

    /**
     * @throws Exception
     */
    private function help(array $field): string
    {
        return $this->openFieldWrapper(
            $field,
            'alert alert-info'
        ) . htmlspecialchars($field['field_help'] ?? $field['help'] ?? '') . '</div>';
    }

    private function attributes(array $field): string
    {
        $settings = $this->fieldSettings($field) ?? [];
        $settings = is_array($settings) ? $settings : [];

        return implode(' ', array_filter([
                !empty($settings['required']) ? 'required' : '',
                !empty($settings['hidden']) ? 'hidden' : '',
                !empty($settings['readonly']) ? 'readonly' : '',
                !empty($settings['disabled']) ? 'disabled' : '',
        ]));
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    private function repeater(
        array $field,
        string $context,
        string $prefix,
        ?string $objectId = null,
    ): string {
        $fieldName = $this->fieldName($field);

        $rows = $this->value($context, $fieldName, $objectId);

        if (! is_array($rows)) {
            $rows = [];
        }

        $templateRow = $field['children']['default'] ?? [];

        $html = $this->openFieldWrapper($field, 'cf-repeater-render');

        $html .= sprintf(
            '<input type="hidden" name="%s[%s]" value="">',
            esc_attr($prefix),
            esc_attr($fieldName)
        );
        $html .= '<label>' . esc_html($this->fieldLabel($field)) . '</label>';

        $html .= '<div class="cf-repeater-items">';

        if ($rows === []) {
            $rows[] = [
                '_uuid' => Uuid::generateAsString(),
            ];
        }

        foreach ($rows as $rowIndex => $rowData) {
            $rowUuid = $rowData['_uuid'] ?? Uuid::generateAsString();

            $html .= sprintf(
                '<div class="cf-repeater-item" aria-label="%s row %d">',
                esc_attr($this->fieldLabel($field)),
                $rowIndex + 1
            );
            $html .= '<div class="cf-repeater-toolbar">';
            $html .= sprintf(
                '<button type="button" class="btn btn-xs btn-danger cf-remove-row" aria-label="Remove %s row %d">Remove</button>',
                esc_attr($this->fieldLabel($field)),
                $rowIndex + 1
            );
            $html .= '</div>';

            foreach ($templateRow as $childField) {
                $html .= $this->renderRepeaterField(
                    field: $childField,
                    rowData: $rowData,
                    rowIndex: $rowIndex,
                    rowUuid: $rowUuid,
                    parentField: $fieldName,
                    prefix: $prefix
                );
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        $html .= '<template class="cf-repeater-template">';

        $html .= '<div class="cf-repeater-item">';
        $html .= '<div class="cf-repeater-toolbar">';
        $html .= '<button type="button" class="btn btn-xs btn-danger cf-remove-row">Remove</button>';
        $html .= '</div>';

        foreach ($templateRow as $childField) {
            $html .= $this->renderRepeaterTemplateField(
                field: $childField,
                parentField: $fieldName
            );
        }

        $html .= '</div>';
        $html .= '</template>';

        $html .= sprintf(
            '<button 
            type="button" 
            class="btn btn-default cf-add-repeater-row"
            data-field="%s"
            data-prefix="%s"
        >
            Add Row
        </button>',
            esc_attr($fieldName),
            esc_attr($prefix)
        );

        $html .= '</div>';

        return $html;
    }

    /**
     * @param array $field
     * @param array $rowData
     * @param int $rowIndex
     * @param string $rowUuid
     * @param string $parentField
     * @param string $prefix
     * @return string
     * @throws Exception
     * @throws ReflectionException
     */
    private function renderRepeaterField(
        array $field,
        array $rowData,
        int $rowIndex,
        string $rowUuid,
        string $parentField,
        string $prefix
    ): string {
        $fieldName = $this->fieldName($field);
        $value = $rowData[$fieldName] ?? '';

        $inputName = sprintf(
            '%s[%s][%d][%s]',
            $prefix,
            $parentField,
            $rowIndex,
            $fieldName
        );

        return match ($this->fieldType($field)) {
            'textarea' => $this->textarea($field, $inputName, $value),
            'richtext' => $this->richtext($field, $inputName, $value),
            'select' => $this->select($field, $inputName, $value),
            'checkbox' => $this->checkboxList($field, $inputName, is_array($value) ? $value : []),
            'radio' => $this->radioList($field, $inputName, $value),
            'gallery' => $this->gallery($field, $inputName, $value),
            'oembed' => $this->oembed($field, $inputName, $value),
            'image' => $this->image($field, $inputName, $value),

            default => $this->input($field, $inputName, $value, $this->fieldType($field)),
        };
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    private function layout(
        array $field,
        array $zones,
        string $class,
        string $context,
        string $prefix,
        ?string $objectId = null
    ): string {
        $html = $this->openFieldWrapper($field, esc_attr($class));

        foreach ($zones as $zone) {
            $html .= '<div class="cf-render-layout-column">';

            foreach (($field['children'][$zone] ?? []) as $child) {
                $html .= $this->renderField(
                    field: $child,
                    context: $context,
                    prefix: $prefix,
                    objectId: $objectId
                );
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @param array $field
     * @param string $parentField
     * @return string
     * @throws Exception
     * @throws ReflectionException
     */
    private function renderRepeaterTemplateField(array $field, string $parentField): string
    {
        $fieldName = $this->fieldName($field);

        $inputName = sprintf(
            '__PREFIX__[%s][__INDEX__][%s]',
            $parentField,
            $fieldName
        );

        return match ($this->fieldType($field)) {
            'textarea' => $this->textarea($field, $inputName, ''),
            'richtext' => $this->richtext($field, $inputName, ''),
            'select' => $this->select($field, $inputName, ''),
            'checkbox' => $this->checkboxList($field, $inputName, []),
            'radio' => $this->radioList($field, $inputName, ''),
            'gallery' => $this->gallery($field, $inputName, []),
            'oembed' => $this->oembed($field, $inputName, ''),
            'image' => $this->image($field, $inputName, []),
            default => $this->input($field, $inputName, '', $this->fieldType($field)),
        };
    }

    private function fieldId(string $name): string
    {
        return 'cf_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
    }

    /**
     * @throws Exception
     */
    private function describedBy(array $field, string $id): string
    {
        $ids = [];

        if (! empty($field['field_help'])) {
            $ids[] = $id . '_help';
        }

        if (! empty($field['error'])) {
            $ids[] = $id . '_error';
        }

        return $ids !== [] ? ' aria-describedby="' . esc_attr(implode(' ', $ids)) . '"' : '';
    }

    private function requiredAttrs(array $field): string
    {
        $settings = $this->fieldSettings($field) ?? [];

        return ! empty($settings['required'])
        ? ' required aria-required="true"'
        : '';
    }

    private function invalidAttrs(array $field): string
    {
        return ! empty($field['error'])
        ? ' aria-invalid="true"'
        : '';
    }

    /**
     * @throws Exception
     */
    private function helpText(array $field, string $id): string
    {
        if (empty($field['field_help'])) {
            return '';
        }

        return sprintf(
            '<p id="%s_help" class="help-block">%s</p>',
            esc_attr($id),
            esc_html($field['field_help'])
        );
    }

    /**
     * @throws Exception
     */
    private function errorText(array $field, string $id): string
    {
        if (empty($field['error'])) {
            return '';
        }

        return sprintf(
            '<p id="%s_error" class="text-danger" role="alert">%s</p>',
            esc_attr($id),
            esc_html($field['error'])
        );
    }

    /**
     * @throws Exception
     */
    private function label(array $field, string $id): string
    {
        $label = $this->fieldLabel($field);
        $settings = $this->fieldSettings($field) ?? [];

        $required = ! empty($settings['required'])
        ? ' <span class="text-danger" aria-hidden="true">*</span><span class="sr-only"> required</span>'
        : '';

        return sprintf(
            '<label for="%s"%s>%s%s</label>',
            esc_attr($id),
            $this->labelStyle($field),
            esc_html($label),
            $required
        );
    }

    /**
     * @throws Exception
     */
    private function oembed(array $field, string $name, mixed $value): string
    {
        $id = $this->fieldId($this->fieldName($field));

        $url = is_string($value) ? $value : '';

        return sprintf(
            $this->openFieldWrapper($field, 'form-group cf-oembed-field') . '
                %s
    
                <div class="input-group">
                    <input
                        id="%s"
                        type="url"
                        name="%s"
                        value="%s"
                        placeholder="%s"
                        class="form-control cf-oembed-url"
                        %s%s%s%s
                    >
    
                    <span class="input-group-btn">
                        <button 
                            type="button" 
                            class="btn btn-default cf-oembed-preview-button"
                            data-target="%s"
                        >
                            Preview
                        </button>
                    </span>
                </div>
    
                %s
                %s
    
                <div 
                    class="cf-oembed-preview"
                    aria-live="polite"
                    aria-atomic="true"
                ></div>
            </div>',
            $this->label($field, $id),
            esc_attr($id),
            esc_attr($name),
            esc_attr($url),
            esc_attr($field['field_placeholder'] ?? esc_attr__('Paste a YouTube, Vimeo, or supported embed URL', 'custom-fields')),
            $this->requiredAttrs($field),
            $this->invalidAttrs($field),
            $this->describedBy($field, $id),
            $this->inputStyle($field),
            esc_attr($id),
            $this->helpText($field, $id),
            $this->errorText($field, $id)
        );
    }

    /**
     * @throws Exception
     */
    private function image(array $field, string $name, mixed $value): string
    {
        /**
         * IMPORTANT:
         * Use the full input name for the ID, not only field_name.
         *
         * This keeps IDs unique inside:
         * - repeaters
         * - flexible content
         * - column containers
         */
        $id = $this->fieldId($name);

        $item = $this->normalizeCustomFieldValues($value);

        $url = $item['url'] ?? '';
        $imageName = $item['name'] ?? basename($url);

        $html = $this->openFieldWrapper($field, 'form-group cf-image-field');

        $html .= $this->label($field, $id);

        $html .= sprintf(
            '<input type="hidden" id="%s" name="%s" class="cf-image-value" value="%s">',
            esc_attr($id),
            esc_attr($name),
            esc_attr(json_encode($item, JSON_UNESCAPED_SLASHES))
        );

        $html .= sprintf(
            '<input 
        type="file"
        class="cf-image-upload-input hidden"
        accept="image/*"
        data-target="%s"
    >',
            esc_attr($id)
        );

        $html .= sprintf(
            '<div 
        class="cf-image-dropzone"
        tabindex="0"
        role="button"
        aria-label="Upload image by clicking or dragging a file here"
        data-target="%s"
    >
        <div class="cf-image-dropzone-inner">
            <i class="fa fa-cloud-upload" aria-hidden="true"></i>
            <p>' . esc_html__('Drop image here or click to upload', 'custom-fields') . '</p>
        </div>
    </div>',
            esc_attr($id)
        );

        $html .= '<div class="cf-image-preview">';

        if ($url !== '') {
            $html .= sprintf(
                '<div class="cf-image-item" data-url="%s" data-name="%s" data-mime="%s">
                <button type="button" class="cf-image-preview-button" aria-label="Preview image %s">
                    <img src="%s" alt="%s" loading="lazy">
                </button>
                <button type="button" class="btn btn-xs btn-danger cf-image-remove" aria-label="Remove image %s">Remove</button>
                <small>%s</small>
            </div>',
                esc_attr($url),
                esc_attr($imageName),
                esc_attr($item['mime'] ?? ''),
                esc_attr($imageName),
                esc_attr($url),
                esc_attr($imageName),
                esc_attr($imageName),
                esc_html($imageName)
            );
        }

        $html .= '</div>';

        $html .= sprintf(
            '<button type="button" class="btn btn-primary cf-open-elfinder-image" data-target="%s">
            Select Image
        </button>',
            esc_attr($id)
        );

        $html .= $this->helpText($field, $id);
        $html .= $this->errorText($field, $id);
        $html .= '</div>';

        return $html;
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeException
     */
    private function flexibleContent(
        array $field,
        string $context,
        string $prefix,
        ?string $objectId = null
    ): string {
        $fieldName = $this->fieldName($field);
        $fieldLabel = $this->fieldLabel($field);

        $rows = $this->value($context, $fieldName, $objectId);

        if (! is_array($rows)) {
            $rows = [];
        }

        $settings = $this->fieldSettings($field);
        $layouts = $settings['layouts'] ?? [];

        $html = $this->openFieldWrapper($field, 'cf-flexible-render');
        $html .= '<label>' . esc_html($fieldLabel) . '</label>';

        $html .= sprintf(
            '<input type="hidden" name="%s[%s]" value="">',
            esc_attr($prefix),
            esc_attr($fieldName)
        );

        $html .= '<div class="cf-flexible-items">';

        foreach ($rows as $rowIndex => $rowData) {
            $layoutName = $rowData['_layout'] ?? '';

            $layout = $this->findFlexibleLayout($layouts, $layoutName);

            if ($layout === null) {
                continue;
            }

            $html .= $this->renderFlexibleRow(
                fieldName: $fieldName,
                layout: $layout,
                rowData: $rowData,
                rowIndex: $rowIndex,
                prefix: $prefix
            );
        }

        $html .= '</div>';

        $html .= '<div class="cf-flexible-add-wrapper">';
        $html .= '<select class="form-control input-sm cf-flexible-layout-select">';
        $html .= '<option value="">Select layout...</option>';

        foreach ($layouts as $layout) {
            $html .= sprintf(
                '<option value="%s">%s</option>',
                esc_attr($layout['name'] ?? ''),
                esc_html($layout['label'] ?? $layout['name'] ?? '')
            );
        }

        $html .= '</select>';

        $html .= sprintf(
            '<button type="button" class="btn btn-default cf-add-flexible-row" data-field="%s" data-prefix="%s">
            Add Layout
        </button>',
            esc_attr($fieldName),
            esc_attr($prefix)
        );

        $html .= '</div>';

        $html .= $this->renderFlexibleTemplates($fieldName, $layouts, $prefix);

        $html .= '</div>';

        return $html;
    }

    private function findFlexibleLayout(array $layouts, string $layoutName): ?array
    {
        return array_find($layouts, fn($layout) => ($layout['name'] ?? '') === $layoutName);
    }

    /**
     * @throws Exception
     */
    private function renderFlexibleRow(
        string $fieldName,
        array $layout,
        array $rowData,
        int $rowIndex,
        string $prefix
    ): string {
        $layoutName = $layout['name'] ?? '';
        $layoutLabel = $layout['label'] ?? $layoutName;
        $fields = $layout['fields'] ?? [];

        $html = '<div class="cf-flexible-item" data-layout="' . esc_attr($layoutName) . '">';

        $html .= '<div class="cf-flexible-toolbar">';
        $html .= '<strong>' . esc_html($layoutLabel) . '</strong>';
        $html .= '<button type="button" class="btn btn-xs btn-danger pull-right cf-remove-flexible-row">Remove</button>';
        $html .= '</div>';

        $html .= sprintf(
            '<input type="hidden" name="%s[%s][%d][_layout]" value="%s">',
            esc_attr($prefix),
            esc_attr($fieldName),
            $rowIndex,
            esc_attr($layoutName)
        );

        $html .= sprintf(
            '<input type="hidden" name="%s[%s][%d][_uuid]" value="%s">',
            esc_attr($prefix),
            esc_attr($fieldName),
            $rowIndex,
            esc_attr($rowData['_uuid'] ?? Uuid::generateAsString())
        );

        foreach ($fields as $childField) {
            $html .= $this->renderFlexibleField(
                field: $childField,
                rowData: $rowData,
                rowIndex: $rowIndex,
                parentField: $fieldName,
                prefix: $prefix
            );
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    private function renderFlexibleField(
        array $field,
        array $rowData,
        int $rowIndex,
        string $parentField,
        string $prefix
    ): string {
        $fieldName = $this->fieldName($field);
        $value = $rowData[$fieldName] ?? '';

        if ($this->fieldType($field) === 'image' && is_string($value)) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        $inputName = sprintf(
            '%s[%s][%d][%s]',
            $prefix,
            $parentField,
            $rowIndex,
            $fieldName
        );

        return match ($this->fieldType($field)) {
            'textarea' => $this->textarea($field, $inputName, $value),
            'richtext' => $this->richtext($field, $inputName, $value),
            'select' => $this->select($field, $inputName, $value),
            'checkbox' => $this->checkboxList($field, $inputName, $value),
            'radio' => $this->radioList($field, $inputName, $value),
            'gallery' => $this->gallery($field, $inputName, $value),
            'oembed' => $this->oembed($field, $inputName, $value),
            'image' => $this->image($field, $inputName, $value),
            'layout_2col' => $this->renderFlexibleLayout(
                field: $field,
                rowData: $rowData,
                rowIndex: $rowIndex,
                parentField: $parentField,
                prefix: $prefix,
                zones: ['left', 'right'],
                class: 'cf-render-layout-2col'
            ),
            'layout_3col' => $this->renderFlexibleLayout(
                field: $field,
                rowData: $rowData,
                rowIndex: $rowIndex,
                parentField: $parentField,
                prefix: $prefix,
                zones: ['left', 'middle', 'right'],
                class: 'cf-render-layout-3col'
            ),
            'repeater' => $this->renderFlexibleRepeater(
                field: $field,
                rowData: $rowData,
                rowIndex: $rowIndex,
                parentField: $parentField,
                prefix: $prefix
            ),

            default => $this->input($field, $inputName, $value, $this->fieldType($field)),
        };
    }

    /**
     * @param string $fieldName
     * @param array $layouts
     * @param string $prefix
     * @return string
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    private function renderFlexibleTemplates(string $fieldName, array $layouts, string $prefix): string
    {
        $html = '<div class="cf-flexible-templates hidden">';

        foreach ($layouts as $layout) {
            $layoutName = $layout['name'] ?? '';
            $layoutLabel = $layout['label'] ?? $layoutName;

            $html .= '<template data-layout="' . esc_attr($layoutName) . '">';
            $html .= '<div class="cf-flexible-item" data-layout="' . esc_attr($layoutName) . '">';

            $html .= '<div class="cf-flexible-toolbar">';
            $html .= '<strong>' . esc_html($layoutLabel) . '</strong>';
            $html .= '<button type="button" class="btn btn-xs btn-danger pull-right cf-remove-flexible-row">Remove</button>';
            $html .= '</div>';

            $html .= sprintf(
                '<input type="hidden" name="__PREFIX__[%s][__INDEX__][_layout]" value="%s">',
                esc_attr($fieldName),
                esc_attr($layoutName)
            );

            $html .= sprintf(
                '<input type="hidden" name="__PREFIX__[%s][__INDEX__][_uuid]" value="%s">',
                esc_attr($fieldName),
                esc_attr('__UUID__')
            );

            foreach (($layout['fields'] ?? []) as $childField) {
                $html .= $this->renderFlexibleTemplateField(
                    field: $childField,
                    parentField: $fieldName
                );
            }

            $html .= '</div>';
            $html .= '</template>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @param array $field
     * @param string $parentField
     * @return string
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    private function renderFlexibleTemplateField(array $field, string $parentField): string
    {
        $fieldName = $this->fieldName($field);
        $fieldType = $this->fieldType($field);

        $inputName = sprintf(
            '__PREFIX__[%s][__INDEX__][%s]',
            $parentField,
            $fieldName
        );

        return match ($fieldType) {
            'textarea' => $this->textarea($field, $inputName, ''),
            'richtext' => $this->richtext($field, $inputName, ''),
            'select' => $this->select($field, $inputName, ''),
            'checkbox' => $this->checkboxList($field, $inputName, []),
            'radio' => $this->radioList($field, $inputName, ''),
            'gallery' => $this->gallery($field, $inputName, []),
            'oembed' => $this->oembed($field, $inputName, ''),
            'image' => $this->image($field, $inputName, []),
            'repeater' => $this->renderFlexibleTemplateRepeater(
                field: $field,
                parentField: $parentField
            ),
            'layout_2col' => $this->renderFlexibleTemplateLayout(
                field: $field,
                parentField: $parentField,
                zones: ['left', 'right'],
                class: 'cf-render-layout-2col'
            ),
            'layout_3col' => $this->renderFlexibleTemplateLayout(
                field: $field,
                parentField: $parentField,
                zones: ['left', 'middle', 'right'],
                class: 'cf-render-layout-3col'
            ),

            default => $this->input(
                field: $field,
                name: $inputName,
                value: '',
                type: $fieldType
            ),
        };
    }

    /**
     * @throws Exception
     */
    private function openFieldWrapper(array $field, string $extraClass = 'form-group', array $attrs = []): string
    {
        $settings = $this->fieldSettings($field);
        $conditional = $settings['conditional_logic'] ?? [];

        $attrHtml = '';

        foreach ($attrs as $key => $value) {
            $attrHtml .= sprintf(' %s="%s"', esc_attr($key), esc_attr((string) $value));
        }

        return sprintf(
            '<div class="%s cf-runtime-field" data-field-name="%s" data-conditional="%s"%s>',
            esc_attr($this->fieldWrapperClass($field, $extraClass)),
            esc_attr($this->fieldName($field)),
            esc_attr(json_encode($conditional, JSON_UNESCAPED_SLASHES)),
            $attrHtml
        );
    }

    private function fieldWrapperClass(array $field, string $baseClass = 'form-group'): string
    {
        $styles = $this->fieldStyles($field);
        $classes = [$baseClass];

        if (! empty($styles['css_class'])) {
            $classes[] = $styles['css_class'];
        }

        if (! empty($styles['class'])) {
            $classes[] = $styles['class'];
        }

        if (! empty($styles['width'])) {
            $classes[] = 'cf-width-' . $styles['width'];
        }

        return implode(' ', array_filter($classes));
    }

    /**
     * @throws Exception
     */
    private function inputStyle(array $field): string
    {
        $styles = $this->fieldStyles($field);
        $css = [];

        if (! empty($styles['input_background'])) {
            $css[] = 'background-color:' . $styles['input_background'];
        }

        if (! empty($styles['text_color'])) {
            $css[] = 'color:' . $styles['text_color'];
        }

        if (! empty($styles['border_radius'])) {
            $css[] = 'border-radius:' . $styles['border_radius'];
        }

        if (! empty($styles['font_size'])) {
            $css[] = 'font-size:' . $styles['font_size'];
        }

        if (! empty($styles['google_font'])) {
            $this->enqueueGoogleFont($styles['google_font']);

            $css[] = "font-family:'" . str_replace("'", '', $styles['google_font']) . "', sans-serif";
        }

        return $css !== [] ? ' style="' . esc_attr(implode(';', $css)) . '"' : '';
    }

    /**
     * @throws Exception
     */
    private function labelStyle(array $field): string
    {
        $styles = $this->fieldStyles($field);
        $css = [];

        if (! empty($styles['label_color'])) {
            $css[] = 'color:' . $styles['label_color'];
        }

        return $css !== [] ? ' style="' . esc_attr(implode(';', $css)) . '"' : '';
    }

    /**
     * @throws Exception
     */
    private function enqueueGoogleFont(string $font): void
    {
        if ($font === '' || in_array($font, $this->loadedGoogleFonts, true)) {
            return;
        }

        $this->loadedGoogleFonts[] = $font;

        $query = str_replace(' ', '+', trim($font));

        echo sprintf(
            '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=%s:wght@300;400;500;600;700&display=swap">',
            esc_attr($query)
        );
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    private function renderFlexibleTemplateLayout(
        array $field,
        string $parentField,
        array $zones,
        string $class
    ): string {
        $html = '<div class="' . esc_attr($class) . ' cf-runtime-field" data-field-name="' . esc_attr($this->fieldName($field)) . '" data-conditional="[]">';

        foreach ($zones as $zone) {
            $html .= '<div class="cf-render-layout-column">';

            foreach (($field['children'][$zone] ?? []) as $childField) {
                $html .= $this->renderFlexibleTemplateField(
                    field: $childField,
                    parentField: $parentField
                );
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    private function normalizeCustomFieldValues(mixed $values): array
    {
        if (is_array($values)) {
            return $values;
        }

        if (! is_string($values)) {
            return [];
        }

        $values = trim($values);

        if ($values === '') {
            return [];
        }

        try {
            $decoded = json_decode($values, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * @param array $field
     * @param array $rowData
     * @param int $rowIndex
     * @param string $parentField
     * @param string $prefix
     * @param array $zones
     * @param string $class
     * @return string
     * @throws Exception
     * @throws ReflectionException
     */
    private function renderFlexibleLayout(
        array $field,
        array $rowData,
        int $rowIndex,
        string $parentField,
        string $prefix,
        array $zones,
        string $class
    ): string {
        $html = '<div class="' . esc_attr($class) . ' cf-runtime-field" data-field-name="' . esc_attr($this->fieldName($field)) . '" data-conditional="[]">';

        foreach ($zones as $zone) {
            $html .= '<div class="cf-render-layout-column">';

            foreach (($field['children'][$zone] ?? []) as $childField) {
                $html .= $this->renderFlexibleField(
                    field: $childField,
                    rowData: $rowData,
                    rowIndex: $rowIndex,
                    parentField: $parentField,
                    prefix: $prefix
                );
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @param array $field
     * @param array $rowData
     * @param int $rowIndex
     * @param string $parentField
     * @param string $prefix
     * @return string
     * @throws Exception
     * @throws ReflectionException
     */
    private function renderFlexibleRepeater(
        array $field,
        array $rowData,
        int $rowIndex,
        string $parentField,
        string $prefix
    ): string {
        $fieldName = $this->fieldName($field);
        $fieldLabel = $this->fieldLabel($field);

        $rows = $rowData[$fieldName] ?? [];

        if (! is_array($rows)) {
            $rows = [];
        }

        $templateRow = $field['children']['default'] ?? [];

        $html = $this->openFieldWrapper($field, 'cf-repeater-render');

        $html .= sprintf(
            '<input type="hidden" name="%s[%s][%d][%s]" value="">',
            esc_attr($prefix),
            esc_attr($parentField),
            $rowIndex,
            esc_attr($fieldName)
        );

        $html .= '<label>' . esc_html($fieldLabel) . '</label>';
        $html .= '<div class="cf-repeater-items">';

        foreach ($rows as $childRowIndex => $childRowData) {
            $rowUuid = $childRowData['_uuid'] ?? Uuid::generateAsString();

            $html .= '<div class="cf-repeater-item">';
            $html .= '<div class="cf-repeater-toolbar">';
            $html .= '<button type="button" class="btn btn-xs btn-danger cf-remove-row">Remove</button>';
            $html .= '</div>';

            foreach ($templateRow as $childField) {
                $childFieldName = $this->fieldName($childField);
                $value = $childRowData[$childFieldName] ?? '';

                $inputName = sprintf(
                    '%s[%s][%d][%s][%d][%s]',
                    $prefix,
                    $parentField,
                    $rowIndex,
                    $fieldName,
                    $childRowIndex,
                    $childFieldName
                );

                $html .= match ($this->fieldType($childField)) {
                    'image' => $this->image($childField, $inputName, $value),
                    'gallery' => $this->gallery($childField, $inputName, $value),
                    'textarea' => $this->textarea($childField, $inputName, $value),
                    'richtext' => $this->richtext($childField, $inputName, $value),
                    'select' => $this->select($childField, $inputName, $value),
                    'checkbox' => $this->checkboxList($childField, $inputName, $value),
                    'radio' => $this->radioList($childField, $inputName, $value),
                    'oembed' => $this->oembed($childField, $inputName, $value),
                    default => $this->input($childField, $inputName, $value, $this->fieldType($childField)),
                };
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        $html .= '<template class="cf-repeater-template">';
        $html .= '<div class="cf-repeater-item">';
        $html .= '<div class="cf-repeater-toolbar">';
        $html .= '<button type="button" class="btn btn-xs btn-danger cf-remove-row">Remove</button>';
        $html .= '</div>';

        foreach ($templateRow as $childField) {
            $childFieldName = $this->fieldName($childField);

            $inputName = sprintf(
                '__PREFIX__[__FIELD__][__INDEX__][%s]',
                $childFieldName
            );

            $html .= match ($this->fieldType($childField)) {
                'image' => $this->image($childField, $inputName, []),
                'gallery' => $this->gallery($childField, $inputName, []),
                'textarea' => $this->textarea($childField, $inputName, ''),
                'richtext' => $this->richtext($childField, $inputName, ''),
                'select' => $this->select($childField, $inputName, ''),
                'checkbox' => $this->checkboxList($childField, $inputName, []),
                'radio' => $this->radioList($childField, $inputName, ''),
                'oembed' => $this->oembed($childField, $inputName, ''),
                default => $this->input($childField, $inputName, '', $this->fieldType($childField)),
            };
        }

        $html .= '</div>';
        $html .= '</template>';

        $html .= sprintf(
            '<button type="button" class="btn btn-default cf-add-repeater-row" data-field="%s" data-prefix="%s[%s][%d]">Add Row</button>',
            esc_attr($fieldName),
            esc_attr($prefix),
            esc_attr($parentField),
            $rowIndex
        );

        $html .= '</div>';

        return $html;
    }

    /**
     * @param array $field
     * @param string $parentField
     * @return string
     * @throws Exception
     * @throws ReflectionException
     */
    private function renderFlexibleTemplateRepeater(
        array $field,
        string $parentField
    ): string {
        $fieldName = $this->fieldName($field);
        $templateRow = $field['children']['default'] ?? [];

        $html = $this->openFieldWrapper($field, 'cf-repeater-render');

        $html .= sprintf(
            '<input type="hidden" name="__PREFIX__[%s][__INDEX__][%s]" value="">',
            esc_attr($parentField),
            esc_attr($fieldName)
        );

        $html .= '<label>' . esc_html($this->fieldLabel($field)) . '</label>';
        $html .= '<div class="cf-repeater-items"></div>';

        $html .= '<template class="cf-repeater-template">';
        $html .= '<div class="cf-repeater-item">';
        $html .= '<div class="cf-repeater-toolbar">';
        $html .= '<button type="button" class="btn btn-xs btn-danger cf-remove-row">Remove</button>';
        $html .= '</div>';

        foreach ($templateRow as $childField) {
            $childFieldName = $this->fieldName($childField);

            $inputName = sprintf(
                '__PREFIX__[%s][__INDEX__][%s][__ROW_INDEX__][%s]',
                $parentField,
                $fieldName,
                $childFieldName
            );

            $html .= match ($this->fieldType($childField)) {
                'image' => $this->image($childField, $inputName, []),
                'gallery' => $this->gallery($childField, $inputName, []),
                'textarea' => $this->textarea($childField, $inputName, ''),
                'richtext' => $this->richtext($childField, $inputName, ''),
                'select' => $this->select($childField, $inputName, ''),
                'checkbox' => $this->checkboxList($childField, $inputName, []),
                'radio' => $this->radioList($childField, $inputName, ''),
                'oembed' => $this->oembed($childField, $inputName, ''),
                default => $this->input($childField, $inputName, '', $this->fieldType($childField)),
            };
        }

        $html .= '</div>';
        $html .= '</template>';

        $html .= sprintf(
            '<button type="button" class="btn btn-default cf-add-repeater-row" data-field="%s" data-prefix="__PREFIX__[%s][__INDEX__]">Add Row</button>',
            esc_attr($fieldName),
            esc_attr($parentField)
        );

        $html .= '</div>';

        return $html;
    }

    private function fieldName(array $field): string
    {
        return (string) ($field['field_name'] ?? $field['name'] ?? '');
    }

    private function fieldLabel(array $field): string
    {
        return (string) ($field['field_label'] ?? $field['label'] ?? $this->fieldName($field));
    }

    private function fieldType(array $field): string
    {
        return (string) ($field['field_type'] ?? $field['type'] ?? 'text');
    }

    private function fieldSettings(array $field): array
    {
        $settings = $field['field_settings'] ?? $field['settings'] ?? [];

        if (! is_array($settings)) {
            $settings = [];
        }

        if (
                isset($field['conditional_logic']) &&
                is_array($field['conditional_logic']) &&
                ! isset($settings['conditional_logic'])
        ) {
            $settings['conditional_logic'] = $field['conditional_logic'];
        }

        foreach (['required', 'hidden', 'readonly', 'disabled'] as $key) {
            if (array_key_exists($key, $field) && ! array_key_exists($key, $settings)) {
                $settings[$key] = (bool) $field[$key];
            }
        }

        return $settings;
    }

    private function fieldOptions(array $field): array
    {
        $options = $field['field_options'] ?? $field['options'] ?? [];

        return is_array($options) ? $options : [];
    }

    private function fieldStyles(array $field): array
    {
        $styles = $field['style_settings'] ?? $field['styles'] ?? [];

        return is_array($styles) ? $styles : [];
    }

    private function fieldValidation(array $field): array
    {
        $validation = $field['validation_rules'] ?? $field['validation'] ?? [];

        return is_array($validation) ? $validation : [];
    }
}
