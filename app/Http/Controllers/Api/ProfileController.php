<?php

namespace App\Http\Controllers\Api;

use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Auth;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{

    #[OA\Put(
        tags: [PROFILE_TAG],
        path: "/v1/profile",
        operationId: "ProfileController@updateProfile",
        summary: "Update current user profile",
        security: [Auth_JWT]

    )]
    #[OA\RequestBody(
        content: new OA\JsonContent(ref: "schemas/update_profile_request.yaml")
    )]
    #[Response2xx(200, ref: "user_resource.yaml")]
    #[ResponseDefault()]
    public function updateProfile(UserProfileRequest $userProfileRequest)
    {
        $user = Auth::user();
        $data = $userProfileRequest->validated();
        $user->update($data);
        return new UserResource($user);
    }
    #[OA\Put(
        tags: [PROFILE_TAG],
        path: "/v1/profile/password",
        operationId: "ProfileController@changePassword",
        summary: "Update current user profile",
        security: [Auth_JWT]

    )]
    #[OA\RequestBody(
        content: new OA\JsonContent(ref: "schemas/update_profile_password_request.yaml")
    )]
    #[Response2xx(204)]
    #[ResponseDefault()]
    public function changePassword(UserProfileRequest $userProfileRequest)
    {
        $user = Auth::user();
        $data = $userProfileRequest->validated();
        $user->update($data);
        return new UserResource($user);
    }
}