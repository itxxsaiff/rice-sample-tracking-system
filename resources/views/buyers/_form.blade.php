<div class="form-section">
    <div class="form-section-title"><i class="bi bi-person-vcard"></i> Buyer details</div>
    <div class="row">
        <x-form.input class="col-md-6 mb-3" name="name" label="Buyer / company name" :value="$buyer->name" required placeholder="e.g. Karachi Rice Exporters" autocapitalize="words" />
        <x-form.input class="col-md-6 mb-3" name="city" label="City" :value="$buyer->city" required list="citySuggestions" placeholder="Start typing a city" autocapitalize="words" autocomplete="off" />
        <x-form.input class="col-md-6 mb-3" name="contact_person" label="Contact person" :value="$buyer->contact_person" autocapitalize="words" />
        <x-form.input class="col-md-6 mb-3" name="phone" type="tel" label="Contact number" :value="$buyer->phone" inputmode="tel" placeholder="03xx xxxxxxx" />
        <x-form.input class="col-12 mb-3" name="address" label="Address" :value="$buyer->address" />
        <x-form.textarea class="col-12 mb-0" name="notes" label="Notes" :value="$buyer->notes" rows="3" />
    </div>
</div>
@include('partials.city-datalist')
