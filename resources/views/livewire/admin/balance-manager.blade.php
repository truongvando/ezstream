<div class="max-w-7xl mx-auto p-6">
    <!-- Header -->
    <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center">
                    <svg class="w-8 h-8 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Quản lý số dư
                </h2>
                <p class="text-gray-600 dark:text-gray-400">Điều chỉnh số dư user và xem thống kê bonus</p>
            </div>
        </div>
    </div>

    <!-- Bonus Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Tổng bonus đã trả</div>
            <div class="text-2xl font-bold text-green-600">${{ number_format($bonusStats['total_bonuses_paid'], 2) }}</div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Tổng nạp có bonus</div>
            <div class="text-2xl font-bold text-blue-600">${{ number_format($bonusStats['total_deposits_with_bonus'], 2) }}</div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">% bonus trung bình</div>
            <div class="text-2xl font-bold text-purple-600">{{ number_format($bonusStats['average_bonus_percentage'], 1) }}%</div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Users có bonus</div>
            <div class="text-2xl font-bold text-orange-600">{{ number_format($bonusStats['users_with_bonuses']) }}</div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Bonus tháng này</div>
            <div class="text-2xl font-bold text-red-600">${{ number_format($bonusStats['bonuses_this_month'], 2) }}</div>
        </div>
    </div>

    <!-- Search -->
    <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6 mb-6">
        <div class="flex gap-4">
            <div class="flex-1">
                <input wire:model.live="search" type="text" placeholder="Tìm user theo tên hoặc email..."
                       class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Số dư</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tổng nạp</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tier bonus</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($users as $user)
                        @php
                            $bonusService = app(\App\Services\BonusService::class);
                            $currentTier = $bonusService->getBonusTier($user->total_deposits);
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $user->name }}</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-lg font-bold text-green-600">${{ number_format($user->balance, 2) }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-lg font-medium text-blue-600">${{ number_format($user->total_deposits, 2) }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full
                                    @if($currentTier['name'] === 'none') bg-gray-100 text-gray-800
                                    @elseif($currentTier['name'] === '2%') bg-green-100 text-green-800
                                    @elseif($currentTier['name'] === '3%') bg-blue-100 text-blue-800
                                    @elseif($currentTier['name'] === '4%') bg-purple-100 text-purple-800
                                    @elseif($currentTier['name'] === '5%') bg-red-100 text-red-800
                                    @endif">
                                    {{ $currentTier['name'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <button wire:click="openAdjustmentModal({{ $user->id }})"
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                    Điều chỉnh
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                Không có user nào
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $users->links() }}
        </div>
    </div>

    <!-- Adjustment Modal -->
    @if($showAdjustmentModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">💰 Điều chỉnh số dư</h3>

                @if($selectedUserId)
                    @php $selectedUser = \App\Models\User::find($selectedUserId); @endphp
                    <div class="mb-4 p-3 bg-gray-100 dark:bg-gray-700 rounded">
                        <div class="font-medium">{{ $selectedUser->name }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Số dư hiện tại: ${{ number_format($selectedUser->balance, 2) }}</div>
                    </div>
                @endif

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Loại điều chỉnh</label>
                        <select wire:model="adjustmentType" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                            <option value="add">➕ Cộng tiền</option>
                            <option value="subtract">➖ Trừ tiền</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Số tiền ($)</label>
                        <input wire:model="adjustmentAmount" type="number" step="0.01" min="0.01" max="10000"
                               class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                               placeholder="0.00">
                        @error('adjustmentAmount') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Lý do</label>
                        <input wire:model="adjustmentReason" type="text" maxlength="255"
                               class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                               placeholder="Nhập lý do điều chỉnh...">
                        @error('adjustmentReason') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button wire:click="adjustBalance"
                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        ✅ Xác nhận
                    </button>
                    <button wire:click="closeAdjustmentModal"
                            class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                        ❌ Hủy
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
