{{--
    "Export PDF" button that first asks which report to make.
    $options: list of [href, label, hint, icon].
--}}
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#pdfChoiceModal"><i class="bi bi-file-earmark-pdf"></i> Export PDF</button>
@push('modals')
    <div class="modal fade" id="pdfChoiceModal" tabindex="-1" aria-labelledby="pdfChoiceTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold" id="pdfChoiceTitle">Export PDF — {{ $title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-2">
                    <p class="text-muted small mb-2">What should the PDF show?</p>
                    <div class="list-group">
                        @foreach ($options as [$href, $label, $hint, $icon])
                            <a href="{{ $href }}" class="list-group-item list-group-item-action d-flex gap-3 align-items-center py-3" data-pdf-export>
                                <span class="stat-icon tone-navy flex-shrink-0"><i class="bi bi-{{ $icon }}"></i></span>
                                <span><span class="d-block fw-semibold">{{ $label }}</span><span class="small text-muted">{{ $hint }}</span></span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endpush
