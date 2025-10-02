<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Http\JsonResponse;

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
            //
        });
    }
    public function render($request, Throwable $exception): \Illuminate\Http\Response|JsonResponse|\Symfony\Component\HttpFoundation\Response|\Illuminate\Http\RedirectResponse
    {
        // Если запрос ожидает JSON (API)
        if ($request->expectsJson() || $request->is('api/*')) {

            // Валидационные ошибки
            if ($exception instanceof ValidationException) {
                return response()->json([
                    'message' => 'Ошибка валидации',
                    'errors'  => $exception->errors(),
                ], 422);
            }

            // Неавторизованный
            if ($exception instanceof AuthenticationException) {
                return response()->json([
                    'message' => 'Не авторизован',
                ], 401);
            }

            // HTTP исключения (404, 403, ...)
            if ($exception instanceof HttpException) {
                return response()->json([
                    'message' => $exception->getMessage() ?: 'Ошибка HTTP',
                ], $exception->getStatusCode());
            }

            // Любые другие исключения
            return response()->json([
                'message' => $exception->getMessage() ?: 'Внутренняя ошибка сервера',
            ], 500);
        }

        // Для обычных web-запросов оставляем стандартное поведение
        return parent::render($request, $exception);
    }

    protected function unauthenticated($request, AuthenticationException $exception): \Illuminate\Http\Response|JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Не авторизован'], 401);
        }

        // Для web оставляем редирект
        return redirect()->guest(route('login'));
    }
}
