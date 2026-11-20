<x-layouts.app title="Dispatch Samples" :back="route('dispatches.index')" action-bar>
    <x-page-header title="Dispatch Samples" subtitle="Send one or more samples to one or more buyers. A separate dispatch is recorded for every sample–buyer pair." />

    <form method="POST" action="{{ route('dispatches.store') }}" data-offline-op="dispatch.create" novalidate>
        @csrf
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h2><i class="bi bi-basket text-primary me-2"></i>Samples</h2></div>
                    <div class="card-body">
                        <div class="picker" data-picker data-multiple data-source="{{ route('lookup.samples') }}" data-name="sample_ids" data-initial='@json($selectedSamples)'>
                            <label class="form-label required" for="samplePicker">Add samples</label>
                            <div class="search-input mb-2">
                                <i class="bi bi-search"></i>
                                <input type="text" id="samplePicker" data-picker-input @class(['form-control', 'is-invalid' => $errors->has('sample_ids') || $errors->has('sample_ids.*')]) placeholder="Search sample no., mill or variety" autocomplete="off" autocorrect="off" spellcheck="false">
                            </div>
                            <div class="picker-menu" role="listbox"></div>
                            <div data-picker-chips data-sample-chips class="mt-3"></div>
                        </div>
                        @error('sample_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @error('sample_ids.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <p class="form-text mb-0">Tip: open a sample and tap <strong>Dispatch</strong> to pre-fill this list.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100 quick-scope">
                    <div class="card-header justify-content-between">
                        <h2><i class="bi bi-people text-primary me-2"></i>Buyers <span class="badge rounded-pill text-bg-light border ms-1" data-choice-count="buyers">0</span></h2>
                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-quick-add="#quickBuyerModal"><i class="bi bi-plus-circle"></i> New buyer</button>
                    </div>
                    <div class="card-body">
                        @if ($buyers->isEmpty())
                            <div class="alert alert-warning mb-3">No buyers yet. Add one with <strong>New buyer</strong>.</div>
                        @endif
                        <div class="search-input mb-2"><i class="bi bi-search"></i>
                            <input type="search" class="form-control" placeholder="Filter buyers" data-choice-filter="#buyerList" aria-label="Filter buyers">
                        </div>
                        <div id="buyerList" class="choice-list" data-choice-group="buyers">
                            @foreach ($buyers as $buyer)
                                <label class="choice-item">
                                    <input class="form-check-input" type="checkbox" name="buyer_ids[]" value="{{ $buyer->id }}" @checked(in_array($buyer->id, $selectedBuyers))>
                                    <span class="min-w-0"><span class="d-block fw-medium text-truncate">{{ $buyer->name }}</span><span class="small text-muted">{{ $buyer->city }}</span></span>
                                </label>
                            @endforeach
                            <div class="picker-empty d-none" data-choice-empty>No buyers match.</div>
                        </div>
                        @error('buyer_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <x-form.input class="col-md-4 mb-3" name="dispatch_date" type="date" label="Dispatch date" :value="now()" required max="{{ now()->format('Y-m-d') }}" />
                            <x-form.input class="col-md-8 mb-3" name="notes" label="Notes" placeholder="Courier, tracking no., who carried it…" />
                        </div>
                        <div class="alert alert-secondary mb-0 d-flex align-items-center gap-2" data-dispatch-summary>
                            <i class="bi bi-info-circle"></i><span data-summary-text>Select at least one sample and one buyer</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="action-bar action-bar-float">
            <a href="{{ route('dispatches.index') }}" class="btn btn-light">Cancel</a>
            <button class="btn btn-primary" data-busy-text="Sending…"><i class="bi bi-send"></i> Record dispatch</button>
        </div>
    </form>

    @push('modals')
        <div class="modal fade" id="quickBuyerModal" tabindex="-1" aria-labelledby="quickBuyerTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
                <form class="modal-content" method="POST" action="{{ route('buyers.store') }}" data-quick-form="buyer" novalidate>
                    <div class="modal-header"><h5 class="modal-title fw-semibold" id="quickBuyerTitle">New buyer</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <div class="mb-3"><label class="form-label required" for="qb_name">Buyer / company name</label><input class="form-control" id="qb_name" name="name" required autocapitalize="words"><div class="invalid-feedback"></div></div>
                        <div class="mb-3"><label class="form-label required" for="qb_city">City</label><input class="form-control" id="qb_city" name="city" list="citySuggestions" required autocapitalize="words" autocomplete="off"><div class="invalid-feedback"></div></div>
                        <div class="mb-3"><label class="form-label" for="qb_phone">Contact number</label><input class="form-control" id="qb_phone" name="phone" type="tel" inputmode="tel"><div class="invalid-feedback"></div></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Add buyer</button></div>
                </form>
            </div>
        </div>
        @include('partials.city-datalist')
    @endpush
</x-layouts.app>
