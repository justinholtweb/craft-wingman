<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Templates;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use Illuminate\Database\Eloquent\Model;
use JustinHoltWeb\Wingman\Elements\WingmanElement;
use JustinHoltWeb\Wingman\Lookup\ReverseLookup;

/**
 * The `wingman` template global — `wingman.element(model)` in Twig,
 * `$wingman->element($model)` in Blade. Reverse lookups need Pro.
 */
class WingmanVariable
{
    public function __construct(private readonly ReverseLookup $lookup) {}

    /** The element for an app model row, if it has a live one. */
    public function element(mixed $model): ?WingmanElement
    {
        return $model instanceof Model ? $this->lookup->element($model) : null;
    }

    /**
     * Pro: entries relating to a model (or its element), through `field` or any Wingman field.
     *
     * @return ElementQueryInterface<ElementInterface>
     */
    public function relatedEntries(Model|WingmanElement $subject, ?string $field = null): ElementQueryInterface
    {
        return $this->lookup->related($subject, $field);
    }

    /**
     * Pro: any element type relating to a model, by ref handle (`entry`, `asset`, `user`, …).
     *
     * @return ElementQueryInterface<ElementInterface>
     */
    public function related(Model|WingmanElement $subject, string $elementType, ?string $field = null): ElementQueryInterface
    {
        return $this->lookup->related($subject, $field, $elementType);
    }
}
