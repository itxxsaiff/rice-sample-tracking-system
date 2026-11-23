@extends('pdf.layout')

@section('content')
    <div class="criteria">
        @forelse ($criteria as $label => $value){{ $label }}: <b>{{ $value }}</b>@unless ($loop->last)<span class="sep">|</span>@endunless @empty All samples @endforelse
    </div>

    @include('pdf._summary')

    <h2>Samples <span class="count">({{ number_format($total) }})</span></h2>
    @if ($truncated)
        <p class="note">Showing the latest {{ number_format($samples->count()) }} of {{ number_format($total) }} samples. Narrow the filters to print the rest.</p>
    @endif

    @if ($samples->isEmpty())
        <div class="empty">No samples match these filters.</div>
    @else
        <table class="grid">
            <thead>
                <tr><th class="no">#</th><th>Sample</th><th>Mill</th><th>Variety</th><th>Collected</th><th>Quantity</th><th>Sent to</th></tr>
            </thead>
            <tbody>
                @foreach ($samples as $sample)
                    <tr>
                        <td class="no">{{ $loop->iteration }}</td>
                        <td class="nowrap">
                            <div class="strong">{{ $sample->sample_number }}</div>
                            @if ($sample->notes)<div class="sub">{{ Str::limit($sample->notes, 60) }}</div>@endif
                        </td>
                        <td><div class="strong">{{ $sample->mill->name }}</div><div class="sub">{{ $sample->mill->city }}</div></td>
                        <td>{{ $sample->variety->name }}</td>
                        <td class="nowrap">{{ $sample->collection_date->format('d M Y') }}</td>
                        <td class="nowrap">{{ $sample->quantityLabel() ?? '—' }}</td>
                        <td>
                            @forelse ($sample->dispatches as $dispatch)
                                <div>{{ $dispatch->buyer->name }} — <span class="st st-{{ $dispatch->stageKey() }}">{{ $dispatch->stageLabel() }}</span></div>
                            @empty
                                <span class="muted">Not sent</span>
                            @endforelse
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
