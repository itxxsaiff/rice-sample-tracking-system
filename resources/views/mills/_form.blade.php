<div class="form-section">
    <div class="form-section-title"><i class="bi bi-buildings"></i> Mill details</div>
    <div class="row">
        <x-form.input class="col-md-6 mb-3" name="name" label="Mill name" :value="$mill->name" required placeholder="e.g. Al-Rehman Rice Mills" autocapitalize="words" />
        <x-form.input class="col-md-6 mb-3" name="city" label="City" :value="$mill->city" required list="citySuggestions" placeholder="Start typing a city" autocapitalize="words" autocomplete="off" />
        <x-form.input class="col-md-6 mb-3" name="contact_person" label="Contact person" :value="$mill->contact_person" autocapitalize="words" />
        <x-form.input class="col-md-6 mb-3" name="phone" type="tel" label="Phone" :value="$mill->phone" inputmode="tel" placeholder="03xx xxxxxxx" />
        <x-form.input class="col-12 mb-3" name="address" label="Address" :value="$mill->address" />
        <x-form.textarea class="col-12 mb-0" name="notes" label="Notes" :value="$mill->notes" rows="3" />
    </div>
</div>
@include('partials.city-datalist')
