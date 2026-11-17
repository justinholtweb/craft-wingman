<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman;

use CraftCms\Cms\Element\Eloquent\EloquentElements;
use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Textarea;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use Illuminate\Support\Facades\Log;
use JustinHoltWeb\Wingman\Registry\GeneratedClassLoader;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;
use Override;
use Throwable;

use function CraftCms\Cms\t;

/**
 * Wingman — Introduce your app's Eloquent models to Craft — elements, relation fields and reverse lookups, configured in the CP instead of in PHP.
 *
 * See PLAN.md for the build phases.
 *
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public bool $hasCpSettings = true;

    public const string EDITION_LITE = 'lite';

    public const string EDITION_PRO = 'pro';

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
        $registry = $this->app->make(ModelRegistry::class);
        $elementTypes = [];

        foreach ($this->getSettings()->models as $handle => $config) {
            try {
                $label = $config['label'] ?? null;
                $registry->add((string) $handle, (string) ($config['modelClass'] ?? ''), is_string($label) ? $label : null);
                $elementTypes[] = ModelRegistry::elementClass((string) $handle);
            } catch (Throwable $e) {
                Log::warning("Wingman skipped model {$handle}: {$e->getMessage()}");
            }
        }

        if ($elementTypes !== []) {
            $this->app->make(EloquentElements::class)->register(...$elementTypes);
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
        return Ui::make([
            Field::make(t('Model directories', category: 'wingman'), Textarea::make('modelPaths'))
                ->instructions(t('Directories scanned for Eloquent models, one per line (default: app/Models).', category: 'wingman')),
            Field::make(t('Backfill on register', category: 'wingman'), Lightswitch::make('autoBackfill'))
                ->instructions(t('Create elements for existing model rows as soon as a model is registered.', category: 'wingman')),
        ]);
    }
}
