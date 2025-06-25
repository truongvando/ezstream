<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        <!-- Flash Messages -->
        @if (session()->has('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p>{{ session('success') }}</p>
            </div>
        @endif
        
        @if (session()->has('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p>{{ session('error') }}</p>
            </div>
        @endif
        
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ $currentPackageId ? 'Nâng Cấp Gói Dịch Vụ' : 'Chọn Gói Dịch Vụ' }}
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ $currentPackageId ? 'Chọn một gói cao hơn để nâng cấp và tận hưởng nhiều tính năng hơn.' : 'Chọn gói phù hợp nhất với nhu cầu của bạn để bắt đầu.' }}
                </p>

                @if ($hasPendingSubscription)
                    <div class="mt-4 p-4 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-700">
                        <p class="font-bold">Lưu ý</p>
                        <p>Bạn đang có một gói chờ thanh toán. Vui lòng hoàn tất hoặc hủy giao dịch đó trước khi mua gói mới.</p>
                    </div>
                @endif
                
                <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach ($packages as $package)
                        <div @class([
                            'relative p-6 rounded-lg border flex flex-col',
                            'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700' => $package->id != $currentPackageId,
                            'border-2 border-blue-500 bg-blue-50 dark:bg-blue-900/50' => $package->id == $currentPackageId,
                        ])>
                            @if($package->id == $currentPackageId)
                                <div class="absolute -top-3 right-3 bg-blue-500 text-white text-xs font-bold px-3 py-1 rounded-full">Gói Hiện Tại</div>
                            @endif
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ $package->name }}</h3>
                            <p class="mt-2 text-3xl font-black text-gray-900 dark:text-white">
                                {{ number_format($package->price, 0, ',', '.') }} <span class="text-base font-medium text-gray-500 dark:text-gray-400">VND/tháng</span>
                            </p>
                            <p class="mt-4 text-sm text-gray-600 dark:text-gray-400 flex-grow">{{ $package->description }}</p>
                            
                            <ul class="mt-6 space-y-2 text-sm text-gray-700 dark:text-gray-300">
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                    Tối đa <strong>{{ $package->max_streams }}</strong> luồng
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                    <strong>{{ $package->storage_limit ? round($package->storage_limit / (1024*1024*1024)) : 'N/A' }} GB</strong> dung lượng
                                </li>
                                @if($package->features)
                                    @foreach(json_decode($package->features) as $feature)
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                        {{ $feature }}
                                    </li>
                                    @endforeach
                                @endif
                            </ul>

                            <div class="mt-8">
                                @if($package->id == $currentPackageId)
                                    <button disabled class="w-full text-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-400 cursor-not-allowed">
                                        Đang Sử Dụng
                                    </button>
                                @elseif($currentPackageId && $package->price > \App\Models\ServicePackage::find($currentPackageId)->price)
                                    <button wire:click="selectPackage({{ $package->id }})" wire:loading.attr="disabled" @if($hasPendingSubscription) disabled @endif
                                            class="w-full text-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 disabled:bg-gray-400">
                                        Nâng Cấp
                                    </button>
                                @elseif(!$currentPackageId)
                                    <button wire:click="selectPackage({{ $package->id }})" wire:loading.attr="disabled" @if($hasPendingSubscription) disabled @endif
                                            class="w-full text-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400">
                                        Chọn Gói Này
                                    </button>
                                @else
                                    <button disabled class="w-full text-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-400 cursor-not-allowed">
                                        Không thể hạ cấp
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
