@php $hideMill ??= false; @endphp
<div class="table-responsive">
    <table class="table table-hover table-stack align-middle">
        <thead><tr>
            <th>Sample</th>
            @unless ($hideMill)<th>Mill</th>@endunless
            <th>Variety</th><th>Collected</th><th>Quantity</th>@isset($samples->first()->dispatches_count)<th>Sent to</th>@endisset<th class="text-end"></th>
        </tr></thead>
        <tbody>
        @foreach ($samples as $sample)
            <tr class="row-link" data-href="{{ route('samples.show', $sample) }}">
                <td class="cell-primary">
                    <div class="d-flex align-items-center gap-2">
                        @if ($sample->photo_path)
                            <img src="{{ route('samples.photo', $sample) }}" alt="" class="sample-thumb" loading="lazy">
                        @else
                            <span class="sample-thumb d-inline-flex align-items-center justify-content-center text-muted"><i class="bi bi-basket"></i></span>
                        @endif
                        <div class="min-w-0">
                            <a href="{{ route('samples.show', $sample) }}" class="cell-title mono">{{ $sample->sample_number }}</a>
                            @unless ($hideMill)<div class="cell-sub d-md-none text-truncate">{{ $sample->mill->name }} · {{ $sample->mill->city }}</div>@endunless
                        </div>
                    </div>
                </td>
                @unless ($hideMill)
                    <td class="hide-mobile"><div class="fw-medium">{{ $sample->mill->name }}</div><div class="cell-sub">{{ $sample->mill->city }}</div></td>
                @endunless
                <td data-label="Variety"><span class="badge text-bg-light border fw-semibold">{{ $sample->variety->name }}</span></td>
                <td data-label="Collected"><span>{{ $sample->collection_date->format('d M Y') }}</span></td>
                <td data-label="Quantity"><span>{{ $sample->quantityLabel() ?? '—' }}</span></td>
                @isset($sample->dispatches_count)
                    <td data-label="Sent to"><span>@if ($sample->dispatches_count)<span class="badge rounded-pill text-bg-light border">{{ $sample->dispatches_count }} buyer{{ $sample->dispatches_count > 1 ? 's' : '' }}</span>@else<span class="text-muted small">Not sent</span>@endif</span></td>
                @endisset
                <td class="text-end hide-mobile"><i class="bi bi-chevron-right text-muted"></i></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
