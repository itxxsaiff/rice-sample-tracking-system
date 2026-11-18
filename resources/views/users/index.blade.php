<x-layouts.app title="Manage Users">
    <x-page-header title="Manage Users" subtitle="Create employee logins and control who can access the system.">
        <x-slot:actions>
            <a href="{{ route('users.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i> Add user</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <form method="GET" class="filter-bar" data-auto-submit>
            <div class="row g-2">
                <div class="col-12 col-md-6">
                    <div class="search-input"><i class="bi bi-search"></i>
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search name, email or phone">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select name="role" class="form-select">
                        <option value="">All roles</option>
                        @foreach (\App\Enums\UserRole::options() as $value => $label)
                            <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Any status</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Deactivated</option>
                    </select>
                </div>
            </div>
        </form>

        @if ($users->isEmpty())
            <x-empty-state icon="people" title="No users found" text="Try a different search, or add a new user." />
        @else
            <div class="table-responsive">
                <table class="table table-hover table-stack align-middle">
                    <thead><tr><th>Name</th><th>Role</th><th>Phone</th><th>Last sign-in</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td class="cell-primary">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar">{{ $user->initials() }}</span>
                                    <div class="min-w-0">
                                        <div class="cell-title">{{ $user->name }} @if ($user->is(auth()->user()))<span class="badge text-bg-light border ms-1">You</span>@endif</div>
                                        <div class="cell-sub text-truncate">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Role"><span>{{ $user->role->label() }}</span></td>
                            <td data-label="Phone"><span>{{ $user->phone ?: '—' }}</span></td>
                            <td data-label="Last sign-in"><span>{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</span></td>
                            <td data-label="Status"><span><x-status-badge :status="$user->is_active ? 'active' : 'inactive'" /></span></td>
                            <td class="cell-actions text-end text-nowrap">
                                <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i> Edit</a>
                                @can('toggleActive', $user)
                                    <form method="POST" action="{{ route('users.toggle-active', $user) }}" class="d-inline"
                                          data-confirm="{{ $user->is_active ? "{$user->name} will no longer be able to sign in. Their records stay in the system." : "{$user->name} will be able to sign in again." }}"
                                          data-confirm-title="{{ $user->is_active ? 'Deactivate account?' : 'Reactivate account?' }}"
                                          data-confirm-button="{{ $user->is_active ? 'Deactivate' : 'Reactivate' }}"
                                          data-confirm-variant="{{ $user->is_active ? 'danger' : 'primary' }}">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-sm {{ $user->is_active ? 'btn-outline-danger' : 'btn-soft' }}">
                                            <i class="bi bi-{{ $user->is_active ? 'person-slash' : 'person-check' }}"></i> {{ $user->is_active ? 'Deactivate' : 'Reactivate' }}
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if ($users->hasPages())<div class="card-footer">{{ $users->links() }}</div>@endif
        @endif
    </div>
</x-layouts.app>
