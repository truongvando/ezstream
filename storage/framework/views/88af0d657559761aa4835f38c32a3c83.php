<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quên Mật Khẩu - EZSTREAM</title>
    
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
        <a href="<?php echo e(route('login')); ?>" class="flex items-center text-gray-600 hover:text-red-600 transition-all duration-300 hover-lift">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            <span class="font-medium">Về Đăng Nhập</span>
        </a>
    </div>

    <!-- Main Content -->
    <div class="relative z-10 min-h-screen flex items-center justify-center py-12 px-6">
        <div class="w-full max-w-md animate-fade-in">
            <!-- Forgot Password Form -->
            <div class="glass-card rounded-3xl p-8 hover-lift">
                <!-- Header -->
                <div class="text-center mb-8">
                    <!-- Logo -->
                    <div class="w-20 h-20 gradient-accent rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-lg accent-glow">
                        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                    </div>
                    
                    <!-- Brand -->
                    <h1 class="text-3xl font-black text-gray-800 mb-2">
                        Quên Mật Khẩu?
                    </h1>
                    <p class="text-gray-600 text-lg leading-relaxed">
                        Không sao! Nhập email của bạn và chúng tôi sẽ gửi <span class="text-red-600 font-semibold">link đặt lại mật khẩu</span>
                    </p>
                </div>

                <!-- Session Status -->
                <?php if(session('status')): ?>
                    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-2xl">
                        <p class="text-green-700 text-sm font-medium"><?php echo e(session('status')); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Forgot Password Form -->
                <form method="POST" action="<?php echo e(route('password.email')); ?>" class="space-y-6">
                    <?php echo csrf_field(); ?>

                    <!-- Email -->
                    <div class="space-y-2">
                        <label for="email" class="block text-gray-700 font-semibold text-sm">
                            Email
                        </label>
                        <div class="relative">
                            <input id="email" 
                                   type="email" 
                                   name="email" 
                                   value="<?php echo e(old('email')); ?>" 
                                   required 
                                   autofocus 
                                   placeholder="Nhập email của bạn"
                                   class="w-full px-4 py-4 bg-white border border-gray-200 rounded-2xl text-gray-800 placeholder-gray-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/20 transition-all text-lg" />
                            <div class="absolute inset-y-0 right-0 flex items-center pr-4">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                                </svg>
                            </div>
                        </div>
                        <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <p class="text-red-500 text-sm mt-1"><?php echo e($message); ?></p>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" 
                            class="w-full gradient-accent text-white py-4 px-6 rounded-2xl font-black text-lg hover:shadow-lg transform hover:scale-[1.02] transition-all duration-300">
                        <span class="flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            GỬI LINK ĐẶT LẠI
                        </span>
                    </button>
                </form>

                <!-- Back to Login -->
                <div class="mt-8 text-center">
                    <p class="text-gray-600">
                        Nhớ lại mật khẩu? 
                        <a href="<?php echo e(route('login')); ?>" class="text-red-600 font-bold hover:text-red-700 transition-all">
                            Đăng nhập ngay
                        </a>
                    </p>
                </div>
            </div>

            <!-- Help Info -->
            <div class="mt-8 text-center">
                <div class="glass-card rounded-2xl p-4">
                    <div class="flex items-center justify-center space-x-2 text-gray-500 text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="font-medium">Kiểm tra thư mục spam nếu không thấy email</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php /**PATH D:\laragon\www\ezstream\resources\views\auth\forgot-password.blade.php ENDPATH**/ ?>