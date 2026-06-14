<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sistema Rastreamento de Frota Nacional Gás</title>
    <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
</head>
<body>
    <div class="container">
        <ul class="nav nav-pills">
            @if(\Auth::check())
                <li>
                    {{ link_to_route('monitora.logout', 'Logout')}}
                </li>
            @endif
        </ul>
        @yield('content')
    </div>
</body>
</html>
