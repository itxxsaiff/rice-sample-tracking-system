@php
    $row ??= [];
    $millId = $row['mill_id'] ?? null;
    $mill = $millId ? ($mills[$millId] ?? null) : null;
    $base = "samples[{$key}]";
@endphp
<div class="sample-row">
    <div class="sample-row-head">
        <div class="sample-row-num"><span data-row-number>1</span> Sample</div>
        <button type="button" class="btn btn-sm btn-light text-danger" data-remove-row aria-label="Remove this sample"><i class="bi bi-trash"></i> Remove</button>
    </div>
    <div class="row">
        <x-form.picker class="col-md-6 mb-3" :name="$base.'[mill_id]'" label="Rice mill" :source="route('lookup.mills')" required
                       :value="$millId" :display="$mill ? $mill->name.' — '.$mill->city : null"
                       placeholder="Search mill name or city" quick-add="#quickMillModal" quick-label="New mill" />
        <x-form.variety-select class="col-md-6 mb-3" :name="$base.'[rice_variety_id]'" :options="$varieties" :value="$row['rice_variety_id'] ?? null" />
        <x-form.input class="col-12 col-md-4 mb-3" :name="$base.'[collection_date]'" type="date" label="Collection date" :value="$row['collection_date'] ?? now()->format('Y-m-d')" required max="{{ now()->format('Y-m-d') }}" data-row-date />
        <x-form.input class="col-6 col-md-4 mb-3" :name="$base.'[quantity]'" type="number" label="Sample quantity" :value="$row['quantity'] ?? null" step="0.01" min="0" inputmode="decimal" placeholder="e.g. 5" />
        <x-form.select class="col-6 col-md-4 mb-3" :name="$base.'[quantity_unit]'" label="Unit" :options="config('rice.sample_units')" :value="$row['quantity_unit'] ?? 'kg'" data-unit-select />
        <x-form.textarea class="col-12 mb-0" :name="$base.'[notes]'" label="Notes" :value="$row['notes'] ?? null" rows="1" placeholder="Moisture, broken %, colour, price indication…" />
    </div>
</div>
