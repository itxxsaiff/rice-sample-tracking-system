@extends('pdf.layout')

@section('content')
    <table class="party">
        <tr><td colspan="4" class="name">{{ $mill->name }}</td></tr>
        <tr>
            <td class="label">City</td><td style="width: 37%">{{ $mill->city ?: '—' }}</td>
            <td class="label">Contact person</td><td>{{ $mill->contact_person ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Phone</td><td>{{ $mill->phone ?: '—' }}</td>
            <td class="label">Address</td><td>{{ $mill->address ?: '—' }}</td>
        </tr>
        @if ($varietyName)
            <tr><td class="label">Showing</td><td colspan="3"><b>{{ $varietyName }}</b> samples only</td></tr>
        @endif
    </table>

    @include('pdf._summary')

    @if ($part === 'samples')
        <h2>Samples <span class="count">({{ number_format($samples->count()) }})</span></h2>
        @if ($samples->isEmpty())
            <div class="empty">No samples recorded for this mill yet.</div>
        @else
            <table class="grid">
                <thead>
                    <tr>
                        <th class="no">#</th><th>Sample</th><th>Variety</th><th>Collected</th><th>Quantity</th><th>Sent to</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($samples as $sample)
                        <tr>
                            <td class="no">{{ $loop->iteration }}</td>
                            <td class="nowrap">
                                <div class="strong">{{ $sample->sample_number }}</div>
                                @if ($sample->notes)<div class="sub">{{ Str::limit($sample->notes, 80) }}</div>@endif
                            </td>
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
    @else
        <h2>Buyer responses <span class="count">({{ number_format($dispatches->count()) }})</span></h2>
        @if ($dispatches->isEmpty())
            <div class="empty">No samples from this mill have been sent to buyers yet.</div>
        @else
            @include('pdf._dispatches')
        @endif
    @endif
@endsection
