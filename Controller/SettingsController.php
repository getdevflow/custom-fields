<?php

declare(strict_types=1);

namespace Plugin\CustomFields\Controller;

use App\Application\Devflow;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use ReflectionException;

use function App\Shared\Helpers\admin_url;
use function App\Shared\Helpers\get_option;
use function App\Shared\Helpers\update_option;
use function Codefy\Framework\Helpers\view;
use function Qubus\Routing\Helpers\redirect;
use function Qubus\Security\Helpers\esc_html__;

final class SettingsController
{
    /**
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     * @throws \Exception
     */
    public function edit(): ResponseInterface
    {
        return view('plugin::CustomFields/view/settings', [
            'title' => esc_html__('Custom Fields Settings', 'custom-fields'),
            'settings' => [
                'uninstall_on_deactivate' => $this->optionBool('custom_fields_uninstall_on_deactivate', false),
                'default_gallery_size' => get_option('custom_fields_default_gallery_size', 'medium'),
                'default_field_style' => get_option('custom_fields_default_field_style', 'default'),
            ],
        ]);
    }

    /**
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeException
     */
    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();

        update_option(
            'custom_fields_uninstall_on_deactivate',
            isset($body['uninstall_on_deactivate']) ? 1 : 0
        );

        update_option(
            'custom_fields_default_gallery_size',
            (string) ($body['default_gallery_size'] ?? 'medium')
        );

        update_option(
            'custom_fields_default_field_style',
            (string) ($body['default_field_style'] ?? 'default')
        );

        Devflow::$PHP->flash->success(
            esc_html__('Custom Fields settings updated successfully.', 'custom-fields')
        );

        return redirect(admin_url('plugin/custom-fields/settings/'));
    }

    /**
     * @throws ReflectionException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    private function optionBool(string $key, bool $default = false): bool
    {
        $value = get_option($key, $default ? '1' : '0');

        if (is_array($value)) {
            $value = reset($value);
        }

        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
    }
}
