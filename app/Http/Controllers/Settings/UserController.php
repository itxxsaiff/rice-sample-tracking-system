<?php

namespace App\Http\Controllers\Settings;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->input('status') === 'active'))
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate(20)->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.create', ['user' => new User(['name' => ''])]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = new User($request->safe()->only(['name', 'email', 'phone', 'password']));
        $user->role = UserRole::from($request->validated('role'));
        $user->is_active = true;
        $user->save();

        return redirect()->route('users.index')->with('success', "{$user->name}'s account has been created. Share the email and password with them.");
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('users.edit', compact('user'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->fill($request->safe()->only(['name', 'email', 'phone']));

        if ($request->filled('password')) {
            $user->password = $request->validated('password');
        }

        if ($request->has('role') && $request->user()->can('changeRole', $user)) {
            $user->role = UserRole::from($request->validated('role'));
        }

        $user->save();

        return redirect()->route('users.index')->with('success', "{$user->name}'s account has been updated.");
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $this->authorize('toggleActive', $user);

        $user->is_active = ! $user->is_active;
        $user->save();

        return back()->with('success', $user->is_active
            ? "{$user->name} can sign in again."
            : "{$user->name} has been deactivated and can no longer sign in.");
    }
}
