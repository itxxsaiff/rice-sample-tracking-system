@props(['size' => 36])
<span {{ $attributes->merge(['class' => 'brand-mark', 'style' => "width:{$size}px;height:{$size}px"]) }}>
    <svg viewBox="12 6 40 52" width="{{ round($size * .62) }}" height="{{ round($size * .62) }}" aria-hidden="true" fill="currentColor"><ellipse cx="32" cy="16" rx="4.5" ry="8"/><ellipse cx="22" cy="26" rx="4.5" ry="8" transform="rotate(-35 22 26)"/><ellipse cx="42" cy="26" rx="4.5" ry="8" transform="rotate(35 42 26)"/><ellipse cx="22" cy="40" rx="4.5" ry="8" transform="rotate(-35 22 40)"/><ellipse cx="42" cy="40" rx="4.5" ry="8" transform="rotate(35 42 40)"/><rect x="30.5" y="24" width="3" height="32" rx="1.5"/></svg>
</span>
