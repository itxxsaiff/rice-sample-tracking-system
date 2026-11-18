<div class="d-flex gap-3 align-items-center">
    @if ($dispatch->sample->photo_path)
        <img src="{{ route('samples.photo', $dispatch->sample) }}" alt="" class="sample-thumb" style="width:56px;height:56px">
    @else
        <span class="sample-thumb d-inline-flex align-items-center justify-content-center text-muted" style="width:56px;height:56px"><i class="bi bi-basket fs-5"></i></span>
    @endif
    <div class="min-w-0">
        <a href="{{ route('samples.show', $dispatch->sample) }}" class="fw-semibold mono">{{ $dispatch->sample->sample_number }}</a>
        <span class="badge text-bg-light border ms-1">{{ $dispatch->sample->variety->name }}</span>
        <div class="small text-muted text-truncate">{{ $dispatch->sample->mill->name }}, {{ $dispatch->sample->mill->city }}</div>
        <div class="small">To <a href="{{ route('buyers.show', $dispatch->buyer) }}" class="fw-medium">{{ $dispatch->buyer->name }}</a> · sent {{ $dispatch->dispatch_date->format('d M Y') }}</div>
    </div>
</div>
