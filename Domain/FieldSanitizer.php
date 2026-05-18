<?php

declare(strict_types=1);

namespace Plugin\CustomFields\Domain;

use function array_map;
use function filter_var;
use function is_array;
use function is_string;
use function strip_tags;
use function trim;

use const FILTER_SANITIZE_EMAIL;
use const FILTER_SANITIZE_URL;

final class FieldSanitizer
{
    public function sanitizeValue(array $field, mixed $value): mixed
    {
        return match ($field['field_type']) {
            'email' => is_string($value) ? filter_var(trim($value), FILTER_SANITIZE_EMAIL) : '',
            'url', 'oembed' => is_string($value) ? filter_var(trim($value), FILTER_SANITIZE_URL) : '',
            'image' => $this->sanitizeImageValue($value),
            'textarea' => is_string($value) ? trim(strip_tags($value)) : '',
            'richtext' => is_string($value) ? trim($value) : '',
            'checkbox', 'gallery', 'repeater', 'flexible_content' => $this->sanitizeGalleryValue($value),
            'password' => is_string($value) ? $value : '',
            default => is_string($value) ? trim(strip_tags($value)) : $value,
        };
    }

    public function sanitizeSubmittedFields(array $fields, array $submitted): array
    {
        $clean = [];

        foreach ($fields as $field) {
            $name = $field['field_name'];

            if (! array_key_exists($name, $submitted)) {
                continue;
            }

            $clean[$name] = $this->sanitizeValue($field, $submitted[$name]);
        }

        return $clean;
    }

    private function sanitizeArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_map(function (mixed $item): mixed {
            if (is_array($item)) {
                return $this->sanitizeArray($item);
            }

            return is_string($item) ? trim(strip_tags($item)) : $item;
        }, $value);
    }

    private function sanitizeRepeaterValue(mixed $value): array
    {
        if ($value === '' || $value === null) {
            return [];
        }

        return $this->sanitizeArray($value);
    }

    private function sanitizeGalleryValue(mixed $value): array
    {
        if ($value === '' || $value === null) {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $this->sanitizeArray($decoded) : [];
        }

        return $this->sanitizeArray($value);
    }

    private function sanitizeImageValue(mixed $value): array
    {
        if ($value === '' || $value === null) {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $this->sanitizeArray($decoded) : [];
        }

        return is_array($value) ? $this->sanitizeArray($value) : [];
    }
}
