<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Xác Thực Email - EZSTREAM</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800,900&display=swap" rel="stylesheet" />
    
    <!-- Scripts -->
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    
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
        
        .animate-pulse-slow {
            animation: pulse 3s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>

<body class="min-h-screen gradient-bg subtle-pattern">
    <!-- Floating Shapes -->
    <div class="floating-shape w-32 h-32 top-10 left-10" style="animation-delay: 0s;"></div>
    <div class="floating-shape w-24 h-24 top-1/4 right-20" style="animation-delay: 2s;"></div>
    <div class="floating-shape w-20 h-20 bottom-1/4 left-1/4" style="animation-delay: 4s;"></div>
    <div class="floating-shape w-16 h-16 bottom-10 right-10" style="animation-delay: 1s;"></div>

    <!-- Main Content -->
    <div class="relative z-10 min-h-screen flex items-center justify-center py-12 px-6">
        <div class="w-full max-w-md animate-fade-in">
            <!-- Verify Email Form -->
            <div class="glass-card rounded-3xl p-8 hover-lift">
                <!-- Header -->
                <div class="text-center mb-8">
                    <!-- Logo -->
                    <div class="w-20 h-20 gradient-accent rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-lg accent-glow">
                        <svg class="w-10 h-10 text-white animate-pulse-slow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    
                    <!-- Brand -->
                    <h1 class="text-3xl font-black text-gray-800 mb-2">
                        Xác Thực Email
                    </h1>
                    <p class="text-gray-600 text-lg leading-relaxed">
                        Cảm ơn bạn đã đăng ký! Vui lòng kiểm tra email và nhấp vào <span class="text-red-600 font-semibold">link xác thực</span> để kích hoạt tài khoản
                    </p>
                </div>

                <!-- Status Message -->
                <?php if(session('status') == 'verification-link-sent'): ?>
                    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-2xl">
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <p class="text-green-700 text-sm font-medium">
                                Link xác thực mới đã được gửi đến email của bạn!
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="space-y-4">
                    <!-- Resend Email Button -->
                    <form method="POST" action="<?php echo e(route('verification.send')); ?>">
                        <?php echo csrf_field(); ?>
                        <button type="submit" 
                                class="w-full gradient-accent text-white py-4 px-6 rounded-2xl font-black text-lg hover:shadow-lg transform hover:scale-[1.02] transition-all duration-300">
                            <span class="flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                GỬI LẠI EMAIL XÁC THỰC
                            </span>
                        </button>
                    </form>

                    <!-- Logout Button -->
                    <form method="POST" action="<?php echo e(route('logout')); ?>">
                        <?php echo csrf_field(); ?>
                        <button type="submit" 
                                class="w-full bg-gray-100 text-gray-700 border border-gray-200 py-4 px-6 rounded-2xl font-semibold text-lg hover:bg-gray-200 transition-all duration-300">
                            <span class="flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                ĐĂNG XUẤT
                            </span>
                        </button>
                    </form>
                </div>

                <!-- Help Info -->
                <div class="mt-8">
                    <div class="glass-card rounded-2xl p-4">
                        <div class="space-y-3 text-gray-500 text-sm">
                            <div class="flex items-start space-x-3">
                                <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="font-medium">Kiểm tra thư mục spam nếu không thấy email</span>
                            </div>
                            <div class="flex items-start space-x-3">
                                <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="font-medium">Link xác thực có hiệu lực trong 60 phút</span>
                            </div>
                            <div class="flex items-start space-x-3">
                                <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                </svg>
                                <span class="font-medium">Liên hệ hỗ trợ nếu gặp vấn đề: <br><span class="text-red-600 font-mono font-semibold">0971.125.260</span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php /**PATH D:\laragon\www\ezstream\resources\views\auth\verify-email.blade.php ENDPATH**/ ?>