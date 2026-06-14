<?php

namespace App\Http\Controllers\Api;

use App\Core\GetIndexRequest;
use App\Core\Response200WithPagination;
use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Schema;
class UserController extends Controller
{
    #[GetIndexRequest(
        tags: [USER_TAG],
        path: "/v1/users",
        operationId: "UserController@index",
        summary: "Show list of users. Only Administrator System able to run this action",
        security: [Auth_JWT],

        filters: [
            new OA\Parameter(
                in: "query",
                name: "filter[role_id]",
                description: "filter by role id",
                schema: new Schema(type: "string", format: "uuid")

            ),
            new OA\Parameter(
                in: "query",
                name: "filter[is_active]",
                description: "filter by active",
                schema: new Schema(type: "boolean")
            ),
        ]
    )]
    #[Response200WithPagination(ref: "schemas/user_resource.yaml", description: "User list")]
    #[ResponseDefault()]

    public function index(UserRequest $request)
    {
        $models = UserResource::collection(User::
            accounts($request->only(["search", "filter", "sort-by", "sort-dir"]))
            ->paginate());
        return $models;
    }

    #[OA\Post(
        tags: [USER_TAG],
        path: "/v1/user",
        operationId: "UserController@store",
        summary: "Create new User. Only Administrator System able to run this action",

        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                ref: "schemas/new_user_request.yaml"
            )

        ),
        security: [Auth_JWT]

    )]
    #[Response2xx(201, "User created", ref: "schemas/user_resource.yaml")]
    #[ResponseDefault()]
    public function store(UserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'data' => new UserResource($user),
        ], 201);
    }


    #[OA\Patch(
        tags: [USER_TAG],
        path: "/v1/user/{user}",
        operationId: "UserController@update",
        summary: "Update user data. Only Administrator System able to run this action",
        parameters: [
            new OA\Parameter(
                in: "path",
                name: "user",
                schema: new Schema(type: 'string', format: "uuid")
            )
        ],
        requestBody: new RequestBody(
            content: new OA\JsonContent(ref: "schemas/update_user.yaml")
        ),
        security: [Auth_JWT]

    )]
    #[Response2xx(description: "User data updated.")]
    #[ResponseDefault()]
    public function update(UserRequest $request, User $user)
    {
        // $this->authorizeAdminSistem($request);

        $validated = $request->validated();

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => new UserResource($user)
        ]);
    }



}
