<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                📁 File Manager
            </h2>
            <div class="flex items-center space-x-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <span id="file-count">{{ $files->total() }} file(s)</span>
                    <span class="mx-2">•</span>
                    <span id="storage-used">{{ number_format($storageUsage / 1024 / 1024 / 1024, 2) }} GB</span>
                </div>
                @if($canUpload)
                    <button id="upload-btn" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        📤 Upload
                    </button>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Storage Usage Card -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                                    <span class="text-blue-600 dark:text-blue-400">💾</span>
                                </div>
                            </div>
                            <div class="ml-3 flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Storage</p>
                                <div class="flex items-center mt-1">
                                    <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                        {{ number_format($storageUsage / 1024 / 1024 / 1024, 1) }}GB
                                    </p>
                                    @if(!$isAdmin)
                                        <span class="text-sm text-gray-500 dark:text-gray-400 ml-1">
                                            / {{ number_format($storageLimit / 1024 / 1024 / 1024, 0) }}GB
                                        </span>
                                    @endif
                                </div>
                                @if(!$isAdmin)
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 mt-2">
                                        <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-300"
                                             style="width: {{ min(($storageUsage / $storageLimit) * 100, 100) }}%"></div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Files Card -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                                    <span class="text-green-600 dark:text-green-400">📁</span>
                                </div>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Total Files</p>
                                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $files->total() }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Selected Files Card -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center">
                                    <span class="text-yellow-600 dark:text-yellow-400">✅</span>
                                </div>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Selected</p>
                                <p id="selected-count" class="text-lg font-semibold text-gray-900 dark:text-gray-100">0</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upload Status Card -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                                    <span class="text-purple-600 dark:text-purple-400">
                                        @if($canUpload)
                                            ✅
                                        @else
                                            ⚠️
                                        @endif
                                    </span>
                                </div>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Upload</p>
                                <p class="text-sm font-semibold {{ $canUpload ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    @if($isAdmin)
                                        Unlimited
                                    @elseif($canUpload)
                                        Available
                                    @else
                                        Quota Full
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search & Filters -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0 lg:space-x-4">
                        <!-- Search -->
                        <div class="flex-1 max-w-md">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                                <input type="text" id="search-input" placeholder="Search files..."
                                       class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <!-- Filters -->
                        <div class="flex items-center space-x-3">
                            <select id="sort-select" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                                <option value="newest">Newest First</option>
                                <option value="oldest">Oldest First</option>
                                <option value="largest">Largest First</option>
                                <option value="smallest">Smallest First</option>
                                <option value="name">Name A-Z</option>
                            </select>

                            <!-- Bulk Actions -->
                            <div id="bulk-actions" class="hidden flex items-center space-x-2">
                                <button id="select-all-btn" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Select All
                                </button>
                                <button id="bulk-delete-btn" class="inline-flex items-center px-3 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    🗑️ Delete Selected
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upload Modal (Hidden by default) -->
            @if($canUpload)
            <div id="upload-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                    <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100" id="modal-title">
                                        📤 Upload Video
                                    </h3>
                                    <div class="mt-4">
                                        <div id="upload-form" class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center hover:border-blue-400 transition-colors cursor-pointer">
                                            <input type="file" id="file-input" accept="video/mp4,.mp4" class="hidden">
                                            <div class="space-y-2">
                                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                                </svg>
                                                <p class="text-gray-600 dark:text-gray-400">
                                                    <span class="font-medium text-blue-600 hover:text-blue-500 cursor-pointer">Click to select file</span>
                                                    or drag and drop
                                                </p>
                                                <p class="text-xs text-gray-500">
                                                    MP4 only • Max {{ number_format($maxFileSize / 1024 / 1024 / 1024, 0) }}GB
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Upload Progress -->
                                        <div id="upload-progress" class="hidden mt-4">
                                            <div class="bg-gray-200 dark:bg-gray-700 rounded-full h-2 mb-2">
                                                <div id="progress-bar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                                            </div>
                                            <p id="upload-status" class="text-sm text-gray-600 dark:text-gray-400">Preparing...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="button" id="close-upload-modal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Files Grid -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if($files->count() > 0)
                        <!-- Files Grid -->
                        <div id="files-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                            @foreach($files as $file)
                            <div class="file-card group relative bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden hover:shadow-lg transition-all duration-200 hover:scale-105"
                                 data-file-id="{{ $file->id }}"
                                 data-file-name="{{ $file->original_name }}"
                                 data-file-size="{{ $file->size }}"
                                 data-created-at="{{ $file->created_at->timestamp }}">

                                <!-- Selection Checkbox -->
                                <div class="absolute top-2 left-2 z-10">
                                    <input type="checkbox" class="file-checkbox w-4 h-4 text-blue-600 bg-white border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
                                           value="{{ $file->id }}"
                                           onchange="updateSelection()">
                                </div>

                                <!-- File Preview -->
                                <div class="aspect-video bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-800 dark:to-gray-900 flex items-center justify-center relative">
                                    <div class="text-4xl">🎬</div>

                                    <!-- File Type Badge -->
                                    <div class="absolute top-2 right-2 bg-black bg-opacity-75 text-white text-xs px-2 py-1 rounded">
                                        MP4
                                    </div>

                                    <!-- File Size Badge -->
                                    <div class="absolute bottom-2 right-2 bg-black bg-opacity-75 text-white text-xs px-2 py-1 rounded">
                                        {{ number_format($file->size / 1024 / 1024, 1) }}MB
                                    </div>

                                    <!-- Hover Actions -->
                                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-50 transition-all duration-200 flex items-center justify-center opacity-0 group-hover:opacity-100">
                                        <div class="flex space-x-2">
                                            @if($file->public_url)
                                                <button onclick="previewFile('{{ $file->public_url }}', '{{ $file->original_name }}')"
                                                        class="bg-white text-gray-900 p-2 rounded-full hover:bg-gray-100 transition-colors"
                                                        title="Preview">
                                                    👁️
                                                </button>
                                            @endif
                                            <button onclick="downloadFile({{ $file->id }})"
                                                    class="bg-white text-gray-900 p-2 rounded-full hover:bg-gray-100 transition-colors"
                                                    title="Download">
                                                �
                                            </button>
                                            <button onclick="deleteFile({{ $file->id }}, '{{ $file->original_name }}')"
                                                    class="bg-red-600 text-white p-2 rounded-full hover:bg-red-700 transition-colors"
                                                    title="Delete">
                                                🗑️
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- File Info -->
                                <div class="p-4">
                                    <h3 class="font-medium text-gray-900 dark:text-gray-100 text-sm truncate mb-2" title="{{ $file->original_name }}">
                                        {{ $file->original_name }}
                                    </h3>

                                    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                        <span>{{ $file->created_at->diffForHumans() }}</span>
                                        @if($file->disk === 'bunny_stream')
                                            <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded-full">Stream</span>
                                        @elseif($file->disk === 'bunny_cdn')
                                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full">CDN</span>
                                        @elseif($file->disk === 'hybrid')
                                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full">Hybrid</span>
                                        @else
                                            <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded-full">Local</span>
                                        @endif
                                    </div>

                                    @if($isAdmin && $file->user)
                                        <div class="mt-2 flex items-center text-xs text-blue-600 dark:text-blue-400">
                                            <span class="mr-1">👤</span>
                                            <span>{{ $file->user->name }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>

                        <!-- Pagination -->
                        <div class="mt-6">
                            {{ $files->links() }}
                        </div>
                    @else
                        <!-- Empty State -->
                        <div class="text-center py-12">
                            <div class="mx-auto h-24 w-24 text-gray-400 mb-4">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-full h-full">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M7 4V2a1 1 0 011-1h8a1 1 0 011 1v2h4a1 1 0 110 2h-1v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6H3a1 1 0 110-2h4zM6 6v12h12V6H6zm3-2V2h6v2H9z"></path>
                                </svg>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">No files yet</h3>
                            <p class="text-gray-500 dark:text-gray-400 mb-4">Upload your first video to get started</p>
                            @if($canUpload)
                                <button onclick="openUploadModal()" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    📤 Upload Video
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- File Preview Modal -->
    <div id="preview-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="preview-modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100" id="preview-modal-title">
                            File Preview
                        </h3>
                        <button type="button" onclick="closePreviewModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <div id="preview-content" class="w-full">
                        <!-- Video will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notifications Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

    @push('scripts')
    <script>
    // 🎯 Modern File Manager JavaScript
    class FileManager {
        constructor() {
            this.selectedFiles = new Set();
            this.allFiles = [];
            this.filteredFiles = [];
            this.currentSort = 'newest';
            this.searchTerm = '';

            this.init();
        }

        init() {
            this.bindEvents();
            this.loadFiles();
            this.setupUploadHandlers();
        }

        bindEvents() {
            // Upload button
            document.getElementById('upload-btn')?.addEventListener('click', () => this.openUploadModal());

            // Search
            document.getElementById('search-input')?.addEventListener('input', (e) => this.handleSearch(e.target.value));

            // Sort
            document.getElementById('sort-select')?.addEventListener('change', (e) => this.handleSort(e.target.value));

            // Bulk actions
            document.getElementById('select-all-btn')?.addEventListener('click', () => this.selectAll());
            document.getElementById('bulk-delete-btn')?.addEventListener('click', () => this.bulkDelete());

            // Modal close buttons
            document.getElementById('close-upload-modal')?.addEventListener('click', () => this.closeUploadModal());
        }

        loadFiles() {
            // Load files from DOM
            const fileCards = document.querySelectorAll('.file-card');
            this.allFiles = Array.from(fileCards).map(card => ({
                id: parseInt(card.dataset.fileId),
                name: card.dataset.fileName,
                size: parseInt(card.dataset.fileSize),
                createdAt: parseInt(card.dataset.createdAt),
                element: card
            }));
            this.filteredFiles = [...this.allFiles];
        }

        handleSearch(term) {
            this.searchTerm = term.toLowerCase();
            this.filterAndSort();
        }

        handleSort(sortType) {
            this.currentSort = sortType;
            this.filterAndSort();
        }

        filterAndSort() {
            // Filter
            this.filteredFiles = this.allFiles.filter(file =>
                file.name.toLowerCase().includes(this.searchTerm)
            );

            // Sort
            this.filteredFiles.sort((a, b) => {
                switch (this.currentSort) {
                    case 'newest':
                        return b.createdAt - a.createdAt;
                    case 'oldest':
                        return a.createdAt - b.createdAt;
                    case 'largest':
                        return b.size - a.size;
                    case 'smallest':
                        return a.size - b.size;
                    case 'name':
                        return a.name.localeCompare(b.name);
                    default:
                        return 0;
                }
            });

            this.renderFiles();
        }

        renderFiles() {
            const grid = document.getElementById('files-grid');
            if (!grid) return;

            // Hide all files first
            this.allFiles.forEach(file => {
                file.element.style.display = 'none';
            });

            // Show filtered files in order
            this.filteredFiles.forEach((file, index) => {
                file.element.style.display = 'block';
                file.element.style.order = index;
            });
        }

        updateSelection() {
            const checkboxes = document.querySelectorAll('.file-checkbox:checked');
            this.selectedFiles = new Set(Array.from(checkboxes).map(cb => parseInt(cb.value)));

            // Update UI
            document.getElementById('selected-count').textContent = this.selectedFiles.size;

            const bulkActions = document.getElementById('bulk-actions');
            if (this.selectedFiles.size > 0) {
                bulkActions?.classList.remove('hidden');
            } else {
                bulkActions?.classList.add('hidden');
            }
        }

        selectAll() {
            const checkboxes = document.querySelectorAll('.file-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);

            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
            });

            this.updateSelection();
        }

        async bulkDelete() {
            if (this.selectedFiles.size === 0) return;

            const fileNames = Array.from(this.selectedFiles).map(id => {
                const file = this.allFiles.find(f => f.id === id);
                return file ? file.name : 'Unknown';
            }).slice(0, 3).join(', ');

            const displayNames = this.selectedFiles.size > 3
                ? `${fileNames} and ${this.selectedFiles.size - 3} more`
                : fileNames;

            if (!confirm(`Are you sure you want to delete ${this.selectedFiles.size} file(s)?\n\n${displayNames}`)) {
                return;
            }

            try {
                const response = await this.apiCall('/files/delete', {
                    method: 'POST',
                    body: JSON.stringify({
                        bulk_ids: Array.from(this.selectedFiles)
                    })
                });

                if (response.success) {
                    this.showToast(response.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    this.showToast(response.message || 'Delete failed', 'error');
                }
            } catch (error) {
                this.showToast('Error deleting files: ' + error.message, 'error');
            }
        }

        openUploadModal() {
            document.getElementById('upload-modal')?.classList.remove('hidden');
        }

        closeUploadModal() {
            document.getElementById('upload-modal')?.classList.add('hidden');
        }

        setupUploadHandlers() {
            const fileInput = document.getElementById('file-input');
            const uploadForm = document.getElementById('upload-form');

            if (!fileInput || !uploadForm) return;

            // Click to select file
            uploadForm.addEventListener('click', () => fileInput.click());

            // Drag and drop
            uploadForm.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadForm.classList.add('border-blue-400', 'bg-blue-50', 'dark:bg-blue-900');
            });

            uploadForm.addEventListener('dragleave', (e) => {
                e.preventDefault();
                uploadForm.classList.remove('border-blue-400', 'bg-blue-50', 'dark:bg-blue-900');
            });

            uploadForm.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadForm.classList.remove('border-blue-400', 'bg-blue-50', 'dark:bg-blue-900');

                const file = e.dataTransfer.files[0];
                if (file && window.handleFileUpload) {
                    fileInput.files = e.dataTransfer.files;
                    window.handleFileUpload(file);
                }
            });

            // Listen for upload completion
            window.addEventListener('fileUploaded', (event) => {
                this.showToast(`File "${event.detail.file_name}" uploaded successfully!`, 'success');
                this.closeUploadModal();
                setTimeout(() => location.reload(), 1000);
            });

            // Custom upload success handler
            window.uploadSuccessHandler = (data) => {
                window.dispatchEvent(new CustomEvent('fileUploaded', { detail: data }));
            };
        }

        async apiCall(url, options = {}) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            const defaultOptions = {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                credentials: 'include'
            };

            const response = await fetch(url, { ...defaultOptions, ...options });
            return await response.json();
        }

        showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            if (!container) return;

            const toast = document.createElement('div');
            toast.className = `max-w-sm w-full bg-white dark:bg-gray-800 shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden transform transition-all duration-300 translate-x-full`;

            const bgColor = {
                success: 'bg-green-50 dark:bg-green-900',
                error: 'bg-red-50 dark:bg-red-900',
                warning: 'bg-yellow-50 dark:bg-yellow-900',
                info: 'bg-blue-50 dark:bg-blue-900'
            }[type] || 'bg-gray-50 dark:bg-gray-900';

            const icon = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            }[type] || 'ℹ️';

            toast.innerHTML = `
                <div class="p-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <span class="text-lg">${icon}</span>
                        </div>
                        <div class="ml-3 w-0 flex-1 pt-0.5">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">${message}</p>
                        </div>
                        <div class="ml-4 flex-shrink-0 flex">
                            <button onclick="this.parentElement.parentElement.parentElement.parentElement.remove()" class="bg-white dark:bg-gray-800 rounded-md inline-flex text-gray-400 hover:text-gray-500 focus:outline-none">
                                <span class="sr-only">Close</span>
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            `;

            container.appendChild(toast);

            // Animate in
            setTimeout(() => {
                toast.classList.remove('translate-x-full');
            }, 100);

            // Auto remove after 5 seconds
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }
    }

    // Global functions for backward compatibility
    function updateSelection() {
        window.fileManager?.updateSelection();
    }

    async function deleteFile(fileId, fileName) {
        if (!confirm(`Are you sure you want to delete "${fileName}"?`)) {
            return;
        }

        try {
            const response = await window.fileManager.apiCall('/files/delete', {
                method: 'POST',
                body: JSON.stringify({ file_id: fileId })
            });

            if (response.success) {
                window.fileManager.showToast(response.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                window.fileManager.showToast(response.message || 'Delete failed', 'error');
            }
        } catch (error) {
            window.fileManager.showToast('Error: ' + error.message, 'error');
        }
    }

    function previewFile(url, fileName) {
        const modal = document.getElementById('preview-modal');
        const content = document.getElementById('preview-content');
        const title = document.getElementById('preview-modal-title');

        title.textContent = fileName;
        content.innerHTML = `
            <video controls class="w-full max-h-96 rounded-lg">
                <source src="${url}" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        `;

        modal.classList.remove('hidden');
    }

    function closePreviewModal() {
        document.getElementById('preview-modal')?.classList.add('hidden');
    }

    function downloadFile(fileId) {
        window.open(`/api/secure-download/${fileId}`, '_blank');
    }

    function openUploadModal() {
        window.fileManager?.openUploadModal();
    }

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        window.fileManager = new FileManager();
    });
    </script>
    @endpush
</x-app-layout>
