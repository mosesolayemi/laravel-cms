{{--
    The package's own document shell, used when a layout names none and the
    host has not configured `cms.rendering.shell`. Deliberately bare: a site
    points its layouts at a shell carrying its own <head> and assets.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    @hasSection('meta_description')
        <meta name="description" content="@yield('meta_description')">
    @endif
    @include('cms::partials.styles')
    @stack('cms_head')
</head>
<body class="cms-body">
    @yield('cms_header')

    <main>
        @yield('content')
    </main>

    @yield('cms_footer')

    @stack('cms_body_end')
    @stack('scripts')
</body>
</html>
