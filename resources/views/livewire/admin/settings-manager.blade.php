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
                        </div>

                        <div class="flex items-center gap-4 mt-8">
                            <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
