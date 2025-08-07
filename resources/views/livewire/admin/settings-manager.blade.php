<div>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h2 class="text-2xl font-semibold mb-6">Application Settings</h2>

                    @if (session()->has('message'))
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                            <p>{{ session('message') }}</p>
                        </div>
                    @endif

                    <form wire:submit.prevent="save">
                        <div class="space-y-6">
                            <!-- Payment Settings -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                    Payment Gateway Settings
                                </h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Configure VietQR and Bank API settings.
                                </p>
                            </div>

                            <div>
                                <x-input-label for="payment_api_endpoint" value="Bank History API Endpoint" />
                                <x-text-input id="payment_api_endpoint" type="url" class="mt-1 block w-full" wire:model.defer="settings.payment_api_endpoint" />
                                <x-input-error :messages="$errors->get('settings.payment_api_endpoint')" class="mt-2" />
                            </div>
                            
                            <div>
                                <x-input-label for="payment_bank_id" value="Bank ID (e.g., 970436 for VCB)" />
                                <x-text-input id="payment_bank_id" type="text" class="mt-1 block w-full" wire:model.defer="settings.payment_bank_id" />
                                <x-input-error :messages="$errors->get('settings.payment_bank_id')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="payment_account_no" value="Bank Account Number" />
                                <x-text-input id="payment_account_no" type="text" class="mt-1 block w-full" wire:model.defer="settings.payment_account_no" />
                                <x-input-error :messages="$errors->get('settings.payment_account_no')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="payment_account_name" value="Bank Account Name" />
                                <x-text-input id="payment_account_name" type="text" class="mt-1 block w-full" wire:model.defer="settings.payment_account_name" />
                                <x-input-error :messages="$errors->get('settings.payment_account_name')" class="mt-2" />
                            </div>

                            <!-- File Storage Settings -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-6 mt-8">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                    💾 File Storage Mode
                                </h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Choose how VPS downloads files for cost optimization.
                                </p>
                            </div>

                            <!-- Storage Mode Selection -->
                            <div class="space-y-3">
                                <!-- Stream Library Storage (Only Option) -->

                                <label class="flex items-center p-4 bg-orange-50 dark:bg-orange-900/20 rounded-lg border border-orange-200 dark:border-orange-700 cursor-pointer hover:bg-orange-100 dark:hover:bg-orange-900/30 transition-colors">
                                    <input type="radio" wire:model.defer="settings.storage_mode" value="stream_library" checked
                                           class="h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300">
                                    <div class="ml-3 flex-1">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 mr-2 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                            </svg>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">BunnyCDN Stream Library</span>
                                            <span class="ml-2 px-2 py-1 text-xs bg-orange-100 text-orange-800 rounded-full">Only Option</span>
                                        </div>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                            Upload to Stream Library for HLS streaming. Optimized for SRS server with adaptive bitrate and auto-delete functionality.
                                        </p>
                                    </div>
                                </label>

                                <!-- Info about removed storage modes -->
                                <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700">
                                    <div class="flex items-start">
                                        <svg class="w-5 h-5 mr-2 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <div>
                                            <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200">Simplified Storage System</h4>
                                            <p class="text-xs text-blue-600 dark:text-blue-300 mt-1">
                                                EZStream now exclusively uses BunnyCDN Stream Library for optimal streaming performance,
                                                simplified management, and enhanced auto-delete functionality. Previous storage modes
                                                (Server, CDN, Hybrid, Auto) have been removed to focus on the best streaming experience.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- System Info -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-6 mt-8">
                                <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700">
                                    <div class="flex items-start">
                                        <svg class="w-6 h-6 mr-3 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                        <div>
                                            <h3 class="text-lg font-medium text-blue-900 dark:text-blue-100">🎬 EZStream Engine</h3>
                                            <p class="text-sm text-blue-700 dark:text-blue-300 mt-2">
                                                EZStream sử dụng <strong>FFmpeg Direct Streaming</strong> và <strong>BunnyCDN Stream Library</strong> để đảm bảo hiệu suất streaming tối ưu:
                                            </p>
                                            <ul class="text-sm text-blue-700 dark:text-blue-300 mt-2 space-y-1">
                                                <li>✅ FFmpeg direct RTMP streaming - ổn định cao</li>
                                                <li>✅ Streaming đa nền tảng đồng thời</li>
                                                <li>✅ Quản lý playlist động và loop 24/7</li>
                                                <li>✅ Auto-delete video sau khi stream</li>
                                                <li>✅ Giám sát real-time và logging chi tiết</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 mt-8">
                            <x-primary-button type="submit">{{ __('Save & Refresh Agents') }}</x-primary-button>
                            <x-secondary-button type="button" wire:click="refreshAgentSettings">
                                🔄 Refresh Agents Only
                            </x-secondary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
