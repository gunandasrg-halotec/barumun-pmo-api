<?php


use App\Core\ExceptionResponse;
use App\Enums\DeliveryProcessLogEnum;
use App\Http\Middleware\ApiResponseHeader;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\KeyAuthMiddleware;
use App\Http\Middleware\PinAuthMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;



return Application::configure(basePath: dirname(__DIR__))

    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->alias([
            "jwtAuth" => JwtAuthMiddleware::class,
            "pinAuth" => PinAuthMiddleware::class,
        ]);

        $middleware->api([ApiResponseHeader::class]);
        // $middleware->appendToGroup("api", [ApiResponseHeader::class]);
        $middleware->web(remove: [
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        ]);
    })
    ->withExceptions(
        function (Exceptions $exceptions) {


            $exceptions->context(fn() => [
                'current_route' => request()?->route()?->uri(),
                "exception_type" => get_class($exceptions),
                'user' => auth()?->user()?->display_name,
            ]);

            if (request()->is("api/*")) {

                ExceptionResponse::setReponse($exceptions);
            }


        }
    )
    ->withEvents(discover: [


    ])
    ->create();
