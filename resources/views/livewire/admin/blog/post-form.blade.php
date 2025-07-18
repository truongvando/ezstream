<div>
    <form wire:submit.prevent="save">
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
                                <input wire:model.lazy="title" type="text" id="title" class="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 dark:text-white dark:bg-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="Tiêu đề cho link card">
                                @error('title') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label for="link" class="block text-sm font-medium leading-6 text-gray-900 dark:text-gray-200">Đường dẫn (URL)</label>
                                <input wire:model="link" type="text" id="link" class="mt-2 block w-full rounded-md border-0 py-1.5 text-gray-900 dark:text-white dark:bg-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="https://example.com/subdomain">
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
                                <select wire:model="status" id="status" class="mt-2 block w-full rounded-md border-0 py-1.5 pl-3 pr-10 text-gray-900 dark:text-white dark:bg-gray-700 ring-1 ring-inset ring-gray-300 dark:ring-gray-600 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                    <option value="DRAFT">Bản nháp</option>
                                    <option value="PUBLISHED">Công khai</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-lg bg-white dark:bg-gray-800 shadow-sm ring-1 ring-gray-900/5 dark:ring-white/10">
                        <div class="p-6">
                            <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-gray-200">Ảnh đại diện</h3>
                            <div class="mt-2">
                                <input wire:model="featured_image" type="file" id="featured_image" class="sr-only">
                                <label for="featured_image" class="cursor-pointer mt-2 flex justify-center rounded-lg border border-dashed border-gray-900/25 dark:border-white/25 px-6 py-10">
                                    <div class="text-center">
                                         @if ($featured_image)
                                            <img src="{{ $featured_image->temporaryUrl() }}" class="mx-auto h-24 w-auto object-cover">
                                        @elseif ($existing_featured_image)
                                            <img src="{{ asset('storage/' . $existing_featured_image) }}" class="mx-auto h-24 w-auto object-cover">
                                        @else
                                            <svg class="mx-auto h-12 w-12 text-gray-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M1.5 6a2.25 2.25 0 012.25-2.25h16.5A2.25 2.25 0 0122.5 6v12a2.25 2.25 0 01-2.25 2.25H3.75A2.25 2.25 0 011.5 18V6zM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0021 18v-1.94l-2.69-2.689a1.5 1.5 0 00-2.12 0l-.88.879.97.97a.75.75 0 11-1.06 1.06l-5.16-5.159a1.5 1.5 0 00-2.12 0L3 16.061zm10.125-7.81a1.125 1.125 0 112.25 0 1.125 1.125 0 01-2.25 0z" clip-rule="evenodd" />
                                            </svg>
                                        @endif
                                        <div class="mt-4 flex text-sm leading-6 text-gray-600">
                                            <p class="pl-1">Nhấn để tải lên</p>
                                        </div>
                                        <p class="text-xs leading-5 text-gray-600">PNG, JPG, GIF up to 2MB</p>
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
