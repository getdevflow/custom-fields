<?php

declare(strict_types=1);

namespace Plugin\CustomFields\Controller;

use App\Application\Devflow;
use Cocur\Slugify\Slugify;
use JsonException;
use Plugin\CustomFields\Domain\FieldTypeRegistry;
use Plugin\CustomFields\Domain\FieldValidator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Http\Factories\JsonResponseFactory;
use Qubus\ValueObjects\Identity\Ulid;
use Random\RandomException;
use ReflectionException;

use function App\Shared\Helpers\public_site_upload_url;
use function App\Shared\Helpers\site_path;
use function preg_match;
use function Qubus\Security\Helpers\esc_attr;
use function Qubus\Security\Helpers\esc_html;
use function Qubus\Security\Helpers\esc_html__;
use function trim;

use const JSON_THROW_ON_ERROR;

final readonly class AjaxFieldController
{
    public function __construct(
        private FieldValidator $validator = new FieldValidator()
    ) {
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws \Exception
     */
    public function fieldTemplate(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $type = (string) ($body['type'] ?? 'text');

        if (! FieldTypeRegistry::exists($type)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid field type.',
            ], 422);
        }

        $fieldType = FieldTypeRegistry::find($type);

        return $this->json([
            'success' => true,
            'field' => [
                'id' => Ulid::generateAsString(),
                'type' => $type,
                'name' => new Slugify()->slugify($fieldType['label']),
                'label' => $fieldType['label'],
                'placeholder' => '',
                'help' => '',
                'default' => '',
                'required' => false,
                'hidden' => false,
                'readonly' => false,
                'disabled' => false,
                'options' => [],
                'styles' => [],
                'validation' => [],
            ],
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws \Exception
     */
    public function validateField(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();

        $field = [
            'field_type' => $body['type'] ?? 'text',
            'field_name' => $body['name'] ?? '',
            'field_label' => $body['label'] ?? '',
            'field_settings' => [
                'required' => filter_var($body['required'] ?? false, FILTER_VALIDATE_BOOL),
            ],
            'validation_rules' => [
                'min' => $body['min'] ?? null,
                'max' => $body['max'] ?? null,
                'options' => $body['options'] ?? [],
            ],
        ];

        $errors = [];

        if (trim((string) $field['field_name']) === '') {
            $errors[] = 'Field name is required.';
        }

        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string) $field['field_name'])) {
            $errors[] = esc_html__(
                'Field name may only contain letters, numbers, and underscores, and cannot start with a number.',
                'custom-fields'
            );
        }

        if (! FieldTypeRegistry::exists((string) $field['field_type'])) {
            $errors[] = 'Invalid field type.';
        }

        $valueErrors = $this->validator->validateField($field, $body['value'] ?? null);

        return $this->json([
            'success' => $errors === [] && $valueErrors === [],
            'errors' => array_merge($errors, $valueErrors),
        ], $errors === [] && $valueErrors === [] ? 200 : 422);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws \Exception
     */
    public function validateGroup(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $body = $request->getParsedBody();

            $errors = [];

            if (trim((string) ($body['title'] ?? '')) === '') {
                $errors[] = esc_html__('Field group title is required.', 'custom-fields');
            }

            $fields = json_decode((string) ($body['fields_json'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);

            if ($fields === []) {
                $errors[] = esc_html__('Add at least one field to this field group.', 'custom-fields');
            }

            foreach ($fields as $field) {
                if (empty($field['name'])) {
                    $errors[] = esc_html__('Each field requires a field name.', 'custom-fields');
                    continue;
                }

                if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string) $field['name'])) {
                    $errors[] = sprintf(esc_html__('Field "%s" has an invalid name.', 'custom-fields'), $field['name']);
                }

                if (! FieldTypeRegistry::exists((string) ($field['type'] ?? ''))) {
                    $errors[] = sprintf(esc_html__('Field "%s" has an invalid field type.', 'custom-fields'), $field['name']);
                }
            }

            return $this->json([
                'success' => $errors === [],
                'errors' => $errors,
            ], $errors === [] ? 200 : 422);
        } catch (JsonException) {
            return $this->json([
                'success' => false,
                'errors' => [esc_html__('The field group data is invalid JSON.', 'custom-fields')],
            ], 422);
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception
     * @throws \Exception
     */
    public function oembedPreview(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();

        $url = trim((string) ($body['url'] ?? ''));

        if ($url === '') {
            return $this->json([
                'success' => false,
                'message' => esc_html__('Please enter a URL.', 'custom-fields'),
            ], 422);
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->json([
                'success' => false,
                'message' => esc_html__('Please enter a valid URL.', 'custom-fields'),
            ], 422);
        }

        return $this->json([
            'success' => true,
            'html' => $this->previewHtml($url),
        ]);
    }

    /**
     * @throws Exception
     */
    private function previewHtml(string $url): string
    {
        if ($youtubeId = $this->youtubeId($url)) {
            return sprintf(
                '<div class="cf-oembed-card cf-oembed-video">
                    <div class="cf-oembed-video-inner">
                        <iframe
                            src="https://www.youtube.com/embed/%s"
                            title="YouTube video preview"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            allowfullscreen
                        ></iframe>
                    </div>
                </div>',
                esc_attr($youtubeId)
            );
        }

        if ($vimeoId = $this->vimeoId($url)) {
            return sprintf(
                '<div class="cf-oembed-card cf-oembed-video">
                    <div class="cf-oembed-video-inner">
                        <iframe
                            src="https://player.vimeo.com/video/%s"
                            title="Vimeo video preview"
                            frameborder="0"
                            allow="autoplay; fullscreen; picture-in-picture"
                            allowfullscreen
                        ></iframe>
                    </div>
                </div>',
                esc_attr($vimeoId)
            );
        }

        return sprintf(
            '<div class="cf-oembed-card">
            <strong>' . esc_html__('Embed URL', 'custom-fields') . '</strong>
            <p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>
        </div>',
            esc_attr($url),
            esc_html($url)
        );
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception
     * @throws RandomException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws TypeException
     * @throws ReflectionException
     * @throws \Exception
     */
    public function imageUpload(ServerRequestInterface $request): ResponseInterface
    {
        $files = $request->getUploadedFiles();
        $file = $files['image'] ?? null;

        if (! $file instanceof UploadedFileInterface) {
            return JsonResponseFactory::create([
                'success' => false,
                'message' => esc_html__('No image was uploaded.', 'custom-fields'),
            ], 422);
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            return JsonResponseFactory::create([
                'success' => false,
                'message' => esc_html__('The image upload failed.', 'custom-fields'),
            ], 422);
        }

        $mime = $file->getClientMediaType() ?? '';

        if (! str_starts_with($mime, 'image/')) {
            return JsonResponseFactory::create([
                'success' => false,
                'message' => esc_html__('Only image uploads are allowed.', 'custom-fields'),
            ], 422);
        }

        $originalName = $file->getClientFilename() ?: 'image';
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeName = bin2hex(random_bytes(16)) . ($extension ? '.' . strtolower($extension) : '');

        $uploadDir = site_path('uploads/custom-fields');

        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $targetPath = $uploadDir . Devflow::$PHP::DS . $safeName;

        $file->moveTo($targetPath);

        $url = public_site_upload_url('custom-fields/' . $safeName);

        return JsonResponseFactory::create([
            'success' => true,
            'image' => [
                'url' => $url,
                'name' => $originalName,
                'mime' => $mime,
            ],
        ]);
    }

    private function youtubeId(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return null;
        }

        $host = $parts['host'] ?? '';
        $path = trim($parts['path'] ?? '', '/');

        if (str_contains($host, 'youtu.be')) {
            return $path !== '' ? $path : null;
        }

        if (str_contains($host, 'youtube.com')) {
            parse_str($parts['query'] ?? '', $query);

            if (! empty($query['v'])) {
                return (string) $query['v'];
            }

            if (str_starts_with($path, 'shorts/')) {
                return substr($path, strlen('shorts/'));
            }

            if (str_starts_with($path, 'embed/')) {
                return substr($path, strlen('embed/'));
            }
        }

        return null;
    }

    private function vimeoId(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return null;
        }

        $host = $parts['host'] ?? '';

        if (! str_contains($host, 'vimeo.com')) {
            return null;
        }

        $path = trim($parts['path'] ?? '', '/');

        return preg_match('/^\d+$/', $path) ? $path : null;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws \Exception
     */
    public function normalizeName(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();

        return $this->json([
            'success' => true,
            'name' => new Slugify()->slugify((string) ($body['label'] ?? 'field')),
        ]);
    }

    /**
     * @throws \Exception
     */
    private function json(array $payload, int $status = 200): ResponseInterface
    {
        return JsonResponseFactory::create($payload, $status);
    }
}
