<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            Thông tin cá nhân
        </h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Cập nhật thông tin tài khoản và địa chỉ email của bạn.
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <x-input-label for="name" value="Họ và tên" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="email" value="Địa chỉ Email" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <div class="mt-3 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg">
                        <p class="text-sm text-yellow-800 dark:text-yellow-200 flex items-center">
                            <svg class="w-4 h-4 mr-2 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                            Email của bạn chưa được xác thực.
                        </p>
                        
                        <button form="send-verification" class="mt-2 text-sm text-yellow-700 dark:text-yellow-300 underline hover:no-underline font-medium">
                            Nhấn vào đây để gửi lại email xác thực.
                        </button>

                        @if (session('status') === 'verification-link-sent')
                            <p class="mt-2 font-medium text-sm text-green-600 dark:text-green-400">
                                ✅ Link xác thực mới đã được gửi tới email của bạn.
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <!-- Telegram Notifications Section -->
        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
            <div class="flex items-center space-x-2 mb-4">
                <svg class="w-5 h-5 text-blue-500" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 0C5.374 0 0 5.373 0 12s5.374 12 12 12 12-5.373 12-12S18.626 0 12 0zm5.568 8.16c-.169 1.858-.896 6.728-.896 6.728-.302 1.4-1.123 1.645-2.279 1.023l-3.005-2.49-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.374-.12l-6.873 4.329-2.96-.924c-.643-.203-.657-.643.135-.953l11.566-4.458c.538-.196 1.006.129.856.922z"/>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Thông báo Telegram
                </h3>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Nhập Bot Token và Chat ID của Telegram để nhận thông báo về streams và hệ thống.
            </p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <x-input-label for="telegram_bot_token" value="Telegram Bot Token" />
                    <x-text-input id="telegram_bot_token" name="telegram_bot_token" type="text" 
                        class="mt-1 block w-full" 
                        :value="old('telegram_bot_token', $user->telegram_bot_token)" 
                        autocomplete="off"
                        placeholder="1234567890:ABCDEFghijklmnop..." />
                    <x-input-error class="mt-2" :messages="$errors->get('telegram_bot_token')" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Nhận từ @BotFather trên Telegram
                    </p>
                </div>

                <div>
                    <x-input-label for="telegram_chat_id" value="Telegram Chat ID" />
                    <x-text-input id="telegram_chat_id" name="telegram_chat_id" type="text" 
                        class="mt-1 block w-full" 
                        :value="old('telegram_chat_id', $user->telegram_chat_id)" 
                        autocomplete="off"
                        placeholder="123456789" />
                    <x-input-error class="mt-2" :messages="$errors->get('telegram_chat_id')" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        ID chat hoặc kênh để nhận thông báo
                    </p>
                </div>
            </div>

            <!-- Telegram Setup Help -->
            <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg">
                <h4 class="text-sm font-medium text-blue-900 dark:text-blue-200 mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Hướng dẫn thiết lập Telegram
                </h4>
                <ol class="text-xs text-blue-800 dark:text-blue-300 space-y-1">
                    <li>1. Tìm @BotFather trên Telegram và tạo bot mới với lệnh /newbot</li>
                    <li>2. Sao chép Bot Token mà @BotFather cung cấp</li>
                    <li>3. Tìm @userinfobot để lấy Chat ID của bạn</li>
                    <li>4. Điền thông tin vào các ô trên và lưu</li>
                </ol>
            </div>
        </div>

        <div class="flex items-center gap-4 pt-4">
            <x-primary-button class="flex items-center space-x-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Lưu thay đổi</span>
            </x-primary-button>

            @if (session('status') === 'profile-updated')
                <p class="text-sm text-green-600 dark:text-green-400 flex items-center"
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 3000)">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Đã lưu thành công!
                </p>
            @endif
        </div>
    </form>
</section>
