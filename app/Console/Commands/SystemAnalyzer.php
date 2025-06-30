<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SystemAnalyzer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:analyze 
                           {--export : Export kết quả ra file}
                           {--unused : Chỉ hiển thị file không dùng}
                           {--dependencies : Phân tích dependencies}
                           {--cleanup : Đề xuất cleanup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Phân tích hệ thống, tìm file rác và dependencies';

    private $results = [];
    private $unusedFiles = [];
    private $dependencies = [];
    private $routes = [];
    private $views = [];
    private $controllers = [];
    private $models = [];
    private $services = [];
    private $jobs = [];
    private $livewire = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Bắt đầu phân tích hệ thống...');
        
        // 1. Thu thập thông tin các file
        $this->collectSystemFiles();
        
        // 2. Phân tích dependencies
        if ($this->option('dependencies') || !$this->hasOptions()) {
            $this->analyzeDependencies();
        }
        
        // 3. Tìm file không được sử dụng
        if ($this->option('unused') || !$this->hasOptions()) {
            $this->findUnusedFiles();
        }
        
        // 4. Đề xuất cleanup
        if ($this->option('cleanup') || !$this->hasOptions()) {
            $this->suggestCleanup();
        }
        
        // 5. Export kết quả
        if ($this->option('export')) {
            $this->exportResults();
        }
        
        $this->displayResults();
    }

    private function hasOptions(): bool
    {
        return $this->option('unused') || $this->option('dependencies') || $this->option('cleanup');
    }

    private function collectSystemFiles()
    {
        $this->info('📁 Thu thập thông tin file...');
        
        // Controllers
        $this->controllers = $this->scanDirectory('app/Http/Controllers', '*.php');
        
        // Models  
        $this->models = $this->scanDirectory('app/Models', '*.php');
        
        // Services
        $this->services = $this->scanDirectory('app/Services', '*.php');
        
        // Jobs
        $this->jobs = $this->scanDirectory('app/Jobs', '*.php');
        
        // Livewire
        $this->livewire = $this->scanDirectory('app/Livewire', '*.php');
        
        // Views
        $this->views = $this->scanDirectory('resources/views', '*.blade.php');
        
        // Routes
        $this->routes = [
            'web.php' => base_path('routes/web.php'),
            'api.php' => base_path('routes/api.php'),
            'auth.php' => base_path('routes/auth.php'),
            'console.php' => base_path('routes/console.php'),
        ];
        
        $this->line('✅ Thu thập hoàn tất');
    }

    private function scanDirectory($path, $pattern = '*')
    {
        $fullPath = base_path($path);
        if (!File::exists($fullPath)) {
            return [];
        }
        
        return collect(File::allFiles($fullPath))
            ->filter(function ($file) use ($pattern) {
                return fnmatch($pattern, $file->getFilename());
            })
            ->mapWithKeys(function ($file) use ($path) {
                $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                return [$relativePath => $file->getPathname()];
            })
            ->toArray();
    }

    private function analyzeDependencies()
    {
        $this->info('🔗 Phân tích dependencies...');
        
        // Phân tích Controllers
        foreach ($this->controllers as $name => $path) {
            $this->analyzeControllerDependencies($name, $path);
        }
        
        // Phân tích Livewire
        foreach ($this->livewire as $name => $path) {
            $this->analyzeLivewireDependencies($name, $path);
        }
        
        // Phân tích Views
        foreach ($this->views as $name => $path) {
            $this->analyzeViewDependencies($name, $path);
        }
        
        // Phân tích Routes
        foreach ($this->routes as $name => $path) {
            if (File::exists($path)) {
                $this->analyzeRouteDependencies($name, $path);
            }
        }
    }

    private function analyzeControllerDependencies($name, $path)
    {
        $content = File::get($path);
        $className = $this->extractClassName($content);
        
        $deps = [
            'models' => $this->findModelUsage($content),
            'services' => $this->findServiceUsage($content),
            'jobs' => $this->findJobUsage($content),
            'views' => $this->findViewUsage($content),
            'routes_to' => $this->findRoutesPointingTo($className),
        ];
        
        $this->dependencies['controllers'][$name] = $deps;
    }

    private function analyzeLivewireDependencies($name, $path)
    {
        $content = File::get($path);
        $className = $this->extractClassName($content);
        
        $deps = [
            'models' => $this->findModelUsage($content),
            'services' => $this->findServiceUsage($content),
            'jobs' => $this->findJobUsage($content),
            'view' => $this->findLivewireView($content, $name),
            'routes_to' => $this->findRoutesPointingTo($className),
        ];
        
        $this->dependencies['livewire'][$name] = $deps;
    }

    private function analyzeViewDependencies($name, $path)
    {
        $content = File::get($path);
        
        $deps = [
            'components' => $this->findComponentUsage($content),
            'livewire' => $this->findLivewireUsage($content),
            'includes' => $this->findIncludeUsage($content),
            'extends' => $this->findExtendsUsage($content),
        ];
        
        $this->dependencies['views'][$name] = $deps;
    }

    private function analyzeRouteDependencies($name, $path)
    {
        $content = File::get($path);
        
        $deps = [
            'controllers' => $this->findControllerRoutes($content),
            'livewire' => $this->findLivewireRoutes($content),
            'middleware' => $this->findMiddlewareUsage($content),
        ];
        
        $this->dependencies['routes'][$name] = $deps;
    }

    private function findUnusedFiles()
    {
        $this->info('🗑️ Tìm file không được sử dụng...');
        
        // Kiểm tra Controllers không được route
        $this->findUnusedControllers();
        
        // Kiểm tra Views không được gọi
        $this->findUnusedViews();
        
        // Kiểm tra Livewire không được sử dụng
        $this->findUnusedLivewire();
        
        // Kiểm tra Services không được inject
        $this->findUnusedServices();
        
        // Kiểm tra Models không được reference
        $this->findUnusedModels();
        
        // Kiểm tra Jobs không được dispatch
        $this->findUnusedJobs();
    }

    private function findUnusedControllers()
    {
        foreach ($this->controllers as $name => $path) {
            $className = $this->extractClassName(File::get($path));
            $isUsed = false;
            
            // Kiểm tra trong routes
            foreach ($this->routes as $routeName => $routePath) {
                if (File::exists($routePath)) {
                    $routeContent = File::get($routePath);
                    if (Str::contains($routeContent, $className) || 
                        Str::contains($routeContent, class_basename($className))) {
                        $isUsed = true;
                        break;
                    }
                }
            }
            
            if (!$isUsed) {
                $this->unusedFiles['controllers'][] = $name;
            }
        }
    }

    private function findUnusedViews()
    {
        foreach ($this->views as $name => $path) {
            $viewName = $this->pathToViewName($name);
            $isUsed = false;
            
            // Kiểm tra trong Controllers
            foreach ($this->controllers as $controllerPath) {
                $content = File::get($controllerPath);
                if (Str::contains($content, $viewName) || Str::contains($content, "'{$viewName}'") || Str::contains($content, "\"{$viewName}\"")) {
                    $isUsed = true;
                    break;
                }
            }
            
            // Kiểm tra trong Livewire - CẢI THIỆN: tìm theo convention
            if (!$isUsed) {
                foreach ($this->livewire as $livewireName => $livewirePath) {
                    $content = File::get($livewirePath);
                    
                    // Method 1: Explicit render() call
                    if (Str::contains($content, $viewName) || Str::contains($content, "'{$viewName}'") || Str::contains($content, "\"{$viewName}\"")) {
                        $isUsed = true;
                        break;
                    }
                    
                    // Method 2: Convention-based (Livewire auto-discovers views)
                    $expectedViewPath = $this->livewireToViewPath($livewireName);
                    if ($viewName === $expectedViewPath) {
                        $isUsed = true;
                        break;
                    }
                }
            }
            
            // Kiểm tra trong Views khác (includes, extends)
            if (!$isUsed) {
                foreach ($this->views as $otherPath) {
                    if ($otherPath !== $path) {
                        $content = File::get($otherPath);
                        if (Str::contains($content, $viewName)) {
                            $isUsed = true;
                            break;
                        }
                    }
                }
            }
            
            // Kiểm tra các view đặc biệt (layouts, components)
            if (!$isUsed) {
                $isUsed = $this->isSpecialView($viewName);
            }
            
            if (!$isUsed) {
                $this->unusedFiles['views'][] = $name;
            }
        }
    }

    /**
     * Convert Livewire class path to expected view path
     */
    private function livewireToViewPath($livewirePath)
    {
        // app/Livewire/Admin/Dashboard.php -> livewire.admin.dashboard
        $path = str_replace(['app/Livewire/', '.php'], ['', ''], $livewirePath);
        $path = str_replace(['\\', '/'], '.', $path);
        return 'livewire.' . Str::kebab($path);
    }

    /**
     * Check if view is special (layouts, components, auth)
     */
    private function isSpecialView($viewName)
    {
        $specialPatterns = [
            'layouts.*',
            'components.*', 
            'auth.*',
            'profile.*',
            'livewire.*',
            'admin.*',
            'partials.*'
        ];
        
        foreach ($specialPatterns as $pattern) {
            if (fnmatch($pattern, $viewName)) {
                return true;
            }
        }
        
        return false;
    }

    private function findUnusedLivewire()
    {
        foreach ($this->livewire as $name => $path) {
            $className = $this->extractClassName(File::get($path));
            $componentName = $this->classToComponentName($className);
            $isUsed = false;
            
            // Kiểm tra trong routes
            foreach ($this->routes as $routePath) {
                if (File::exists($routePath)) {
                    $content = File::get($routePath);
                    if (Str::contains($content, $className) || Str::contains($content, $componentName)) {
                        $isUsed = true;
                        break;
                    }
                }
            }
            
            // Kiểm tra trong views
            if (!$isUsed) {
                foreach ($this->views as $viewPath) {
                    $content = File::get($viewPath);
                    if (Str::contains($content, $componentName) || Str::contains($content, "@livewire('{$componentName}')")) {
                        $isUsed = true;
                        break;
                    }
                }
            }
            
            if (!$isUsed) {
                $this->unusedFiles['livewire'][] = $name;
            }
        }
    }

    private function findUnusedServices()
    {
        foreach ($this->services as $name => $path) {
            $className = $this->extractClassName(File::get($path));
            $isUsed = false;
            
            // Tìm trong tất cả PHP files
            $allPhpFiles = array_merge($this->controllers, $this->livewire, $this->jobs, $this->models);
            
            foreach ($allPhpFiles as $phpPath) {
                $content = File::get($phpPath);
                if (Str::contains($content, $className) || Str::contains($content, class_basename($className))) {
                    $isUsed = true;
                    break;
                }
            }
            
            if (!$isUsed) {
                $this->unusedFiles['services'][] = $name;
            }
        }
    }

    private function findUnusedModels()
    {
        foreach ($this->models as $name => $path) {
            $className = $this->extractClassName(File::get($path));
            $isUsed = false;
            
            // Tìm trong tất cả PHP files
            $allPhpFiles = array_merge($this->controllers, $this->livewire, $this->jobs, $this->services);
            
            foreach ($allPhpFiles as $phpPath) {
                $content = File::get($phpPath);
                if (Str::contains($content, $className) || Str::contains($content, class_basename($className))) {
                    $isUsed = true;
                    break;
                }
            }
            
            if (!$isUsed) {
                $this->unusedFiles['models'][] = $name;
            }
        }
    }

    private function findUnusedJobs()
    {
        foreach ($this->jobs as $name => $path) {
            $className = $this->extractClassName(File::get($path));
            $isUsed = false;
            
            // Tìm dispatch calls
            $allPhpFiles = array_merge($this->controllers, $this->livewire, $this->services, $this->jobs);
            
            foreach ($allPhpFiles as $phpPath) {
                $content = File::get($phpPath);
                if (Str::contains($content, $className) || 
                    Str::contains($content, class_basename($className)) ||
                    Str::contains($content, "dispatch(new {$className}") ||
                    Str::contains($content, "{$className}::dispatch")) {
                    $isUsed = true;
                    break;
                }
            }
            
            if (!$isUsed) {
                $this->unusedFiles['jobs'][] = $name;
            }
        }
    }

    private function suggestCleanup()
    {
        $this->info('🧹 Đề xuất cleanup...');
        
        $this->results['cleanup_suggestions'] = [
            'safe_to_delete' => [],
            'review_needed' => [],
            'definitely_unused' => [],
            'migrations' => $this->findOldMigrations(),
            'temp_files' => $this->findTempFiles(),
        ];
        
        // Phân loại file cần xóa
        $this->categorizeDeletableFiles();
    }

    private function categorizeDeletableFiles()
    {
        // File chắc chắn không dùng (test files, etc.)
        $definitelyUnused = [
            'test-google-drive.blade.php',
            'test-streaming.blade.php', 
            'webhook-test.blade.php',
            'welcome.blade.php'
        ];
        
        foreach ($this->unusedFiles as $type => $files) {
            foreach ($files as $file) {
                $fileName = basename($file);
                
                if (in_array($fileName, $definitelyUnused)) {
                    $this->results['cleanup_suggestions']['definitely_unused'][] = $file;
                } elseif ($this->isSafeToDelete($type, $file)) {
                    $this->results['cleanup_suggestions']['safe_to_delete'][$type][] = $file;
                } else {
                    $this->results['cleanup_suggestions']['review_needed'][$type][] = $file;
                }
            }
        }
    }

    private function isSafeToDelete($type, $file)
    {
        // Controllers - cần review
        if ($type === 'controllers') {
            return false;
        }
        
        // Services - cần review 
        if ($type === 'services') {
            $safeServices = ['MockSshService.php']; // Mock service có thể xóa
            return in_array(basename($file), $safeServices);
        }
        
        // Views - một số có thể xóa an toàn
        if ($type === 'views') {
            $safeViews = [
                'test-',
                'welcome.blade.php'
            ];
            
            foreach ($safeViews as $pattern) {
                if (Str::contains(basename($file), $pattern)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    private function findOldMigrations()
    {
        $migrations = collect(File::files(database_path('migrations')))
            ->map(function ($file) {
                return [
                    'file' => $file->getFilename(),
                    'path' => $file->getPathname(),
                    'date' => $this->extractMigrationDate($file->getFilename()),
                ];
            })
            ->sortBy('date');
            
        return $migrations->toArray();
    }

    private function findTempFiles()
    {
        $tempFiles = [];
        
        // Storage temp files
        if (File::exists(storage_path('app/temp'))) {
            $tempFiles['storage_temp'] = File::files(storage_path('app/temp'));
        }
        
        // Log files cũ
        if (File::exists(storage_path('logs'))) {
            $logFiles = collect(File::files(storage_path('logs')))
                ->filter(function ($file) {
                    return $file->getMTime() < now()->subDays(30)->timestamp;
                });
            $tempFiles['old_logs'] = $logFiles->toArray();
        }
        
        return $tempFiles;
    }

    private function displayResults()
    {
        $this->info('📊 KẾT QUẢ PHÂN TÍCH HỆ THỐNG');
        $this->line('=' . str_repeat('=', 50));
        
        // Thống kê tổng quan
        $this->displayOverview();
        
        // File không sử dụng
        if (!empty($this->unusedFiles)) {
            $this->displayUnusedFiles();
        }
        
        // Dependencies
        if (!empty($this->dependencies)) {
            $this->displayDependencies();
        }
        
        // Cleanup suggestions
        if (isset($this->results['cleanup_suggestions'])) {
            $this->displayCleanupSuggestions();
        }
    }

    private function displayOverview()
    {
        $this->info('📈 TỔNG QUAN HỆ THỐNG:');
        $this->table(['Loại File', 'Số Lượng'], [
            ['Controllers', count($this->controllers)],
            ['Models', count($this->models)],
            ['Services', count($this->services)],
            ['Jobs', count($this->jobs)],
            ['Livewire', count($this->livewire)],
            ['Views', count($this->views)],
        ]);
    }

    private function displayUnusedFiles()
    {
        $this->warn('🗑️ FILE KHÔNG SỬ DỤNG:');
        
        foreach ($this->unusedFiles as $type => $files) {
            if (!empty($files)) {
                $this->line("  📁 {$type}: " . count($files) . " files");
                foreach ($files as $file) {
                    $this->line("    - {$file}");
                }
            }
        }
    }

    private function displayDependencies()
    {
        $this->info('🔗 DEPENDENCIES (Top 5):');
        
        // Hiển thị top dependencies
        foreach (['controllers', 'livewire'] as $type) {
            if (isset($this->dependencies[$type])) {
                $this->line("  📁 {$type}:");
                $count = 0;
                foreach ($this->dependencies[$type] as $name => $deps) {
                    if ($count >= 5) break;
                    $totalDeps = array_sum(array_map('count', array_filter($deps, 'is_array')));
                    $this->line("    - {$name}: {$totalDeps} dependencies");
                    $count++;
                }
            }
        }
    }

    private function displayCleanupSuggestions()
    {
        $this->warn('🧹 ĐỀ XUẤT CLEANUP:');
        
        $suggestions = $this->results['cleanup_suggestions'];
        
        if (!empty($suggestions['definitely_unused'])) {
            $this->line('  🗑️ Chắc chắn có thể xóa:');
            foreach ($suggestions['definitely_unused'] as $file) {
                $this->line("    - {$file}");
            }
        }
        
        if (!empty($suggestions['safe_to_delete'])) {
            $this->line('  ✅ An toàn để xóa:');
            foreach ($suggestions['safe_to_delete'] as $type => $files) {
                $this->line("    📁 {$type}: " . count($files) . " files");
                foreach ($files as $file) {
                    $this->line("      - {$file}");
                }
            }
        }
        
        if (!empty($suggestions['review_needed'])) {
            $this->line('  ⚠️ Cần review trước khi xóa:');
            foreach ($suggestions['review_needed'] as $type => $files) {
                $this->line("    📁 {$type}: " . count($files) . " files");
            }
        }
        
        // Hiển thị lệnh cleanup
        $this->displayCleanupCommands();
    }

    private function displayCleanupCommands()
    {
        $this->info('🔧 LỆNH CLEANUP:');
        
        $suggestions = $this->results['cleanup_suggestions'];
        
        if (!empty($suggestions['definitely_unused'])) {
            $this->line('  # Xóa file test và welcome:');
            foreach ($suggestions['definitely_unused'] as $file) {
                $this->line("  rm {$file}");
            }
        }
        
        if (!empty($suggestions['safe_to_delete']['services'])) {
            $this->line('  # Xóa mock services:');
            foreach ($suggestions['safe_to_delete']['services'] as $file) {
                $this->line("  rm {$file}");
            }
        }
    }

    private function exportResults()
    {
        $exportData = [
            'timestamp' => now()->toDateTimeString(),
            'overview' => [
                'controllers' => count($this->controllers),
                'models' => count($this->models),
                'services' => count($this->services),
                'jobs' => count($this->jobs),
                'livewire' => count($this->livewire),
                'views' => count($this->views),
            ],
            'unused_files' => $this->unusedFiles,
            'dependencies' => $this->dependencies,
            'cleanup_suggestions' => $this->results['cleanup_suggestions'] ?? [],
        ];
        
        $filename = 'system_analysis_' . now()->format('Y_m_d_H_i_s') . '.json';
        $path = storage_path("app/{$filename}");
        
        File::put($path, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $this->info("📄 Kết quả đã được export: {$path}");
    }

    // Helper methods
    private function extractClassName($content)
    {
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function pathToViewName($path)
    {
        return str_replace(['resources/views/', '.blade.php', '/'], ['', '', '.'], $path);
    }

    private function classToComponentName($className)
    {
        return Str::kebab(class_basename($className));
    }

    private function extractMigrationDate($filename)
    {
        if (preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})/', $filename, $matches)) {
            return $matches[1];
        }
        return null;
    }

    // Các helper methods cho dependency analysis
    private function findModelUsage($content) { 
        $models = [];
        foreach ($this->models as $name => $path) {
            $className = $this->extractClassName(File::get($path));
            if ($className && Str::contains($content, $className)) {
                $models[] = $className;
            }
        }
        return $models;
    }

    private function findServiceUsage($content) { 
        $services = [];
        foreach ($this->services as $name => $path) {
            $className = $this->extractClassName(File::get($path));
            if ($className && Str::contains($content, $className)) {
                $services[] = $className;
            }
        }
        return $services;
    }

    private function findJobUsage($content) { 
        $jobs = [];
        foreach ($this->jobs as $name => $path) {
            $className = $this->extractClassName(File::get($path));
            if ($className && (Str::contains($content, $className) || Str::contains($content, "dispatch(new {$className}"))) {
                $jobs[] = $className;
            }
        }
        return $jobs;
    }

    private function findViewUsage($content) { 
        preg_match_all('/view\([\'"]([^\'"]+)[\'"]/', $content, $matches);
        return $matches[1] ?? [];
    }

    private function findRoutesPointingTo($className) { 
        $routes = [];
        foreach ($this->routes as $name => $path) {
            if (File::exists($path)) {
                $content = File::get($path);
                if (Str::contains($content, $className)) {
                    $routes[] = $name;
                }
            }
        }
        return $routes;
    }

    private function findLivewireView($content, $componentPath) { 
        // Tìm view được render bởi Livewire component
        if (preg_match('/render\([\'"]([^\'"]+)[\'"]/', $content, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function findComponentUsage($content) { 
        preg_match_all('/<x-([^>\s]+)/', $content, $matches);
        return $matches[1] ?? [];
    }

    private function findLivewireUsage($content) { 
        preg_match_all('/@livewire\([\'"]([^\'"]+)[\'"]/', $content, $matches);
        return $matches[1] ?? [];
    }

    private function findIncludeUsage($content) { 
        preg_match_all('/@include\([\'"]([^\'"]+)[\'"]/', $content, $matches);
        return $matches[1] ?? [];
    }

    private function findExtendsUsage($content) { 
        preg_match_all('/@extends\([\'"]([^\'"]+)[\'"]/', $content, $matches);
        return $matches[1] ?? [];
    }

    private function findControllerRoutes($content) { 
        preg_match_all('/([A-Z][a-zA-Z]+Controller)/', $content, $matches);
        return array_unique($matches[1] ?? []);
    }

    private function findLivewireRoutes($content) { 
        preg_match_all('/Livewire::component\([\'"]([^\'"]+)[\'"]/', $content, $matches);
        return $matches[1] ?? [];
    }

    private function findMiddlewareUsage($content) { 
        preg_match_all('/middleware\([\'"]([^\'"]+)[\'"]/', $content, $matches);
        return $matches[1] ?? [];
    }
}
