{{--
    "From / To" reference-number filter (fields from_no and to_no), e.g. sample no. 255 to 290.
    The Apply button submits the filter form (Enter works too; iPhone number keypads have no Enter key).
--}}
@props(['from' => null, 'to' => null, 'prefix' => 'SMP', 'label' => 'Sample no.', 'class' => 'col-6 col-lg-3'])
@foreach (['from_no' => ['From', $from], 'to_no' => ['To', $to]] as $name => [$text, $value])
    <div class="{{ $class }}">
        <div class="input-group date-filter">
            <span class="input-group-text">{{ $text }}</span>
            <span class="input-group-text number-prefix d-none d-md-flex">{{ $prefix }}-</span>
            <input type="text" name="{{ $name }}" value="{{ $value }}" inputmode="numeric" autocomplete="off" class="form-control"
                   placeholder="{{ $name === 'from_no' ? '0255' : '0300' }}" aria-label="{{ $label }} {{ strtolower($text) }}" title="{{ $label }} {{ strtolower($text) }}">
            @if ($name === 'to_no')
                <button type="submit" class="btn btn-primary" title="Apply filter" aria-label="Apply filter"><i class="bi bi-funnel"></i><span class="d-none d-xl-inline ms-1">Apply</span></button>
            @endif
        </div>
    </div>
@endforeach
