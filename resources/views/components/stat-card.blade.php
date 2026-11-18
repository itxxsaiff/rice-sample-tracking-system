@props(['label', 'value', 'icon' => null, 'tone' => 'navy', 'href' => null])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif class="card stat-card tone-{{ $tone }}">
    <div class="stat-label">{{ $label }}</div>
    <div class="stat-value mono">{{ is_numeric($value) ? number_format($value) : $value }}</div>
</{{ $tag }}>
