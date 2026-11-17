$cls = JustinHoltWeb\Wingman\Registry\ModelRegistry::elementClass('Customer');
echo 'class exists: ', class_exists($cls) ? 'yes' : 'no', "\n";
echo 'registered: ', in_array($cls, app(CraftCms\Cms\Element\Eloquent\EloquentElements::class)->types()) ? 'yes' : 'no', "\n";
echo 'modelClass: ', $cls::modelClass(), " | attrs: ", implode(',', $cls::modelAttributes()), " | listed: ", implode(',', $cls::listedModelAttributes()), " | label: ", $cls::labelAttribute(), "\n";
echo 'field type: ', $cls::relationFieldType(), ' -> ', ($cls::relationFieldType())::elementType(), "\n";
echo 'elements before backfill: ', $cls::find()->count(), "\n";
foreach (App\Models\Customer::all() as $m) { if (! $cls::findForModel($m)) { $el = $cls::fromModel($m); CraftCms\Cms\Support\Facades\Elements::saveElement($el); } }
echo 'elements after backfill: ', $cls::find()->count(), "\n";
$new = App\Models\Customer::create(['name' => 'Katherine Johnson', 'email' => 'kj@example.com', 'company' => 'NASA']);
echo 'ModelSync created element for new model: ', $cls::findForModel($new) ? 'yes' : 'no', "\n";
$el = $cls::find()->one();
echo 'element title/type: ', $el->title, ' / ', get_class($el), ' / company=', $el->company ?? '(none)', "\n";
