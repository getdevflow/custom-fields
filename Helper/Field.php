<?php

declare(strict_types=1);

namespace Plugin\CustomFields\Helper;

use App\Application\Devflow;
use JsonException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;

use function App\Shared\Helpers\cms_render_content;
use function App\Shared\Helpers\get_content_attribute;
use function App\Shared\Helpers\get_current_site_id;
use function App\Shared\Helpers\get_product_attribute;
use function App\Shared\Helpers\get_user_attribute;
use function Qubus\Security\Helpers\purify_html;
use function sprintf;

final class Field
{
    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public static function get(
        string $field,
        ?string $objectId = null,
        string $context = 'content',
        mixed $default = null,
        ?string $siteId = null
    ): mixed {
        $direct = self::raw($field, $objectId, $context, null, $siteId);

        if ($direct !== null && $direct !== '') {
            return self::purifyValue(self::normalizeValue($direct));
        }

        $payload = self::payload($objectId, $context, $siteId);

        $found = self::findRecursive($field, $payload);

        return $found === null ? $default : self::purifyValue(self::normalizeValue($found));
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeException
     */
    public static function raw(
        string $field,
        ?string $objectId = null,
        string $context = 'content',
        mixed $default = null,
        ?string $siteId = null
    ): mixed {
        if ($objectId === null || $objectId === '') {
            return $default;
        }

        return match ($context) {
            'content' => get_content_attribute($objectId, $field, $default),
            'product' => get_product_attribute($objectId, $field, $default),
            'user' => get_user_attribute($objectId, $field, $siteId, $default),
            default => $default,
        };
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public static function has(
        string $field,
        string|int|null $objectId,
        string $context = 'content',
        ?string $siteId = null
    ): bool {
        $value = self::get($field, $objectId, $context, null, $siteId);

        return is_array($value)
        ? $value !== []
        : $value !== null && $value !== '';
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public static function image(
        string $field,
        string|int|null $objectId,
        string $context = 'content',
        ?string $siteId = null
    ): ?array {
        return self::normalizeValue(
            self::get($field, $objectId, $context, null, $siteId)
        );
    }

    public static function images(
        string $field,
        string|int|null $objectId,
        string $context = 'content',
        ?string $siteId = null
    ): array {
        $payload = self::payload($objectId, $context, $siteId);

        $matches = self::findRecursive($field, $payload);

        return array_values(array_filter(array_map(
            static fn (mixed $value): ?array => self::normalizeValue($value),
            $matches
        )));
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public static function gallery(
        string $field,
        string|int|null $objectId,
        string $context = 'content',
        ?string $siteId = null
    ): array {
        $value = self::get($field, $objectId, $context, [], $siteId);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): ?array => is_array($item)
                ? [
                    'url' => $item['url'] ?? null,
                    'name' => $item['name'] ?? null,
                    'mime' => $item['mime'] ?? null,
                ]
                : null,
            $value
        )));
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public static function rows(
        string $field,
        string|int|null $objectId,
        string $context = 'content',
        ?string $siteId = null
    ): array {
        $value = self::get($field, $objectId, $context, [], $siteId);

        return is_array($value) ? $value : [];
    }

    private static function payload(
        ?string $objectId = null,
        string $context = 'content',
        ?string $siteId = null
    ): array {
        if ($objectId === null || $objectId === '') {
            return [];
        }

        $dfdb = Devflow::db();

        /*
         * IMPORTANT:
         * Replace this section with your real direct attribute-column lookup.
         *
         * The normal attribute helpers only fetch by key, so recursive lookup
         * needs access to the full *_attribute JSON column.
         */
        $json = match ($context) {
            'content' => self::attributeColumn(
                table: $dfdb->prefix . 'content',
                idColumn: 'content_id',
                id: (string) $objectId,
                attributeColumn: 'content_attribute'
            ),

            'product' => self::attributeColumn(
                table: $dfdb->prefix . 'product',
                idColumn: 'product_id',
                id: (string) $objectId,
                attributeColumn: 'product_attribute'
            ),

            'user' => self::attributeColumn(
                table: $dfdb->basePrefix . 'user',
                idColumn: 'user_id',
                id: (string) $objectId,
                attributeColumn: 'user_attribute'
            ),

            default => null,
        };

        if (! is_string($json) || trim($json) === '') {
            return [];
        }

        $decoded = self::decodeJson($json);

        return is_array($decoded) ? self::normalizeValue($decoded) : [];
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     */
    private static function attributeColumn(
        string $table,
        string $idColumn,
        string $id,
        string $attributeColumn
    ): ?string {
        $dfdb = Devflow::db();

        $row = $dfdb
            ->table($table)
            ->where($idColumn, $id)
            ->findOne()
            ->toArray();

        if ($table === $dfdb->basePrefix . 'user') {
            $sql = sprintf(
                "SELECT * FROM %s 
            LEFT JOIN %s 
            ON %s = %s 
            WHERE %s = '%s' AND %s = '%s'",
                $table,
                $dfdb->basePrefix . 'site_user',
                $table . '.user_id',
                $dfdb->basePrefix . 'site_user.user_id',
                $table . '.' . $idColumn,
                $id,
                $dfdb->basePrefix . 'site_user.site_id',
                get_current_site_id()
            );

            $row = $dfdb
                ->query($sql)
                ->findOne()
                ->toArray();
        }

        if (! $row) {
            return null;
        }

        return isset($row[$attributeColumn])
        ? (string) cms_render_content($row[$attributeColumn])
        : null;
    }

    private static function findRecursive(string $field, mixed $value): mixed
    {
        if (! is_array($value)) {
            return null;
        }

        if (array_key_exists($field, $value)) {
            return $value[$field];
        }

        foreach ($value as $item) {
            $found = self::findRecursive($field, $item);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private static function normalizeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = self::decodeJson($value);

            return $decoded === null
            ? $value
            : self::normalizeValue($decoded);
        }

        if (is_array($value)) {
            return array_map(function ($item) {
                return self::normalizeValue($item);
            }, $value);
        }

        return $value;
    }

    private static function decodeJson(string $value): mixed
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (! str_starts_with($trimmed, '{') && ! str_starts_with($trimmed, '[')) {
            return null;
        }

        try {
            return json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    private static function purifyValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return purify_html($value);
        }

        if (is_array($value)) {
            return array_map(
                static fn (mixed $item): mixed => self::purifyValue($item),
                $value
            );
        }

        return $value;
    }
}
