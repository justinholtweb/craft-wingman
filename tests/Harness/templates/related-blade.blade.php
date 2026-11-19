{{-- Pro: reverse lookups — entries relating to each customer, through the `customers` field and through any Wingman field. --}}
<ul>@foreach ($craft->customers()->orderBy("name")->all() as $c)<li data-id="{{ $c->id }}">{{ $c }}:{{ implode(',', $wingman->relatedEntries($c, 'customers')->ids()) }}/{{ $wingman->relatedEntries($c->getModel())->count() }}</li>@endforeach</ul>
