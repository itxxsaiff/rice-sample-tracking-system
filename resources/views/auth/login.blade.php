<x-layouts.guest title="Sign in">
    <div class="auth-card">
        <div class="text-center mb-4">
            <x-brand-mark :size="60" class="d-flex mx-auto mb-3" style="border-radius:18px" />
            <h1 class="h4 fw-bold mb-1">Rice Sample Tracking</h1>
            <p class="text-muted mb-0">Sign in to continue</p>
        </div>
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('login.store') }}" novalidate>
                    @csrf
                    <x-form.input name="email" type="email" label="Email address" required autofocus
                                  autocomplete="username" inputmode="email" autocapitalize="none" placeholder="you@example.com" />
                    <x-form.input name="password" type="password" label="Password" required autocomplete="current-password" />
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" @checked(old('remember'))>
                        <label class="form-check-label" for="remember">Keep me signed in on this device</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100" data-busy-text="Signing in…">Sign in</button>
                </form>
            </div>
        </div>
        <p class="text-center text-muted small mt-4 mb-0">Accounts are created by the administrator.</p>
    </div>
</x-layouts.guest>
