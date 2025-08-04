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
                                <!-- Server Storage -->
                                <label class="flex items-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-700 cursor-pointer hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors">
                                    <input type="radio" wire:model.defer="settings.storage_mode" value="server"
                                           class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300">
                                    <div class="ml-3 flex-1">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                                            </svg>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Server Storage</span>
                                            <span class="ml-2 px-2 py-1 text-xs bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100 rounded-full">Recommended</span>
                                        </div>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                            VPS download from server directly. <strong>Saves 80% bandwidth cost</strong> ($10/month vs $50/month)
                                        </p>
                                    </div>
                                </label>

                                <!-- CDN Storage -->
                                <label class="flex items-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700 cursor-pointer hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors">
                                    <input type="radio" wire:model.defer="settings.storage_mode" value="cdn"
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                    <div class="ml-3 flex-1">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/>
                                            </svg>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Bunny CDN</span>
                                        </div>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                            VPS download from CDN. Faster global delivery but higher bandwidth cost.
                                        </p>
                                    </div>
                                </label>

                                <!-- Hybrid Storage -->
                                <label class="flex items-center p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-700 cursor-pointer hover:bg-purple-100 dark:hover:bg-purple-900/30 transition-colors">
                                    <input type="radio" wire:model.defer="settings.storage_mode" value="hybrid"
                                           class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300">
                                    <div class="ml-3 flex-1">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Hybrid Mode</span>
                                        </div>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                            Server first, fallback to CDN if server fails. Best reliability with cost savings.
                                        </p>
                                    </div>
                                </label>

                                <!-- Stream Library Storage -->
                                <label class="flex items-center p-4 bg-orange-50 dark:bg-orange-900/20 rounded-lg border border-orange-200 dark:border-orange-700 cursor-pointer hover:bg-orange-100 dark:hover:bg-orange-900/30 transition-colors">
                                    <input type="radio" wire:model.defer="settings.storage_mode" value="stream_library"
                                           class="h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300">
                                    <div class="ml-3 flex-1">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 mr-2 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                            </svg>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">BunnyCDN Stream Library</span>
                                            <span class="ml-2 px-2 py-1 text-xs bg-orange-100 text-orange-800 rounded-full">SRS Only</span>
                                        </div>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                            Upload to Stream Library for HLS streaming. Optimized for SRS server with adaptive bitrate.
                                        </p>
                                    </div>
                                </label>

                                <!-- Auto Storage -->
                                <label class="flex items-center p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg border border-indigo-200 dark:border-indigo-700 cursor-pointer hover:bg-indigo-100 dark:hover:bg-indigo-900/30 transition-colors">
                                    <input type="radio" wire:model.defer="settings.storage_mode" value="auto"
                                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                                    <div class="ml-3 flex-1">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                            </svg>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Auto Select</span>
                                            <span class="ml-2 px-2 py-1 text-xs bg-indigo-100 text-indigo-800 rounded-full">Recommended</span>
                                        </div>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                            Automatically choose: Stream Library for SRS streaming, CDN for FFmpeg streaming.
                                        </p>
                                    </div>
                                </label>
                            </div>

                            <!-- Streaming Method Settings -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-6 mt-8">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                    🎬 Streaming Method
                                </h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Choose streaming method and processing mode for optimal performance.
                                </p>
                            </div>

                            <!-- Streaming Method Selection -->
                            <div class="space-y-3">
                                <!-- SRS Streaming (NEW) -->
                                <label class="flex items-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700 cursor-pointer hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors">
                                    <input type="radio" wire:model.defer="settings.streaming_method" value="srs"
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                    <div class="ml-3 flex-1">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                            </svg>
                                            <span class="font-medium text-blue-900 dark:text-blue-100">SRS Server Streaming</span>
                                            <span class="ml-2 px-2 py-1 text-xs bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-200 rounded-full">NEW</span>
                                        </div>
                                        <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                                            ✅ Superior stability and reconnect handling<br>
                                            ✅ Multi-destination streaming support<br>
                                            ✅ Real-time monitoring and statistics<br>
                                            ✅ Auto-fallback to FFmpeg if needed
                                        </p>
                                    </div>
                                </label>

                                <!-- FFmpeg Encoding Mode (High CPU) -->
                                <label class="flex items-center p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-700 cursor-pointer hover:bg-yellow-100 dark:hover:bg-yellow-900/30 transition-colors">
                                    <input type="radio" wire:model.defer="settings.streaming_method" value="ffmpeg_encoding"
                                           class="h-4 w-4 text-yellow-600 focus:ring-yellow-500 border-gray-300">
                                    <div class="ml-3 flex-1">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 mr-2 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 15.5c-.77.833.192 2.5 1.732 2.5z"/>
                                            </svg>
                                            <span class="font-medium text-yellow-900 dark:text-yellow-100">FFmpeg Encoding Mode</span>
                                            <span class="ml-2 px-2 py-1 text-xs bg-yellow-100 dark:bg-yellow-800 text-yellow-800 dark:text-yellow-200 rounded-full">High CPU</span>
                                        </div>
                                        <p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">
                                            ✅ Stable for long streams (24+ hours)<br>
                                            ✅ Regenerates timestamps, prevents DTS errors<br>
                                            ❌ High CPU usage, only 1 stream per weak VPS
                                        </p>
                                    </div>
                                </label>

                                <!-- FFmpeg Copy Mode with Fast Restart -->
                                <label class="flex items-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-700 cursor-pointer hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors">
                                    <input type="radio" wire:model.defer="settings.streaming_method" value="ffmpeg_copy"
                                           class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300">
                                    <div class="ml-3 flex-1">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                            </svg>
                                            <span class="font-medium text-green-900 dark:text-green-100">FFmpeg Copy Mode + Fast Restart</span>
                                            <span class="ml-2 px-2 py-1 text-xs bg-green-100 dark:bg-green-800 text-green-800 dark:text-green-200 rounded-full">Legacy</span>
                                        </div>
                                        <p class="text-sm text-green-700 dark:text-green-300 mt-1">
                                            ✅ Fast processing, preserves original quality<br>
                                            ✅ Auto-detects DTS errors and restarts instantly<br>
                                            ✅ Low CPU usage, suitable for weak VPS
                                        </p>
                                    </div>
                                </label>
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
