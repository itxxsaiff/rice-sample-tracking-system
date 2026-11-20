<div class="form-section">
    <div class="form-section-title"><i class="bi bi-basket"></i> Sample details</div>
    <div class="row">
        <x-form.picker class="col-md-6 mb-3" name="mill_id" label="Rice mill" :source="route('lookup.mills')" required
                       :value="$selectedMill?->id" :display="$selectedMill ? $selectedMill->name.' — '.$selectedMill->city : null"
                       placeholder="Search mill name or city" quick-add="#quickMillModal" quick-label="New mill" />
        <x-form.variety-select class="col-md-6 mb-3" name="rice_variety_id" :options="$varieties" :value="$sample->rice_variety_id" />
        <x-form.input class="col-12 col-md-4 mb-3" name="collection_date" type="date" label="Collection date" :value="$sample->collection_date" required max="{{ now()->format('Y-m-d') }}" />
        <x-form.input class="col-6 col-md-4 mb-3" name="quantity" type="number" label="Sample quantity" :value="$sample->quantity !== null ? (float) $sample->quantity : null" step="0.01" min="0" inputmode="decimal" placeholder="e.g. 5" />
        <x-form.select class="col-6 col-md-4 mb-3" name="quantity_unit" label="Unit" :options="config('rice.sample_units')" :value="$sample->quantity_unit ?? 'kg'" />
        <x-form.textarea class="col-12 mb-0" name="notes" label="Notes" :value="$sample->notes" rows="2" placeholder="Moisture, broken %, colour, price indication…" />
    </div>
</div>
@include('partials.quick-add-modals')
