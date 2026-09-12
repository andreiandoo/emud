<?php

use App\Http\Middleware\AuthenticateCatalogApiKey;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: [
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
            '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
            '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
            '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22', '2400:cb00::/32',
            '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
            '2a06:98c0::/29', '2c0f:f248::/32',
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'catalog.api' => AuthenticateCatalogApiKey::class,
        ]);

        // The shop and the back office have separate sign-in screens, so neither route is named
        // "login". Sending a customer to the admin form, or an operator to the shop form, would
        // strand both after they authenticate.
        $middleware->redirectGuestsTo(fn (Request $request): string => $request->is('admin', 'admin/*')
            ? route('admin.login')
            : route('customer.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The catalog API answers failures in the same envelope as successes, so an integration
        // can branch on `error.code` instead of parsing prose or guessing from the status alone.
        // Scoped to /api so the storefront and the back office keep Laravel's own pages.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            [$status, $code] = match (true) {
                $e instanceof ValidationException => [422, 'VALIDATION_FAILED'],
                $e instanceof ModelNotFoundException => [404, 'NOT_FOUND'],
                $e instanceof NotFoundHttpException => [404, 'NOT_FOUND'],
                $e instanceof MethodNotAllowedHttpException => [405, 'METHOD_NOT_ALLOWED'],
                $e instanceof ThrottleRequestsException => [429, 'RATE_LIMITED'],
                $e instanceof AuthenticationException => [401, 'UNAUTHENTICATED'],
                $e instanceof HttpExceptionInterface => [$e->getStatusCode(), 'REQUEST_FAILED'],
                default => [500, 'SERVER_ERROR'],
            };

            $error = ['code' => $code, 'message' => match ($code) {
                'VALIDATION_FAILED' => 'The request parameters are not valid.',
                'NOT_FOUND' => 'No published record matches this request.',
                'METHOD_NOT_ALLOWED' => 'That method is not allowed on this endpoint.',
                'RATE_LIMITED' => 'Too many requests. Retry after the window resets.',
                'UNAUTHENTICATED' => 'A valid API key is required.',
                // A 500 says nothing about its cause: a resolver failure must not hand a
                // consumer a database or upstream exception to read.
                'SERVER_ERROR' => config('app.debug') ? $e->getMessage() : 'The request could not be completed.',
                default => $e->getMessage(),
            }];

            if ($e instanceof ValidationException) {
                $error['details'] = $e->errors();
            }

            $response = response()->json(['error' => $error], $status);

            if ($e instanceof ThrottleRequestsException && $retry = $e->getHeaders()['Retry-After'] ?? null) {
                $response->headers->set('Retry-After', (string) $retry);
            }

            return $response;
        });
    })->create();
