@extends('layouts.master')

@section('content')
<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistema Dubena Gás em Casa</title>
    <!-- CSS -->
    <link rel="stylesheet" href="http://fonts.googleapis.com/css?family=Roboto:400,100,300,500">
    <link href="{{URL::to('bootstrap/css/bootstrap.css')}}" rel="stylesheet">
    <link href="{{URL::to('bootstrap/css/bootstrap-responsive.css')}}" rel="stylesheet">
    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css" rel="stylesheet">
    <link href="{{URL::to('bootstrap/css/login-style.css')}}" rel="stylesheet">
    <link href="{{URL::to('css/novolayout.css')}}" rel="stylesheet">
    <script src="{{URL::to('plugins/jQuery/jQuery-2.1.4.min.js')}}"></script>
    <script src="{{URL::to('dist/js/jquery.backstretch.min.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('bootstrap/js/bootstrap.min.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('plugins/bootbox.min.js')}}"></script>
</head>

<body>
    <!-- Top content -->
    <div class="top-content">
        <div class="inner-bg">
            <div class="row-novo">
            <div class="container">
                <div class="row">
                    <div class="col-sm-4 col-sm-offset-4 form-box">
                        <div class="form-top">
                            <div class="form-top-left">
                                <h3>Bem-vindo ao sistema Dubena Gás em Casa</h3>
                            </div>
                            <div class="brand-logo"></div>
                            <div class="form-top-left">
                                <p>Informe o usuário e senha para entrar</p>
                            </div>
                        </div>
                        <div class="form-bottom">
                            {!! Form::open(array('route' => 'handleLogin', 'method' => 'POST','id'=>'fmLogin')) !!}
                            <div class="form-group{{ $errors->has('email') ? ' has-error' : '' }}">
                                <input id="email" type="text" name="email" class="form-control" placeholder="Usu&aacute;rio" value="{{ old('email') }}"/>
                                @if ($errors->has('email'))
                                <span class="help-block">
                                    <strong>{{ $errors->first('email') }}</strong>
                                </span>
                                @endif
                            </div>
                            <div class="form-group{{ $errors->has('password') ? ' has-error' : '' }}">
                                <input id="password" type="password" name="password" class="form-control" placeholder="Senha"/>
                                @if ($errors->has('password'))
                                <span class="help-block">
                                    <strong>{{ $errors->first('password') }}</strong>
                                </span>
                                @endif
                            </div>
                            <div class="row">
                                <div class="col-xs-8">
                                </div><!-- /.col -->
                                <div class="col-xs-4">
                                    <button id="login" type="submit" class="btn btn-yellow btn-block btn-flat">Entrar</button>
                                </div><!-- /.col -->
                            </div>
                            {{Form::hidden('ativo','1')}}
                            {!! Form::close() !!}
                        </div>
                    </div>
                </div>
            <div class="brand-line"></div>
            </div>
            </div>
        </div>

    </div>

    <script>
        var submit = false;
        jQuery(document).ready(function() {
            if (window.top !== window.self)  {
                $(".top-content").html("");
                bootbox.alert('Não autorizado. Recarregue a página');
            }
            $(".brand-logo").css('background-image','url('+"{{URL::to('img/logo_dubena.svg')}}" + ')');
            $('#email').focus();

            $("#fmLogin").on('submit', function () {
                if(submit)
                    return false;
                submit = true;
            });

            @if($errors->has('error'))
                bootbox.alert("{{$errors->first('error')}}");
            @endif
        });
    </script>
</body>
@endsection
