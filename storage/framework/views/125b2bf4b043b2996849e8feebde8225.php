<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EZSTREAM - Nền tảng Live Stream Tự động #1 Việt Nam</title>
    <meta name="description" content="Hệ thống quản lý live stream tự động trên mạng lưới VPS toàn cầu. Auto-recovery, streaming 24/7, monitoring real-time. Hỗ trợ YouTube, Facebook, TikTok.">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#FF0000',
                        'primary-dark': '#CC0000',
                        'primary-light': '#FF3333',
                        accent: '#FF6B6B',
                        'gray-850': '#1f2937',
                        'gray-950': '#0f172a'
                    },
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                        'mono': ['JetBrains Mono', 'monospace']
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'glow': 'glow 2s ease-in-out infinite alternate',
                        'slide-up': 'slideUp 0.8s ease-out',
                        'fade-in': 'fadeIn 1s ease-out',
                        'scale-in': 'scaleIn 0.6s ease-out',
                    }
                }
            }
        }
    </script>
    
    <!-- Custom CSS -->
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        /* Gradients */
        .gradient-primary { background: linear-gradient(135deg, #FF0000 0%, #CC0000 50%, #FF3333 100%); }
        .gradient-text { background: linear-gradient(135deg, #FF0000 0%, #FF6B6B 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .gradient-border { background: linear-gradient(135deg, #FF0000, #FF6B6B); padding: 2px; border-radius: 12px; }
        
        /* Glass Effects */
        .glass { backdrop-filter: blur(20px); background: rgba(255, 255, 255, 0.9); border: 1px solid rgba(255, 0, 0, 0.1); }
        .glass-dark { backdrop-filter: blur(20px); background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); }
        .glass-light { backdrop-filter: blur(20px); background: rgba(255, 255, 255, 0.95); border: 1px solid rgba(255, 0, 0, 0.2); }
        
        /* Animations */
        @keyframes float { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-20px); } }
        @keyframes glow { from { box-shadow: 0 0 20px #FF0000; } to { box-shadow: 0 0 40px #FF0000, 0 0 60px #FF0000; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes scaleIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
        
        /* Patterns */
        .mesh-pattern { background-image: radial-gradient(circle at 25px 25px, rgba(255,0,0,0.2) 2px, transparent 0), radial-gradient(circle at 75px 75px, rgba(255,107,107,0.2) 2px, transparent 0); background-size: 100px 100px; }
        .grid-pattern { background-image: linear-gradient(rgba(255,0,0,0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,0,0,0.1) 1px, transparent 1px); background-size: 50px 50px; }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #FF0000; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #CC0000; }
        
        /* Hover Effects */
        .hover-lift { transition: all 0.3s ease; }
        .hover-lift:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(255,0,0,0.2); }
        
        /* Live Indicator */
        .live-dot { animation: pulse 2s infinite; background: #FF0000; }
        
        /* VPS Network Animation */
        .vps-node { animation: float 4s ease-in-out infinite; }
        .vps-node:nth-child(2) { animation-delay: 1s; }
        .vps-node:nth-child(3) { animation-delay: 2s; }
        .vps-node:nth-child(4) { animation-delay: 3s; }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-white transition-all duration-500 overflow-x-hidden" 
      x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' || (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches) }" 
      x-init="$watch('darkMode', val => { localStorage.setItem('darkMode', val); val ? document.documentElement.classList.add('dark') : document.documentElement.classList.remove('dark') }); darkMode ? document.documentElement.classList.add('dark') : document.documentElement.classList.remove('dark')">

    <!-- Navigation -->
    <nav class="fixed w-full z-50 transition-all duration-500" :class="darkMode ? 'glass-dark' : 'glass-light'">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex justify-between items-center py-5">
                <!-- Logo -->
                <div class="flex items-center space-x-4 animate-slide-up">
                    <div class="relative">
                        <div class="w-14 h-14 gradient-primary rounded-2xl flex items-center justify-center shadow-2xl animate-glow">
                            <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8.5 8.64L13.77 12L8.5 15.36V8.64M6.5 5V19L17.5 12L6.5 5Z"/>
                            </svg>
                        </div>
                        <div class="absolute -top-1 -right-1 w-4 h-4 bg-green-500 rounded-full live-dot"></div>
                    </div>
                    <div>
                        <h1 class="text-2xl font-black gradient-text font-inter">EZSTREAM</h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">Auto Livestream Platform</p>
                    </div>
                </div>
                
                <!-- Navigation Links -->
                <div class="hidden lg:flex items-center space-x-10">
                    <a href="#features" class="relative group font-medium text-gray-700 dark:text-gray-300 hover:text-primary transition-all duration-300">
                        Tính năng
                        <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-primary transition-all duration-300 group-hover:w-full"></span>
                    </a>
                    <a href="#pricing" class="relative group font-medium text-gray-700 dark:text-gray-300 hover:text-primary transition-all duration-300">
                        Giá cả
                        <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-primary transition-all duration-300 group-hover:w-full"></span>
                    </a>
                    <a href="#features" class="relative group font-medium text-gray-700 dark:text-gray-300 hover:text-primary transition-all duration-300">
                        Mạng lưới
                        <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-primary transition-all duration-300 group-hover:w-full"></span>
                    </a>
                    <a href="#support" class="relative group font-medium text-gray-700 dark:text-gray-300 hover:text-primary transition-all duration-300">
                        Hỗ trợ
                        <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-primary transition-all duration-300 group-hover:w-full"></span>
                    </a>
                </div>
                
                <!-- Actions -->
                <div class="flex items-center space-x-4">
                    <!-- Dark Mode Toggle -->
                    <button @click="darkMode = !darkMode" 
                            class="p-3 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-all duration-300 hover-lift">
                        <svg x-show="!darkMode" class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                        </svg>
                        <svg x-show="darkMode" class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </button>
                    
                    <?php if(auth()->guard()->check()): ?>
                        <a href="<?php echo e(route('dashboard')); ?>" 
                           class="gradient-primary hover:opacity-90 px-8 py-3 rounded-xl text-white font-bold transition-all duration-300 hover-lift shadow-xl">
                            Dashboard
                        </a>
                    <?php else: ?>
                        <a href="<?php echo e(route('login')); ?>" 
                           class="text-gray-600 dark:text-gray-300 hover:text-primary px-6 py-3 transition-all duration-300 font-semibold">
                            Đăng nhập
                        </a>
                        <a href="<?php echo e(route('register')); ?>" 
                           class="gradient-primary hover:opacity-90 px-8 py-3 rounded-xl text-white font-bold transition-all duration-300 hover-lift shadow-xl">
                            Đăng ký ngay
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Hero Section -->
    <section class="relative min-h-screen flex items-center justify-center pt-24 overflow-hidden">
        <!-- Dynamic Background -->
        <div class="absolute inset-0 mesh-pattern opacity-30"></div>
        <div class="absolute inset-0 bg-gradient-to-br from-primary/5 via-transparent to-accent/10"></div>
        
        <!-- Floating Elements -->
        <div class="absolute inset-0 pointer-events-none">
            <div class="vps-node absolute top-20 left-10 w-3 h-3 bg-primary/40 rounded-full"></div>
            <div class="vps-node absolute top-32 right-20 w-4 h-4 bg-accent/30 rounded-full"></div>
            <div class="vps-node absolute bottom-40 left-20 w-2 h-2 bg-primary/50 rounded-full"></div>
            <div class="vps-node absolute bottom-60 right-10 w-5 h-5 bg-accent/20 rounded-full"></div>
            <div class="vps-node absolute top-1/2 left-1/3 w-3 h-3 bg-primary/30 rounded-full"></div>
            <div class="vps-node absolute top-1/3 right-1/3 w-4 h-4 bg-accent/40 rounded-full"></div>
        </div>
        
        <div class="relative z-10 max-w-7xl mx-auto px-6 text-center">
            <!-- Status Badge -->
            <div class="inline-flex items-center px-6 py-3 rounded-2xl glass mb-8 animate-fade-in">
                <div class="w-3 h-3 bg-green-500 rounded-full live-dot mr-3"></div>
                <span class="font-mono text-sm font-semibold">🔴 LIVE - <?php echo e($stats['active_streams']); ?> streams đang hoạt động</span>
            </div>
            
            <!-- Main Headline -->
            <div class="animate-slide-up">
                <h1 class="text-5xl md:text-7xl lg:text-8xl font-black leading-none mb-8">
                    <span class="gradient-text block">LIVE STREAM</span>
                    <span class="text-gray-900 dark:text-white block">AUTOMATION</span>
                </h1>
                
                <!-- Animated Subtitle -->
                <div class="relative mb-12">
                    <p class="text-xl md:text-2xl lg:text-3xl text-gray-600 dark:text-gray-300 max-w-4xl mx-auto leading-relaxed font-medium">
                        Hệ thống <span class="gradient-text font-bold">streaming tự động</span> ổn định và chuyên nghiệp
                        <br class="hidden md:block">
                        <span class="text-lg md:text-xl opacity-80">Upload video • Auto streaming • 24/7 monitoring</span>
                    </p>
                </div>
            </div>
            
            <!-- CTA Section -->
            <div class="animate-scale-in mb-20">
                <div class="flex flex-col sm:flex-row gap-6 justify-center items-center mb-8">
                    <?php if(auth()->guard()->check()): ?>
                        <a href="<?php echo e(route('dashboard')); ?>" 
                           class="group relative gradient-primary hover:opacity-90 px-12 py-5 rounded-2xl text-white font-black text-xl transition-all duration-500 hover-lift shadow-2xl">
                            <span class="relative z-10">VÀO DASHBOARD</span>
                            <div class="absolute inset-0 bg-white/20 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo e(route('register')); ?>" 
                           class="group relative gradient-primary hover:opacity-90 px-12 py-5 rounded-2xl text-white font-black text-xl transition-all duration-500 hover-lift shadow-2xl">
                            <span class="relative z-10">BẮT ĐẦU NGAY</span>
                            <div class="absolute inset-0 bg-white/20 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        </a>
                        <a href="#pricing" 
                           class="group px-12 py-5 rounded-2xl border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-black text-xl hover:border-primary hover:text-primary transition-all duration-300 hover-lift">
                            XEM GÓI DỊCH VỤ
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Trust Indicators -->
                <div class="flex flex-wrap justify-center items-center gap-8 text-sm text-gray-500 dark:text-gray-400">
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="font-semibold">Setup đơn giản</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="font-semibold">Streaming ổn định</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-gray-700 dark:text-gray-300">Hỗ trợ 24/7</span>
                    </div>
                </div>
            </div>
            
            <!-- Performance Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 max-w-5xl mx-auto animate-fade-in mb-16">
                <div class="glass rounded-2xl p-6 hover-lift">
                    <div class="text-4xl md:text-5xl font-black gradient-text mb-2">99.9%</div>
                    <div class="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">UPTIME</div>
                </div>
                <div class="glass rounded-2xl p-6 hover-lift">
                    <div class="text-4xl md:text-5xl font-black gradient-text mb-2"><?php echo e($stats['total_vps']); ?></div>
                    <div class="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">SERVERS</div>
                </div>
                <div class="glass rounded-2xl p-6 hover-lift">
                    <div class="text-4xl md:text-5xl font-black gradient-text mb-2">24/7</div>
                    <div class="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">SUPPORT</div>
                </div>
                <div class="glass rounded-2xl p-6 hover-lift">
                    <div class="text-4xl md:text-5xl font-black gradient-text mb-2"><?php echo e($stats['active_streams']); ?></div>
                    <div class="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">LIVE STREAMS</div>
                </div>
            </div>
            
            <!-- Scroll Indicator -->
            <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 animate-bounce">
                <div class="w-8 h-12 rounded-full border-2 border-gray-400 dark:border-gray-600 flex justify-center">
                    <div class="w-1 h-3 bg-primary rounded-full mt-2 animate-pulse"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="relative py-32 bg-gray-50 dark:bg-gray-900 overflow-hidden">
        <!-- Background Effects -->
        <div class="absolute inset-0 mesh-pattern opacity-10"></div>
        
        <div class="relative z-10 max-w-7xl mx-auto px-6">
            <!-- Section Header -->
            <div class="text-center mb-20 animate-fade-in">
                <div class="inline-flex items-center px-4 py-2 rounded-full bg-primary/10 border border-primary/20 mb-6">
                    <span class="text-primary font-mono text-sm font-semibold">PRICING PLANS</span>
                </div>
                <h2 class="text-5xl md:text-6xl font-black mb-6">
                    <span class="text-gray-900 dark:text-white">Gói dịch vụ</span>
                    <span class="gradient-text block">Phù hợp</span>
                </h2>
                <p class="text-xl text-gray-600 dark:text-gray-400 max-w-3xl mx-auto leading-relaxed">
                    Chọn gói dịch vụ phù hợp với nhu cầu của bạn. 
                    <span class="text-gray-900 dark:text-white font-semibold">Không ràng buộc, hủy bất kỳ lúc nào</span>
                </p>
            </div>

            <!-- Pricing Cards -->
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 max-w-6xl mx-auto">
                <?php $__currentLoopData = $stats['service_packages']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $package): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="group relative animate-scale-in hover-lift" style="animation-delay: <?php echo e($loop->index * 0.2); ?>s;">
                    <?php if($package->is_popular): ?>
                        <div class="absolute -top-4 left-1/2 transform -translate-x-1/2 z-20">
                            <div class="gradient-primary px-6 py-2 rounded-full text-white text-sm font-bold shadow-lg">
                                PHỔ BIẾN NHẤT
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="relative bg-white dark:bg-gray-800 rounded-3xl p-8 border-2 border-gray-200 dark:border-gray-700 group-hover:border-primary/50 transition-all duration-300 h-full z-10 <?php echo e($package->is_popular ? 'transform scale-105 border-primary/50' : ''); ?>">
                        <!-- Package Header -->
                        <div class="text-center mb-8">
                            <h3 class="text-2xl font-black text-gray-900 dark:text-white mb-2"><?php echo e($package->name); ?></h3>
                            <p class="text-gray-600 dark:text-gray-400 mb-6"><?php echo e($package->description); ?></p>
                            <div class="mb-6">
                                <span class="text-5xl font-black gradient-text"><?php echo e(number_format($package->price, 0, ',', '.')); ?></span>
                                <span class="text-gray-600 dark:text-gray-400 text-lg">đ/tháng</span>
                            </div>
                        </div>
                        
                        <!-- Features -->
                        <div class="space-y-4 mb-8">
                            <?php
                                $features = is_string($package->features) 
                                    ? json_decode($package->features, true) ?? [] 
                                    : (is_array($package->features) ? $package->features : []);
                            ?>
                            <?php if(count($features) > 0): ?>
                                <?php $__currentLoopData = $features; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $feature): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="text-gray-700 dark:text-gray-300"><?php echo e($feature); ?></span>
                                </div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            <?php else: ?>
                                <!-- Default features if none specified -->
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="text-gray-700 dark:text-gray-300">Streaming tự động 24/7</span>
                                </div>
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="text-gray-700 dark:text-gray-300">Hỗ trợ kỹ thuật</span>
                                </div>
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="text-gray-700 dark:text-gray-300">Monitoring real-time</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- CTA Button -->
                        <div class="text-center">
                            <?php if(auth()->guard()->check()): ?>
                                <a href="<?php echo e(route('services')); ?>" 
                                   class="w-full inline-block gradient-primary hover:opacity-90 px-8 py-4 rounded-2xl text-white font-bold transition-all duration-300 hover-lift">
                                    Chọn gói này
                                </a>
                            <?php else: ?>
                                <a href="<?php echo e(route('register')); ?>" 
                                   class="w-full inline-block gradient-primary hover:opacity-90 px-8 py-4 rounded-2xl text-white font-bold transition-all duration-300 hover-lift">
                                    Bắt đầu ngay
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>

            <!-- Additional Info -->
            <div class="text-center mt-16">
                <p class="text-gray-600 dark:text-gray-400 mb-4">
                    Tất cả các gói đều bao gồm hỗ trợ 24/7 và không có phí setup
                </p>
                <div class="flex flex-wrap justify-center items-center gap-8 text-sm text-gray-500 dark:text-gray-500">
                    <div class="flex items-center space-x-2">
                        <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span>Không ràng buộc hợp đồng</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span>Hủy bất kỳ lúc nào</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span>Nâng cấp/hạ cấp linh hoạt</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="relative py-32 bg-gray-900 dark:bg-gray-950 overflow-hidden">
        <!-- Background Effects -->
        <div class="absolute inset-0 grid-pattern opacity-20"></div>
        <div class="absolute top-0 left-1/4 w-96 h-96 bg-primary/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 right-1/4 w-96 h-96 bg-accent/10 rounded-full blur-3xl"></div>
        
        <div class="relative z-10 max-w-7xl mx-auto px-6">
            <!-- Section Header -->
            <div class="text-center mb-20 animate-fade-in">
                <div class="inline-flex items-center px-4 py-2 rounded-full bg-primary/10 border border-primary/20 mb-6">
                    <span class="text-primary font-mono text-sm font-semibold">CORE FEATURES</span>
                </div>
                <h2 class="text-5xl md:text-6xl font-black mb-6">
                    <span class="text-white">Tính năng</span>
                    <span class="gradient-text block">Chính</span>
                </h2>
                <p class="text-xl text-gray-400 max-w-3xl mx-auto leading-relaxed">
                    Hệ thống streaming tự động đơn giản và ổn định, được thiết kế cho 
                    <span class="text-white font-semibold">creator và doanh nghiệp</span>
                </p>
            </div>
            
            <!-- Features Grid -->
            <div class="grid lg:grid-cols-3 gap-8 mb-20">
                <!-- Feature 1: File Upload & Management -->
                <div class="group relative animate-scale-in">
                    <div class="absolute inset-0 gradient-primary rounded-3xl opacity-0 group-hover:opacity-10 transition-opacity duration-500"></div>
                    <div class="glass-dark rounded-3xl p-8 h-full hover-lift">
                        <div class="relative">
                            <!-- Icon -->
                            <div class="w-20 h-20 gradient-primary rounded-3xl flex items-center justify-center mb-6 group-hover:animate-glow">
                                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                            </div>
                            
                            <!-- Content -->
                            <h3 class="text-2xl font-black text-white mb-4">Upload & Quản lý File</h3>
                            <p class="text-gray-300 mb-6 leading-relaxed">
                                Upload video dễ dàng với hệ thống quản lý file an toàn. 
                                <span class="text-primary font-semibold">Cloud storage</span> đáng tin cậy.
                            </p>
                            
                            <!-- Features List -->
                            <div class="space-y-3">
                                <div class="flex items-center text-sm">
                                    <div class="w-2 h-2 bg-green-400 rounded-full mr-3 animate-pulse"></div>
                                    <span class="text-gray-300">Upload tốc độ cao với <span class="text-white font-semibold">resumable</span></span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <div class="w-2 h-2 bg-blue-400 rounded-full mr-3 animate-pulse"></div>
                                    <span class="text-gray-300">Hỗ trợ nhiều định dạng video</span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <div class="w-2 h-2 bg-purple-400 rounded-full mr-3 animate-pulse"></div>
                                    <span class="text-gray-300">Quản lý thư viện video</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Feature 2: Auto Streaming -->
                <div class="group relative animate-scale-in" style="animation-delay: 0.2s;">
                    <div class="absolute inset-0 gradient-primary rounded-3xl opacity-0 group-hover:opacity-10 transition-opacity duration-500"></div>
                    <div class="glass-dark rounded-3xl p-8 h-full hover-lift">
                        <div class="relative">
                            <!-- Icon -->
                            <div class="w-20 h-20 gradient-primary rounded-3xl flex items-center justify-center mb-6 group-hover:animate-glow">
                                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            
                            <!-- Content -->
                            <h3 class="text-2xl font-black text-white mb-4">Streaming Tự động</h3>
                            <p class="text-gray-300 mb-6 leading-relaxed">
                                Streaming tự động với backup URL khi gặp sự cố. 
                                <span class="text-primary font-semibold">Streaming 24/7</span> ổn định.
                            </p>
                            
                            <!-- Features List -->
                            <div class="space-y-3">
                                <div class="flex items-center text-sm">
                                    <div class="w-2 h-2 bg-green-400 rounded-full mr-3 animate-pulse"></div>
                                    <span class="text-gray-300">Tự động khởi động lại khi <span class="text-white font-semibold">lỗi</span></span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <div class="w-2 h-2 bg-blue-400 rounded-full mr-3 animate-pulse"></div>
                                    <span class="text-gray-300">Hỗ trợ YouTube, Facebook, TikTok</span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <div class="w-2 h-2 bg-purple-400 rounded-full mr-3 animate-pulse"></div>
                                    <span class="text-gray-300">Playlist và lặp video</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Feature 3: Monitoring & Support -->
                <div class="group relative animate-scale-in" style="animation-delay: 0.4s;">
                    <div class="absolute inset-0 gradient-primary rounded-3xl opacity-0 group-hover:opacity-10 transition-opacity duration-500"></div>
                    <div class="glass-dark rounded-3xl p-8 h-full hover-lift">
                        <div class="relative">
                            <!-- Icon -->
                            <div class="w-20 h-20 gradient-primary rounded-3xl flex items-center justify-center mb-6 group-hover:animate-glow">
                                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </div>
                            
                            <!-- Content -->
                            <h3 class="text-2xl font-black text-white mb-4">Monitoring & Hỗ trợ</h3>
                            <p class="text-gray-300 mb-6 leading-relaxed">
                                Dashboard theo dõi chi tiết với <span class="text-primary font-semibold">thông báo Telegram</span>. 
                                Hỗ trợ kỹ thuật 24/7.
                            </p>
                            
                            <!-- Features List -->
                            <div class="space-y-3">
                                <div class="flex items-center text-sm">
                                    <div class="w-2 h-2 bg-green-400 rounded-full mr-3 animate-pulse"></div>
                                    <span class="text-gray-300">Thông báo real-time qua <span class="text-white font-semibold">Telegram</span></span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <div class="w-2 h-2 bg-blue-400 rounded-full mr-3 animate-pulse"></div>
                                    <span class="text-gray-300">Dashboard theo dõi trạng thái</span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <div class="w-2 h-2 bg-purple-400 rounded-full mr-3 animate-pulse"></div>
                                    <span class="text-gray-300">Hỗ trợ kỹ thuật 24/7</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Platform Support -->
            <div class="text-center animate-fade-in">
                <h3 class="text-2xl font-bold text-white mb-8">Hỗ trợ tất cả nền tảng streaming</h3>
                <div class="flex flex-wrap justify-center items-center gap-12 opacity-80">
                    <!-- YouTube -->
                    <div class="flex items-center space-x-3 hover:opacity-100 transition-opacity">
                        <svg class="w-10 h-10 text-primary" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                        </svg>
                        <span class="text-white font-semibold">YouTube</span>
                    </div>
                    <!-- Facebook -->
                    <div class="flex items-center space-x-3 hover:opacity-100 transition-opacity">
                        <svg class="w-10 h-10 text-blue-500" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                        <span class="text-white font-semibold">Facebook</span>
                    </div>
                    <!-- TikTok -->
                    <div class="flex items-center space-x-3 hover:opacity-100 transition-opacity">
                        <svg class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>
                        </svg>
                        <span class="text-white font-semibold">TikTok</span>
                    </div>
                    <!-- Twitch -->
                    <div class="flex items-center space-x-3 hover:opacity-100 transition-opacity">
                        <svg class="w-10 h-10 text-purple-500" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0L1.714 4.286v15.428h5.143V24l4.286-4.286h3.428L22.286 12V0zm14.571 11.143l-3.428 3.428h-3.429l-3 3v-3H6.857V1.714h13.714Z"/>
                        </svg>
                        <span class="text-white font-semibold">Twitch</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Support Section -->
    <section id="support" class="relative py-32 bg-gray-50 dark:bg-gray-900 overflow-hidden">
        <div class="relative z-10 max-w-7xl mx-auto px-6">
            <!-- Section Header -->
            <div class="text-center mb-20 animate-fade-in">
                <div class="inline-flex items-center px-4 py-2 rounded-full bg-primary/10 border border-primary/20 mb-6">
                    <span class="text-primary font-mono text-sm font-semibold">SUPPORT & HELP</span>
                </div>
                <h2 class="text-5xl md:text-6xl font-black mb-6">
                    <span class="text-gray-900 dark:text-white">Hỗ trợ</span>
                    <span class="gradient-text block">24/7</span>
                </h2>
                <p class="text-xl text-gray-600 dark:text-gray-400 max-w-3xl mx-auto leading-relaxed">
                    Chúng tôi luôn sẵn sàng hỗ trợ bạn với đội ngũ kỹ thuật chuyên nghiệp
                </p>
            </div>
            
            <!-- Support Options -->
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 max-w-6xl mx-auto">
                <!-- Live Chat -->
                <div class="group bg-white dark:bg-gray-800 rounded-3xl p-8 border-2 border-gray-200 dark:border-gray-700 hover:border-primary/50 transition-all duration-300 hover-lift">
                    <div class="w-16 h-16 gradient-primary rounded-2xl flex items-center justify-center mb-6 group-hover:animate-glow">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Live Chat</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        Trò chuyện trực tiếp với đội ngũ hỗ trợ qua Telegram hoặc website
                    </p>
                    <div class="space-y-2 text-sm text-gray-500 dark:text-gray-500">
                        <div>⏰ Phản hồi trong vòng 5 phút</div>
                        <div>🕐 Hoạt động 24/7</div>
                    </div>
                </div>
                
                <!-- Documentation -->
                <div class="group bg-white dark:bg-gray-800 rounded-3xl p-8 border-2 border-gray-200 dark:border-gray-700 hover:border-primary/50 transition-all duration-300 hover-lift">
                    <div class="w-16 h-16 gradient-primary rounded-2xl flex items-center justify-center mb-6 group-hover:animate-glow">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C20.832 18.477 19.246 18 17.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Tài liệu</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        Hướng dẫn chi tiết từ setup đến sử dụng nâng cao
                    </p>
                    <div class="space-y-2 text-sm text-gray-500 dark:text-gray-500">
                        <div>📖 Hướng dẫn setup</div>
                        <div>🎥 Video tutorials</div>
                        <div>❓ FAQ thường gặp</div>
                    </div>
                </div>
                
                <!-- Phone Support -->
                <div class="group bg-white dark:bg-gray-800 rounded-3xl p-8 border-2 border-gray-200 dark:border-gray-700 hover:border-primary/50 transition-all duration-300 hover-lift">
                    <div class="w-16 h-16 gradient-primary rounded-2xl flex items-center justify-center mb-6 group-hover:animate-glow">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Hỗ trợ trực tiếp</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        Liên hệ trực tiếp qua điện thoại hoặc Zalo để được hỗ trợ nhanh nhất
                    </p>
                    <div class="space-y-2 text-sm text-gray-500 dark:text-gray-500">
                        <div>📞 Gọi điện trực tiếp</div>
                        <div>💬 Chat qua Zalo</div>
                        <div>⚡ Phản hồi ngay lập tức</div>
                    </div>
                </div>
            </div>
            
            <!-- Contact Info -->
            <div class="text-center mt-16">
                <div class="bg-white dark:bg-gray-800 rounded-3xl p-8 border-2 border-gray-200 dark:border-gray-700 max-w-2xl mx-auto">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Liên hệ trực tiếp</h3>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="text-center">
                            <div class="text-primary font-bold text-lg mb-2">Zalo / Điện thoại</div>
                            <div class="text-gray-600 dark:text-gray-400 font-mono text-xl">0971.125.260</div>
                        </div>
                        <div class="text-center">
                            <div class="text-primary font-bold text-lg mb-2">Telegram</div>
                            <div class="text-gray-600 dark:text-gray-400">@ezstream_support</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="relative py-32 overflow-hidden">
        <!-- Background -->
        <div class="absolute inset-0 gradient-primary"></div>
        <div class="absolute inset-0 mesh-pattern opacity-20"></div>
        
        <!-- Floating Elements -->
        <div class="absolute inset-0 pointer-events-none">
            <div class="vps-node absolute top-10 left-10 w-4 h-4 bg-white/20 rounded-full"></div>
            <div class="vps-node absolute top-20 right-20 w-6 h-6 bg-white/10 rounded-full"></div>
            <div class="vps-node absolute bottom-20 left-20 w-3 h-3 bg-white/30 rounded-full"></div>
            <div class="vps-node absolute bottom-10 right-10 w-5 h-5 bg-white/15 rounded-full"></div>
        </div>
        
        <div class="relative z-10 max-w-5xl mx-auto px-6 text-center">
            <!-- Badge -->
            <div class="inline-flex items-center px-6 py-3 rounded-2xl bg-white/10 backdrop-blur-sm border border-white/20 mb-8">
                <span class="text-white font-mono text-sm font-semibold">🚀 SẴN SÀNG STREAMING?</span>
            </div>
            
            <!-- Headline -->
            <h2 class="text-5xl md:text-7xl font-black text-white mb-8 leading-tight">
                Bắt đầu ngay
                <span class="block text-white/80">hôm nay!</span>
            </h2>
            
            <!-- Subtitle -->
            <p class="text-xl md:text-2xl text-white/80 mb-12 max-w-3xl mx-auto leading-relaxed">
                Tham gia cùng <span class="text-white font-bold">các creator</span> và doanh nghiệp 
                đang sử dụng hệ thống streaming tự động của chúng tôi
            </p>
            
            <!-- CTA Buttons -->
            <div class="flex flex-col sm:flex-row gap-6 justify-center items-center mb-16">
                <?php if(auth()->guard()->guest()): ?>
                    <a href="<?php echo e(route('register')); ?>" 
                       class="group relative px-12 py-5 bg-white text-primary rounded-2xl text-xl font-black transition-all duration-500 hover-lift shadow-2xl">
                        <span class="relative z-10 flex items-center">
                            <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                            ĐĂNG KÝ NGAY
                        </span>
                        <div class="absolute inset-0 bg-gray-100 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    </a>
                    <a href="#pricing" 
                       class="px-12 py-5 border-2 border-white/30 text-white rounded-2xl text-xl font-black hover:bg-white/10 transition-all duration-300 hover-lift">
                        XEM GIÁ
                    </a>
                <?php else: ?>
                    <a href="<?php echo e(route('dashboard')); ?>" 
                       class="group relative px-12 py-5 bg-white text-primary rounded-2xl text-xl font-black transition-all duration-500 hover-lift shadow-2xl">
                        <span class="relative z-10 flex items-center">
                            <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                            VÀO DASHBOARD
                        </span>
                        <div class="absolute inset-0 bg-gray-100 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Trust Indicators -->
            <div class="flex flex-wrap justify-center items-center gap-12 text-white/60">
                <div class="flex items-center space-x-3">
                    <svg class="w-6 h-6 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    <span class="font-semibold">Setup nhanh chóng</span>
                </div>
                <div class="flex items-center space-x-3">
                    <svg class="w-6 h-6 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    <span class="font-semibold">Hỗ trợ 24/7</span>
                </div>
                <div class="flex items-center space-x-3">
                    <svg class="w-6 h-6 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    <span class="font-semibold">Ổn định & tin cậy</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="relative bg-black py-20">
        <!-- Background -->
        <div class="absolute inset-0 grid-pattern opacity-10"></div>
        
        <div class="relative z-10 max-w-7xl mx-auto px-6">
            <!-- Main Footer Content -->
            <div class="grid lg:grid-cols-4 md:grid-cols-2 gap-12 mb-12">
                <!-- Company Info -->
                <div class="lg:col-span-2">
                    <div class="flex items-center space-x-4 mb-8">
                        <div class="w-16 h-16 gradient-primary rounded-3xl flex items-center justify-center shadow-2xl">
                            <svg class="w-9 h-9 text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8.5 8.64L13.77 12L8.5 15.36V8.64M6.5 5V19L17.5 12L6.5 5Z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-3xl font-black gradient-text">EZSTREAM</h3>
                            <p class="text-gray-400 font-mono">Livestream Platform</p>
                        </div>
                    </div>
                    <p class="text-gray-400 text-lg leading-relaxed mb-8 max-w-lg">
                        Hệ thống streaming tự động ổn định và đáng tin cậy. 
                        <span class="text-white font-semibold">Dịch vụ chuyên nghiệp</span>, hỗ trợ 24/7.
                    </p>
                </div>
                
                <!-- Quick Links -->
                <div>
                    <h4 class="text-xl font-bold text-white mb-6">Liên kết</h4>
                    <ul class="space-y-4">
                        <li><a href="#features" class="text-gray-400 hover:text-white transition-colors font-medium">Tính năng</a></li>
                        <li><a href="#pricing" class="text-gray-400 hover:text-white transition-colors font-medium">Giá cả</a></li>
                        <li><a href="#support" class="text-gray-400 hover:text-white transition-colors font-medium">Hỗ trợ</a></li>
                        <?php if(auth()->guard()->check()): ?>
                            <li><a href="<?php echo e(route('dashboard')); ?>" class="text-gray-400 hover:text-white transition-colors font-medium">Dashboard</a></li>
                        <?php else: ?>
                            <li><a href="<?php echo e(route('login')); ?>" class="text-gray-400 hover:text-white transition-colors font-medium">Đăng nhập</a></li>
                            <li><a href="<?php echo e(route('register')); ?>" class="text-gray-400 hover:text-white transition-colors font-medium">Đăng ký</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                                 <!-- Contact -->
                <div>
                    <h4 class="text-xl font-bold text-white mb-6">Liên hệ</h4>
                    <ul class="space-y-4">
                        <li class="text-gray-400">
                            <span class="text-white font-medium">Zalo/Phone:</span><br>
                            <span class="font-mono text-lg">0971.125.260</span>
                        </li>
                        <li class="text-gray-400">
                            <span class="text-white font-medium">Telegram:</span><br>
                            @ezstream_support
                        </li>
                        <li class="text-gray-400">
                            <span class="text-white font-medium">Hỗ trợ:</span><br>
                            24/7 - Phản hồi nhanh
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Bottom Footer -->
            <div class="border-t border-gray-800 pt-8">
                <div class="flex flex-col lg:flex-row justify-between items-center gap-6">
                    <div class="flex flex-col md:flex-row items-center gap-6 text-gray-400 text-sm">
                        <p>&copy; <?php echo e(date('Y')); ?> EZSTREAM. All rights reserved.</p>
                        <p class="font-mono">Built with ❤️ using Laravel & Livewire</p>
                    </div>
                    <div class="flex flex-wrap gap-6">
                        <a href="#" class="text-gray-400 hover:text-white text-sm transition-colors font-medium">Privacy Policy</a>
                        <a href="#" class="text-gray-400 hover:text-white text-sm transition-colors font-medium">Terms of Service</a>
                        <a href="#" class="text-gray-400 hover:text-white text-sm transition-colors font-medium">Cookie Policy</a>
                        <a href="#" class="text-gray-400 hover:text-white text-sm transition-colors font-medium">DMCA</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    </div>
</body>
</html> <?php /**PATH D:\laragon\www\ezstream\resources\views/welcome.blade.php ENDPATH**/ ?>