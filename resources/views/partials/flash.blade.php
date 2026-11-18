<div class="toast-stack" aria-live="polite">
    @foreach (['success' => 'check-circle-fill', 'error' => 'exclamation-octagon-fill', 'warning' => 'exclamation-triangle-fill'] as $type => $icon)
        @if (session($type))
            <div class="toast align-items-center text-bg-{{ $type === 'error' ? 'danger' : $type }}" role="status">
                <div class="d-flex">
                    <div class="toast-body d-flex gap-2 align-items-center"><i class="bi bi-{{ $icon }}"></i><span>{{ session($type) }}</span></div>
                    <button type="button" class="btn-close {{ $type === 'warning' ? '' : 'btn-close-white' }} me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        @endif
    @endforeach
</div>
