<?php

declare(strict_types=1);

namespace Plugin\CustomFields\Helper;

use function in_array;

final class Checker
{
    public static function checked(mixed $values, string $needle): string
    {
        if (is_array($values)) {
            return in_array($needle, $values, true) ? 'checked' : '';
        }

        return (string) $values === $needle ? 'checked' : '';
    }

    public static function selected(mixed $value, mixed $expected): string
    {
        if (is_array($value) || is_array($expected)) {
            return '';
        }

        return (string) $value === (string) $expected ? 'selected' : '';
    }

    public static function selectedIn(mixed $values, string $needle): string
    {
        if (! is_array($values)) {
            return '';
        }

        return in_array($needle, $values, true) ? 'selected' : '';
    }

    public static function contentTypeChecked(mixed $values, string $slug): string
    {
        if (! is_array($values)) {
            return '';
        }

        return in_array('content:' . $slug, $values, true) ? 'selected' : '';
    }
}
