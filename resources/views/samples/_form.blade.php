@php $editing = $sample->exists; @endphp
<div class="form-section">
    <div class="form-section-title"><i class="bi bi-basket"></i> Sample details</div>
    <div class="row">
        <x-form.picker class="col-md-6 mb-3" name="mill_id" label="Rice mill" :source="route('lookup.mills')" required
                       :value="$selectedMill?->id" :display="$selectedMill ? $selectedMill->name.' — '.$selectedMill->city : null"
                       placeholder="Search mill name or city" quick-add="#quickMillModal" quick-label="New mill" />
        <x-form.variety-select class="col-md-6 mb-3" name="rice_variety_id" :options="$varieties" :value="$sample->rice_variety_id" />
        <x-form.input class="col-6 mb-3" name="collection_date" type="date" label="Collection date" :value="$sample->collection_date" required max="{{ now()->format('Y-m-d') }}" />
        <x-form.input class="col-6 mb-3" name="sample_number" label="Sample no." :value="$sample->sample_number"
                      :placeholder="$editing ? '' : ($nextNumber ?? '').' (auto)'" :hint="$editing ? null : 'Leave blank to auto-number'" autocapitalize="characters" />
        <x-form.input class="col-6 mb-3" name="quantity" type="number" label="Sample quantity" :value="$sample->quantity !== null ? (float) $sample->quantity : null" step="0.01" min="0" inputmode="decimal" placeholder="e.g. 500" />
        <x-form.select class="col-6 mb-3" name="quantity_unit" label="Unit" :options="config('rice.sample_units')" :value="$sample->quantity_unit ?? 'g'" />
    </div>
</div>
<div class="form-section">
    <div class="form-section-title"><i class="bi bi-camera"></i> Photo <span class="text-muted fw-normal small">(optional)</span></div>
    @if ($editing && $sample->photo_path)
        <div class="d-flex align-items-center gap-3 mb-3">
            <img src="{{ route('samples.photo', $sample) }}" alt="Current photo" class="rounded-3" style="width:88px;height:88px;object-fit:cover">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo">
                <label class="form-check-label" for="remove_photo">Remove current photo</label>
            </div>
        </div>
    @endif
    <x-form.photo-input name="photo" :label="$editing && $sample->photo_path ? 'Replace photo' : ''" class="mb-0" />
</div>
<div class="form-section">
    <div class="form-section-title"><i class="bi bi-three-dots"></i> More</div>
    <div class="row">
        <x-form.select class="col-md-6 mb-3" name="sample_bundle_id" label="Bundle" placeholder="Not in a bundle" :value="$sample->sample_bundle_id"
                       :options="$bundles->mapWithKeys(fn ($b) => [$b->id => $b->bundle_number.' · '.$b->bundle_date->format('d M').' ('.$b->samples_count.' samples)'])" />
        <x-form.textarea class="col-md-6 mb-0" name="notes" label="Notes" :value="$sample->notes" rows="2" placeholder="Moisture, broken %, colour, price indication…" />
    </div>
</div>
@include('partials.quick-add-modals')
