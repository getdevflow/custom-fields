<?php

declare(strict_types=1);

namespace Plugin\CustomFields\Domain;

use Qubus\Exception\Exception;

use function Qubus\Security\Helpers\esc_html__;

final class FieldTypeRegistry
{
    /**
     * @throws Exception
     */
    public static function all(): array
    {
        return [
            'text' => [
                'label' => esc_html__('Text', 'custom-fields'),
                'icon' => 'fa-bold',
                'category' => esc_html__('Basic', 'custom-fields'),
                'supports_options' => false,
                'supports_placeholder' => true,
                'supports_styles' => true,
            ],
            'textarea' => [
                'label' => esc_html__('Textarea', 'custom-fields'),
                'icon' => null,
                'category' => esc_html__('Basic', 'custom-fields'),
                'supports_options' => false,
                'supports_placeholder' => true,
                'supports_styles' => true,
            ],
            'richtext' => [
                'label' => esc_html__('Rich Text', 'custom-fields'),
                'icon' => null,
                'category' => esc_html__('Basic', 'custom-fields'),
                'supports_options' => false,
                'supports_placeholder' => false,
                'supports_styles' => true,
            ],
            'email' => [
                'label' => esc_html__('Email', 'custom-fields'),
                'icon' => 'fa-envelope',
                'category' => esc_html__('Basic', 'custom-fields'),
                'supports_options' => false,
                'supports_placeholder' => true,
                'supports_styles' => true,
            ],
            'password' => [
                'label' => esc_html__('Password', 'custom-fields'),
                'icon' => 'fa-key',
                'category' => esc_html__('Basic', 'custom-fields'),
                'supports_options' => false,
                'supports_placeholder' => true,
                'supports_styles' => true,
            ],
            'tel' => [
                'label' => esc_html__('Telephone', 'custom-fields'),
                'icon' => 'fa-phone',
                'category' => esc_html__('Basic', 'custom-fields'),
                'supports_options' => false,
                'supports_placeholder' => true,
                'supports_styles' => true,
            ],
            'select' => [
                'label' => esc_html__('Select Dropdown', 'custom-fields'),
                'icon' => 'fa-chevron-down',
                'category' => esc_html__('Choices', 'custom-fields'),
                'supports_options' => true,
                'supports_placeholder' => true,
                'supports_styles' => true,
            ],
            'checkbox' => [
                'label' => esc_html__('Checklist', 'custom-fields'),
                'icon' => 'fa-square-check',
                'category' => esc_html__('Choices', 'custom-fields'),
                'supports_options' => true,
                'supports_placeholder' => false,
                'supports_styles' => true,
            ],
            'radio' => [
                'label' => esc_html__('Radio Buttons', 'custom-fields'),
                'icon' => 'fa-circle-dot',
                'category' => esc_html__('Choices', 'custom-fields'),
                'supports_options' => true,
                'supports_placeholder' => false,
                'supports_styles' => true,
            ],
            'gallery' => [
                'label' => esc_html__('Gallery', 'custom-fields'),
                'icon' => 'fa-images',
                'category' => esc_html__('Media', 'custom-fields'),
                'supports_options' => false,
                'supports_placeholder' => false,
                'supports_styles' => true,
            ],
            'image' => [
                'label' => esc_html__('Image', 'custom-fields'),
                'icon' => 'fa-image',
                'category' => esc_html__('Media', 'custom-fields'),
                'supports_options' => false,
                'supports_placeholder' => false,
                'supports_styles' => false,
            ],
            'oembed' => [
                'label' => 'oEmbed',
                'icon' => 'fa-video',
                'category' => esc_html__('Media', 'custom-fields'),
                'supports_options' => false,
                'supports_placeholder' => true,
                'supports_styles' => true,
            ],
            'repeater' => [
                'label' => esc_html__('Repeater', 'custom-fields'),
                'icon' => 'fa-repeat',
                'category' => esc_html__('Structure', 'custom-fields'),
                'supports_options' => false,
                'supports_placeholder' => false,
                'supports_styles' => false,
            ],
            'flexible_content' => [
                'label' => esc_html__('Flexible Content', 'custom-fields'),
                'icon' => 'fa-shuffle',
                'category' => esc_html__('Structure', 'custom-fields'),
                'supports_options' => false,
                'supports_placeholder' => false,
                'supports_styles' => false,
            ],
            'help' => [
                'label' => esc_html__('Help Text', 'custom-fields'),
                'icon' => 'fa-info',
                'category' => esc_html__('Utility', 'custom-fields'),
                'supports_options' => false,
                'supports_placeholder' => false,
                'supports_styles' => false,
            ],
            'layout_2col' => [
                'label' => esc_html__('2 Column Container', 'custom-fields'),
                'icon' => 'fa-columns',
                'category' => esc_html__('Structure', 'custom-fields'),
                'supports_options' => false,
                'supports_placeholder' => false,
                'supports_styles' => true,
                'is_container' => true,
            ],
            'layout_3col' => [
                'label' => esc_html__('3 Column Container', 'custom-fields'),
                'icon' => 'fa-columns',
                'category' => esc_html__('Structure', 'custom-fields'),
                'supports_options' => false,
                'supports_placeholder' => false,
                'supports_styles' => true,
                'is_container' => true,
            ],
        ];
    }

    public static function exists(string $type): bool
    {
        return isset(self::all()[$type]);
    }

    public static function find(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }
}
