<?php

declare(strict_types=1);

namespace Plugin\CustomFields\Domain;

use function filter_var;
use function in_array;
use function is_array;
use function is_string;
use function mb_strlen;
use function trim;

use const FILTER_VALIDATE_EMAIL;
use const FILTER_VALIDATE_URL;

final class FieldValidator
{
    public function validateSubmittedFields(array $fields, array $submitted): array
    {
        $errors = [];

        foreach ($fields as $field) {
            if (! $this->shouldValidateField($field, $submitted)) {
                continue;
            }

            $name = $field['field_name'] ?? $field['name'] ?? '';
            $value = $submitted[$name] ?? null;

            $fieldErrors = $this->validateField($field, $value);

            if ($fieldErrors !== []) {
                $errors[$name] = $fieldErrors;
            }
        }

        return $errors;
    }

    public function validateField(array $field, mixed $value): array
    {
        $errors = [];

        $settings = $field['field_settings'] ?? [];
        $rules = $field['validation_rules'] ?? [];

        $label = $field['field_label'] ?: $field['field_name'];

        if (($settings['required'] ?? false) && $this->isEmpty($value)) {
            $errors[] = "{$label} is required.";
        }

        if ($this->isEmpty($value)) {
            return $errors;
        }

        if ($field['field_type'] === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "{$label} must be a valid email address.";
        }

        if (in_array($field['field_type'], ['url', 'oembed'], true) && ! filter_var($value, FILTER_VALIDATE_URL)) {
            $errors[] = "{$label} must be a valid URL.";
        }

        if (isset($rules['min']) && is_string($value) && mb_strlen($value) < (int) $rules['min']) {
            $errors[] = "{$label} must be at least {$rules['min']} characters.";
        }

        if (isset($rules['max']) && is_string($value) && mb_strlen($value) > (int) $rules['max']) {
            $errors[] = "{$label} must not be greater than {$rules['max']} characters.";
        }

        if (isset($rules['options']) && is_array($rules['options']) && ! in_array($value, $rules['options'], true)) {
            $errors[] = "{$label} has an invalid selected value.";
        }

        return $errors;
    }

    private function shouldValidateField(array $field, array $submitted): bool
    {
        $settings = $field['field_settings'] ?? $field['settings'] ?? [];
        $conditional = $settings['conditional_logic'] ?? [];

        if (empty($conditional['enabled'])) {
            return true;
        }

        $rules = $conditional['rules'] ?? [];

        if ($rules === []) {
            return true;
        }

        $matches = [];

        foreach ($rules as $rule) {
            $matches[] = $this->conditionMatches($rule, $submitted);
        }

        $passed = ($conditional['logic'] ?? 'all') === 'any'
        ? in_array(true, $matches, true)
        : ! in_array(false, $matches, true);

        return ($conditional['action'] ?? 'show') === 'show'
        ? $passed
        : ! $passed;
    }

    private function conditionMatches(array $rule, array $submitted): bool
    {
        $field = $rule['field'] ?? '';
        $operator = $rule['operator'] ?? 'equals';
        $expected = (string) ($rule['value'] ?? '');

        $value = $submitted[$field] ?? null;

        return match ($operator) {
            'equals' => is_array($value)
            ? in_array($expected, $value, true)
            : (string) $value === $expected,

            'not_equals' => is_array($value)
            ? ! in_array($expected, $value, true)
            : (string) $value !== $expected,

            'empty' => $value === null || $value === '' || $value === [],

            'not_empty' => ! ($value === null || $value === '' || $value === []),

            'contains' => is_array($value)
            ? in_array($expected, $value, true)
            : str_contains((string) $value, $expected),

            default => false,
        };
    }

    private function isEmpty(mixed $value): bool
    {
        if (is_array($value)) {
            return $value === [];
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        return $value === null;
    }
}
