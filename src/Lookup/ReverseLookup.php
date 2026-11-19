<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Lookup;

use CraftCms\Cms\Database\Table;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\ElementTypes;
use CraftCms\Cms\Element\Eloquent\ModelLinks;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Support\Facades\Fields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JustinHoltWeb\Wingman\Elements\WingmanElement;
use JustinHoltWeb\Wingman\Fields\WingmanField;
use JustinHoltWeb\Wingman\Plugin;
use JustinHoltWeb\Wingman\Registry\ModelRegistry;
use LogicException;

/**
 * Model ⇄ element lookups.
 *
 * Lite: the element for a model (or many models, in one query).
 * Pro: reverse lookups — the entries (or other elements) that relate *to* a model
 * through a Wingman relation field — one at a time, or for many models at once.
 *
 * Everything here goes through Craft element queries, so status, site and
 * permissions-free front-end rules are Craft's own: live elements of the current site.
 */
class ReverseLookup
{
    public function __construct(
        private readonly ModelRegistry $registry,
        private readonly ModelLinks $links,
        private readonly ElementTypes $elementTypes,
    ) {}

    /** The live element of a registered model's row, or null (unregistered model, no element, or disabled). */
    public function element(Model $model): ?WingmanElement
    {
        if (($handle = $this->registry->handleForModel($model::class)) === null || ($id = $this->links->elementId($model)) === null) {
            return null;
        }

        /** @var class-string<WingmanElement> $type */
        $type = ModelRegistry::elementClass($handle);
        $element = $type::find()->id($id)->one();

        return $element instanceof WingmanElement ? $element : null;
    }

    /**
     * Live elements for many rows of one model, in one query.
     *
     * @param  iterable<mixed>  $models
     * @return array<string, WingmanElement> Keyed by the model's key
     */
    public function elements(iterable $models): array
    {
        $models = collect($models)->filter(fn ($m) => $m instanceof Model)->values();

        if ($models->isEmpty()) {
            return [];
        }

        /** @var Model $first */
        $first = $models->first();
        $handle = $this->registry->handleForModel($first::class);

        if ($handle === null) {
            return [];
        }

        $keys = $models->filter(fn (Model $m) => $m instanceof $first)->map(fn (Model $m) => (string) $m->getKey())->all();
        $elementIds = DB::table(Table::ELEMENTS_MODELS)
            ->where('modelType', $first->getMorphClass())
            ->whereIn('modelKey', $keys)
            ->pluck('modelKey', 'elementId')
            ->all();

        if ($elementIds === []) {
            return [];
        }

        /** @var class-string<WingmanElement> $type */
        $type = ModelRegistry::elementClass($handle);
        $byKey = [];

        foreach ($type::find()->id(array_keys($elementIds))->all() as $element) {
            /** @var WingmanElement $element */
            $byKey[(string) $elementIds[$element->id]] = $element;
        }

        return $byKey;
    }

    /**
     * Pro: elements of `$elementType` (an element type's ref handle, `entry` by default)
     * that relate to the model through a Wingman relation field — `$field`, or any.
     *
     * @return ElementQueryInterface<ElementInterface>
     */
    public function related(Model|WingmanElement $subject, ?string $field = null, string $elementType = 'entry'): ElementQueryInterface
    {
        $this->requirePro();
        $type = $this->elementType($elementType);
        $element = $subject instanceof WingmanElement ? $subject : $this->element($subject);

        // Nothing to relate to: a query that finds nothing, not one that finds everything.
        if ($element === null) {
            return $type::find()->id(false);
        }

        $criteria = ['targetElement' => $element->id];

        if ($field !== null) {
            $criteria['field'] = $this->field($field, $element::class)->handle;
        }

        return $type::find()->relatedTo($criteria);
    }

    /**
     * Pro: the related elements for many models at once — one relations query and
     * one element query, however many models (no N+1).
     *
     * @param  iterable<mixed>  $models
     * @return array<string, list<ElementInterface>> Keyed by the model's key; models with none get []
     */
    public function relatedByModel(iterable $models, ?string $field = null, string $elementType = 'entry'): array
    {
        $this->requirePro();
        $type = $this->elementType($elementType);
        $models = collect($models)->filter(fn ($m) => $m instanceof Model)->values();
        $result = $models->mapWithKeys(fn (Model $m) => [(string) $m->getKey() => []])->all();
        $elements = $this->elements($models);

        if ($elements === []) {
            return $result;
        }

        $keyByElementId = [];
        foreach ($elements as $key => $element) {
            $keyByElementId[$element->id] = $key;
        }

        $relations = DB::table(Table::RELATIONS)->whereIn('targetId', array_keys($keyByElementId));

        if ($field !== null) {
            $relations->where('fieldId', $this->field($field, reset($elements)::class)->id);
        } else {
            $wingmanFieldIds = Fields::getAllFields()->filter(fn ($f) => $f instanceof WingmanField)->map(fn ($f) => $f->id)->values()->all();
            $relations->whereIn('fieldId', $wingmanFieldIds ?: [0]);
        }

        $pairs = $relations->orderBy('sortOrder')->get(['sourceId', 'targetId']);

        if ($pairs->isEmpty()) {
            return $result;
        }

        $sources = [];
        foreach ($type::find()->id($pairs->pluck('sourceId')->unique()->values()->all())->all() as $source) {
            $sources[$source->id] = $source;
        }

        foreach ($pairs as $pair) {
            $key = $keyByElementId[(int) $pair->targetId] ?? null;
            $source = $sources[(int) $pair->sourceId] ?? null;

            if ($key !== null && $source !== null && ! in_array($source, $result[$key], true)) {
                $result[$key][] = $source;
            }
        }

        return $result;
    }

    /** @return class-string<ElementInterface> */
    private function elementType(string $refHandle): string
    {
        $type = $refHandle === 'entry' ? Entry::class : $this->elementTypes->typeByRefHandle($refHandle);

        if ($type === null) {
            throw new InvalidArgumentException("“{$refHandle}” isn’t an element type.");
        }

        return $type;
    }

    /** A Wingman relation field that points at `$elementClass`'s elements. */
    private function field(string $handle, string $elementClass): WingmanField
    {
        $field = Fields::getFieldByHandle($handle);

        if (! $field instanceof WingmanField || $field::elementType() !== $elementClass) {
            throw new InvalidArgumentException("“{$handle}” isn’t a {$elementClass::displayName()} relation field.");
        }

        return $field;
    }

    private function requirePro(): void
    {
        if (! Plugin::isPro()) {
            throw new LogicException('Reverse lookups need Wingman Pro.');
        }
    }
}
