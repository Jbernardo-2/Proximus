<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateUserAction;
use App\Actions\UpdateUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = $request->string('search')->trim()->toString();
        $role = UserRole::tryFrom($request->string('role')->toString());
        $status = $request->string('status')->toString();

        $users = User::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($role !== null, fn ($query) => $query->where('role', $role->value))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request, CreateUserAction $createUser): JsonResponse
    {
        $user = $createUser->handle($request->validated());

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $updateUser): UserResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new UserResource($updateUser->handle($actor, $user, $request->validated()));
    }
}
