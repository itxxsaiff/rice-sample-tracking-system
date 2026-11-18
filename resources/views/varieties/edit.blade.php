<x-layouts.app :title="'Edit '.$variety->name" :back="route('varieties.index')" action-bar>
    <x-page-header :title="'Edit variety '.$variety->name" />
    <form method="POST" action="{{ route('varieties.update', $variety) }}" class="card" novalidate>
        @csrf @method('PUT')
        <div class="form-section">
            <x-form.input name="name" label="Variety name" :value="$variety->name" required />
            <x-form.input name="description" label="Description" :value="$variety->description" />
            <input type="hidden" name="is_active" value="0">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $variety->is_active))>
                <label class="form-check-label" for="is_active">Active <span class="text-muted">— inactive varieties are hidden from new samples but kept on old records</span></label>
            </div>
        </div>
        <div class="action-bar">
            <a href="{{ route('varieties.index') }}" class="btn btn-light">Cancel</a>
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save changes</button>
        </div>
    </form>
</x-layouts.app>
