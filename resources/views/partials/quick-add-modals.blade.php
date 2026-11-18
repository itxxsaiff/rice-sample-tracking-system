{{-- Inline "add new" dialogs used from sample/bundle/dispatch forms. --}}
@push('modals')
    <div class="modal fade" id="quickMillModal" tabindex="-1" aria-labelledby="quickMillTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
            <form class="modal-content" method="POST" action="{{ route('mills.store') }}" data-quick-form="mill" novalidate>
                <div class="modal-header"><h5 class="modal-title fw-semibold" id="quickMillTitle">New rice mill</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label required" for="qm_name">Mill name</label><input class="form-control" id="qm_name" name="name" required autocapitalize="words"><div class="invalid-feedback"></div></div>
                    <div class="mb-3"><label class="form-label required" for="qm_city">City</label><input class="form-control" id="qm_city" name="city" list="citySuggestions" required autocapitalize="words" autocomplete="off"><div class="invalid-feedback"></div></div>
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label" for="qm_contact">Contact person</label><input class="form-control" id="qm_contact" name="contact_person"><div class="invalid-feedback"></div></div>
                        <div class="col-6 mb-3"><label class="form-label" for="qm_phone">Phone</label><input class="form-control" id="qm_phone" name="phone" type="tel" inputmode="tel"><div class="invalid-feedback"></div></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add mill</button></div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="quickVarietyModal" tabindex="-1" aria-labelledby="quickVarietyTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('varieties.store') }}" data-quick-form="variety" novalidate>
                <div class="modal-header"><h5 class="modal-title fw-semibold" id="quickVarietyTitle">New rice variety</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label required" for="qv_name">Variety name</label><input class="form-control" id="qv_name" name="name" required placeholder="e.g. 1847"><div class="invalid-feedback"></div></div>
                    <div class="mb-0"><label class="form-label" for="qv_desc">Description</label><input class="form-control" id="qv_desc" name="description"><div class="invalid-feedback"></div></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add variety</button></div>
            </form>
        </div>
    </div>
    @include('partials.city-datalist')
@endpush
