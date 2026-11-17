use CraftCms\Cms\Support\Facades\{Fields, EntryTypes, Elements};
use CraftCms\Cms\FieldLayout\LayoutElements\CustomField;
use CraftCms\Cms\Entry\Elements\Entry;
$fieldClass = JustinHoltWeb\Wingman\Registry\ModelRegistry::fieldClass('Customer');
$f = Fields::getFieldByHandle('customers') ?? Fields::createField(['type' => $fieldClass, 'name' => 'Customers', 'handle' => 'customers']);
if (! $f->id) echo 'field: ', Fields::saveField($f) ? 'saved' : json_encode($f->getErrors()), "\n";
$type = EntryTypes::getEntryTypeByHandle('article');
$layout = $type->getFieldLayout();
$tab = $layout->getTabs()[0];
if (! collect($tab->getElements())->first(fn ($e) => $e instanceof CustomField && $e->getField()->handle === 'customers')) {
    $tab->setElements([...$tab->getElements(), new CustomField($f)]);
    $type->setFieldLayout($layout);
    echo 'layout: ', EntryTypes::saveEntryType($type) ? 'saved' : json_encode($type->getErrors()), "\n";
}
$cls = JustinHoltWeb\Wingman\Registry\ModelRegistry::elementClass('Customer');
$ids = $cls::find()->limit(2)->ids();
$a = Entry::find()->slug('hello-momentum-2')->one();
$a->setFieldValue('customers', $ids);
echo 'entry: ', Elements::saveElement($a) ? 'saved' : json_encode($a->getErrors()), "\n";
