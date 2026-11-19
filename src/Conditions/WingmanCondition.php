<?php

declare(strict_types=1);

namespace JustinHoltWeb\Wingman\Conditions;

use CraftCms\Cms\Element\Conditions\ElementCondition;
use JustinHoltWeb\Wingman\Elements\WingmanElement;
use JustinHoltWeb\Wingman\Plugin;
use Override;

/**
 * Craft's element condition, plus (Pro) a rule per listed model attribute.
 */
class WingmanCondition extends ElementCondition
{
    #[Override]
    protected function selectableConditionRules(): array
    {
        $rules = parent::selectableConditionRules();
        $type = $this->elementType;

        if (Plugin::isPro() && $type !== null && is_subclass_of($type, WingmanElement::class)) {
            foreach ($type::listedModelAttributes() as $attribute) {
                $rules[] = ['class' => ModelAttributeConditionRule::class, 'attribute' => $attribute];
            }
        }

        return $rules;
    }
}
