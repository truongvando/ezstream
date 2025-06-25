<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-red-900 dark:text-red-100">
            Xóa tài khoản
        </h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Khi tài khoản bị xóa, tất cả dữ liệu và tài nguyên sẽ bị xóa vĩnh viễn. Trước khi xóa, hãy tải xuống dữ liệu bạn muốn giữ lại.
        </p>
    </header>

    <!-- Warning Box -->
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg p-4">
        <div class="flex items-start space-x-3">
            <svg class="w-6 h-6 text-red-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
            </svg>
            <div>
                <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Hành động không thể hoàn tác</h3>
                <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                    <p>Việc xóa tài khoản sẽ:</p>
                    <ul class="list-disc list-inside mt-2 space-y-1">
                        <li>Xóa vĩnh viễn tất cả streams và cấu hình</li>
                        <li>Xóa tất cả files đã upload</li>
                        <li>Hủy các gói dịch vụ đang hoạt động</li>
                        <li>Xóa lịch sử giao dịch và thanh toán</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
        class="flex items-center space-x-2"
    >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
        </svg>
        <span>Xóa tài khoản vĩnh viễn</span>
    </x-danger-button>

    <x-modal-v2 name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <div class="flex items-center space-x-4 mb-6">
                <div class="w-12 h-12 bg-red-100 dark:bg-red-900/20 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                        Xác nhận xóa tài khoản
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Hành động này không thể hoàn tác
                    </p>
                </div>
            </div>

            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg p-4 mb-6">
                <p class="text-sm text-red-800 dark:text-red-200">
                    <strong>Bạn có chắc chắn muốn xóa tài khoản?</strong>
                </p>
                <p class="text-sm text-red-700 dark:text-red-300 mt-2">
                    Tất cả dữ liệu, streams, files và cài đặt của bạn sẽ bị xóa vĩnh viễn. 
                    Vui lòng nhập mật khẩu để xác nhận bạn thực sự muốn xóa tài khoản.
                </p>
            </div>

            <div class="mb-6">
                <x-input-label for="password" value="Nhập mật khẩu để xác nhận" />
                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-full"
                    placeholder="Mật khẩu của bạn"
                    required
                />
                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="flex justify-end space-x-3">
                <x-secondary-button x-on:click="$dispatch('close')" class="flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    <span>Hủy bỏ</span>
                </x-secondary-button>

                <x-danger-button class="flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    <span>Xóa tài khoản vĩnh viễn</span>
                </x-danger-button>
            </div>
        </form>
    </x-modal-v2>
</section>
