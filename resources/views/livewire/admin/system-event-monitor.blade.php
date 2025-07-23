<div wire:poll.2s class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 mt-6">
    <h2 class="text-xl font-semibold text-gray-800 dark:text-white mb-4">Real-time System Event Monitor</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Time</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Level</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Message</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Context</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($events as $event)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $event->created_at->format('H:i:s') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @php
                                $levelClass = [
                                    'info' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                    'warning' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                    'error' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                                ][strtolower($event->level)] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
                            @endphp
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $levelClass }}">
                                {{ strtoupper($event->level) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ $event->type }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ \Illuminate\Support\Str::limit($event->message, 70) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">
                            @if($event->context)
                                <button wire:click="$dispatch('openModal', { component: 'admin.event-context-modal', arguments: { context: {{ json_encode($event->context) }} } })" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                    View
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            No system events recorded yet. Waiting for new events...
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
