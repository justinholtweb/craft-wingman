<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Concerns;

use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use Illuminate\Database\Eloquent\Collection;
use JustinHoltWeb\Wingman\Elements\WingmanElement;
use JustinHoltWeb\Wingman\Lookup\ReverseLookup;

/**
 * For app models registered with Wingman (Pro): reach Craft content from the model.
 *
 * ```php
 * class Customer extends Model
 * {
 *     use \JustinHoltWeb\Wingman\Concerns\HasCraftElements;
 * }
 *
 * $customer->craftElement();                    // the customer's element
 * $customer->craftEntries('customers')->all();  // entries relating to it through the `customers` field
 *
 * $customers = Customer::all();
 * Customer::loadCraftEntries($customers, 'customers');   // two queries for the lot
 * $customers->first()->getRelation('craftEntries');      // list of entries
 * ```
 */
trait HasCraftElements
{
    /** The model's Craft element (live, current site), if it has one. */
    public function craftElement(): ?WingmanElement
    {
        return app(ReverseLookup::class)->element($this);
    }

    /**
     * Entries relating to this model, through `$field` or any Wingman field.
     *
     * @return ElementQueryInterface<ElementInterface>
     */
    public function craftEntries(?string $field = null): ElementQueryInterface
    {
        return app(ReverseLookup::class)->related($this, $field);
    }

    /**
     * Any element type relating to this model, by ref handle (`entry`, `asset`, `user`, …).
     *
     * @return ElementQueryInterface<ElementInterface>
     */
    public function craftRelated(string $elementType, ?string $field = null): ElementQueryInterface
    {
        return app(ReverseLookup::class)->related($this, $field, $elementType);
    }

    /**
     * Eager-loads related entries onto many models as the `craftEntries` relation.
     *
     * @param  Collection<int, static>  $models
     */
    public static function loadCraftEntries(Collection $models, ?string $field = null): void
    {
        $related = app(ReverseLookup::class)->relatedByModel($models, $field);

        foreach ($models as $model) {
            $model->setRelation('craftEntries', $related[(string) $model->getKey()] ?? []);
        }
    }
}
