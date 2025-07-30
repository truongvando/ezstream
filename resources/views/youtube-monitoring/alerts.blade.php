<x-sidebar-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('YouTube Alerts') }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Quản lý thông báo từ các kênh YouTube đang theo dõi
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <button onclick="markAllAsRead()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition-colors duration-200">
                    Đánh dấu tất cả đã đọc
                </button>
                <a href="{{ route('youtube.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg transition-colors duration-200">
                    Quay lại Monitoring
                </a>
            </div>
        </div>
    </x-slot>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <select id="channelFilter" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                    <option value="">Tất cả kênh</option>
                </select>
            </div>
            <div class="md:w-48">
                <select id="typeFilter" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                    <option value="">Tất cả loại</option>
                    <option value="new_video">🎬 Video mới</option>
                    <option value="subscriber_milestone">🎯 Mốc Subscribers</option>
                    <option value="view_milestone">👀 Mốc Views</option>
                    <option value="growth_spike">📈 Tăng trưởng đột biến</option>
                    <option value="video_viral">🔥 Video viral</option>
                    <option value="channel_inactive">😴 Kênh không hoạt động</option>
                </select>
            </div>
            <div class="md:w-48">
                <select id="statusFilter" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                    <option value="">Tất cả trạng thái</option>
                    <option value="unread">Chưa đọc</option>
                    <option value="read">Đã đọc</option>
                </select>
            </div>
            <button onclick="applyFilters()" class="bg-blue-500 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors duration-200">
                Lọc
            </button>
        </div>
    </div>

    <!-- Alerts List -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div id="alertsContainer">
            <!-- Alerts will be loaded here -->
        </div>
        
        <!-- Pagination -->
        <div id="paginationContainer" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            <!-- Pagination will be loaded here -->
        </div>
    </div>

    @push('scripts')
    <script>
        let currentPage = 1;
        let currentFilters = {};

        // Load alerts on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadAlerts();
            loadChannels();
        });

        async function loadAlerts(page = 1) {
            try {
                const params = new URLSearchParams({
                    page: page,
                    ...currentFilters
                });

                const response = await fetch(`/youtube-alerts?${params}`, {
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    }
                });

                const data = await response.json();
                renderAlerts(data.alerts);
                renderPagination(data.alerts);
                
            } catch (error) {
                console.error('Error loading alerts:', error);
                document.getElementById('alertsContainer').innerHTML = 
                    '<div class="text-center py-12"><p class="text-red-500">Lỗi tải alerts</p></div>';
            }
        }

        async function loadChannels() {
            try {
                const response = await fetch('/youtube-alerts', {
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    }
                });

                const data = await response.json();
                const channelFilter = document.getElementById('channelFilter');
                
                data.channels.forEach(channel => {
                    const option = document.createElement('option');
                    option.value = channel.id;
                    option.textContent = channel.channel_name;
                    channelFilter.appendChild(option);
                });
                
            } catch (error) {
                console.error('Error loading channels:', error);
            }
        }

        function renderAlerts(alertsData) {
            const container = document.getElementById('alertsContainer');
            
            if (alertsData.data.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zM4 19h6v-2H4v2zM4 15h8v-2H4v2zM4 11h10V9H4v2zM4 7h12V5H4v2z"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Không có alert nào</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Alerts sẽ xuất hiện khi có hoạt động từ các kênh đang theo dõi.</p>
                    </div>
                `;
                return;
            }

            const alertsHtml = alertsData.data.map(alert => `
                <div class="border-b border-gray-200 dark:border-gray-700 last:border-b-0">
                    <div class="flex items-start space-x-4 p-6 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors ${!alert.is_read ? 'bg-blue-50 dark:bg-blue-900' : ''}">
                        <div class="flex-shrink-0">
                            <span class="text-3xl">${getAlertIcon(alert.type)}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">${alert.title}</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">${alert.message}</p>
                                    <div class="flex items-center space-x-4 mt-3">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">
                                            <strong>Kênh:</strong> ${alert.channel.channel_name}
                                        </span>
                                        <span class="text-sm text-gray-500 dark:text-gray-400">
                                            ${formatDateTime(alert.triggered_at)}
                                        </span>
                                        ${!alert.is_read ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">Mới</span>' : ''}
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2 ml-4">
                                    ${!alert.is_read ? `<button onclick="markAsRead(${alert.id})" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 text-sm">Đánh dấu đã đọc</button>` : ''}
                                    <button onclick="deleteAlert(${alert.id})" class="text-red-600 hover:text-red-800 dark:text-red-400 text-sm">Xóa</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');

            container.innerHTML = alertsHtml;
        }

        function renderPagination(alertsData) {
            const container = document.getElementById('paginationContainer');
            
            if (alertsData.last_page <= 1) {
                container.innerHTML = '';
                return;
            }

            let paginationHtml = '<div class="flex items-center justify-between">';
            paginationHtml += `<div class="text-sm text-gray-700 dark:text-gray-300">Hiển thị ${alertsData.from} đến ${alertsData.to} trong ${alertsData.total} kết quả</div>`;
            paginationHtml += '<div class="flex space-x-2">';

            // Previous button
            if (alertsData.current_page > 1) {
                paginationHtml += `<button onclick="loadAlerts(${alertsData.current_page - 1})" class="px-3 py-2 text-sm bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 dark:hover:bg-gray-500">Trước</button>`;
            }

            // Page numbers
            for (let i = Math.max(1, alertsData.current_page - 2); i <= Math.min(alertsData.last_page, alertsData.current_page + 2); i++) {
                const isActive = i === alertsData.current_page;
                paginationHtml += `<button onclick="loadAlerts(${i})" class="px-3 py-2 text-sm ${isActive ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-500'} rounded">${i}</button>`;
            }

            // Next button
            if (alertsData.current_page < alertsData.last_page) {
                paginationHtml += `<button onclick="loadAlerts(${alertsData.current_page + 1})" class="px-3 py-2 text-sm bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 dark:hover:bg-gray-500">Sau</button>`;
            }

            paginationHtml += '</div></div>';
            container.innerHTML = paginationHtml;
        }

        function applyFilters() {
            currentFilters = {
                channel_id: document.getElementById('channelFilter').value,
                type: document.getElementById('typeFilter').value,
                unread_only: document.getElementById('statusFilter').value === 'unread' ? '1' : ''
            };

            // Remove empty filters
            Object.keys(currentFilters).forEach(key => {
                if (!currentFilters[key]) {
                    delete currentFilters[key];
                }
            });

            loadAlerts(1);
        }

        async function markAsRead(alertId) {
            try {
                const response = await fetch(`/youtube-alerts/${alertId}/read`, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    }
                });

                if (response.ok) {
                    loadAlerts(currentPage);
                }
            } catch (error) {
                console.error('Error marking alert as read:', error);
            }
        }

        async function markAllAsRead() {
            try {
                const response = await fetch('/youtube-alerts/read-all', {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    }
                });

                if (response.ok) {
                    loadAlerts(currentPage);
                }
            } catch (error) {
                console.error('Error marking all as read:', error);
            }
        }

        async function deleteAlert(alertId) {
            if (!confirm('Bạn có chắc muốn xóa alert này?')) return;

            try {
                const response = await fetch(`/youtube-alerts/${alertId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    }
                });

                if (response.ok) {
                    loadAlerts(currentPage);
                }
            } catch (error) {
                console.error('Error deleting alert:', error);
            }
        }

        function getAlertIcon(type) {
            const icons = {
                'new_video': '🎬',
                'subscriber_milestone': '🎯',
                'view_milestone': '👀',
                'growth_spike': '📈',
                'video_viral': '🔥',
                'channel_inactive': '😴'
            };
            return icons[type] || '📢';
        }

        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('vi-VN', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    </script>
    @endpush
</x-sidebar-layout>
