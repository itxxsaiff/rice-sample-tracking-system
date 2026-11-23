{{-- One dispatch on the "Dispatch Samples" form: its own samples, buyers, date and notes. --}}
@php
    $group ??= [];
    $base = "dispatches[{$key}]";
    $dot = "dispatches.{$key}";
    $buyerIds = $group['buyer_ids'] ?? [];
    $sampleError = $errors->first("{$dot}.sample_ids") ?: $errors->first("{$dot}.sample_ids.*");
    $buyerError = $errors->first("{$dot}.buyer_ids") ?: $errors->first("{$dot}.buyer_ids.*");
@endphp
<section class="dispatch-group" data-dispatch-group>
    <div class="sample-row-head">
        <div class="sample-row-num"><span data-group-number>1</span> Dispatch</div>
        <button type="button" class="btn btn-sm btn-light text-danger" data-remove-group aria-label="Remove this dispatch"><i class="bi bi-trash"></i> Remove</button>
    </div>
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="dispatch-panel h-100">
                <h3 class="dispatch-panel-title"><i class="bi bi-basket text-primary"></i> Samples <span class="badge rounded-pill text-bg-light border" data-sample-count>0</span></h3>
                <div class="picker" data-picker data-multiple data-source="{{ route('lookup.samples') }}" data-name="{{ $base }}[sample_ids]" data-initial='@json($group['samples'] ?? [])'>
                    <label class="form-label required" for="samplePicker-{{ $key }}">Add samples</label>
                    <div class="search-input mb-2">
                        <i class="bi bi-search"></i>
                        <input type="text" id="samplePicker-{{ $key }}" data-picker-input @class(['form-control', 'is-invalid' => $sampleError]) placeholder="Search sample no., mill or variety" autocomplete="off" autocorrect="off" spellcheck="false">
                    </div>
                    <div class="picker-menu" role="listbox"></div>
                    <div data-picker-chips data-sample-chips class="mt-3"></div>
                </div>
                @if ($sampleError)<div class="invalid-feedback d-block">{{ $sampleError }}</div>@endif
            </div>
        </div>
        <div class="col-lg-6">
            <div class="dispatch-panel h-100 quick-scope">
                <div class="d-flex justify-content-between align-items-center gap-2">
                    <h3 class="dispatch-panel-title"><i class="bi bi-people text-primary"></i> Buyers <span class="badge rounded-pill text-bg-light border" data-buyer-count>0</span></h3>
                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-quick-add="#quickBuyerModal"><i class="bi bi-plus-circle"></i> New buyer</button>
                </div>
                <div class="search-input mb-2"><i class="bi bi-search"></i>
                    <input type="search" class="form-control" placeholder="Filter buyers" data-choice-filter aria-label="Filter buyers">
                </div>
                <div class="choice-list" data-buyer-list data-input-name="{{ $base }}[buyer_ids][]">
                    @foreach ($buyers as $buyer)
                        <label class="choice-item">
                            <input class="form-check-input" type="checkbox" name="{{ $base }}[buyer_ids][]" value="{{ $buyer->id }}" @checked(in_array($buyer->id, $buyerIds))>
                            <span class="min-w-0"><span class="d-block fw-medium text-truncate">{{ $buyer->name }}</span><span class="small text-muted">{{ $buyer->city }}</span></span>
                        </label>
                    @endforeach
                    <div class="picker-empty d-none" data-choice-empty>No buyers match.</div>
                </div>
                @if ($buyerError)<div class="invalid-feedback d-block">{{ $buyerError }}</div>@endif
            </div>
        </div>
    </div>
    <div class="row mt-3">
        <x-form.input class="col-md-4 mb-3" :name="$base.'[dispatch_date]'" type="date" label="Dispatch date" :value="$group['dispatch_date'] ?? now()->format('Y-m-d')" required max="{{ now()->format('Y-m-d') }}" data-group-date />
        <x-form.input class="col-md-8 mb-3" :name="$base.'[notes]'" label="Notes" :value="$group['notes'] ?? null" placeholder="Courier, tracking no., who carried it…" />
    </div>
    <div class="alert alert-secondary mb-0 d-flex align-items-center gap-2" data-group-summary>
        <i class="bi bi-info-circle"></i><span data-summary-text>Select at least one sample and one buyer</span>
    </div>
</section>
