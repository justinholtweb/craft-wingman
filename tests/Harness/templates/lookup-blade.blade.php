{{-- Lite: model → element. --}}
<ul>@foreach ($craft->customers()->orderBy("name")->all() as $c)@php($el = $wingman->element($c->getModel()))<li data-id="{{ $el->id ?? 'none' }}">{{ $el ? $el->name : '' }}</li>@endforeach</ul>
<p id="none">{{ $wingman->element(null) === null ? 'null' : 'x' }}</p>
<p id="invoice">{{ $craft->wingmanInvoices()->orderBy('number')->one()?->number ?? '' }}</p>
