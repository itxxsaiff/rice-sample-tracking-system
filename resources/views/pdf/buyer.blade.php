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

    <h2>Samples sent <span class="count">({{ number_format($dispatches->count()) }})</span></h2>
    @if ($dispatches->isEmpty())
        <div class="empty">No samples sent to this buyer{{ $statusLabel ? ' in this list' : '' }}.</div>
    @else
        @include('pdf._dispatches', ['hideBuyer' => true, 'showLoaded' => true])
    @endif

    @if ($loadings->isNotEmpty())
        <h2>Truck loadings <span class="count">({{ number_format($loadings->count()) }})</span></h2>
        @include('pdf._loadings', ['hideBuyer' => true])
    @endif
@endsection
