<?php

declare(strict_types=1);

namespace Plugin\CustomFields\Service;

use Plugin\CustomFields\Domain\FieldSanitizer;
use Plugin\CustomFields\Domain\FieldValidator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;

use function App\Shared\Helpers\update_content_attribute;
use function App\Shared\Helpers\update_product_attribute;
use function App\Shared\Helpers\update_user_attribute;

final readonly class FieldValueService
{
    public function __construct(
        private FieldSanitizer $sanitizer = new FieldSanitizer(),
        private FieldValidator $validator = new FieldValidator()
    ) {
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function saveProductFields(string $productId, array $fields, array $submitted): array
    {
        $errors = $this->validator->validateSubmittedFields($fields, $submitted);

        if ($errors !== []) {
            return $errors;
        }

        $clean = $this->sanitizer->sanitizeSubmittedFields($fields, $submitted);

        foreach ($clean as $key => $value) {
            update_product_attribute($productId, $key, $value);
        }

        return [];
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function saveContentFields(string $contentId, array $fields, array $submitted): array
    {
        $errors = $this->validator->validateSubmittedFields($fields, $submitted);

        if ($errors !== []) {
            return $errors;
        }

        $clean = $this->sanitizer->sanitizeSubmittedFields($fields, $submitted);

        foreach ($clean as $key => $value) {
            update_content_attribute($contentId, $key, $value);
        }

        return [];
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function saveUserFields(string $siteId, string $userId, array $fields, array $submitted): array
    {
        $errors = $this->validator->validateSubmittedFields($fields, $submitted);

        if ($errors !== []) {
            return $errors;
        }

        $clean = $this->sanitizer->sanitizeSubmittedFields($fields, $submitted);

        foreach ($clean as $key => $value) {
            update_user_attribute($siteId, $userId, $key, $value);
        }

        return [];
    }
}
