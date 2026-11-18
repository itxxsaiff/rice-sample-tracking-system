@props(['label', 'value', 'icon', 'tone' => 'green', 'href' => null])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif class="card stat-card">
    <span class="stat-icon tone-{{ $tone }}"><i class="bi bi-{{ $icon }}"></i></span>
    <div class="min-w-0">
        <div class="stat-value mono">{{ is_numeric($value) ? number_format($value) : $value }}</div>
        <div class="stat-label">{{ $label }}</div>
    </div>
</{{ $tag }}>
