<x-layouts.app :title="'Edit '.$sample->sample_number" :back="route('samples.show', $sample)" action-bar>
    <x-page-header :title="'Edit sample '.$sample->sample_number" />
    <form method="POST" action="{{ route('samples.update', $sample) }}" enctype="multipart/form-data" class="card" novalidate>
        @csrf @method('PUT')
        @include('samples._form')
        <div class="action-bar">
            <a href="{{ route('samples.show', $sample) }}" class="btn btn-light">Cancel</a>
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save changes</button>
        </div>
    </form>
</x-layouts.app>
