<x-layouts.app title="My Account">
    <x-page-header title="My Account" subtitle="Update your details and password." />

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h2><i class="bi bi-person-circle text-primary me-2"></i>Profile</h2></div>
                <form method="POST" action="{{ route('profile.update') }}" class="card-body">
                    @csrf @method('PUT')
                    <x-form.input name="name" label="Full name" :value="$user->name" required autocomplete="name" />
                    <x-form.input name="email" type="email" label="Email (used to sign in)" :value="$user->email" required inputmode="email" autocapitalize="none" />
                    <x-form.input name="phone" type="tel" label="Phone" :value="$user->phone" inputmode="tel" />
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted">Role: <strong>{{ $user->role->label() }}</strong></span>
                        <button class="btn btn-primary">Save details</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h2><i class="bi bi-shield-lock text-primary me-2"></i>Change password</h2></div>
                <form method="POST" action="{{ route('profile.password') }}" class="card-body">
                    @csrf @method('PUT')
                    <x-form.input name="current_password" type="password" label="Current password" required autocomplete="current-password" />
                    <x-form.input name="password" type="password" label="New password" required autocomplete="new-password" hint="At least 8 characters." />
                    <x-form.input name="password_confirmation" type="password" label="Confirm new password" required autocomplete="new-password" />
                    <div class="text-end"><button class="btn btn-primary">Update password</button></div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
