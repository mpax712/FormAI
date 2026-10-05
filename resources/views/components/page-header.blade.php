@props(['eyebrow' => null, 'title', 'description' => null])
<div class="page-header">
    <div>
        @if($eyebrow)<span class="eyebrow">{{ $eyebrow }}</span>@endif
        <h1>{{ $title }}</h1>
        @if($description)<p>{{ $description }}</p>@endif
    </div>
    @if(trim($slot) !== '')<div class="page-actions">{{ $slot }}</div>@endif
</div>
