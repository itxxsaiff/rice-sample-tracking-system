const MAX_DIMENSION = 2000;
const QUALITY = 0.85;

/**
 * Downscale an image in the browser before upload. iPhone photos are often
 * 5–10 MB; re-encoding to a ~2000px JPEG keeps uploads fast on mobile data
 * and strips EXIF metadata (including GPS location).
 * Falls back to the original file if the browser cannot decode it.
 */
export async function compressImage(file) {
    if (!file.type.startsWith('image/') || file.type === 'image/gif') return file;

    try {
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        const scale = Math.min(1, MAX_DIMENSION / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close?.();

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', QUALITY));
        if (!blob || blob.size >= file.size) return file;

        const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
        return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
    } catch {
        return file;
    }
}

function formatSize(bytes) {
    return bytes > 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.round(bytes / 1024)} KB`;
}

/**
 * <div data-image-upload data-multiple data-max="10"> wrapper containing:
 *   - input[type=file][data-image-target] (the real field submitted with the form)
 *   - optional extra inputs[data-image-source] (e.g. a camera-capture input)
 *   - [data-image-previews] container
 * Selected files are compressed and merged into the target input via DataTransfer.
 */
export function initImageInputs(scope = document) {
    scope.querySelectorAll('[data-image-upload]').forEach((wrap) => {
        if (wrap.dataset.imageReady) return;
        wrap.dataset.imageReady = '1';
        const target = wrap.querySelector('[data-image-target]');
        const sources = wrap.querySelectorAll('[data-image-source]');
        const previews = wrap.querySelector('[data-image-previews]');
        const status = wrap.querySelector('[data-image-status]');
        const multiple = wrap.hasAttribute('data-multiple');
        const max = parseInt(wrap.dataset.max || '10', 10);
        const canAssign = typeof DataTransfer !== 'undefined';
        let files = [];

        const render = () => {
            previews.innerHTML = '';
            files.forEach((file, index) => {
                const tile = document.createElement('div');
                tile.className = 'photo-tile';
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.alt = file.name;
                img.onload = () => URL.revokeObjectURL(img.src);
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'photo-remove';
                remove.setAttribute('aria-label', 'Remove photo');
                remove.innerHTML = '<i class="bi bi-x-lg"></i>';
                remove.addEventListener('click', () => { files.splice(index, 1); sync(); });
                tile.append(img, remove);
                previews.appendChild(tile);
            });
            if (status) {
                const total = files.reduce((sum, f) => sum + f.size, 0);
                status.textContent = files.length ? `${files.length} photo${files.length > 1 ? 's' : ''} ready · ${formatSize(total)}` : '';
            }
        };

        const sync = () => {
            if (canAssign) {
                const dt = new DataTransfer();
                files.forEach((f) => dt.items.add(f));
                target.files = dt.files;
            }
            render();
        };

        const handle = async (input) => {
            const picked = Array.from(input.files || []);
            if (!picked.length) return;
            if (!canAssign) { files = picked; render(); return; } // Old browsers: submit originals.

            if (status) status.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Preparing photos…';
            const compressed = await Promise.all(picked.map(compressImage));
            files = multiple ? files.concat(compressed).slice(0, max) : compressed.slice(0, 1);
            if (input !== target) input.value = '';
            sync();
        };

        target.addEventListener('change', () => handle(target));
        sources.forEach((src) => src.addEventListener('change', () => handle(src)));
    });
}
