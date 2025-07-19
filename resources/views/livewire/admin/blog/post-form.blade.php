<div>
    <form action="{{ $post->exists ? route('admin.blog.update', $post->id) : route('admin.blog.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @if($post->exists)
            @method('PUT')
        @endif
        <div class="p-4 sm:p-6 lg:p-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                        {{ $post->exists ? 'Sửa Link Card' : 'Tạo Link Card mới' }}
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Điền thông tin cho thẻ quảng cáo của bạn.
                    </p>
                </div>
                <div class="space-x-4">
                    <a href="{{ route('admin.blog.index') }}" class="rounded-md bg-white dark:bg-gray-700 px-3 py-2 text-sm font-semibold text-gray-900 dark:text-gray-200 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600">
                        Hủy
                    </a>
                    <button type="submit" class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        Lưu
                    </button>
                </div>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-y-6 gap-x-4 lg:grid-cols-3">
                <!-- Main content -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="rounded-lg bg-white dark:bg-gray-800 shadow-sm ring-1 ring-gray-900/5 dark:ring-white/10">
                        <div class="p-6 space-y-6">
                            <div>
                                <label for="title" class="block text-sm font-medium leading-6 text-gray-900 dark:text-gray-200">Tiêu đề</label>
                                <input name="title" type="text" id="title" value="{{ old('title', $title) }}" class="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 dark:text-white dark:bg-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="Tiêu đề cho link card" required>
                                @error('title') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label for="link" class="block text-sm font-medium leading-6 text-gray-900 dark:text-gray-200">Đường dẫn (URL)</label>
                                <input name="link" type="url" id="link" value="{{ old('link', $link) }}" class="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 dark:text-white dark:bg-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="https://example.com/subdomain" required>
                                @error('link') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="rounded-lg bg-white dark:bg-gray-800 shadow-sm ring-1 ring-gray-900/5 dark:ring-white/10">
                        <div class="p-6">
                            <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-gray-200">Trạng thái</h3>
                            <div class="mt-6">
                                <label for="status" class="block text-sm font-medium leading-6 text-gray-900 dark:text-gray-200 sr-only">Trạng thái</label>
                                <select name="status" id="status" class="mt-2 block w-full rounded-md border-0 py-1.5 pl-3 pr-10 text-gray-900 dark:text-white dark:bg-gray-700 ring-1 ring-inset ring-gray-300 dark:ring-gray-600 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6" required>
                                    <option value="DRAFT" {{ old('status', $status) == 'DRAFT' ? 'selected' : '' }}>Bản nháp</option>
                                    <option value="PUBLISHED" {{ old('status', $status) == 'PUBLISHED' ? 'selected' : '' }}>Công khai</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-lg bg-white dark:bg-gray-800 shadow-sm ring-1 ring-gray-900/5 dark:ring-white/10">
                        <div class="p-6">
                            <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-gray-200">Ảnh đại diện</h3>
                            <div class="mt-2" wire:ignore>
                                <input type="file" name="featured_image" id="featured_image" accept="image/*" class="sr-only" onchange="previewBlogImage(this)">
                                <input type="hidden" id="has_featured_image" name="has_featured_image" value="0">
                                <label for="featured_image" class="cursor-pointer mt-2 flex justify-center rounded-lg border border-dashed border-gray-900/25 dark:border-white/25 px-6 py-10">
                                    <div class="text-center">
                                        <div id="image-container">
                                            @if ($existing_featured_image)
                                                <img id="image-preview" src="{{ $existing_featured_image }}" class="mx-auto h-32 w-auto object-cover rounded-lg shadow-sm">
                                                <div id="upload-placeholder" class="hidden">
                                            @else
                                                <img id="image-preview" src="" class="mx-auto h-32 w-auto object-cover rounded-lg shadow-sm hidden">
                                                <div id="upload-placeholder">
                                            @endif
                                                    <svg id="upload-icon" class="mx-auto h-12 w-12 text-gray-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M1.5 6a2.25 2.25 0 012.25-2.25h16.5A2.25 2.25 0 0122.5 6v12a2.25 2.25 0 01-2.25 2.25H3.75A2.25 2.25 0 011.5 18V6zM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0021 18v-1.94l-2.69-2.689a1.5 1.5 0 00-2.12 0l-.88.879.97.97a.75.75 0 11-1.06 1.06l-5.16-5.159a1.5 1.5 0 00-2.12 0L3 16.061zm10.125-7.81a1.125 1.125 0 112.25 0 1.125 1.125 0 01-2.25 0z" clip-rule="evenodd" />
                                                    </svg>
                                                    <div class="mt-4 flex text-sm leading-6 text-gray-600">
                                                        <p class="pl-1">Nhấn để tải lên ảnh đại diện</p>
                                                    </div>
                                                    <p class="text-xs leading-5 text-gray-600">PNG, JPG, GIF up to 2MB</p>
                                                </div>
                                        </div>
                                        <div id="file-info" class="mt-2 text-xs text-gray-500 hidden"></div>
                                    </div>
                                </label>
                                @error('featured_image') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Store preview data globally to persist across Livewire updates
let blogImagePreviewData = null;

function previewBlogImage(input) {
    const file = input.files[0];

    if (file) {
        // Validate file type
        if (!file.type.startsWith('image/')) {
            alert('Vui lòng chọn file ảnh (PNG, JPG, GIF)');
            input.value = '';
            return;
        }

        // Validate file size (2MB)
        if (file.size > 2 * 1024 * 1024) {
            alert('Kích thước file không được vượt quá 2MB');
            input.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            // Store preview data globally
            blogImagePreviewData = {
                src: e.target.result,
                name: file.name,
                size: file.size
            };

            // Set hidden input to indicate file is selected
            const hasImageInput = document.getElementById('has_featured_image');
            if (hasImageInput) {
                hasImageInput.value = '1';
            }

            // Apply preview
            applyBlogImagePreview();
        };

        reader.onerror = function() {
            alert('Có lỗi khi đọc file ảnh');
            input.value = '';
            blogImagePreviewData = null;
        };

        reader.readAsDataURL(file);
    } else {
        // Reset preview
        blogImagePreviewData = null;

        // Reset hidden input
        const hasImageInput = document.getElementById('has_featured_image');
        if (hasImageInput) {
            hasImageInput.value = '0';
        }

        resetBlogImagePreview();
    }
}

function applyBlogImagePreview() {
    if (!blogImagePreviewData) return;

    const preview = document.getElementById('image-preview');
    const placeholder = document.getElementById('upload-placeholder');
    const fileInfo = document.getElementById('file-info');

    if (preview && placeholder && fileInfo) {
        // Show preview image
        preview.src = blogImagePreviewData.src;
        preview.classList.remove('hidden');

        // Hide placeholder
        placeholder.classList.add('hidden');

        // Show file info
        const sizeInMB = (blogImagePreviewData.size / (1024 * 1024)).toFixed(2);
        fileInfo.textContent = `${blogImagePreviewData.name} (${sizeInMB} MB)`;
        fileInfo.classList.remove('hidden');
    }
}

function resetBlogImagePreview() {
    const preview = document.getElementById('image-preview');
    const placeholder = document.getElementById('upload-placeholder');
    const fileInfo = document.getElementById('file-info');

    if (preview && placeholder && fileInfo) {
        preview.classList.add('hidden');
        placeholder.classList.remove('hidden');
        fileInfo.classList.add('hidden');
    }
}

// Restore preview after Livewire updates
document.addEventListener('livewire:updated', function() {
    if (blogImagePreviewData) {
        setTimeout(applyBlogImagePreview, 100);
    }
});

// Prevent form submission on Enter key in file input
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('featured_image');
    if (fileInput) {
        fileInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
    }

    // Check if there's existing image and ensure it's visible
    const existingImage = document.getElementById('image-preview');
    const placeholder = document.getElementById('upload-placeholder');

    if (existingImage && existingImage.src && existingImage.src !== window.location.href && existingImage.src !== '') {
        // There's an existing image, make sure it's visible
        console.log('Found existing image:', existingImage.src);
        existingImage.classList.remove('hidden');
        if (placeholder) {
            placeholder.classList.add('hidden');
        }

        // Test if image loads successfully
        existingImage.onerror = function() {
            console.error('Failed to load existing image:', existingImage.src);
            existingImage.classList.add('hidden');
            if (placeholder) {
                placeholder.classList.remove('hidden');
            }
        };

        existingImage.onload = function() {
            console.log('Existing image loaded successfully:', existingImage.src);
        };
    } else {
        console.log('No existing image found or invalid src');
    }

    // Apply preview on page load if data exists
    if (blogImagePreviewData) {
        applyBlogImagePreview();
    }
});
</script>
