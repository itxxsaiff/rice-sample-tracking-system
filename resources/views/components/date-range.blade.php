{{-- "From / To" date filter for list pages (fields named from and to). --}}
@props(['from' => null, 'to' => null, 'label' => 'Date', 'class' => 'col-6 col-lg-3'])
<div class="{{ $class }}">
    <div class="input-group date-filter">
        <span class="input-group-text">From</span>
        <input type="date" name="from" value="{{ $from }}" max="{{ now()->format('Y-m-d') }}" class="form-control" aria-label="{{ $label }} from" title="{{ $label }} from">
    </div>
</div>
<div class="{{ $class }}">
    <div class="input-group date-filter">
        <span class="input-group-text">To</span>
        <input type="date" name="to" value="{{ $to }}" max="{{ now()->format('Y-m-d') }}" class="form-control" aria-label="{{ $label }} to" title="{{ $label }} to">
    </div>
</div>
