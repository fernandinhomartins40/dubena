<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sistema Dubena Gás em Casa</title>
    <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link rel="icon" href="{{asset('logo_icon.svg')}}" type="image/x-icon" />
</head>
<body>
    <div class="container">
        <ul class="nav nav-pills">
            @if(\Auth::check())
            <li>
                {{ link_to_route('logout', 'Logout')}}
            </li>
            @endif
        </ul>
        @yield('content')
    </div>
</body>
</html>
