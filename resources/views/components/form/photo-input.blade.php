@props(['name', 'label' => 'Photo', 'multiple' => false, 'max' => null, 'hint' => null])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $error = $errors->first($key) ?: $errors->first($key.'.*');
    $max ??= config('rice.max_photos_per_upload');
@endphp
<div {{ $attributes->merge(['class' => 'mb-3']) }} data-image-upload @if ($multiple) data-multiple data-max="{{ $max }}" @endif>
    @if ($label)<label class="form-label">{{ $label }}</label>@endif
    <div class="upload-buttons">
        <label class="btn btn-soft mb-0">
            <i class="bi bi-camera"></i> Take photo
            <input type="file" accept="image/*" capture="environment" class="d-none" data-image-source>
        </label>
        <label class="btn btn-light mb-0">
            <i class="bi bi-images"></i> {{ $multiple ? 'Choose photos' : 'Choose photo' }}
            <input type="file" name="{{ $multiple ? $name.'[]' : $name }}" accept="image/*,.heic,.heif" @if ($multiple) multiple @endif class="d-none" data-image-target>
        </label>
    </div>
    <div class="small text-muted mt-2" data-image-status></div>
    <div class="photo-grid mt-2" data-image-previews></div>
    @if ($error)<div class="invalid-feedback d-block">{{ $error }}</div>@endif
    @if ($hint)<div class="form-text">{{ $hint }}</div>@endif
</div>
