<?php
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\PrivateResponses;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\EncryptedPhotoInput::class);
        $middleware->web(prepend: [PrivateResponses::class]);
        $middleware->alias(['role' => EnsureRole::class]);
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'api_key', 'pages', 'document', 'answers', 'questions']);
        $exceptions->dontReport([ValidationException::class]);
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if($request->expectsJson())return response()->json(['message'=>'Sesi berakhir. Masuk kembali untuk melanjutkan.'],401);
        });
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            if($request->expectsJson())return response()->json(['message'=>'Data atau halaman tidak ditemukan.'],404);
        });
    })->create();
