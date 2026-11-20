<div class="form-section">
    <div class="form-section-title"><i class="bi bi-basket"></i> Sample details</div>
    <div class="row">
        <x-form.picker class="col-md-6 mb-3" name="mill_id" label="Rice mill" :source="route('lookup.mills')" required
                       :value="$selectedMill?->id" :display="$selectedMill ? $selectedMill->name.' — '.$selectedMill->city : null"
                       placeholder="Search mill name or city" quick-add="#quickMillModal" quick-label="New mill" />
        <x-form.variety-select class="col-md-6 mb-3" name="rice_variety_id" :options="$varieties" :value="$sample->rice_variety_id" />
        <x-form.input class="col-md-6 mb-3" name="collection_date" type="date" label="Collection date" :value="$sample->collection_date" required max="{{ now()->format('Y-m-d') }}" />
        <x-form.textarea class="col-12 mb-0" name="notes" label="Notes" :value="$sample->notes" rows="2" placeholder="Moisture, broken %, colour, price indication…" />
    </div>
</div>
@include('partials.quick-add-modals')
