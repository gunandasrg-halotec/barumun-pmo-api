<?php

namespace App\Http\Controllers\Api;

use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\LoginResource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use OpenApi\Attributes as OA;
class AuthController extends Controller
{
    #[OA\Post(
        tags: [AUTHENTICATION_TAG],
        path: "/v1/auth/login",
        description: "Login as user",
        summary: "Authenticate user ",
        operationId: "AuthController@login"

    )]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            ref: "schemas/login_request.yaml"
        )
    )]
    #[OA\Response(response: 200, description: "Login accepted",
        content: new OA\JsonContent(ref: "schemas/login_resource.yaml"))]
    #[ResponseDefault()]
    public function login(LoginRequest $loginRequest): JsonResponse
    {

        $token = $loginRequest->authenticate();
        $user = Auth::user();
        $user->update(["last_login_at"=>Carbon::now()]);
        $user = new LoginResource($user);
        return response()->json([
            "user" => $user,
            "token" => $token
        ], status: 200, headers: ['Authorization' => "Bearer {$token}"]);
    }

    #[OA\Get(
        tags: [AUTHENTICATION_TAG],
        operationId: "AuthController@me",
        path: "/v1/auth/me",
        description: "Get current user info",
        security: [Auth_JWT],
        responses: [
            new OA\Response(
                response: 200,
                description: "OK",
                content: new OA\JsonContent(ref: "schemas/login_resource.yaml")
            )
        ]


    )]
    #[ResponseDefault()]

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('role');

        return response()->json(new LoginResource($user));
    }

    #[OA\Post(
        tags: [AUTHENTICATION_TAG],
        path: "/v1/auth/logout",
        operationId: "AuthController@logout",
        summary: "Logout from system",
        responses: [
            new Response2xx(204, "successfully logged out from system")
        ],
        security: [Auth_JWT]
    )]

    public function logout(Request $request)
    {
        Auth::logout();
        return response(status: 204);
    }
}
