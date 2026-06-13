<?php

namespace App\Http\Middleware;


use Auth;
use Closure;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\App;
use PHPOpenSourceSaver\JWTAuth\Factory as JAuth;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenBlacklistedException;
use Exception;
use Illuminate\Support\Facades\Auth as AuthUser;
class JwtAuthMiddleware
{
    protected JAuth $auth;
    public function __construct(JAuth $auth)
    {

        Auth::shouldUse("api");
        $this->auth = $auth;

    }
    public function handle(Request $request, Closure $next)
    {


        try {
            JWTAuth::parseToken()->authenticate();
            $user = Auth::user();
            if (!$user->is_active) {
                Auth::logout();
                abort(401, "Your account has been disabled");
            }

        } catch (TokenExpiredException $e) {
            try {
                $token = JWTAuth::parseToken()->refresh();
                $response = $next($request);
                $response->headers->set('Authorization', "Bearer {$token}");
                return $response;
            } catch (\Throwable $th) {
                $message = $th->getMessage();
                abort(401, $message);
            }
        } catch (TokenBlacklistedException $e) {
            $reason = "Token blacklisted";
            $message = "User already logged out; reason: {$reason}.";
            abort(401, $message);
        } catch (Exception $e) {
            
            if (App::isProduction()) {
                abort(401, "Unauthorized Request. Please Login first");
            }
            abort(401, "JWT_AUTH_MIDDLEWARE ERROR" . $e->getMessage());



        }

        return $next($request);

    }
}




