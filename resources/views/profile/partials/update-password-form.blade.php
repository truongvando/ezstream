<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            Đổi mật khẩu
        </h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Đảm bảo tài khoản của bạn sử dụng mật khẩu dài và ngẫu nhiên để bảo mật.
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <div class="grid grid-cols-1 gap-6">
            <div>
                <x-input-label for="update_password_current_password" value="Mật khẩu hiện tại" />
                <x-text-input id="update_password_current_password" name="current_password" type="password" 
                    class="mt-1 block w-full" autocomplete="current-password" 
                    placeholder="Nhập mật khẩu hiện tại" />
                <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="update_password_password" value="Mật khẩu mới" />
                <x-text-input id="update_password_password" name="password" type="password" 
                    class="mt-1 block w-full" autocomplete="new-password" 
                    placeholder="Nhập mật khẩu mới (tối thiểu 8 ký tự)" />
                <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
                
                <!-- Password Strength Indicator -->
                <div class="mt-2" x-data="{ password: '' }" x-init="
                    $watch('password', value => {
                        const input = document.getElementById('update_password_password');
                        input.addEventListener('input', (e) => {
                            password = e.target.value;
                        });
                    })
                ">
                    <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                        <div class="flex items-center space-x-2">
                            <span>Độ mạnh:</span>
                            <div class="flex space-x-1">
                                <div class="w-2 h-2 rounded-full" :class="password.length >= 4 ? 'bg-red-500' : 'bg-gray-300'"></div>
                                <div class="w-2 h-2 rounded-full" :class="password.length >= 6 ? 'bg-yellow-500' : 'bg-gray-300'"></div>
                                <div class="w-2 h-2 rounded-full" :class="password.length >= 8 && /[A-Z]/.test(password) ? 'bg-green-500' : 'bg-gray-300'"></div>
                                <div class="w-2 h-2 rounded-full" :class="password.length >= 8 && /[A-Z]/.test(password) && /[0-9]/.test(password) ? 'bg-green-600' : 'bg-gray-300'"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <x-input-label for="update_password_password_confirmation" value="Xác nhận mật khẩu mới" />
                <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" 
                    class="mt-1 block w-full" autocomplete="new-password" 
                    placeholder="Nhập lại mật khẩu mới" />
                <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        <!-- Password Requirements -->
        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2 flex items-center">
                <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Yêu cầu mật khẩu
            </h4>
            <ul class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                <li class="flex items-center space-x-2">
                    <svg class="w-3 h-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Tối thiểu 8 ký tự</span>
                </li>
                <li class="flex items-center space-x-2">
                    <svg class="w-3 h-3 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Nên có chữ hoa, chữ thường và số</span>
                </li>
                <li class="flex items-center space-x-2">
                    <svg class="w-3 h-3 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Tránh sử dụng thông tin cá nhân</span>
                </li>
            </ul>
        </div>

        <div class="flex items-center gap-4 pt-4">
            <x-primary-button class="flex items-center space-x-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <span>Cập nhật mật khẩu</span>
            </x-primary-button>

            @if (session('status') === 'password-updated')
                <p class="text-sm text-green-600 dark:text-green-400 flex items-center"
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 3000)">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Mật khẩu đã được cập nhật!
                </p>
            @endif
        </div>
    </form>
</section>
