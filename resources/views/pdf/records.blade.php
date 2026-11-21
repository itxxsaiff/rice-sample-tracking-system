@extends('pdf.layout')

@section('content')
    <div class="criteria">
        @foreach ($criteria as $label => $value){{ $label }}: <b>{{ $value }}</b>@unless ($loop->last)<span class="sep">|</span>@endunless @endforeach
    </div>

    @include('pdf._summary')

    <h2>{{ $typeLabel }} <span class="count">({{ number_format($total) }})</span></h2>
    @if ($truncated)
        <p class="note">Showing the latest {{ number_format($records->count()) }} of {{ number_format($total) }} records. Narrow the filters to print the rest.</p>
    @endif

    @if ($records->isEmpty())
        <div class="empty">No records match these filters.</div>
    @elseif ($type === 'loadings')
        @include('pdf._loadings', ['loadings' => $records])
    @else
        @include('pdf._dispatches', ['dispatches' => $records])
    @endif
@endsection
