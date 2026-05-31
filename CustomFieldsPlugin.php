<?php

declare(strict_types=1);

namespace Plugin\CustomFields;

use App\Application\Devflow;
use App\Infrastructure\Services\Plugin;
use App\Shared\Services\Registry;
use App\Shared\Services\Utils;
use Plugin\CustomFields\Controller\AjaxFieldController;
use Plugin\CustomFields\Controller\FieldGroupController;
use Plugin\CustomFields\Controller\SettingsController;
use Plugin\CustomFields\Domain\FieldRenderer;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Qubus\EventDispatcher\ActionFilter\Action;
use Qubus\EventDispatcher\ActionFilter\Filter;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Exception;
use Qubus\Expressive\Schema\CreateTable;
use Qubus\Http\ServerRequest;
use ReflectionException;

use function App\Shared\Helpers\add_plugins_submenu;
use function App\Shared\Helpers\cms_enqueue_css;
use function App\Shared\Helpers\cms_enqueue_js;
use function App\Shared\Helpers\delete_option;
use function App\Shared\Helpers\get_option;
use function App\Shared\Helpers\plugin_basename;
use function App\Shared\Helpers\plugin_dir_path;
use function App\Shared\Helpers\plugin_url;
use function App\Shared\Helpers\update_option;
use function dirname;
use function get_class;
use function Qubus\Security\Helpers\esc_html__;
use function Qubus\Security\Helpers\t__;

class CustomFieldsPlugin extends Plugin
{
    /**
     * @inheritDoc
     * @throws Exception
     * @throws ReflectionException
     */
    public function meta(): array
    {
        $plugin = [
            'name' => esc_html__(string: 'Custom Fields', domain: 'custom-fields'),
            'id' => 'custom-fields',
            'slug' => 'CustomFields',
            'author' => 'Joshua Parker',
            'version' => '1.0.2',
            'description' => esc_html__(
                'Full featured navigation/menu builder plugin for Devflow CMF.',
                'custom-fields'
            ),
            'basename' => plugin_basename(dirname(__FILE__)),
            'path' => plugin_dir_path(dirname(__FILE__)),
            'url' => plugin_url('', __CLASS__),
            'pluginUri' => 'https://github.com/getdevflow/custom-fields',
            'authorUri' => 'https://joshuaparker.dev/',
            'className' => get_class($this),
            'screenshot' => plugin_url('CustomFields/images/screenshot.png'),
        ];

        Registry::getInstance()->set('custom-fields', $plugin);

        return $plugin;
    }

    /**
     * @inheritDoc
     * @throws ReflectionException
     */
    public function handle(): void
    {
        Action::getInstance()->addAction(hook: 'cms_admin_head', callback: [$this, 'enqueueStyles']);
        Action::getInstance()->addAction(hook: 'cms_admin_footer', callback: [$this, 'enqueueScripts']);
        Action::getInstance()->addAction(hook: 'cms_admin_head', callback: [$this, 'enqueueRuntimeCss']);
        Action::getInstance()->addAction(hook: 'cms_admin_footer', callback: [$this, 'enqueueRuntimeGalleryJs']);
        Action::getInstance()->addAction(hook: 'plugins_submenu', callback: [$this, 'registerSubmenu']);

        Filter::getInstance()->addFilter(hook: 'content_attribute_box_extended', callback: function ($form, ?string $type = null, ?string $id = null) {
            return $this->renderContentFields($form, $id, $type);
        }, priority: 5, arguments: 3);

        Filter::getInstance()->addFilter(hook: 'content_attribute_box_side', callback: function ($form, ?string $type = null, ?string $id = null) {
            return $this->renderContentFields($form, $id, $type, 'side');
        }, priority: 5, arguments: 3);

        Filter::getInstance()->addFilter(hook: 'product_attribute_box_extended', callback: function ($form, ?string $id = null) {
            return $this->renderProductFields($form, $id);
        }, priority: 5, arguments: 3);

        Filter::getInstance()->addFilter(hook: 'product_attribute_box_side', callback: function ($form, ?string $id = null) {
            return $this->renderProductFields($form, $id, 'side');
        }, priority: 5, arguments: 3);

        Filter::getInstance()->addFilter(hook: 'user.attribute.box.extended', callback: function (string $html = '', ?string $id = null) {
            return $html . $this->renderUserFields($id);
        }, priority: 5, arguments: 2);

        Filter::getInstance()->addFilter(hook: 'user.attribute.box.side', callback: function (string $html = '', ?string $id = null) {
            return $html . $this->renderUserFields($id, 'side');
        }, priority: 5);

        Action::getInstance()->addAction(hook: 'admin_notices', callback: function () {
            $html = '<div id="cf-error-summary" class="alert alert-danger hidden" tabindex="-1">';
                $html .= '<strong>' . t__('Please fix the following custom field errors:', 'custom-fields') . '</strong>';
                $html .= '<ul></ul>';
            $html .= '</div>';
            echo $html;
        }, priority: 1);

        Action::getInstance()->addAction(hook: 'plugins_loaded', callback: [$this, 'render'], priority: 1);
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws TypeException
     */
    protected function migrateUp(): void
    {
        if (!$this->dfdb->schema()->hasTable(table: $this->dfdb->prefix . 'custom_field_group')) {
            $this->dfdb->schema()
                ->create(
                    table: $this->dfdb->prefix . 'custom_field_group',
                    callback: function (CreateTable $table) {
                        $table->string(name: 'id', length: 36)
                            ->primary();
                        $table->string(name: 'title', length: 191)->notNull();
                        $table->string(name: 'slug', length: 191)->notNull()->unique();
                        $table->string(name: 'status', length: 36)->notNull()->defaultValue('active');
                        $table->text(name: 'location')->size('big');
                        $table->text(name: 'settings')->size('big');
                        $table->integer(name: 'field_order')
                            ->size('small')
                            ->notNull()
                            ->defaultValue(0);
                    }
                );
        };

        if (!$this->dfdb->schema()->hasTable(table: $this->dfdb->prefix . 'custom_field')) {
            $this->dfdb->schema()
                ->create(
                    table: $this->dfdb->prefix . 'custom_field',
                    callback: function (CreateTable $table) {
                        $table->string(name: 'id', length: 36)
                            ->primary();
                        $table->string(name: 'group_id', length: 36)->notNull();
                        $table->string(name: 'parent_id', length: 36);
                        $table->string(name: 'parent_zone', length: 64);
                        $table->string(name: 'field_type', length: 34)->notNull();
                        $table->string(name: 'field_name', length: 191)->notNull();
                        $table->string(name: 'field_label', length: 191);
                        $table->string(name: 'field_placeholder', length: 191);
                        $table->text(name: 'field_help');
                        $table->text(name: 'field_default')->size('big');
                        $table->text(name: 'field_options')->size('big');
                        $table->text(name: 'field_settings')->size('big');
                        $table->text(name: 'style_settings')->size('big');
                        $table->text(name: 'validation_rules')->size('big');
                        $table->integer(name: 'sort_order')
                            ->size('small')
                            ->notNull()
                            ->defaultValue(0);

                        $table->foreign('group_id', $this->dfdb->prefix . 'group_id')
                            ->references($this->dfdb->prefix . 'custom_field_group', 'id')
                            ->onDelete('cascade');

                        $table->foreign('parent_id', $this->dfdb->prefix . 'parent_id')
                            ->references($this->dfdb->prefix . 'custom_field', 'id')
                            ->onDelete('cascade');
                    }
                );
        };

        if (false === get_option('custom_fields_uninstall_on_deactivate')) {
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
        }
    }

    /**
     * @throws Exception
     */
    protected function migrateDown(): void
    {
        if ($this->dfdb->schema()->hasTable(table: $this->dfdb->prefix . 'custom_field')) {
            $this->dfdb->schema()->drop(table: $this->dfdb->prefix . 'custom_field');
        }

        if ($this->dfdb->schema()->hasTable(table: $this->dfdb->prefix . 'custom_field_group')) {
            $this->dfdb->schema()->drop(table: $this->dfdb->prefix . 'custom_field_group');
        }
    }

    /**
     * @throws Exception
     */
    public function enqueueStyles(): void
    {
        if (
            !str_starts_with(
                Utils::getPathInfo(
                    '/admin/plugin/' . $this->id() . '/'
                ),
                '/admin/plugin/' . $this->id() . '/'
            )
        ) {
            return;
        }

        cms_enqueue_css(
            config: 'plugin',
            asset: $this->url() . '/css/custom-fields.css',
            slug: $this->id()
        );
    }

    /**
     * @throws Exception
     */
    public function enqueueScripts(): void
    {
        if (
            !str_starts_with(
                Utils::getPathInfo(
                    '/admin/plugin/' . $this->id() . '/'
                ),
                '/admin/plugin/' . $this->id() . '/'
            )
        ) {
            return;
        }

        cms_enqueue_js(
            config: 'plugin',
            asset: $this->url() . '/js/custom-fields-builder.js',
            slug: $this->id()
        );
    }

    /**
     * @throws Exception
     */
    public function enqueueRuntimeCss(): void
    {
        if (
                !str_starts_with(
                    Utils::getPathInfo(
                        '/admin/content-type/'
                    ),
                    '/admin/content-type/'
                ) &&
                !str_starts_with(
                    Utils::getPathInfo(
                        '/admin/product/'
                    ),
                    '/admin/product/'
                ) &&
                !str_starts_with(
                    Utils::getPathInfo(
                        '/admin/user/'
                    ),
                    '/admin/user/'
                ) &&
                !str_starts_with(
                    Utils::getPathInfo(
                        '/admin/user/'
                    ),
                    '/admin/user/'
                )
        ) {
            return;
        }

        cms_enqueue_css(
            config: 'plugin',
            asset: $this->url() . '/css/custom-fields.css',
            slug: $this->id()
        );
    }

    /**
     * @throws Exception
     */
    public function enqueueRuntimeGalleryJs(): void
    {
        if (
                !str_starts_with(
                    Utils::getPathInfo(
                        '/admin/content-type/'
                    ),
                    '/admin/content-type/'
                ) &&
                !str_starts_with(
                    Utils::getPathInfo(
                        '/admin/product/'
                    ),
                    '/admin/product/'
                ) &&
                !str_starts_with(
                    Utils::getPathInfo(
                        '/admin/user/'
                    ),
                    '/admin/user/'
                )
        ) {
            return;
        }

        cms_enqueue_js(
            config: 'plugin',
            asset: $this->url() . '/js/custom-fields-connector.js',
            slug: $this->id()
        );
        cms_enqueue_js(
            config: 'plugin',
            asset: $this->url() . '/js/custom-fields-runtime.js',
            slug: $this->id()
        );
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws ReflectionException
     * @throws TypeException
     */
    public function registerSubmenu(): void
    {
        echo add_plugins_submenu(
            menuTitle: $this->meta()['name'],
            menuRoute: 'plugin/' . $this->meta()['id'],
            screen: $this->meta()['id'],
            permission: 'manage:plugins'
        );
    }

    /**
     * @param mixed $form
     * @param string|null $id
     * @param string $position
     * @return string
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    private function renderProductFields(
        mixed $form,
        ?string $id = null,
        string $position = 'extended'
    ): string {
        return FieldRenderer::make()->renderFor(
            context: 'product',
            fieldNamePrefix: 'product_field',
            objectId: $id,
            type: null,
            position: $position
        );
    }

    /**
     * @param mixed $form
     * @param string|null $contentId
     * @param string|null $contentType
     * @param string $position
     * @return string
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function renderContentFields(
        mixed $form,
        ?string $contentId = null,
        ?string $contentType = null,
        string $position = 'extended'
    ): string {
        return FieldRenderer::make()->renderFor(
            context: 'content',
            fieldNamePrefix: 'content_field',
            objectId: $contentId,
            type: $contentType,
            position: $position
        );
    }

    /**
     * @param string|null $userId
     * @param string $position
     * @return string
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws TypeException
     */
    public function renderUserFields(?string $userId = null, string $position = 'extended'): string
    {
        return FieldRenderer::make()->renderFor(
            context: 'user',
            fieldNamePrefix: 'user_field',
            objectId: $userId,
            type: null,
            position: $position
        );
    }

    public function render(): void
    {
        $router = Devflow::$PHP->router;

        $router->group('/admin/plugin/custom-fields', function ($route) {
            $route->get('/', fn(FieldGroupController $controller) => $controller->index());

            $route->get('/settings/', fn(SettingsController $controller) => $controller->edit());
            $route->post('/settings/update/', fn(SettingsController $controller, ServerRequest $r) => $controller->update($r));

            $route->get('/create/', fn(FieldGroupController $controller) => $controller->create());
            $route->post('/store/', fn(FieldGroupController $controller, ServerRequest $r) => $controller->store($r));

            $route->get('/import/', fn(FieldGroupController $controller) => $controller->import());
            $route->post('/import/', fn(FieldGroupController $controller, ServerRequest $r) => $controller->handleImport($r));

            $route->post('/bulk/', fn(FieldGroupController $controller, ServerRequest $r) => $controller->bulk($r));

            $route->get('/{id}/edit/', fn(FieldGroupController $controller, string $id) => $controller->edit($id));
            $route->post('/{id}/update/', fn(FieldGroupController $controller, ServerRequest $r, string $id) => $controller->update($r, $id));

            $route->get('/{id}/delete/', fn(FieldGroupController $controller, string $id) => $controller->delete($id));
            $route->get('/{id}/clone/', fn(FieldGroupController $controller, string $id) => $controller->clone($id));
            $route->get('/{id}/export/', fn(FieldGroupController $controller, string $id) => $controller->export($id));

            $route->post('/ajax/field-template/', fn(AjaxFieldController $controller, ServerRequest $r) => $controller->fieldTemplate($r));
            $route->post('/ajax/validate-field/', fn(AjaxFieldController $controller, ServerRequest $r) => $controller->validateField($r));
            $route->post('/ajax/validate-group/', fn(AjaxFieldController $controller, ServerRequest $r) => $controller->validateGroup($r));
            $route->post('/ajax/normalize-name/', fn(AjaxFieldController $controller, ServerRequest $r) => $controller->normalizeName($r));
            $route->post('/ajax/oembed-preview/', fn(AjaxFieldController $controller, ServerRequest $r) => $controller->oembedPreview($r));
            $route->post('/ajax/image-upload/', fn(AjaxFieldController $controller, ServerRequest $r) => $controller->imageUpload($r));
        });
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    public function onActivation(): void
    {
        $this->migrateUp();
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws \Exception
     */
    public function onDeactivation(): void
    {
        if ((bool) get_option('custom_fields_uninstall_on_deactivate', '0') === false) {
            return;
        }

        delete_option('custom_fields_uninstall_on_deactivate');
        delete_option('custom_fields_default_gallery_size');
        delete_option('custom_fields_default_field_style');

        $this->migrateDown();
    }
}
