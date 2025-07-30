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

        <!-- Favicon -->
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
        <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon.ico') }}">

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
                <div class="flex items-center justify-between bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-4 py-2">
                    <div class="lg:hidden">
                         <button @click="sidebarOpen = true" type="button" class="-ml-0.5 -mt-0.5 h-12 w-12 inline-flex items-center justify-center rounded-md text-gray-500 hover:text-gray-900 dark:hover:text-white focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500">
                            <span class="sr-only">Open sidebar</span>
                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </div>

                    <!-- Right side - Notifications -->
                    <div class="flex items-center space-x-4 ml-auto">
                        <!-- YouTube Alerts Notification -->
                        <div class="relative" x-data="{ open: false, unreadCount: 0 }" x-init="loadUnreadCount()">
                            <a href="{{ route('youtube.alerts.page') }}"
                               class="relative p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-lg">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zM4 19h6v-2H4v2zM4 15h8v-2H4v2zM4 11h10V9H4v2zM4 7h12V5H4v2z"/>
                                </svg>
                                <!-- Unread count badge -->
                                <span x-show="unreadCount > 0" x-text="unreadCount"
                                      class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center"></span>
                            </a>


                        </div>

                        <!-- Dark mode toggle -->
                        <button @click="darkMode = !darkMode" class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-lg">
                            <svg x-show="!darkMode" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                            </svg>
                            <svg x-show="darkMode" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <main class="flex-1 relative overflow-y-auto focus:outline-none h-full">
                    <div class="h-full">
                        @isset($header)
                            <header class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-4 py-3">
                                {{ $header }}
                            </header>
                        @endisset
                        <div class="p-4">
                            <x-flash-message />
                        </div>
                        <div class="flex-1 px-4 pb-4">
                            @if(isset($slot))
                                {{ $slot }}
                            @else
                                @yield('content')
                            @endif
                        </div>
                    </div>
                </main>
            </div>
        </div>
        @livewireScripts

        <!-- Global file upload script -->
        <script src="{{ asset('js/file-upload.js') }}"></script>

        <!-- YouTube Alerts Script -->
        <script>
            async function loadUnreadCount() {
                try {
                    const response = await fetch('/youtube-alerts/unread-count', {
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        }
                    });
                    const data = await response.json();
                    this.unreadCount = data.unread_count || 0;
                } catch (error) {
                    console.error('Error loading unread count:', error);
                }
            }

            async function loadRecentAlerts() {
                try {
                    const response = await fetch('/youtube-alerts/recent', {
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        }
                    });
                    const data = await response.json();

                    const alertsList = document.getElementById('alertsList');

                    if (data.alerts.length === 0) {
                        alertsList.innerHTML = '<p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">Không có alert nào</p>';
                        return;
                    }

                    alertsList.innerHTML = data.alerts.map(alert => `
                        <div class="flex items-start space-x-3 p-2 rounded-lg ${alert.is_read ? 'bg-gray-50 dark:bg-gray-700' : 'bg-blue-50 dark:bg-blue-900'} hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                            <span class="text-lg">${getAlertIcon(alert.type)}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">${alert.title}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">${alert.message}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">${formatDate(alert.triggered_at)}</p>
                            </div>
                            ${!alert.is_read ? '<div class="w-2 h-2 bg-blue-500 rounded-full"></div>' : ''}
                        </div>
                    `).join('');

                } catch (error) {
                    console.error('Error loading recent alerts:', error);
                    document.getElementById('alertsList').innerHTML = '<p class="text-sm text-red-500 text-center py-4">Lỗi tải alerts</p>';
                }
            }

            async function markAllAsRead() {
                try {
                    const response = await fetch('/youtube-alerts/read-all', {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        }
                    });

                    if (response.ok) {
                        // Reload alerts and count
                        await loadRecentAlerts();
                        await loadUnreadCount();
                    }
                } catch (error) {
                    console.error('Error marking all as read:', error);
                }
            }

            function getAlertIcon(type) {
                const icons = {
                    'new_video': '<span class="text-blue-500">📹</span>',
                    'subscriber_milestone': '<span class="text-green-500">🎯</span>',
                    'view_milestone': '<span class="text-purple-500">👁️</span>',
                    'growth_spike': '<span class="text-emerald-500">📊</span>',
                    'video_viral': '<span class="text-red-500">🔥</span>',
                    'channel_inactive': '<span class="text-gray-500">💤</span>'
                };
                return icons[type] || '<span class="text-blue-500">📢</span>';
            }

            function formatDate(dateString) {
                const date = new Date(dateString);
                const now = new Date();
                const diffInHours = Math.floor((now - date) / (1000 * 60 * 60));

                if (diffInHours < 1) {
                    return 'Vừa xong';
                } else if (diffInHours < 24) {
                    return `${diffInHours} giờ trước`;
                } else {
                    const diffInDays = Math.floor(diffInHours / 24);
                    return `${diffInDays} ngày trước`;
                }
            }

            // Auto-refresh unread count every 5 minutes
            setInterval(async () => {
                if (typeof loadUnreadCount === 'function') {
                    await loadUnreadCount();
                }
            }, 300000); // 5 minutes
        </script>

        @stack('scripts')
    </body>
</html>