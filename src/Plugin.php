<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman;

use CraftCms\Cms\Element\Eloquent\EloquentElements;
use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\Ui\Controls\Table;
use CraftCms\Cms\Ui\Controls\Textarea;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use Illuminate\Support\Facades\Log;
use JustinHoltWeb\Wingman\Registry\GeneratedClassLoader;
use JustinHoltWeb\Wingman\Registry\ModelDiscovery;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;
use Override;
use Throwable;

use function CraftCms\Cms\t;

/**
 * Wingman — introduce your app's Eloquent models to Craft: elements, relation
 * fields and a CP index per model, configured in the CP instead of in PHP.
 *
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public const string EDITION_LITE = 'lite';

    public const string EDITION_PRO = 'pro';

    public bool $hasCpSettings = true;

    protected array $commands = [
        Commands\StatusCommand::class,
        Commands\BackfillCommand::class,
    ];

    protected array $utilities = [
        Utilities\WingmanUtility::class,
    ];

    #[Override]
    public static function editions(): array
    {
        return [self::EDITION_LITE, self::EDITION_PRO];
    }

    public function register(): void
    {
        parent::register();

        // Registered early: Craft may instantiate a generated element type from
        // `elements.type` (or a generated field type from project config) at any point.
        $this->app->singleton(ModelRegistry::class);
        $this->app->make(GeneratedClassLoader::class)->register();
    }

    public function boot(): void
    {
        $this->app->bind(Settings::class, fn (): Settings => $this->getSettings());

        $registry = $this->app->make(ModelRegistry::class);
        $eloquentElements = $this->app->make(EloquentElements::class);

        // One model at a time, each guarded: a model deleted from the app, or one whose
        // identity or CP URI collides with something registered since, must never take
        // the site down. Its elements degrade to Craft's missing-element handling.
        foreach ($this->getSettings()->modelDefinitions() as ['handle' => $handle, 'modelClass' => $class, 'label' => $label]) {
            try {
                $registry->add($handle, $class, $label);
                $eloquentElements->register(ModelRegistry::elementClass($handle));
            } catch (Throwable $e) {
                $registry->remove($handle);
                Log::warning("Wingman skipped model {$handle}: {$e->getMessage()}");
            }
        }
    }

    #[Override]
    protected static function createSettings(): Settings
    {
        return new Settings;
    }

    #[Override]
    public function settingsUi(UiContext $context = new UiContext): Ui
    {
        $settings = $this->getSettings();
        $options = [];

        foreach ($this->app->make(ModelDiscovery::class)->discover($settings->modelPathList()) as $class => $handle) {
            $options[] = ['label' => $class, 'value' => $class];
        }

        return Ui::make([
            Field::make(t('Models', category: 'wingman'), Table::make('models')
                ->columns([
                    'handle' => ['heading' => t('Handle', category: 'wingman'), 'type' => 'singleline', 'code' => true, 'placeholder' => 'Customer'],
                    'modelClass' => ['heading' => t('Eloquent model', category: 'wingman'), 'type' => 'select', 'options' => $options],
                    'label' => ['heading' => t('Label', category: 'wingman'), 'type' => 'singleline', 'placeholder' => t('Customers', category: 'wingman')],
                ])
                ->allowAdd()
                ->allowDelete()
                ->allowReorder()
                ->addRowLabel(t('Add a model', category: 'wingman')))
                ->instructions(t('Each model gets a control panel index, a relation field type and template queries. Handles become class names and can’t change once a model has elements.', category: 'wingman')),
            Field::make(t('Model directories', category: 'wingman'), Textarea::make('modelPaths')->placeholder('app/Models'))
                ->instructions(t('Where to look for Eloquent models, one directory per line, relative to the project root.', category: 'wingman')),
        ]);
    }
}
