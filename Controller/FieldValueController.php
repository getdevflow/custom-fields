<?php

namespace Plugin\CustomFields\Controller;

use JsonException;
use Plugin\CustomFields\Domain\FieldGroupRepository;
use Plugin\CustomFields\Service\FieldValueService;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Http\Response;
use ReflectionException;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final readonly class FieldValueController
{
    public function __construct(
        private FieldGroupRepository $groups,
        private FieldValueService $saver = new FieldValueService()
    ) {
    }

    /**
     * @param ServerRequestInterface $request
     * @param string $productId
     * @return ResponseInterface
     * @throws JsonException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws TypeException
     * @throws ReflectionException
     */
    public function saveProduct(ServerRequestInterface $request, string $productId): ResponseInterface
    {
        $body = $request->getParsedBody();

        $fields = $this->fieldsForContext('product');
        $submitted = $body['product_field'] ?? [];

        $errors = $this->saver->saveProductFields($productId, $fields, $submitted);

        return $this->json([
            'success' => $errors === [],
            'errors' => $errors,
        ], $errors === [] ? 200 : 422);
    }

    /**
     * @param ServerRequestInterface $request
     * @param string $contentId
     * @param string|null $contentType
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function saveContent(
        ServerRequestInterface $request,
        string $contentId,
        ?string $contentType = null
    ): ResponseInterface {
        $body = $request->getParsedBody();

        $fields = $this->fieldsForContext('content', $contentType);
        $submitted = $body['content_field'] ?? [];

        $errors = $this->saver->saveContentFields($contentId, $fields, $submitted);

        return $this->json([
            'success' => $errors === [],
            'errors' => $errors,
        ], $errors === [] ? 200 : 422);
    }

    /**
     * @param ServerRequestInterface $request
     * @param string $siteId
     * @param string $userId
     * @return ResponseInterface
     * @throws ContainerExceptionInterface
     * @throws JsonException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function saveUser(ServerRequestInterface $request, string $siteId, string $userId): ResponseInterface
    {
        $body = $request->getParsedBody();

        $fields = $this->fieldsForContext('user');
        $submitted = $body['user_field'] ?? [];

        $errors = $this->saver->saveUserFields($siteId, $userId, $fields, $submitted);

        return $this->json([
            'success' => $errors === [],
            'errors' => $errors,
        ], $errors === [] ? 200 : 422);
    }

    private function fieldsForContext(string $context, ?string $contentType = null): array
    {
        $fields = [];

        foreach ($this->groups->activeFor($context, $contentType) as $group) {
            foreach ($group['fields'] ?? [] as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * @throws JsonException
     */
    private function json(array $payload, int $status = 200): ResponseInterface
    {
        return new Response(
            body: json_encode($payload, JSON_THROW_ON_ERROR),
            status: $status,
            headers: [
                'Content-Type' => 'application/json',
            ]
        );
    }
}
