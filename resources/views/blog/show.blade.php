<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $post->title }}
        </h2>
    </x-slot>

    <div class="h-full bg-white dark:bg-gray-800">
        <div class="p-6 md:p-8 text-gray-900 dark:text-gray-100">
                    @if($post->featured_image)
                        <img src="{{ asset('storage/' . $post->featured_image) }}" alt="{{ $post->title }}" class="w-full h-auto rounded-lg mb-8">
                    @endif

                    <h1 class="text-3xl md:text-4xl font-bold mb-4">{{ $post->title }}</h1>
                    
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                        <span>Đăng ngày {{ $post->created_at->format('d/m/Y') }}</span>
                    </div>

                    <div class="prose dark:prose-invert max-w-none">
                        {!! $post->body !!}
                    </div>

                    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('blog.index') }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                            &larr; {{ __('Back to Blog') }}
                        </a>
                    </div>
        </div>
    </div>
</x-app-layout>
