<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Blog') }}
        </h2>
    </x-slot>

    <div class="h-full bg-white dark:bg-gray-800">
        <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @forelse ($posts as $post)
                            <a href="{{ $post->link }}" target="_blank" class="block bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                                @if($post->featured_image)
                                    <img src="{{ $post->featured_image }}" alt="{{ $post->title }}" class="w-full h-48 object-cover">
                                @else
                                     <div class="w-full h-48 bg-gray-200 dark:bg-gray-600 flex items-center justify-center">
                                        <span class="text-gray-500">No Image</span>
                                    </div>
                                @endif
                                <div class="p-4">
                                    <h3 class="font-bold text-lg mb-2 text-blue-600 dark:text-blue-400">
                                        {{ $post->title }}
                                    </h3>
                                    <div class="flex justify-between items-center mt-4">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $post->created_at->format('d/m/Y') }}</span>
                                        @if($post->link)
                                            <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full text-xs font-semibold">
                                                🔗 Link
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="col-span-full text-center py-12">
                                <p class="text-gray-500 dark:text-gray-400">Chưa có bài viết nào.</p>
                                <a href="{{ route('admin.blog.create') }}" class="mt-4 inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Tạo bài viết đầu tiên
                                </a>
                            </div>
                        @endforelse
                    </div>

                    <div class="mt-8">
                        {{ $posts->links() }}
                    </div>
        </div>
    </div>
</x-app-layout>
