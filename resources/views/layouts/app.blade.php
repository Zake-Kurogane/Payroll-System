<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Payroll System | @yield('title')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('image/logo.png') }}">

    @yield('vite')
    @vite(['resources/css/profile_drawer.css'])
    @stack('styles')
</head>

<body>
    <div class="shell">
        @include('partials.sidebar')

        <main class="main">
            @include('partials.topbar')

            @yield('content')
        </main>
    </div>

    @can('admin')
        <!-- Edit Profile Drawer (global) -->
        <div id="profileDrawer" class="profile-drawer" aria-hidden="true">
            <div id="profileDrawerOverlay" class="drawer__overlay"></div>

            <aside class="drawer__panel" role="dialog" aria-modal="true" aria-labelledby="profileDrawerTitle">
                <!-- Header -->
                <header class="drawer__head">
                    <div class="drawer__headText">
                        <div class="drawer__title" id="profileDrawerTitle">Edit Profile</div>
                        <div class="drawer__sub">Update display name and password.</div>
                    </div>

                    <button id="closeProfileDrawer" class="drawer__close" type="button" aria-label="Close">
                        &times;
                    </button>
                </header>

                <!-- Body -->
                <form id="profileForm" class="drawer__body" method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    @method('PUT')
                    <div class="grid2">
                        <div class="field">
                            <label for="pfFirstName">First name</label>
                            <input id="pfFirstName" name="first_name" type="text" placeholder="e.g., Juan"
                                value="{{ old('first_name', auth()->user()->first_name ?? '') }}" />
                        </div>

                        <div class="field">
                            <label for="pfMiddleName">Middle name</label>
                            <input id="pfMiddleName" name="middle_name" type="text" placeholder="e.g., D."
                                value="{{ old('middle_name', auth()->user()->middle_name ?? '') }}" />
                        </div>

                        <div class="field field--full">
                            <label for="pfLastName">Last name</label>
                            <input id="pfLastName" name="last_name" type="text" placeholder="e.g., Dela Cruz"
                                value="{{ old('last_name', auth()->user()->last_name ?? '') }}" />
                        </div>

                        <div class="field field--full">
                            <label for="pfUsername">Username</label>
                            <input id="pfUsername" name="name" type="text" placeholder="e.g., admin"
                                value="{{ old('name', auth()->user()->name ?? '') }}" />
                        </div>

                        <div class="field field--full">
                            <label for="pfEmail">Email</label>
                            <input id="pfEmail" name="email" type="email" placeholder="e.g., admin@company.com"
                                value="{{ old('email', auth()->user()->email ?? '') }}" />
                        </div>

                        <div class="field field--full">
                            <label for="pfRole">Role</label>
                            <input id="pfRole" type="text" value="{{ strtoupper(auth()->user()->role ?? 'ADMIN') }}" readonly disabled />
                            <div class="hint">Role is read-only.</div>
                        </div>
                    </div>

                    <div class="sectionTitle">Change password</div>

                    <div class="grid2">
                        <div class="field field--full">
                            <label for="pfCurrentPassword">Current password</label>
                            <input id="pfCurrentPassword" name="current_password" type="password"
                                placeholder="Enter current password" autocomplete="current-password" />
                        </div>

                        <div class="field">
                            <label for="pfNewPassword">New password</label>
                            <input id="pfNewPassword" name="new_password" type="password" placeholder="Enter new password"
                                autocomplete="new-password" />
                        </div>

                        <div class="field">
                            <label for="pfConfirmPassword">Confirm new password</label>
                            <input id="pfConfirmPassword" name="new_password_confirmation" type="password"
                                placeholder="Confirm new password" autocomplete="new-password" />
                        </div>

                        <div class="field field--full">
                            <div class="hint" id="pfError" style="display:none; color:#dc2626; font-weight:900;"></div>
                            @if ($errors->any())
                                <div class="hint" style="display:block; color:#dc2626; font-weight:900;">
                                    @foreach ($errors->all() as $error)
                                        <div>{{ $error }}</div>
                                    @endforeach
                                </div>
                            @endif
                            @if (session('success'))
                                <div class="hint" style="display:block; color:#0f766e; font-weight:900;">
                                    {{ session('success') }}
                                </div>
                            @endif
                        </div>
                    </div>
                </form>

                <!-- Footer -->
                <footer class="drawer__footer">
                    <button id="cancelProfileBtn" type="button" class="btn">Cancel</button>
                    <button type="submit" form="profileForm" class="btn btn--maroon">Save</button>
                </footer>
            </aside>
        </div>
    @endcan

    @yield('body_end')

    @can('admin')
        @if ($errors->any())
            <script>
                const pd = document.getElementById("profileDrawer");
                if (pd) {
                    pd.classList.add("is-open");
                    pd.setAttribute("aria-hidden", "false");
                }
            </script>
        @endif
    @endcan

    @if (session('success'))
        <script>
            alert(@json(session('success')));
        </script>
    @endif

    @stack('scripts')
</body>

</html>
