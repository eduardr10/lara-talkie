<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
    @livewireStyles
</head>

<body>
    @yield('content')
    @livewireScripts
</body>

</html>