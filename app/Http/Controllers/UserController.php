<?php

namespace App\Http\Controllers;

use App\Actions\CreateUserAction;
use App\Actions\UpdateUserAction;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
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

    public function store(StoreUserRequest $request, CreateUserAction $createUser): RedirectResponse
    {
        $createUser->handle($request->validated());

        return redirect()->route('users.index')->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $user): View
    {
        return view('users.form', [
            'managedUser' => $user,
            'roles' => UserRole::cases(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $updateUser): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $updateUser->handle($actor, $user, $request->validated());

        return redirect()->route('users.index')->with('success', 'Usuario actualizado correctamente.');
    }
}
