@push('modals')
    <div class="modal fade" id="photoViewer" tabindex="-1" aria-label="Photo viewer" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-fullscreen-md-down">
            <div class="modal-content bg-dark border-0">
                <div class="modal-header border-0 py-2">
                    <span class="text-white-50 small" data-viewer-caption></span>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 d-flex align-items-center justify-content-center position-relative" style="min-height:60vh">
                    <img src="" alt="" data-viewer-image style="max-width:100%;max-height:80vh;object-fit:contain">
                    <button type="button" class="btn btn-dark position-absolute start-0 ms-2 rounded-circle btn-icon" data-viewer-prev aria-label="Previous photo"><i class="bi bi-chevron-left"></i></button>
                    <button type="button" class="btn btn-dark position-absolute end-0 me-2 rounded-circle btn-icon" data-viewer-next aria-label="Next photo"><i class="bi bi-chevron-right"></i></button>
                </div>
                <div class="modal-footer border-0 py-2 justify-content-center">
                    <a href="#" class="btn btn-sm btn-outline-light" data-viewer-open target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Open full size</a>
                </div>
            </div>
        </div>
    </div>
@endpush
