<?php

declare(strict_types=1);

namespace Plugin\CustomFields\Controller;

use App\Application\Devflow;
use Cocur\Slugify\Slugify;
use Codefy\QueryBus\UnresolvableQueryHandlerException;
use JsonException;
use Laminas\Diactoros\Stream;
use Plugin\CustomFields\Domain\FieldGroupRepository;
use Plugin\CustomFields\Domain\FieldTypeRegistry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Qubus\Exception\Exception;
use Qubus\Http\Response;
use ReflectionException;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\get_all_content_types;
use function Codefy\Framework\Helpers\view;
use function Qubus\Routing\Helpers\redirect;
use function Qubus\Security\Helpers\esc_html__;
use function sprintf;
use function time;
use function trim;

use const JSON_THROW_ON_ERROR;

final readonly class FieldGroupController
{
    public function __construct(
        private FieldGroupRepository $groups
    ) {
    }

    /**
     * @throws \Exception
     */
    public function index(): ResponseInterface
    {
        return view('plugin::CustomFields/view/index', [
            'title' => esc_html__('Custom Field Groups', 'custom-fields'),
            'groups' => $this->groups->all(),
        ]);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws UnresolvableQueryHandlerException
     * @throws \Exception
     */
    public function create(): ResponseInterface
    {
        return view('plugin::CustomFields/view/update', [
            'title' => esc_html__('Add Field Group', 'custom-fields'),
            'group' => null,
            'fields' => [],
            'fieldTypes' => FieldTypeRegistry::all(),
            'contentTypes' => get_all_content_types(),
            'action' => admin_url('plugin/custom-fields/store/'),
        ]);
    }

    /**
     * @throws JsonException
     * @throws Exception
     */
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $payload = $this->payloadFromRequest($request);

        $id = $this->groups->create($payload);

        Devflow::$PHP->flash->success(esc_html__('Field group created successfully.', 'custom-fields'));

        return redirect(admin_url(sprintf('plugin/custom-fields/%s/edit/', $id)));
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws UnresolvableQueryHandlerException
     * @throws \Exception
     */
    public function edit(string $id): ResponseInterface
    {
        $group = $this->groups->find($id);

        if ($group === null) {
            Devflow::$PHP->flash->error(esc_html__('Field group not found.', 'custom-fields'));

            return redirect(admin_url('plugin/custom-fields/'));
        }

        return view('plugin::CustomFields/view/update', [
            'title' => esc_html__('Edit Field Group', 'custom-fields'),
            'group' => $group,
            'fields' => $group['fields'] ?? [],
            'fieldTypes' => FieldTypeRegistry::all(),
            'contentTypes' => get_all_content_types(),
            'action' => admin_url(sprintf('plugin/custom-fields/%s/update/', $id)),
        ]);
    }

    /**
     * @throws JsonException
     * @throws Exception
     */
    public function update(ServerRequestInterface $request, string $id): ResponseInterface
    {
        $payload = $this->payloadFromRequest($request);

        $this->groups->update($id, $payload);

        Devflow::$PHP->flash->success(esc_html__('Field group updated successfully.', 'custom-fields'));

        return redirect(admin_url(sprintf('plugin/custom-fields/%s/edit/', $id)));
    }

    /**
     * @throws Exception
     */
    public function delete(string $id): ResponseInterface
    {
        $this->groups->delete($id);

        Devflow::$PHP->flash->success(esc_html__('Field group deleted successfully.', 'custom-fields'));

        return redirect(admin_url('plugin/custom-fields/'));
    }

    /**
     * @param string $id
     * @return ResponseInterface
     * @throws Exception
     * @throws JsonException
     */
    public function clone(string $id): ResponseInterface
    {
        $newId = $this->groups->clone($id);

        if ($newId === null) {
            Devflow::$PHP->flash->error(esc_html__('Unable to clone field group.', 'custom-fields'));

            return redirect(admin_url('plugin/custom-fields/'));
        }

        Devflow::$PHP->flash->success(esc_html__('Field group cloned successfully.', 'custom-fields'));

        return redirect(admin_url(sprintf('plugin/custom-fields/%s/edit/', $newId)));
    }

    /**
     * @throws JsonException
     */
    private function payloadFromRequest(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        $title = trim((string) ($body['title'] ?? esc_html__('Untitled Field Group', 'custom-fields')));
        $slug = trim((string) ($body['slug'] ?? ''));

        if ($slug === '') {
            $slug = new Slugify()->slugify($title . '-' . time());
        }

        $fieldsJson = (string) ($body['fields_json'] ?? '[]');
        $fields = json_decode($fieldsJson, true, 512, JSON_THROW_ON_ERROR);

        return [
            'title' => $title,
            'slug' => $slug,
            'status' => $body['status'] ?? 'active',
            'location' => $this->normalizeLocation($body),
            'settings' => [
                'position' => $body['position'] ?? 'normal',
                'style' => $body['style'] ?? 'default',
                'hide_on_screen' => $body['hide_on_screen'] ?? [],
            ],
            'field_order' => (int) ($body['field_order'] ?? 0),
            'fields' => $fields,
        ];
    }

    private function normalizeLocation(array $body): array
    {
        $location = $body['location'] ?? [];

        if (! is_array($location)) {
            $location = [];
        }

        $contentTypes = $body['content_types'] ?? [];

        if (is_array($contentTypes)) {
            foreach ($contentTypes as $contentType) {
                $location[] = 'content:' . $contentType;
            }
        }

        return array_values(array_unique($location));
    }

    /**
     * @throws Exception
     * @throws JsonException
     * @throws \Exception
     */
    public function export(string $id): ResponseInterface
    {
        $payload = $this->groups->export($id);

        if ($payload === null) {
            Devflow::$PHP->flash->error(esc_html__('Field group not found.', 'custom-fields'));

            return redirect(admin_url('plugin/custom-fields/'));
        }

        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $stream = fopen('php://temp', 'rb+');
        fwrite($stream, $json);
        rewind($stream);

        $filename = ($payload['slug'] ?? 'field-group') . '.json';

        return new Response(
            body: new Stream($stream),
            status: 200,
            headers: [
                'Content-Type' => 'application/json',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
                'Content-Length' => (string) strlen($json),
            ]
        );
    }

    /**
     * @throws \Exception
     */
    public function import(): ResponseInterface
    {
        return view('plugin::CustomFields/view/import', [
            'title' => esc_html__('Import Field Group', 'custom-fields'),
            'action' => admin_url('plugin/custom-fields/import/'),
        ]);
    }

    /**
     * @throws Exception
     * @throws JsonException
     */
    public function handleImport(ServerRequestInterface $request): ResponseInterface
    {
        $files = $request->getUploadedFiles();
        $file = $files['import_file'] ?? null;

        if ($file === null || $file->getError() !== UPLOAD_ERR_OK) {
            Devflow::$PHP->flash->error(esc_html__('Please upload a valid JSON export file.', 'custom-fields'));

            return redirect(admin_url('plugin/custom-fields/import/'));
        }

        $contents = $file->getStream()->getContents();

        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            Devflow::$PHP->flash->error(esc_html__('The uploaded file is not valid JSON.', 'custom-fields'));

            return redirect(admin_url('plugin/custom-fields/import/'));
        }

        if (($payload['schema'] ?? '') === 'devflow-custom-fields.bundle.v1') {
            $groups = $payload['groups'] ?? [];

            if (! is_array($groups) || $groups === []) {
                Devflow::$PHP->flash->error(esc_html__('The bundle does not contain any field groups.', 'custom-fields'));

                return redirect(admin_url('plugin/custom-fields/import/'));
            }

            foreach ($groups as $group) {
                $this->groups->import($group);
            }

            Devflow::$PHP->flash->success(esc_html__('Field group bundle imported successfully.', 'custom-fields'));

            return redirect(admin_url('plugin/custom-fields/'));
        }

        if (($payload['schema'] ?? '') !== 'devflow-custom-fields.v1') {
            Devflow::$PHP->flash->error(esc_html__('This file is not a valid Devflow Custom Fields export.', 'custom-fields'));

            return redirect(admin_url('plugin/custom-fields/import/'));
        }

        $id = $this->groups->import($payload);

        Devflow::$PHP->flash->success(esc_html__('Field group imported successfully.', 'custom-fields'));

        return redirect(admin_url(sprintf('plugin/custom-fields/%s/edit/', $id)));
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception
     * @throws JsonException
     */
    public function bulk(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();

        $action = (string) ($body['bulk_action'] ?? '');
        $ids = $body['field_group_ids'] ?? [];

        if (! is_array($ids) || $ids === []) {
            Devflow::$PHP->flash->error(esc_html__('Please select at least one field group.', 'custom-fields'));

            return redirect(admin_url('plugin/custom-fields/'));
        }

        $ids = array_values(array_filter(array_map('strval', $ids)));

        if ($action === 'export') {
            return $this->bulkExport($ids);
        }

        match ($action) {
            'delete' => $this->groups->deleteMany($ids),
            'activate' => $this->groups->updateStatusMany($ids, 'active'),
            'deactivate' => $this->groups->updateStatusMany($ids, 'inactive'),
            default => null,
        };

        match ($action) {
            'delete' => Devflow::$PHP->flash->success(
                esc_html__('Selected field groups deleted.', 'custom-fields')
            ),
            'activate' => Devflow::$PHP->flash->success(
                esc_html__('Selected field groups activated.', 'custom-fields')
            ),
            'deactivate' => Devflow::$PHP->flash->success(
                esc_html__('Selected field groups deactivated.', 'custom-fields')
            ),
            default => Devflow::$PHP->flash->error(
                esc_html__('Please select a valid bulk action.', 'custom-fields')
            ),
        };

        return redirect(admin_url('plugin/custom-fields/'));
    }

    /**
     * @throws JsonException
     */
    private function bulkExport(array $ids): ResponseInterface
    {
        $payload = $this->groups->exportMany($ids);

        $json = json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        $stream = fopen('php://temp', 'rb+');
        fwrite($stream, $json);
        rewind($stream);

        return new Response(
            body: new Stream($stream),
            status: 200,
            headers: [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="custom-field-groups.json"',
                'Content-Length' => (string) strlen($json),
            ]
        );
    }
}
