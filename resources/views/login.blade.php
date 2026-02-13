<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    @vite(['resources/css/styles.css', 'resources/js/script.js'])
    <title>Login | Aura Fortune G5 Traders Corporation</title>
</head>

<body>
    <main class="page">
        <section class="card" aria-label="Login card">

            <!-- LEFT PANEL -->
            <aside class="panel panel--left">
                <div class="left-content">
                    <img class="brand-logo" src="/image/logo.png" alt="Aura Fortune G5 Traders Corporation logo" />
                    <h1 class="brand-title">Aura Fortune</h1>
                    <h2 class="brand-subtitle">G5 Traders Corporation</h2>
                    <p class="brand-caption">
                        Secure access for authorized users only.
                    </p>
                </div>
            </aside>

            <!-- RIGHT PANEL -->
            <section class="panel panel--right">
                <div class="form-wrap">
                    <h3 class="form-title">LOG IN</h3>


                    @if ($errors->any())
                        <div class="server-errors" role="alert">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form id="loginForm" class="form" method="POST" action="{{ route('login.submit') }}"
                        autocomplete="on" novalidate>
                        @csrf

                        <label class="field">
                            <span class="field-label">Username</span>
                            <div class="input-wrap @error('username') is-invalid @enderror">
                                <span class="icon" aria-hidden="true">
                                    <svg class="icon__svg" viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                                        <path
                                            d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-3.33 0-8 1.67-8 5v1h16v-1c0-3.33-4.67-5-8-5z" />
                                    </svg>
                                </span>
                                <input id="username" name="username" type="text" placeholder="Enter username"
                                    autocomplete="username" value="{{ old('username') }}" required />
                            </div>

                            {{-- field-specific error --}}
                            @error('username')
                                <small class="error">{{ $message }}</small>
                            @else
                                <small class="error" id="userError"></small>
                            @enderror
                        </label>

                        <label class="field">
                            <span class="field-label">Password</span>
                            <div class="input-wrap @error('password') is-invalid @enderror">
                                <span class="icon" aria-hidden="true">
                                    <svg class="icon__svg" viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                                        <path
                                            d="M12 2a6 6 0 0 0-6 6v3H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2h-2V8a6 6 0 0 0-6-6zm-4 6a4 4 0 0 1 8 0v3H8V8zm-2 5h12v5H6v-5z" />
                                    </svg>
                                </span>
                                <input id="password" name="password" type="password" placeholder="Enter password"
                                    autocomplete="current-password" required />
                            </div>

                            {{-- field-specific error --}}
                            @error('password')
                                <small class="error">{{ $message }}</small>
                            @else
                                <small class="error" id="passError"></small>
                            @enderror
                        </label>

                        <button class="btn" type="submit">LOGIN</button>

                        <p class="hint" id="status" role="status" aria-live="polite"></p>
                    </form>
                </div>
            </section>

        </section>
    </main>
</body>

</html>
