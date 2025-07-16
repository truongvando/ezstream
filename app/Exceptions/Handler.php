<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Handle Redis connection errors with throttling
            if ($this->isRedisConnectionError($e)) {
                RedisConnectionHandler::handleConnectionError($e, 'Queue Worker');
                return false; // Don't report to default logger
            }
        });
    }

    /**
     * Check if the exception is a Redis connection error
     */
    private function isRedisConnectionError(Throwable $exception): bool
    {
        $message = $exception->getMessage();
        $trace = $exception->getTraceAsString();
        
        return (str_contains($message, 'errno=10053') ||
                str_contains($message, 'fwrite(): Send of') ||
                str_contains($message, 'Connection refused') ||
                str_contains($message, 'Connection reset')) &&
               (str_contains($trace, 'predis') ||
                str_contains($trace, 'RedisQueue') ||
                str_contains($trace, 'Redis\\Connections'));
    }
}
