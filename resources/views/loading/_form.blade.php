@php $editing = $loading->exists; @endphp
<div class="form-section">
    <div class="d-flex justify-content-between flex-wrap gap-2 align-items-start">
        @include('dispatches._sample-summary')
        <div class="small text-md-end">
            <div>Confirmed: <strong>{{ $dispatch->confirmedQuantityLabel() }}</strong> · {{ $dispatch->packing_type }}</div>
            @if ($dispatch->loadings->isNotEmpty())<div class="text-muted">Already loaded: {{ collect($dispatch->loadedTotals())->map(fn ($q, $u) => \App\Models\SampleDispatch::formatQuantity($q, $u))->join(' + ') }}</div>@endif
        </div>
    </div>
</div>
<div class="form-section">
    <div class="form-section-title"><i class="bi bi-truck"></i> Truck</div>
    <div class="row">
        <x-form.input class="col-6 col-md-4 mb-3" name="truck_number" label="Truck number" :value="$loading->truck_number" required placeholder="e.g. TLE-1234" autocapitalize="characters" autocomplete="off" />
        <x-form.input class="col-6 col-md-4 mb-3" name="loading_date" type="date" label="Loading date" :value="$loading->loading_date" required max="{{ now()->format('Y-m-d') }}" />
        <x-form.input class="col-12 col-md-4 mb-3" name="receiver_name" label="Receiving / responsible person" :value="$loading->receiver_name" autocapitalize="words" />
        <x-form.input class="col-6 mb-3" name="driver_name" label="Driver name" :value="$loading->driver_name" autocapitalize="words" />
        <x-form.input class="col-6 mb-3" name="driver_phone" type="tel" label="Driver phone" :value="$loading->driver_phone" inputmode="tel" />
    </div>
</div>
<div class="form-section">
    <div class="form-section-title"><i class="bi bi-box-seam"></i> Quantity &amp; packing</div>
    <div class="row">
        <x-form.input class="col-7 col-md-3 mb-3" name="quantity" type="number" label="Loaded quantity" :value="$loading->quantity !== null ? (float) $loading->quantity : null" required step="0.01" min="0" inputmode="decimal" />
        <x-form.select class="col-5 col-md-3 mb-3" name="quantity_unit" label="Unit" :options="config('rice.quantity_units')" :value="$loading->quantity_unit" required />
        <x-form.input class="col-6 col-md-3 mb-3" name="bag_count" type="number" label="No. of bags" :value="$loading->bag_count" min="0" step="1" inputmode="numeric" />
        <x-form.select class="col-6 col-md-3 mb-3" name="packing_type" label="Packing" :options="config('rice.packing_types')" :value="$loading->packing_type" placeholder="Select" />
        <x-form.textarea class="col-md-6 mb-3" name="packing_details" label="Packing details" :value="$loading->packing_details" rows="2" placeholder="Bag size, marking, stitching…" />
        <x-form.textarea class="col-md-6 mb-0" name="notes" label="Loading notes" :value="$loading->notes" rows="2" />
    </div>
</div>
<div class="form-section">
    <div class="form-section-title"><i class="bi bi-camera"></i> {{ $editing ? 'Add more photos' : 'Loading photos' }}</div>
    <x-form.photo-input name="photos" :multiple="true" label="" class="mb-0" hint="Truck, number plate, bags and loading slip. Photos are resized automatically before upload." />
</div>
