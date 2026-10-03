<?php

namespace App\Http\Controllers;

use App\Actions\CreateUserAction;
use App\Actions\RecordSecurityEventAction;
use App\Actions\UpdateUserAction;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\SecurityEvent;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();
        $role = UserRole::tryFrom($request->string('role')->toString());
        $status = $request->string('status')->toString();

        return view('users.index', [
            'users' => User::query()
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
                ->paginate(15)
                ->withQueryString(),
            'roles' => UserRole::cases(),
            'search' => $search,
            'selectedRole' => $role?->value,
            'selectedStatus' => $status,
        ]);
    }

    public function create(): View
    {
        return view('users.form', [
            'managedUser' => new User,
            'roles' => UserRole::cases(),
        ]);
    }

    public function store(
        StoreUserRequest $request,
        CreateUserAction $createUser,
        RecordSecurityEventAction $recordSecurityEvent,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $user = $createUser->handle($request->validated());
        $recordSecurityEvent->handle(
            SecurityEvent::UserCreated,
            $request,
            actor: $actor,
            subject: $user,
            metadata: [
                'channel' => 'web',
                'role' => $user->role->value,
                'is_active' => $user->is_active,
            ],
        );

        return redirect()->route('users.index')->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $user): View
    {
        return view('users.form', [
            'managedUser' => $user,
            'roles' => UserRole::cases(),
        ]);
    }

    public function update(
        UpdateUserRequest $request,
        User $user,
        UpdateUserAction $updateUser,
        RecordSecurityEventAction $recordSecurityEvent,
    ): RedirectResponse {
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
                'channel' => 'web',
                'profile_changed' => $previousName !== $updatedUser->name || $previousEmail !== $updatedUser->email,
                'role_changed' => $previousRole !== $updatedUser->role->value,
                'status_changed' => $previousStatus !== $updatedUser->is_active,
                'password_changed' => filled($data['password'] ?? null),
            ],
        );

        return redirect()->route('users.index')->with('success', 'Usuario actualizado correctamente.');
    }
}
