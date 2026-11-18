@php $editing = $user->exists; @endphp
<div class="form-section">
    <div class="form-section-title"><i class="bi bi-person"></i> Account details</div>
    <div class="row">
        <x-form.input class="col-md-6 mb-3" name="name" label="Full name" :value="$user->name" required autocomplete="off" />
        <x-form.input class="col-md-6 mb-3" name="phone" type="tel" label="Phone" :value="$user->phone" inputmode="tel" />
        <x-form.input class="col-md-6 mb-3" name="email" type="email" label="Email (used to sign in)" :value="$user->email" required inputmode="email" autocapitalize="none" autocomplete="off" />
        @if (! $editing || auth()->user()->can('changeRole', $user))
            <x-form.select class="col-md-6 mb-3" name="role" label="Role" :options="\App\Enums\UserRole::options()" :value="$user->role ?? 'employee'" required
                           hint="Employees manage samples, dispatches and loading. Admins can also manage users." />
        @else
            <div class="col-md-6 mb-3">
                <label class="form-label">Role</label>
                <input class="form-control" value="{{ $user->role->label() }}" disabled>
                <div class="form-text">You cannot change your own role.</div>
            </div>
        @endif
    </div>
</div>
<div class="form-section">
    <div class="form-section-title"><i class="bi bi-key"></i> {{ $editing ? 'Reset password' : 'Password' }}</div>
    @if ($editing)<p class="small text-muted mt-n2">Leave blank to keep the current password.</p>@endif
    <div class="row">
        <x-form.input class="col-md-6 mb-3" name="password" type="password" label="Password" :required="! $editing" autocomplete="new-password" hint="At least 8 characters." />
        <x-form.input class="col-md-6 mb-3" name="password_confirmation" type="password" label="Confirm password" :required="! $editing" autocomplete="new-password" />
    </div>
</div>
