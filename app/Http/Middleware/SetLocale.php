<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Chỉ set locale cho non-Livewire requests
        if (!$request->header('X-Livewire') && session()->has('locale')) {
            $locale = session()->get('locale');
            if (in_array($locale, ['en', 'vi'])) {
                App::setLocale($locale);
            }
        }

        return $next($request);
    }
}
