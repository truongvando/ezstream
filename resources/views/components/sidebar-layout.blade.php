<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' || (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches) }" 
      x-init="
        $watch('darkMode', value => {
          localStorage.setItem('darkMode', value);
          if (value) { document.documentElement.classList.add('dark'); } 
          else { document.documentElement.classList.remove('dark'); }
        });
        if (darkMode) { document.documentElement.classList.add('dark'); }
      " 
      :class="{ 'dark': darkMode }">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'EZStream Control') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles

    </head>
    <body class="font-sans antialiased bg-gray-100 dark:bg-gray-900">
        <div x-data="{ sidebarOpen: window.innerWidth >= 1024 }" @keydown.escape.window="sidebarOpen = false" class="h-screen flex overflow-hidden bg-gray-100 dark:bg-gray-800">
            <!-- Off-canvas menu for mobile, show/hide based on off-canvas menu state. -->
            <div x-show="sidebarOpen" class="relative z-40 lg:hidden" x-ref="dialog" aria-modal="true">
                <div x-show="sidebarOpen"
                     x-transition:enter="transition-opacity ease-linear duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition-opacity ease-linear duration-300"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="fixed inset-0 bg-gray-600 bg-opacity-75"></div>

                <div class="fixed inset-0 flex z-40">
                    <div x-show="sidebarOpen"
                         x-transition:enter="transition ease-in-out duration-300 transform"
                         x-transition:enter-start="-translate-x-full"
                         x-transition:enter-end="translate-x-0"
                         x-transition:leave="transition ease-in-out duration-300 transform"
                         x-transition:leave-start="translate-x-0"
                         x-transition:leave-end="-translate-x-full"
                         @click.away="sidebarOpen = false"
                         class="relative flex-1 flex flex-col max-w-xs w-full bg-white dark:bg-gray-800">
                        @include('layouts.partials.sidebar-content')
                    </div>
                    <div class="flex-shrink-0 w-14" aria-hidden="true"></div>
                </div>
            </div>

            <!-- Static sidebar for desktop -->
            <div class="hidden lg:flex lg:flex-shrink-0">
                <div class="flex flex-col w-64">
                    <div class="flex flex-col h-0 flex-1">
                        <div class="flex-1 flex flex-col overflow-y-auto">
                             @include('layouts.partials.sidebar-content')
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <div class="flex flex-col w-0 flex-1 overflow-hidden">
                <!-- Top bar -->
                <div class="relative z-10 flex-shrink-0 flex h-16 bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                    <button @click="sidebarOpen = true" class="px-4 border-r border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500 lg:hidden">
                        <span class="sr-only">Open sidebar</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                        </svg>
                    </button>
                    <div class="flex-1 px-4 flex justify-between items-center">
                        <div class="flex-1">
                            <!-- Top bar content if needed -->
                        </div>
                    </div>
                </div>

                <main class="flex-1 relative overflow-y-auto focus:outline-none">
                    <div class="h-full">
                        @isset($header)
                            <header class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                                {{ $header }}
                            </header>
                        @endisset
                        <div class="p-6">
                            <x-flash-message />
                        </div>
                        <div class="flex-1">
                            {{ $slot }}
                        </div>
                    </div>
                </main>
            </div>
        </div>
        @livewireScripts
        @stack('scripts')
    </body>
</html>
