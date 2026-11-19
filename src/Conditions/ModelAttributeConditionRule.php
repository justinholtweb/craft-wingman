<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Conditions;

use CraftCms\Cms\Condition\BaseTextConditionRule;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionRuleInterface;
use CraftCms\Cms\Element\Conditions\Contracts\ElementQueryConditionRuleInterface;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Support\Query;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use JustinHoltWeb\Wingman\Elements\WingmanElement;
use Override;

use function CraftCms\Cms\t;

/**
 * Pro: a condition rule per model attribute (“Company is NASA”), for element
 * indexes, custom sources and relation field selection conditions. Only the
 * attributes the element lists — never `$hidden` ones — can be used.
 */
class ModelAttributeConditionRule extends BaseTextConditionRule implements ElementConditionRuleInterface, ElementQueryConditionRuleInterface
{
    public string $attribute = '';

    public function getLabel(): string
    {
        return Str::headline($this->attribute);
    }

    #[Override]
    public function getGroupLabel(): ?string
    {
        return t('Model', category: 'wingman');
    }

    /** @return array<string, mixed> */
    #[Override]
    public function getConfig(): array
    {
        return [...parent::getConfig(), 'attribute' => $this->attribute];
    }

    /** @return array<string, mixed> */
    #[Override]
    public function getRules(): array
    {
        return [...parent::getRules(), 'attribute' => ['required', 'string', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/']];
    }

    public function modifyQuery(Builder $query, ElementQueryInterface $elementQuery): void
    {
        $type = $elementQuery->elementType;

        // An attribute that isn't (or is no longer) listed matches nothing, rather than everything.
        if (! is_subclass_of($type, WingmanElement::class) || ! in_array($this->attribute, $type::listedModelAttributes(), true)) {
            $query->whereRaw('0 = 1');

            return;
        }

        $value = $this->paramValue();

        if ($value === null) {
            return;
        }

        $query->whereParam($type::newModel()->getTable().'.'.$this->attribute, is_string($value) ? Query::escapeCommas($value) : $value, caseInsensitive: true);
    }

    public function matchElement(ElementInterface $element): bool
    {
        if (! $element instanceof WingmanElement || ! in_array($this->attribute, $element::listedModelAttributes(), true)) {
            return false;
        }

        return $this->matchValue($element->getModel()->getAttribute($this->attribute));
    }
}
