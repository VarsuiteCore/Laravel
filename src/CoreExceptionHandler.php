<?php

namespace VarsuiteCore;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\DB;

class CoreExceptionHandler implements ExceptionHandler
{
    protected static $registered = false;
    protected static $reservedMemory;

    protected ExceptionHandler $originalHandler;

    public function __construct(ExceptionHandler $originalHandler)
    {
        $this->originalHandler = $originalHandler;

        $this->registerFatalErrorHandler();
    }

    public function report(\Throwable $e)
    {
        try {
            // Record ready for sending up to Core
            $error = [
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'occurrences' => 1,
                'last_occurrence' => time()
            ];

            // Check if error already exists
            $existingErrorLog = DB::table('vscore_error_logs')
                ->where('code', $error['code'])
                ->where('message', $error['message'])
                ->where('file', $error['file'])
                ->where('line', $error['line'])
                ->first();

            if ($existingErrorLog) {
                // Update existing record
                DB::table('vscore_error_logs')
                    ->where('id', $existingErrorLog->id)
                    ->update([
                        'occurrences' => $existingErrorLog->occurrences + 1,
                        'last_occurrence' => $error['last_occurrence']
                    ]);
            } else {
                // Insert new record
                DB::table('vscore_error_logs')->insert($error);
            }

        } catch (\Throwable $e) {
            // Log the database error for debugging
            \Log::error('CoreExceptionHandler database error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        // Call the original handler
        return $this->originalHandler->report($e);
    }

    public function render($request, \Throwable $e)
    {
        return $this->originalHandler->render($request, $e);
    }

    public function renderForConsole($output, \Throwable $e)
    {
        return $this->originalHandler->renderForConsole($output, $e);
    }

    public function shouldReport(\Throwable $e)
    {
        return $this->originalHandler->shouldReport($e);
    }

    /**
     * Handle PHP errors (warnings, notices, etc.)
     */
    public function handlePhpError($level, $message, $file = '', $line = 0, $context = [])
    {
        // Convert error to exception and log it
        $exception = new \ErrorException($message, 0, $level, $file, $line);
        if ($this->shouldReport($exception)) {
            $this->report($exception);
        }

        // Don't interfere with normal error handling
        return false;
    }

    /**
     * Handle fatal errors during shutdown
     */
    public function handleShutdown()
    {
        // Free reserved memory immediately
        static::$reservedMemory = '';
        
        $error = error_get_last();

        if ($error && in_array($error['type'], [
                E_ERROR,
                E_CORE_ERROR,
                E_COMPILE_ERROR,
                E_PARSE,
                E_RECOVERABLE_ERROR,
                E_USER_ERROR
            ])) {
            $exception = new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );
            if ($this->shouldReport($exception)) {
                $this->report($exception);
            }
        }
    }

    /**
     * Handle uncaught exceptions
     */
    public function handleUncaughtException(\Throwable $exception)
    {
        if ($this->shouldReport($exception)) {
            $this->report($exception);
        }
    }

    private function registerFatalErrorHandler()
    {
        if (static::$registered) {
            return;
        }

        static::$registered = true;
        
        // Reserve memory for fatal error handling
        static::$reservedMemory = str_repeat('x', 32768); // 32KB
        
        // Register error handler for non-fatal errors
        set_error_handler([$this, 'handlePhpError']);

        // Register shutdown handler for fatal errors
        register_shutdown_function([$this, 'handleShutdown']);

        // Register exception handler for uncaught exceptions
        set_exception_handler([$this, 'handleUncaughtException']);
    }
}