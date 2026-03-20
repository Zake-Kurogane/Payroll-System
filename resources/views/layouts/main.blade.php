<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payroll System | @yield('title')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('image/logo.png') }}">

    @yield('vite')
</head>

<body>
    <div class="shell">
        @include('partials.sidebar')

        <main class="main">
            @include('partials.topbar')

            @yield('content')
        </main>
    </div>

    @yield('body_end')

    @stack('scripts')
</body>

</html>
