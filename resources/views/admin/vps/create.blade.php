<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Create New VPS') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST" action="{{ route('vps.store') }}">
                        @csrf
                        <div class="space-y-6">
                            <div>
                                <x-input-label for="name" :value="__('Server Name')" />
                                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', 'New VPS')" required autofocus />
                                <x-input-error class="mt-2" :messages="$errors->get('name')" />
                            </div>

                            <div>
                                <x-input-label for="ip_address" :value="__('IP Address')" />
                                <x-text-input id="ip_address" name="ip_address" type="text" class="mt-1 block w-full" :value="old('ip_address')" required />
                                <x-input-error class="mt-2" :messages="$errors->get('ip_address')" />
                            </div>

                            <div>
                                <x-input-label for="ssh_user" :value="__('SSH User')" />
                                <x-text-input id="ssh_user" name="ssh_user" type="text" class="mt-1 block w-full" value="root" required />
                                <x-input-error class="mt-2" :messages="$errors->get('ssh_user')" />
                            </div>

                            <div>
                                <x-input-label for="ssh_password" :value="__('SSH Password')" />
                                <x-text-input id="ssh_password" name="ssh_password" type="password" class="mt-1 block w-full" required />
                                <x-input-error class="mt-2" :messages="$errors->get('ssh_password')" />
                            </div>

                            <div>
                                <x-input-label for="ssh_port" :value="__('SSH Port')" />
                                <x-text-input id="ssh_port" name="ssh_port" type="number" class="mt-1 block w-full" value="22" required />
                                <x-input-error class="mt-2" :messages="$errors->get('ssh_port')" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-8">
                            <a href="{{ route('admin.vps-servers') }}" class="text-sm text-gray-600 hover:underline dark:text-gray-400">
                                {{ __('Cancel') }}
                            </a>
                            <x-primary-button class="ml-4">
                                {{ __('Create & Provision') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout> 