<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng Ký - VPS Live Control</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800,900&display=swap" rel="stylesheet" />
    
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        .hero-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .glass-effect {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen flex items-center justify-center relative overflow-hidden py-12">
    <!-- Background -->
    <div class="absolute inset-0 hero-gradient"></div>
    <div class="absolute inset-0 bg-black/20"></div>
    
    <!-- Floating Elements -->
    <div class="absolute top-20 left-10 w-20 h-20 bg-white/10 rounded-full animate-float"></div>
    <div class="absolute top-40 right-20 w-32 h-32 bg-white/5 rounded-full animate-float" style="animation-delay: 2s;"></div>
    <div class="absolute bottom-20 left-1/4 w-16 h-16 bg-white/10 rounded-full animate-float" style="animation-delay: 4s;"></div>
    
    <!-- Back to Home -->
    <div class="absolute top-6 left-6 z-10">
        <a href="{{ route('welcome') }}" class="flex items-center text-white/80 hover:text-white transition-colors">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Về Trang Chủ
        </a>
    </div>

    <!-- Register Form -->
    <div class="relative z-10 w-full max-w-md mx-auto px-6">
        <div class="glass-effect rounded-2xl p-8 shadow-2xl">
            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                    <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-white mb-2">Tạo Tài Khoản Mới</h1>
                <p class="text-white/80">Bắt đầu hành trình streaming cùng chúng tôi</p>
            </div>

            <form method="POST" action="{{ route('register') }}" class="space-y-6">
                @csrf

                <!-- Name -->
                <div>
                    <x-input-label for="name" :value="__('Họ và tên')" class="text-white font-medium mb-2" />
                    <x-text-input id="name" 
                                  class="block mt-1 w-full px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-white/60 focus:border-white/40 focus:ring-2 focus:ring-white/20 transition-all backdrop-blur-sm" 
                                  type="text" 
                                  name="name" 
                                  :value="old('name')" 
                                  required 
                                  autofocus 
                                  autocomplete="name"
                                  placeholder="Nhập họ và tên của bạn" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2 text-red-300" />
                </div>

                <!-- Email Address -->
                <div>
                    <x-input-label for="email" :value="__('Email')" class="text-white font-medium mb-2" />
                    <x-text-input id="email" 
                                  class="block mt-1 w-full px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-white/60 focus:border-white/40 focus:ring-2 focus:ring-white/20 transition-all backdrop-blur-sm" 
                                  type="email" 
                                  name="email" 
                                  :value="old('email')" 
                                  required 
                                  autocomplete="username"
                                  placeholder="Nhập email của bạn" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-300" />
                </div>

                <!-- Password -->
                <div>
                    <x-input-label for="password" :value="__('Mật khẩu')" class="text-white font-medium mb-2" />
                    <x-text-input id="password" 
                                  class="block mt-1 w-full px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-white/60 focus:border-white/40 focus:ring-2 focus:ring-white/20 transition-all backdrop-blur-sm"
                                  type="password"
                                  name="password"
                                  required 
                                  autocomplete="new-password"
                                  placeholder="Nhập mật khẩu (tối thiểu 8 ký tự)" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2 text-red-300" />
                </div>

                <!-- Confirm Password -->
                <div>
                    <x-input-label for="password_confirmation" :value="__('Xác nhận mật khẩu')" class="text-white font-medium mb-2" />
                    <x-text-input id="password_confirmation" 
                                  class="block mt-1 w-full px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-white/60 focus:border-white/40 focus:ring-2 focus:ring-white/20 transition-all backdrop-blur-sm"
                                  type="password"
                                  name="password_confirmation" 
                                  required 
                                  autocomplete="new-password"
                                  placeholder="Nhập lại mật khẩu" />
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2 text-red-300" />
                </div>

                <!-- Terms Agreement -->
                <div class="flex items-start">
                    <input type="checkbox" id="terms" class="rounded border-white/20 bg-white/10 text-blue-600 shadow-sm focus:ring-white/20 mt-1" required>
                    <label for="terms" class="ml-3 text-sm text-white/80 leading-relaxed">
                        Tôi đồng ý với 
                        <a href="#" class="text-white hover:underline">Điều khoản dịch vụ</a> 
                        và 
                        <a href="#" class="text-white hover:underline">Chính sách bảo mật</a> 
                        của VPS Live Control
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full bg-white text-blue-600 py-3 px-4 rounded-xl font-bold text-lg hover:bg-gray-50 transform hover:scale-[1.02] transition-all duration-200 shadow-lg">
                    {{ __('Tạo Tài Khoản') }}
                </button>
            </form>

            <!-- Login Link -->
            <div class="mt-8 text-center">
                <p class="text-white/80">
                    Đã có tài khoản? 
                    <a href="{{ route('login') }}" class="text-white font-semibold hover:underline">
                        Đăng nhập ngay
                    </a>
                </p>
            </div>
        </div>
    </div>
</body>
</html> 