@props(['label', 'value', 'icon', 'tone' => 'violet', 'trend' => null, 'trendUp' => true])
<article class="stat-card">
    <div class="stat-icon tone-{{ $tone }}"><i class="bi {{ $icon }}"></i></div>
    <div class="stat-copy"><span>{{ $label }}</span><strong>{{ $value }}</strong>
        @if($trend)<small class="{{ $trendUp ? 'trend-up' : 'trend-down' }}"><i class="bi {{ $trendUp ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i> {{ $trend }}</small>@endif
    </div>
</article>
