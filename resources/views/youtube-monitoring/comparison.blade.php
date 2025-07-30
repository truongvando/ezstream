<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('So sánh kênh YouTube') }}
            </h2>
            <div class="flex space-x-3">
                <a href="{{ route('youtube.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    ← Quay lại
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Channel Selection -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-medium mb-4">Chọn kênh để so sánh (tối đa 5 kênh)</h3>
                    
                    <form id="comparisonForm">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                            @foreach($channels as $channel)
                                <label class="flex items-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                                    <input type="checkbox" name="channels[]" value="{{ $channel->id }}" class="mr-3 channel-checkbox" 
                                           @if($selectedChannels->contains('id', $channel->id)) checked @endif>
                                    <div class="flex items-center flex-1">
                                        <img src="{{ $channel->thumbnail_url }}" alt="{{ $channel->channel_name }}" 
                                             class="w-12 h-12 rounded-full mr-3">
                                        <div>
                                            <div class="font-medium">{{ $channel->channel_name }}</div>
                                            <div class="text-sm text-gray-500">
                                                {{ number_format($channel->latestSnapshot()?->subscriber_count ?? 0) }} subscribers
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        
                        <button type="submit" id="compareBtn" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded disabled:opacity-50" disabled>
                            So sánh kênh
                        </button>
                    </form>
                </div>
            </div>

            <!-- Comparison Results -->
            <div id="comparisonResults" class="hidden">
                <!-- Overview Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-lg font-medium mb-2">Tổng Subscribers</h3>
                        <p class="text-3xl font-bold text-blue-600" id="totalSubscribers">-</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-lg font-medium mb-2">Kênh dẫn đầu</h3>
                        <p class="text-lg font-semibold text-green-600" id="leadingChannel">-</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-lg font-medium mb-2">Tăng trưởng nhanh nhất</h3>
                        <p class="text-lg font-semibold text-purple-600" id="fastestGrowing">-</p>
                    </div>
                </div>

                <!-- Comparison Table -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <h3 class="text-lg font-medium mb-4">Bảng so sánh chi tiết</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700" id="comparisonTable">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kênh</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Subscribers</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Videos</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Lượt xem</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tăng trưởng 24h</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ngày tạo</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700" id="comparisonTableBody">
                                    <!-- Data will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-lg font-medium mb-4">So sánh Subscribers</h3>
                        <div class="h-64">
                            <canvas id="subscriberChart"></canvas>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-lg font-medium mb-4">Xu hướng tăng trưởng</h3>
                        <div class="h-64">
                            <canvas id="growthChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- AI Insights -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-medium mb-4">Nhận xét & Phân tích</h3>
                    <div id="insightsContainer">
                        <!-- Insights will be populated by JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div id="loadingState" class="hidden text-center py-8">
                <div class="inline-flex items-center px-4 py-2 font-semibold leading-6 text-sm shadow rounded-md text-white bg-blue-500 hover:bg-blue-400 transition ease-in-out duration-150 cursor-not-allowed">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Đang so sánh kênh...
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let subscriberChart = null;
        let growthChart = null;

        // Handle checkbox selection
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.channel-checkbox');
            const compareBtn = document.getElementById('compareBtn');
            
            function updateCompareButton() {
                const checkedBoxes = document.querySelectorAll('.channel-checkbox:checked');
                compareBtn.disabled = checkedBoxes.length < 2 || checkedBoxes.length > 5;
            }
            
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateCompareButton);
            });
            
            updateCompareButton();
        });

        // Handle form submission
        document.getElementById('comparisonForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const checkedBoxes = document.querySelectorAll('.channel-checkbox:checked');
            const channelIds = Array.from(checkedBoxes).map(cb => cb.value);
            
            if (channelIds.length < 2) {
                alert('Vui lòng chọn ít nhất 2 kênh để so sánh');
                return;
            }
            
            compareChannels(channelIds);
        });

        function compareChannels(channelIds) {
            // Show loading state
            document.getElementById('loadingState').classList.remove('hidden');
            document.getElementById('comparisonResults').classList.add('hidden');
            
            // Make API call
            fetch('{{ route("youtube.comparison.compare") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    channel_ids: channelIds
                })
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingState').classList.add('hidden');
                
                if (data.success) {
                    displayComparisonResults(data.data);
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(error => {
                document.getElementById('loadingState').classList.add('hidden');
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi so sánh kênh');
            });
        }

        function displayComparisonResults(data) {
            // Show results container
            document.getElementById('comparisonResults').classList.remove('hidden');
            
            // Update overview cards
            document.getElementById('totalSubscribers').textContent = new Intl.NumberFormat().format(data.metrics.total_subscribers);
            document.getElementById('leadingChannel').textContent = data.metrics.leader ? data.metrics.leader.channel_name : 'N/A';
            document.getElementById('fastestGrowing').textContent = data.metrics.fastest_growing ? data.metrics.fastest_growing.channel_name : 'N/A';
            
            // Update comparison table
            updateComparisonTable(data.channels);
            
            // Update charts
            updateCharts(data.charts);
            
            // Update insights
            updateInsights(data.insights);
        }

        function updateComparisonTable(channels) {
            const tbody = document.getElementById('comparisonTableBody');
            tbody.innerHTML = '';
            
            channels.forEach(channel => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-gray-50 dark:hover:bg-gray-700';
                
                const growthColor = channel.growth.growth_rate > 0 ? 'text-green-600' : 
                                  channel.growth.growth_rate < 0 ? 'text-red-600' : 'text-gray-600';
                
                row.innerHTML = `
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <img class="h-10 w-10 rounded-full" src="${channel.thumbnail}" alt="${channel.name}">
                            <div class="ml-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">${channel.name}</div>
                                <div class="text-sm text-gray-500">${channel.url}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                        ${new Intl.NumberFormat().format(channel.subscribers)}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                        ${new Intl.NumberFormat().format(channel.videos)}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                        ${new Intl.NumberFormat().format(channel.views)}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm ${growthColor}">
                        ${channel.growth.growth_rate > 0 ? '+' : ''}${channel.growth.growth_rate.toFixed(2)}%
                        <div class="text-xs text-gray-500">
                            ${channel.growth.subscriber_growth > 0 ? '+' : ''}${new Intl.NumberFormat().format(channel.growth.subscriber_growth)} subs
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${new Date(channel.created_at).toLocaleDateString('vi-VN')}
                    </td>
                `;
                
                tbody.appendChild(row);
            });
        }

        function updateCharts(chartData) {
            // Destroy existing charts
            if (subscriberChart) subscriberChart.destroy();
            if (growthChart) growthChart.destroy();
            
            // Subscriber comparison chart
            const subCtx = document.getElementById('subscriberChart').getContext('2d');
            subscriberChart = new Chart(subCtx, {
                type: 'bar',
                data: {
                    labels: chartData.subscriber_comparison.map(item => item.name),
                    datasets: [{
                        label: 'Subscribers',
                        data: chartData.subscriber_comparison.map(item => item.data[item.data.length - 1]),
                        backgroundColor: [
                            '#ef4444', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6'
                        ].slice(0, chartData.subscriber_comparison.length),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return new Intl.NumberFormat().format(value);
                                }
                            }
                        }
                    }
                }
            });
            
            // Growth trend chart
            const growthCtx = document.getElementById('growthChart').getContext('2d');
            growthChart = new Chart(growthCtx, {
                type: 'line',
                data: {
                    labels: chartData.growth_trends[0]?.data.map((_, index) => `Day ${index + 1}`) || [],
                    datasets: chartData.growth_trends.map((trend, index) => ({
                        label: trend.name,
                        data: trend.data,
                        borderColor: ['#ef4444', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6'][index],
                        backgroundColor: ['#ef4444', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6'][index] + '20',
                        tension: 0.4,
                        fill: false
                    }))
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toFixed(1) + '%';
                                }
                            }
                        }
                    }
                }
            });
        }

        function updateInsights(insights) {
            const container = document.getElementById('insightsContainer');
            container.innerHTML = '';
            
            if (insights.length === 0) {
                container.innerHTML = '<p class="text-gray-500">Chưa có nhận xét nào.</p>';
                return;
            }
            
            insights.forEach(insight => {
                const insightDiv = document.createElement('div');
                insightDiv.className = 'mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border-l-4 border-blue-500';
                
                const iconMap = {
                    'leader': '👑',
                    'growth': '📈',
                    'content': '🎬'
                };
                
                insightDiv.innerHTML = `
                    <h4 class="font-medium text-blue-800 dark:text-blue-200 mb-2">
                        ${iconMap[insight.type] || '💡'} ${insight.title}
                    </h4>
                    <p class="text-blue-700 dark:text-blue-300">${insight.message}</p>
                `;
                
                container.appendChild(insightDiv);
            });
        }
    </script>
    @endpush
</x-app-layout>
