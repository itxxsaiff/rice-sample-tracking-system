@php
    $row ??= [];
    $millId = $row['mill_id'] ?? null;
    $mill = $millId ? ($oldMills[$millId] ?? null) : null;
    $base = "samples[{$key}]";
    $hasDetailErrors = $errors->hasAny(["samples.{$key}.sample_number", "samples.{$key}.collection_date", "samples.{$key}.notes"]);
@endphp
<div class="bundle-row">
    <div class="bundle-row-head">
        <div class="bundle-row-num"><span data-row-number>1</span> Sample</div>
        <button type="button" class="btn btn-sm btn-light text-danger" data-remove-row aria-label="Remove this sample"><i class="bi bi-trash"></i> Remove</button>
    </div>
    <div class="row">
        <x-form.picker class="col-md-6 mb-3" :name="$base.'[mill_id]'" label="Rice mill" :source="route('lookup.mills')" required
                       :value="$millId" :display="$mill ? $mill->name.' — '.$mill->city : null"
                       placeholder="Search mill name or city" quick-add="#quickMillModal" quick-label="New mill" />
        <x-form.variety-select class="col-md-6 mb-3" :name="$base.'[rice_variety_id]'" :options="$varieties" :value="$row['rice_variety_id'] ?? null" />
        <x-form.input class="col-6 mb-3" :name="$base.'[quantity]'" type="number" label="Quantity" :value="$row['quantity'] ?? null" step="0.01" min="0" inputmode="decimal" placeholder="e.g. 500" />
        <x-form.select class="col-6 mb-3" :name="$base.'[quantity_unit]'" label="Unit" :options="config('rice.sample_units')" :value="$row['quantity_unit'] ?? 'g'" data-unit-select />
    </div>
    <x-form.photo-input :name="$base.'[photo]'" label="Photo (optional)" class="mb-2" />
    <details class="mt-2" @if ($hasDetailErrors) open @endif>
        <summary class="small fw-semibold text-primary py-1" style="cursor:pointer">Sample no., date &amp; notes</summary>
        <div class="row mt-2">
            <x-form.input class="col-6 mb-3" :name="$base.'[sample_number]'" label="Sample no." :value="$row['sample_number'] ?? null" placeholder="Auto" autocapitalize="characters" />
            <x-form.input class="col-6 mb-3" :name="$base.'[collection_date]'" type="date" label="Collected" :value="$row['collection_date'] ?? $defaultDate" max="{{ now()->format('Y-m-d') }}" data-row-date />
            <x-form.textarea class="col-12 mb-0" :name="$base.'[notes]'" label="Notes" :value="$row['notes'] ?? null" rows="2" />
        </div>
    </details>
</div>
