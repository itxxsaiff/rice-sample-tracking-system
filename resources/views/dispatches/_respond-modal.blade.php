{{--
    "Record buyer response" popup. One per page; buttons with data-respond='{…}'
    (SampleDispatch::respondData()) open it for their dispatch. If the server
    rejected the input, the page reloads with the popup open again.
--}}
@php
    $reopen = old('respond_dispatch_id') ? \App\Models\SampleDispatch::with(['buyer', 'sample.mill', 'sample.variety'])->find(old('respond_dispatch_id')) : null;
@endphp
<div class="modal fade" id="respondModal" tabindex="-1" aria-labelledby="respondModalTitle" aria-hidden="true"
     @if ($reopen) data-reopen='@json($reopen->respondData())' @endif>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <form method="POST" action="" class="modal-content" data-response-form data-offline-op="dispatch.respond" novalidate>
            @csrf @method('PUT')
            <input type="hidden" name="respond_dispatch_id" value="{{ old('respond_dispatch_id') }}">
            <input type="hidden" name="rejection_date" value="{{ now()->format('Y-m-d') }}">
            <div class="modal-header">
                <div class="min-w-0">
                    <h5 class="modal-title fw-semibold" id="respondModalTitle" data-respond-title>Record buyer response</h5>
                    <div class="small text-muted text-truncate" data-respond-sub></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex gap-3 align-items-center mb-3">
                    <span class="stat-icon tone-navy flex-shrink-0"><i class="bi bi-basket"></i></span>
                    <div class="min-w-0 small">
                        <div class="fw-semibold mono" data-respond-sample></div>
                        <div class="text-muted text-truncate" data-respond-mill></div>
                    </div>
                </div>

                <div class="form-section-title"><i class="bi bi-reply"></i> <span data-respond-question>What did the buyer decide?</span></div>
                <div class="row g-2">
                    @foreach (['confirmed' => ['check-circle-fill', 'green', 'Confirmed', 'Buyer wants to purchase'], 'rejected' => ['x-circle-fill', 'red', 'Rejected', 'Buyer declined the sample']] as $value => [$icon, $tone, $title, $hint])
                        <div class="col-6" data-response-choice="{{ $value }}">
                            <label class="card response-option p-3 h-100 mb-0" style="cursor:pointer">
                                <input type="radio" name="status" value="{{ $value }}" class="visually-hidden" @checked(old('status') === $value)>
                                <span class="stat-icon tone-{{ $tone }} mb-2"><i class="bi bi-{{ $icon }}"></i></span>
                                <span class="fw-semibold">{{ $title }}</span>
                                <span class="small text-muted">{{ $hint }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
                @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <p class="form-text mb-0 mt-2 d-none" data-respond-locked>Trucks have been loaded for this order, so only the confirmation details can be corrected.</p>

                <div class="mt-4 d-none" data-response-section="confirmed">
                    <div class="form-section-title"><i class="bi bi-clipboard-check"></i> Confirmed order</div>
                    <div class="row">
                        <x-form.input class="col-7 col-md-4 mb-3" name="confirmed_quantity" type="number" label="Quantity" required step="0.01" min="0" inputmode="decimal" placeholder="e.g. 500" />
                        <x-form.select class="col-5 col-md-2 mb-3" name="confirmed_quantity_unit" label="Unit" :options="config('rice.quantity_units')" value="maund" required />
                        <x-form.input class="col-7 col-md-4 mb-3" name="rate" type="number" label="Agreed rate" required step="0.01" min="0" inputmode="decimal" prefix="Rs" />
                        <x-form.select class="col-5 col-md-2 mb-3" name="rate_unit" label="Per" :options="config('rice.rate_units')" value="per_maund" required />
                        <x-form.select class="col-md-6 mb-3" name="packing_type" label="Packing type" :options="config('rice.packing_types')" placeholder="Select packing" required />
                        <x-form.input class="col-md-6 mb-3" name="confirmation_date" type="date" label="Confirmation date" :value="now()->format('Y-m-d')" required max="{{ now()->format('Y-m-d') }}" />
                        <x-form.textarea class="col-12 mb-0" name="packing_details" label="Packing details / notes" rows="2" placeholder="e.g. 50 kg PP bags, buyer's printed bags, double stitching" />
                    </div>
                </div>

                <div class="mt-3 d-none" data-response-section="rejected">
                    <div class="alert alert-light border mb-0 small"><i class="bi bi-info-circle me-1"></i> The dispatch will be marked as rejected. It stays in the buyer's history and does not go to loading.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" data-busy-text="Saving…"><i class="bi bi-check-lg"></i> Save response</button>
            </div>
        </form>
    </div>
</div>
