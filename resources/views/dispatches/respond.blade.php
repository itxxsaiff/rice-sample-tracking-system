@php
    $editing = ! $dispatch->isPending();
    $current = old('status', $editing ? $dispatch->status->value : null);
    $lockStatus = $dispatch->isConfirmed() && $dispatch->hasLoadings();
@endphp
<x-layouts.app :title="($editing ? 'Edit response · ' : 'Buyer response · ').$dispatch->dispatch_number" :back="route('dispatches.show', $dispatch)" action-bar>
    <x-page-header :title="$editing ? 'Edit buyer response' : 'Record buyer response'" :subtitle="'Dispatch '.$dispatch->dispatch_number" />

    <form method="POST" action="{{ route('dispatches.respond.update', $dispatch) }}" data-offline-op="dispatch.respond" data-offline-target="{{ $dispatch->uuid }}" data-offline-version="{{ $dispatch->version }}" class="card" data-response-form novalidate>
        @csrf @method('PUT')
        <div class="form-section">
            @include('dispatches._sample-summary')
        </div>
        <div class="form-section">
            <div class="form-section-title"><i class="bi bi-reply"></i> What did {{ $dispatch->buyer->name }} decide?</div>
            <div class="row g-2">
                @foreach (['confirmed' => ['check-circle-fill', 'green', 'Confirmed', 'Buyer wants to purchase'], 'rejected' => ['x-circle-fill', 'red', 'Rejected', 'Buyer declined the sample']] as $value => [$icon, $tone, $title, $hint])
                    @continue($lockStatus && $value === 'rejected')
                    <div class="col-6">
                        <label class="card response-option p-3 h-100 mb-0" style="cursor:pointer">
                            <input type="radio" name="status" value="{{ $value }}" class="visually-hidden" @checked($current === $value)>
                            <span class="stat-icon tone-{{ $tone }} mb-2"><i class="bi bi-{{ $icon }}"></i></span>
                            <span class="fw-semibold">{{ $title }}</span>
                            <span class="small text-muted">{{ $hint }}</span>
                        </label>
                    </div>
                @endforeach
            </div>
            @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            @if ($lockStatus)<p class="form-text mb-0 mt-2">Trucks have been loaded for this order, so only the confirmation details can be corrected.</p>@endif
        </div>

        <div class="form-section d-none" data-response-section="confirmed">
            <div class="form-section-title"><i class="bi bi-clipboard-check"></i> Confirmed order</div>
            <div class="row">
                <x-form.input class="col-7 col-md-4 mb-3" name="confirmed_quantity" type="number" label="Quantity" :value="$dispatch->confirmed_quantity !== null ? (float) $dispatch->confirmed_quantity : null" required step="0.01" min="0" inputmode="decimal" placeholder="e.g. 500" />
                <x-form.select class="col-5 col-md-2 mb-3" name="confirmed_quantity_unit" label="Unit" :options="config('rice.quantity_units')" :value="$dispatch->confirmed_quantity_unit ?? 'maund'" required />
                <x-form.input class="col-7 col-md-4 mb-3" name="rate" type="number" label="Agreed rate" :value="$dispatch->rate !== null ? (float) $dispatch->rate : null" required step="0.01" min="0" inputmode="decimal" prefix="Rs" />
                <x-form.select class="col-5 col-md-2 mb-3" name="rate_unit" label="Per" :options="config('rice.rate_units')" :value="$dispatch->rate_unit ?? 'per_maund'" required />
                <x-form.select class="col-md-4 mb-3" name="packing_type" label="Packing type" :options="config('rice.packing_types')" :value="$dispatch->packing_type" placeholder="Select packing" required />
                <x-form.input class="col-md-4 mb-3" name="confirmation_date" type="date" label="Confirmation date" :value="$dispatch->confirmation_date ?? now()" required max="{{ now()->format('Y-m-d') }}" />
                <x-form.textarea class="col-12 mb-0" name="packing_details" label="Packing details / notes" :value="$dispatch->packing_details" rows="2" placeholder="e.g. 50 kg PP bags, buyer's printed bags, double stitching" />
            </div>
        </div>

        <div class="form-section d-none" data-response-section="rejected">
            <div class="form-section-title"><i class="bi bi-x-octagon"></i> Rejection</div>
            <div class="d-flex flex-wrap gap-2 mb-2">
                @foreach (['Quality not matched', 'Price not agreed', 'High moisture', 'High broken %', 'Colour / polish issue', 'Grain length short', 'Buyer not interested now'] as $reason)
                    <button type="button" class="btn btn-sm btn-light" data-fill-reason="{{ $reason }}">{{ $reason }}</button>
                @endforeach
            </div>
            <div class="row">
                <x-form.textarea class="col-md-8 mb-3" name="rejection_reason" label="Reason" :value="$dispatch->rejection_reason" rows="2" required />
                <x-form.input class="col-md-4 mb-0" name="rejection_date" type="date" label="Rejection date" :value="$dispatch->rejection_date ?? now()" required max="{{ now()->format('Y-m-d') }}" />
            </div>
        </div>

        <div class="action-bar">
            <a href="{{ route('dispatches.show', $dispatch) }}" class="btn btn-light">Cancel</a>
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save response</button>
        </div>
    </form>
</x-layouts.app>
