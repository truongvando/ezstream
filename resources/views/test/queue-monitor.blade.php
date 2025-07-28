<!DOCTYPE html>
<html>
<head>
    <title>Queue Monitor Test</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Auto refresh every 5 seconds
        setTimeout(() => location.reload(), 5000);
    </script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold mb-8">🚦 Stream Queue Monitor (Test)</h1>
        
        <!-- Queue Status -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">📋 Queue Status</h2>
            @php
                $allocation = app(\App\Services\Stream\StreamAllocation::class);
                $queueStatus = $allocation->getQueueStatus();
            @endphp
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div class="bg-blue-50 p-4 rounded">
                    <div class="text-2xl font-bold text-blue-600">{{ $queueStatus['total_queued'] }}</div>
                    <div class="text-blue-600">Streams in Queue</div>
                </div>
                <div class="bg-green-50 p-4 rounded">
                    <div class="text-2xl font-bold text-green-600">{{ now()->format('H:i:s') }}</div>
                    <div class="text-green-600">Last Update</div>
                </div>
            </div>

            @if(!empty($queueStatus['streams']))
                <table class="w-full border-collapse border border-gray-300">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="border border-gray-300 px-4 py-2">Stream ID</th>
                            <th class="border border-gray-300 px-4 py-2">Title</th>
                            <th class="border border-gray-300 px-4 py-2">User</th>
                            <th class="border border-gray-300 px-4 py-2">Priority</th>
                            <th class="border border-gray-300 px-4 py-2">Waiting Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($queueStatus['streams'] as $stream)
                            <tr>
                                <td class="border border-gray-300 px-4 py-2">#{{ $stream['id'] }}</td>
                                <td class="border border-gray-300 px-4 py-2">{{ Str::limit($stream['title'], 30) }}</td>
                                <td class="border border-gray-300 px-4 py-2">{{ $stream['user'] }}</td>
                                <td class="border border-gray-300 px-4 py-2">{{ number_format($stream['priority'], 0) }}</td>
                                <td class="border border-gray-300 px-4 py-2">{{ gmdate('H:i:s', $stream['waiting_time']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-gray-500">📭 Queue is empty</p>
            @endif
        </div>

        <!-- VPS Status -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">🖥️ VPS Status</h2>
            @php
                $vpsServers = \App\Models\VpsServer::where('status', 'ACTIVE')->get();
                $vpsStats = [];
                foreach($vpsServers as $vps) {
                    $statsJson = \Illuminate\Support\Facades\Redis::hget('vps_live_stats', $vps->id);
                    if($statsJson) {
                        $data = json_decode($statsJson, true);
                        $vpsStats[] = [
                            'id' => $vps->id,
                            'name' => $vps->name,
                            'cpu' => $data['cpu_usage'] ?? 0,
                            'ram' => $data['ram_usage'] ?? 0,
                            'streams' => $data['active_streams'] ?? 0,
                            'network_sent' => number_format(($data['network_sent_mb'] ?? 0) / 1024, 1),
                            'network_recv' => number_format(($data['network_recv_mb'] ?? 0) / 1024, 1),
                            'disk_used' => $data['disk_used_gb'] ?? 0,
                            'disk_total' => $data['disk_total_gb'] ?? 0,
                            'updated' => isset($data['received_at']) ? date('H:i:s', $data['received_at']) : 'N/A'
                        ];
                    }
                }
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($vpsStats as $vps)
                    <div class="border rounded-lg p-4 
                        @if($vps['cpu'] > 90) border-red-300 bg-red-50
                        @elseif($vps['cpu'] > 70) border-yellow-300 bg-yellow-50
                        @else border-green-300 bg-green-50 @endif">
                        
                        <h3 class="font-semibold">{{ $vps['name'] }} (ID: {{ $vps['id'] }})</h3>
                        <div class="mt-2 space-y-1 text-sm">
                            <div>CPU: <strong>{{ number_format($vps['cpu'], 1) }}%</strong></div>
                            <div>RAM: <strong>{{ number_format($vps['ram'], 1) }}%</strong></div>
                            <div>Streams: <strong>{{ $vps['streams'] }}</strong></div>
                            @if($vps['disk_total'] > 0)
                                <div>Disk: <strong>{{ number_format($vps['disk_used'], 1) }}/{{ number_format($vps['disk_total'], 1) }}GB</strong></div>
                            @endif
                            <div>Network: ↓{{ $vps['network_recv'] }}GB ↑{{ $vps['network_sent'] }}GB</div>
                            <div>Updated: {{ $vps['updated'] }}</div>
                        </div>
                        
                        @if($vps['cpu'] > 90)
                            <div class="mt-2 text-red-600 font-semibold">🚨 OVERLOADED</div>
                        @elseif($vps['cpu'] > 70)
                            <div class="mt-2 text-yellow-600 font-semibold">⚠️ HIGH LOAD</div>
                        @else
                            <div class="mt-2 text-green-600 font-semibold">✅ HEALTHY</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Recent Streams -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">📺 Recent Streams</h2>
            @php
                $recentStreams = \App\Models\StreamConfiguration::with('user', 'vpsServer')
                    ->orderBy('updated_at', 'desc')
                    ->limit(10)
                    ->get();
            @endphp

            <table class="w-full border-collapse border border-gray-300">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="border border-gray-300 px-4 py-2">ID</th>
                        <th class="border border-gray-300 px-4 py-2">Title</th>
                        <th class="border border-gray-300 px-4 py-2">Status</th>
                        <th class="border border-gray-300 px-4 py-2">VPS</th>
                        <th class="border border-gray-300 px-4 py-2">User</th>
                        <th class="border border-gray-300 px-4 py-2">Updated</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentStreams as $stream)
                        <tr>
                            <td class="border border-gray-300 px-4 py-2">#{{ $stream->id }}</td>
                            <td class="border border-gray-300 px-4 py-2">{{ Str::limit($stream->title, 25) }}</td>
                            <td class="border border-gray-300 px-4 py-2">
                                <span class="px-2 py-1 rounded text-xs
                                    @if($stream->status === 'STREAMING') bg-green-100 text-green-800
                                    @elseif($stream->status === 'PENDING') bg-yellow-100 text-yellow-800
                                    @elseif($stream->status === 'STARTING') bg-blue-100 text-blue-800
                                    @elseif($stream->status === 'ERROR') bg-red-100 text-red-800
                                    @else bg-gray-100 text-gray-800 @endif">
                                    {{ $stream->status }}
                                </span>
                            </td>
                            <td class="border border-gray-300 px-4 py-2">{{ $stream->vpsServer->name ?? 'N/A' }}</td>
                            <td class="border border-gray-300 px-4 py-2">{{ $stream->user->name ?? 'N/A' }}</td>
                            <td class="border border-gray-300 px-4 py-2">{{ $stream->updated_at->format('H:i:s') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Actions -->
        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <h2 class="text-xl font-semibold mb-4">🔧 Test Actions</h2>
            <div class="space-x-4">
                <a href="{{ url('/test/queue-monitor') }}" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">🔄 Refresh</a>
                <a href="{{ url('/test/process-queue') }}" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">⚡ Process Queue</a>
                <a href="{{ url('/admin/streams') }}" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">📺 Manage Streams</a>
            </div>
        </div>
    </div>
</body>
</html>
