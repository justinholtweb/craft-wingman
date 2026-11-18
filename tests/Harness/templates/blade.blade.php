<ul id="blade">@foreach ($craft->customers()->orderBy("name")->all() as $c)<li>{{ $c->name }}</li>@endforeach</ul>
