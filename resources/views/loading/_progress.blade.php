@php
    $percent = $dispatch->loadedPercent();
    $totals = collect($dispatch->loadedTotals())->map(fn ($q, $u) => \App\Models\SampleDispatch::formatQuantity($q, $u))->join(' + ');
@endphp
<div>
    <div class="d-flex justify-content-between small mb-1">
        <span><strong>{{ $totals ?: 'Nothing' }}</strong> loaded</span>
        <span class="text-muted">of {{ $dispatch->confirmedQuantityLabel() }}</span>
    </div>
    @if ($percent !== null)
        <div class="progress" style="height:8px" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Loaded">
            <div class="progress-bar {{ $percent >= 100 ? 'bg-success' : '' }}" style="width: {{ $percent }}%; background-color: {{ $percent >= 100 ? '' : 'var(--brand)' }}"></div>
        </div>
    @endif
</div>
