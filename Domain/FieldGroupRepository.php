<?php

declare(strict_types=1);

namespace Plugin\CustomFields\Domain;

use App\Application\Devflow;
use JsonException;
use Qubus\Expressive\Database;
use Qubus\ValueObjects\Identity\Ulid;

use function Qubus\Support\Helpers\is_false__;
use function Qubus\Support\Helpers\is_null__;

final readonly class FieldGroupRepository
{
    public function __construct(
        private Database $dfdb
    ) {
    }

    public static function make(): self
    {
        return new self(Devflow::$PHP->make(name: Database::class));
    }

    public function all(): array
    {
        $groups = $this->dfdb
            ->table($this->dfdb->prefix . 'custom_field_group')
            ->orderBy('field_order')
            ->orderBy('title')
            ->find(function ($data) {
                $array = [];
                foreach ($data as $d) {
                    $array[] = $d;
                }

                return $array;
            });

        return array_map([$this, 'hydrateGroup'], $groups);
    }

    public function find(string $id): ?array
    {
        $group = $this->dfdb
            ->table($this->dfdb->prefix . 'custom_field_group')
            ->where('id', $id)
            ->findOne();

        if (is_false__($group)) {
            return null;
        }

        $group = $this->hydrateGroup($group->toArray());
        $group['fields'] = $this->fieldsForGroup($id);

        return $group;
    }

    public function activeFor(
        string $context,
        ?string $type = null,
        string $position = 'normal'
    ): array {
        $groups = $this->dfdb
            ->table($this->dfdb->prefix . 'custom_field_group')
            ->where('status', 'active')
            ->orderBy('field_order')
            ->find(function ($data) {
                $array = [];

                foreach ($data as $d) {
                    $array[] = $d;
                }

                return $array;
            });

        $matched = [];

        foreach ($groups as $group) {
            $group = $this->hydrateGroup($group);

            if (! $this->matchesLocation($group, $context, $type)) {
                continue;
            }

            if (! $this->matchesPosition($group, $position)) {
                continue;
            }

            $group['fields'] = $this->fieldsForGroup($group['id']);
            $matched[] = $group;
        }

        return $matched;
    }

    /**
     * @throws JsonException
     */
    public function create(array $payload): string
    {
        $id = Ulid::generateAsString();

        $this->dfdb->table($this->dfdb->prefix . 'custom_field_group')->insert([
            'id' => $id,
            'title' => $payload['title'],
            'slug' => $payload['slug'],
            'status' => $payload['status'] ?? 'active',
            'location' => json_encode($payload['location'] ?? [], JSON_THROW_ON_ERROR),
            'settings' => json_encode($payload['settings'] ?? [], JSON_THROW_ON_ERROR),
            'field_order' => (int) ($payload['field_order'] ?? 0),
        ]);

        $this->syncFields($id, $payload['fields'] ?? []);

        return $id;
    }

    /**
     * @throws JsonException
     */
    public function update(string $id, array $payload): void
    {
        $this->dfdb->table($this->dfdb->prefix . 'custom_field_group')
            ->where('id', $id)
            ->update([
                'title' => $payload['title'],
                'slug' => $payload['slug'],
                'status' => $payload['status'] ?? 'active',
                'location' => json_encode($payload['location'] ?? [], JSON_THROW_ON_ERROR),
                'settings' => json_encode($payload['settings'] ?? [], JSON_THROW_ON_ERROR),
                'field_order' => (int) ($payload['field_order'] ?? 0),
            ]);

        $this->syncFields($id, $payload['fields'] ?? []);
    }

    public function delete(string $id): void
    {
        $this->dfdb->table($this->dfdb->prefix . 'custom_field')
            ->where('group_id', $id)
            ->delete();

        $this->dfdb->table($this->dfdb->prefix . 'custom_field_group')
            ->where('id', $id)
            ->delete();
    }

    /**
     * @throws JsonException
     */
    public function clone(string $id): ?string
    {
        $group = $this->find($id);

        if (is_null__($group)) {
            return null;
        }

        $group['title'] .= ' Copy';
        $group['slug'] .= '-copy';

        return $this->create($group);
    }

    public function fieldsForGroup(string $groupId): array
    {
        $fields = $this->dfdb
            ->table($this->dfdb->prefix . 'custom_field')
            ->where('group_id', $groupId)
            ->orderBy('sort_order')
            ->find(function ($data) {
                $array = [];

                foreach ($data as $d) {
                    $array[] = $d;
                }

                return $array;
            });

        $fields = array_map([$this, 'hydrateField'], $fields);

        return $this->buildFieldTree($fields);
    }

    /**
     * @throws JsonException
     */
    private function syncFields(string $groupId, array $fields): void
    {
        $this->dfdb->table($this->dfdb->prefix . 'custom_field')
            ->where('group_id', $groupId)
            ->delete();

        $this->insertFields($groupId, $fields);
    }

    private function hydrateGroup(array|object $group): array
    {
        $group = (array) $group;

        $group['location'] = $this->decodeJsonArray($group['location'] ?? null);
        $group['settings'] = $this->decodeJsonArray($group['settings'] ?? null);

        return $group;
    }

    private function hydrateField(array|object $field): array
    {
        $field = (array) $field;

        $field['field_options'] = $this->decodeJsonArray($field['field_options'] ?? null);
        $field['field_settings'] = $this->decodeJsonArray($field['field_settings'] ?? null);
        $field['style_settings'] = $this->decodeJsonArray($field['style_settings'] ?? null);
        $field['validation_rules'] = $this->decodeJsonArray($field['validation_rules'] ?? null);

        return $field;
    }

    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function matchesLocation(array $group, string $context, ?string $type = null): bool
    {
        $location = $group['location'] ?? [];

        if (in_array($context, (array) $location, true)) {
            return true;
        }

        if ($context === 'content' && $type !== null) {
            return in_array('content:' . $type, (array) $location, true);
        }

        return false;
    }

    /**
     * @throws JsonException
     */
    private function insertFields(
        string $groupId,
        array $fields,
        ?string $parentId = null,
        ?string $parentZone = null
    ): void {
        foreach ($fields as $index => $field) {
            $type = $field['type'] ?? $field['field_type'] ?? 'text';
            $name = $field['name'] ?? $field['field_name'] ?? '';
            $label = $field['label'] ?? $field['field_label'] ?? null;
            $placeholder = $field['placeholder'] ?? $field['field_placeholder'] ?? null;
            $help = $field['help'] ?? $field['field_help'] ?? null;
            $default = $field['default'] ?? $field['field_default'] ?? null;
            $options = $field['options'] ?? $field['field_options'] ?? [];
            $styles = $field['styles'] ?? $field['style_settings'] ?? [];
            $validation = $field['validation'] ?? $field['validation_rules'] ?? [];

            $options = $this->normalizeFieldOptions($options);

            $fieldId = Ulid::generateAsString();

            $settings = [
                'required' => (bool) ($field['required'] ?? false),
                'hidden' => (bool) ($field['hidden'] ?? false),
                'readonly' => (bool) ($field['readonly'] ?? false),
                'disabled' => (bool) ($field['disabled'] ?? false),
                'conditional_logic' => $field['conditional_logic'] ?? [
                    'enabled' => false,
                    'action' => 'show',
                    'logic' => 'all',
                    'rules' => [],
                ],
            ];

            $galleryPreviewSize = $field['gallery_preview_size'] ?? null;

            if ($type === 'gallery' && in_array($galleryPreviewSize, ['small', 'medium', 'large'], true)) {
                $settings['gallery_preview_size'] = $galleryPreviewSize;
            }

            if ($type === 'flexible_content') {
                $layouts = $field['layouts']
                ?? $field['field_settings']['layouts']
                ?? $field['settings']['layouts']
                ?? [];

                foreach ($layouts as &$layout) {
                    $layout['id'] = 'layout_' . Ulid::generateAsString();
                }

                $settings['layouts'] = $layouts;
            }

            $this->dfdb->table($this->dfdb->prefix . 'custom_field')->insert([
                'id' => $fieldId,
                'group_id' => $groupId,
                'parent_id' => $parentId,
                'parent_zone' => $parentZone,
                'field_type' => $type,
                'field_name' => $name,
                'field_label' => $label ?? null,
                'field_placeholder' => $placeholder ?? null,
                'field_help' => $help ?? null,
                'field_default' => $default ?? null,
                'field_options' => json_encode($options ?? [], JSON_THROW_ON_ERROR),
                'field_settings' => json_encode($settings, JSON_THROW_ON_ERROR),
                'style_settings' => json_encode($styles ?? [], JSON_THROW_ON_ERROR),
                'validation_rules' => json_encode($validation ?? [], JSON_THROW_ON_ERROR),
                'sort_order' => (int) ($field['sort_order'] ?? $index),
            ]);

            foreach (($field['children'] ?? []) as $zone => $children) {
                $this->insertFields($groupId, $children, $fieldId, (string) $zone);
            }
        }
    }

    private function buildFieldTree(array $fields): array
    {
        $byParent = [];

        foreach ($fields as $field) {
            $parentId = $field['parent_id'] ?? null;
            $zone = $field['parent_zone'] ?? 'root';

            $byParent[$parentId][$zone][] = $field;
        }

        $build = function (?string $parentId = null, string $zone = 'root') use (&$build, &$byParent): array {
            $items = $byParent[$parentId][$zone] ?? [];

            foreach ($items as &$item) {
                $childrenByZone = $byParent[$item['id']] ?? [];

                if ($childrenByZone !== []) {
                    $item['children'] = [];

                    foreach ($childrenByZone as $childZone => $children) {
                        $item['children'][$childZone] = $build($item['id'], $childZone);
                    }
                }
            }

            return $items;
        };

        return $build(null);
    }

    public function export(string $id): ?array
    {
        $group = $this->find($id);

        if ($group === null) {
            return null;
        }

        unset($group['id']);

        $group['exported_at'] = date(DATE_ATOM);
        $group['schema'] = 'devflow-custom-fields.v1';

        return $group;
    }

    /**
     * @throws JsonException
     */
    public function import(array $payload): string
    {
        $payload['title'] = ($payload['title'] ?? 'Imported Field Group') . ' Import';
        $payload['slug'] = ($payload['slug'] ?? 'imported-field-group') . '-' . time();

        return $this->create([
            'title' => $payload['title'],
            'slug' => $payload['slug'],
            'status' => $payload['status'] ?? 'inactive',
            'location' => $payload['location'] ?? [],
            'settings' => $payload['settings'] ?? [],
            'field_order' => $payload['field_order'] ?? 0,
            'fields' => $this->normalizeImportedFields($payload['fields'] ?? []),
        ]);
    }

    private function normalizeImportedFields(array $fields): array
    {
        return array_map(function (array $field): array {
            $field['id'] = Ulid::generateAsString();

            if (isset($field['children']) && is_array($field['children'])) {
                foreach ($field['children'] as $zone => $children) {
                    $field['children'][$zone] = $this->normalizeImportedFields($children);
                }
            }

            $settings = $field['field_settings'] ?? $field['settings'] ?? [];

            if (isset($settings['layouts']) && is_array($settings['layouts'])) {
                foreach ($settings['layouts'] as &$layout) {
                    $layout['id'] = 'layout_' . Ulid::generateAsString();

                    if (isset($layout['fields']) && is_array($layout['fields'])) {
                        $layout['fields'] = $this->normalizeImportedFields($layout['fields']);
                    }
                }

                $field['settings'] = $settings;
            }

            return [
                'id' => $field['id'],
                'type' => $field['field_type'] ?? $field['type'] ?? 'text',
                'name' => $field['field_name'] ?? $field['name'] ?? '',
                'label' => $field['field_label'] ?? $field['label'] ?? '',
                'placeholder' => $field['field_placeholder'] ?? $field['placeholder'] ?? '',
                'help' => $field['field_help'] ?? $field['help'] ?? '',
                'default' => $field['field_default'] ?? $field['default'] ?? '',
                'options' => $field['field_options'] ?? $field['options'] ?? [],
                'settings' => $field['field_settings'] ?? $field['settings'] ?? [],
                'styles' => $field['style_settings'] ?? $field['styles'] ?? [],
                'validation' => $field['validation_rules'] ?? $field['validation'] ?? [],
                'conditional_logic' => ($field['field_settings']['conditional_logic'] ?? $field['settings']['conditional_logic'] ?? $field['conditional_logic'] ?? [
                    'enabled' => false,
                    'action' => 'show',
                    'logic' => 'all',
                    'rules' => [],
                ]),
                'children' => $field['children'] ?? [],
                'layouts' => $field['field_settings']['layouts'] ?? $field['settings']['layouts'] ?? $field['layouts'] ?? [],
                'sort_order' => $field['sort_order'] ?? 0,
            ];
        }, $fields);
    }

    public function updateStatusMany(array $ids, string $status): void
    {
        foreach ($ids as $id) {
            $this->dfdb->table($this->dfdb->prefix . 'custom_field_group')
                ->where('id', $id)
                ->update([
                    'status' => $status,
                ]);
        }
    }

    public function deleteMany(array $ids): void
    {
        foreach ($ids as $id) {
            $this->delete($id);
        }
    }

    public function exportMany(array $ids): array
    {
        $groups = [];

        foreach ($ids as $id) {
            $group = $this->export($id);

            if ($group !== null) {
                $groups[] = $group;
            }
        }

        return [
            'schema' => 'devflow-custom-fields.bundle.v1',
            'exported_at' => date(DATE_ATOM),
            'count' => count($groups),
            'groups' => $groups,
        ];
    }

    private function matchesPosition(array $group, string $position): bool
    {
        $settings = $group['settings'] ?? [];

        $groupPosition = $settings['position'] ?? 'normal';

        return $groupPosition === $position;
    }

    private function normalizeFieldOptions(array|string|null $options): array
    {
        if ($options === null || $options === '') {
            return [];
        }

        if (is_array($options)) {
            $normalized = [];

            foreach ($options as $key => $value) {
                if (is_string($key) && ! is_numeric($key)) {
                    $normalized[$key] = (string) $value;
                    continue;
                }

                if (is_string($value) && str_contains($value, '=>')) {
                    [$optionKey, $optionLabel] = array_map(
                        'trim',
                        explode('=>', $value, 2)
                    );

                    if ($optionKey !== '') {
                        $normalized[$optionKey] = $optionLabel;
                    }

                    continue;
                }

                if (is_string($value) && trim($value) !== '') {
                    $normalized[] = trim($value);
                }
            }

            return $normalized;
        }

        $normalized = [];

        foreach (preg_split('/\r\n|\r|\n/', $options) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (str_contains($line, '=>')) {
                [$key, $label] = array_map('trim', explode('=>', $line, 2));

                if ($key !== '') {
                    $normalized[$key] = $label;
                }

                continue;
            }

            $normalized[] = $line;
        }

        return $normalized;
    }
}
