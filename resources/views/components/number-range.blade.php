{{-- "From / To" reference-number filter (fields from_no and to_no), e.g. sample no. 255 to 290. --}}
@props(['from' => null, 'to' => null, 'prefix' => 'SMP', 'label' => 'Sample no.', 'class' => 'col-6 col-lg-3'])
@foreach (['from_no' => ['From', $from], 'to_no' => ['To', $to]] as $name => [$text, $value])
    <div class="{{ $class }}">
        <div class="input-group date-filter">
            <span class="input-group-text">{{ $text }}</span>
            <span class="input-group-text number-prefix">{{ $prefix }}-</span>
            <input type="text" name="{{ $name }}" value="{{ $value }}" inputmode="numeric" autocomplete="off" class="form-control"
                   placeholder="{{ $name === 'from_no' ? '0255' : '0300' }}" aria-label="{{ $label }} {{ strtolower($text) }}" title="{{ $label }} {{ strtolower($text) }}">
        </div>
    </div>
@endforeach
