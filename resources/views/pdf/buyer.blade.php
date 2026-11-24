@extends('pdf.layout')

@section('content')
    <table class="party">
        <tr><td colspan="4" class="name">{{ $buyer->name }}</td></tr>
        <tr>
            <td class="label">City</td><td style="width: 37%">{{ $buyer->city ?: '—' }}</td>
            <td class="label">Contact person</td><td>{{ $buyer->contact_person ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Phone</td><td>{{ $buyer->phone ?: '—' }}</td>
            <td class="label">Address</td><td>{{ $buyer->address ?: '—' }}</td>
        </tr>
        @if ($statusLabel)
            <tr><td class="label">Showing</td><td colspan="3"><b>{{ $statusLabel }}</b> only</td></tr>
        @endif
    </table>

    @include('pdf._summary')

    @if ($part === 'samples')
        <h2>Samples sent <span class="count">({{ number_format($dispatches->count()) }})</span></h2>
        @if ($dispatches->isEmpty())
            <div class="empty">No samples sent to this buyer{{ $statusLabel ? ' in this list' : '' }}.</div>
        @else
            @include('pdf._dispatches', ['hideBuyer' => true, 'showLoaded' => true, 'sampleDetails' => false])
        @endif
    @else
        <h2>Truck loadings <span class="count">({{ number_format($loadings->count()) }})</span></h2>
        @if ($loadings->isEmpty())
            <div class="empty">No trucks loaded for this buyer yet.</div>
        @else
            @include('pdf._loadings', ['hideBuyer' => true, 'sampleDetails' => false])

            <h2>Truck photos</h2>
            @foreach ($loadings as $loading)
                @php $photos = $loading->photos->map(fn ($p) => \App\Support\PdfReport::photo($p->path))->filter()->values(); @endphp
                <div class="truck-photos">
                    <div class="truck-photos-head">
                        <b>{{ $loading->truck_number }}</b> · {{ $loading->loading_number }} · {{ $loading->dispatch->dispatch_number }} · {{ $loading->loading_date->format('d M Y') }} · {{ $loading->quantityLabel() }}
                    </div>
                    @if ($photos->isEmpty())
                        <div class="muted">No photos.</div>
                    @else
                        <table class="photo-grid">
                            @foreach ($photos->chunk(4) as $row)
                                <tr>
                                    @foreach ($row as $src)<td><img src="{{ $src }}" alt=""></td>@endforeach
                                    @for ($i = $row->count(); $i < 4; $i++)<td></td>@endfor
                                </tr>
                            @endforeach
                        </table>
                    @endif
                </div>
            @endforeach
        @endif
    @endif
@endsection
