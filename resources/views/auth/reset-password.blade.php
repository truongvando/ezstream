<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đặt Lại Mật Khẩu - StreamVPS Pro</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800,900&display=swap" rel="stylesheet" />
    
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        .gradient-bg {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 50%, #e2e8f0 100%);
        }
        
        .gradient-accent {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        }
        
        .glass-card {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
        }
        
        .floating-shape {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.1), rgba(185, 28, 28, 0.05));
            animation: float 8s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) scale(1); }
            50% { transform: translateY(-30px) scale(1.1); }
        }
        
        .accent-glow {
            box-shadow: 0 0 30px rgba(220, 38, 38, 0.3);
        }
        
        .hover-lift {
            transition: all 0.3s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .animate-fade-in {
            animation: fadeIn 0.8s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .subtle-pattern {
            background-image: 
                radial-gradient(circle at 20px 20px, rgba(220, 38, 38, 0.03) 1px, transparent 0),
                radial-gradient(circle at 60px 60px, rgba(220, 38, 38, 0.03) 1px, transparent 0);
            background-size: 80px 80px;
        }
    </style>
</head>

<body class="min-h-screen gradient-bg subtle-pattern">
    <!-- Floating Shapes -->
    <div class="floating-shape w-32 h-32 top-10 left-10" style="animation-delay: 0s;"></div>
    <div class="floating-shape w-24 h-24 top-1/4 right-20" style="animation-delay: 2s;"></div>
    <div class="floating-shape w-20 h-20 bottom-1/4 left-1/4" style="animation-delay: 4s;"></div>
    <div class="floating-shape w-16 h-16 bottom-10 right-10" style="animation-delay: 1s;"></div>
    
    <!-- Back to Login -->
    <div class="absolute top-6 left-6 z-20">
        <a href="{{ route('login') }}" class="flex items-center text-gray-600 hover:text-red-600 transition-all duration-300 hover-lift">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            <span class="font-medium">Về Đăng Nhập</span>
        </a>
    </div>

    <!-- Main Content -->
    <div class="relative z-10 min-h-screen flex items-center justify-center py-12 px-6">
        <div class="w-full max-w-md animate-fade-in">
            <!-- Reset Password Form -->
            <div class="glass-card rounded-3xl p-8 hover-lift">
                <!-- Header -->
                <div class="text-center mb-8">
                    <!-- Logo -->
                    <div class="w-20 h-20 gradient-accent rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-lg accent-glow">
                        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    
                    <!-- Brand -->
                    <h1 class="text-3xl font-black text-gray-800 mb-2">
                        Đặt Lại Mật Khẩu
                    </h1>
                    <p class="text-gray-600 text-lg leading-relaxed">
                        Tạo mật khẩu mới cho tài khoản <span class="text-red-600 font-semibold">StreamVPS Pro</span> của bạn
                    </p>
                </div>

                <!-- Reset Password Form -->
                <form method="POST" action="{{ route('password.store') }}" class="space-y-6">
                    @csrf
                    
                    <!-- Password Reset Token -->
                    <input type="hidden" name="token" value="{{ $request->route('token') }}">

                    <!-- Email -->
                    <div class="space-y-2">
                        <label for="email" class="block text-gray-700 font-semibold text-sm">
                            Email
                        </label>
                        <div class="relative">
                            <input id="email" 
                                   type="email" 
                                   name="email" 
                                   value="{{ old('email', $request->email) }}" 
                                   required 
                                   autofocus 
                                   autocomplete="username"
                                   placeholder="Email của bạn"
                                   class="w-full px-4 py-4 bg-white border border-gray-200 rounded-2xl text-gray-800 placeholder-gray-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/20 transition-all text-lg" />
                            <div class="absolute inset-y-0 right-0 flex items-center pr-4">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                                </svg>
                            </div>
                        </div>
                        @error('email')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div class="space-y-2">
                        <label for="password" class="block text-gray-700 font-semibold text-sm">
                            Mật khẩu mới
                        </label>
                        <div class="relative">
                            <input id="password" 
                                   type="password" 
                                   name="password" 
                                   required 
                                   autocomplete="new-password"
                                   placeholder="Nhập mật khẩu mới (tối thiểu 8 ký tự)"
                                   class="w-full px-4 py-4 bg-white border border-gray-200 rounded-2xl text-gray-800 placeholder-gray-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/20 transition-all text-lg" />
                            <div class="absolute inset-y-0 right-0 flex items-center pr-4">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                        </div>
                        @error('password')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Confirm Password -->
                    <div class="space-y-2">
                        <label for="password_confirmation" class="block text-gray-700 font-semibold text-sm">
                            Xác nhận mật khẩu mới
                        </label>
                        <div class="relative">
                            <input id="password_confirmation" 
                                   type="password" 
                                   name="password_confirmation" 
                                   required 
                                   autocomplete="new-password"
                                   placeholder="Nhập lại mật khẩu mới"
                                   class="w-full px-4 py-4 bg-white border border-gray-200 rounded-2xl text-gray-800 placeholder-gray-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/20 transition-all text-lg" />
                            <div class="absolute inset-y-0 right-0 flex items-center pr-4">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </div>
                        @error('password_confirmation')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" 
                            class="w-full gradient-accent text-white py-4 px-6 rounded-2xl font-black text-lg hover:shadow-lg transform hover:scale-[1.02] transition-all duration-300">
                        <span class="flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            ĐẶT LẠI MẬT KHẨU
                        </span>
                    </button>
                </form>

                <!-- Back to Login -->
                <div class="mt-8 text-center">
                    <p class="text-gray-600">
                        Đã nhớ lại mật khẩu? 
                        <a href="{{ route('login') }}" class="text-red-600 font-bold hover:text-red-700 transition-all">
                            Đăng nhập ngay
                        </a>
                    </p>
                </div>
            </div>

            <!-- Security Info -->
            <div class="mt-8 text-center">
                <div class="glass-card rounded-2xl p-4">
                    <div class="flex items-center justify-center space-x-2 text-gray-500 text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        <span class="font-medium">Mật khẩu sẽ được mã hóa an toàn</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
