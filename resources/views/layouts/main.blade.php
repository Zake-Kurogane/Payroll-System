<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payroll System | @yield('title')</title>

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
