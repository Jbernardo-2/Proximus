<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateUserAction;
use App\Actions\RecordSecurityEventAction;
use App\Actions\UpdateUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\SecurityEvent;
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

    public function store(
        StoreUserRequest $request,
        CreateUserAction $createUser,
        RecordSecurityEventAction $recordSecurityEvent,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $user = $createUser->handle($request->validated());
        $recordSecurityEvent->handle(
            SecurityEvent::UserCreated,
            $request,
            actor: $actor,
            subject: $user,
            metadata: [
                'channel' => 'api',
                'role' => $user->role->value,
                'is_active' => $user->is_active,
            ],
        );

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    public function update(
        UpdateUserRequest $request,
        User $user,
        UpdateUserAction $updateUser,
        RecordSecurityEventAction $recordSecurityEvent,
    ): UserResource {
        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validated();
        $previousName = $user->name;
        $previousEmail = $user->email;
        $previousRole = $user->role->value;
        $previousStatus = $user->is_active;
        $updatedUser = $updateUser->handle($actor, $user, $data);
        $recordSecurityEvent->handle(
            SecurityEvent::UserUpdated,
            $request,
            actor: $actor,
            subject: $updatedUser,
            metadata: [
                'channel' => 'api',
                'profile_changed' => $previousName !== $updatedUser->name || $previousEmail !== $updatedUser->email,
                'role_changed' => $previousRole !== $updatedUser->role->value,
                'status_changed' => $previousStatus !== $updatedUser->is_active,
                'password_changed' => filled($data['password'] ?? null),
            ],
        );

        return new UserResource($updatedUser);
    }
}
