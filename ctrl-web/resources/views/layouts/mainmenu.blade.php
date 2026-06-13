<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sistema Dubena Gás em Casa</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Bootstrap 3.3.4 -->
    <link href="{{URL::to('bootstrap/css/bootstrap.css')}}" rel="stylesheet" type="text/css" />
    {{-- <link href="{{URL::to('bootstrap/css/bootstrap-responsive.css')}}" rel="stylesheet" type="text/css" /> --}}
    <link href="{{URL::to('plugins/boostrap-table/css/bootstrap-table.min.css')}}" rel="stylesheet" type="text/css" />
    <!-- Font Awesome Icons -->
    <link href="{{URL::to('css/font-awesome.min.css')}}" rel="stylesheet" type="text/css" />
    <!-- Ionicons -->
    <link href="{{URL::to('css/ionicons.min.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{URL::to('plugins/datatables/dataTables.bootstrap.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{URL::to('dist/css/AdminLTE.min.css')}}" rel="stylesheet" type="text/css" />
    <link rel="icon" href="{{asset('logo_icon.svg')}}" type="image/x-icon" />

    <!-- AdminLTE Skins. We have chosen the skin-blue for this starter
        page. However, you can choose any other skin. Make sure you
        apply the skin class to the body tag so the changes take effect.
    -->
    <link href="{{URL::to('dist/css/skins/skin-blue.min.css')}}" rel="stylesheet" type="text/css" />
    <!-- Select2 -->
    <link href="{{URL::to('plugins/select2/css/select2.min.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{URL::to('plugins/datepicker1/css/bootstrap-datetimepicker.css')}}" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" type="text/css" href="{{URL::to('plugins/handsontable/dist/handsontable.full.css')}}">
    <link href="{{URL::to('plugins/selectize/css/selectize.bootstrap3.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{URL::to('plugins/bootstrap-multiselect/bootstrap-multiselect.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{URL::to('plugins/multi-select/css/multi-select.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{URL::to('plugins/tree-multiselect.min.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{URL::to('plugins/jstree/dist/themes/default/style.min.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{URL::to('plugins/chosen/chosen.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{URL::to('css/form.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{URL::to('css/custom.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{URL::to('css/checkboxes.css')}}" rel="stylesheet" type="text/css" />
    <!-- CSS novo Layout -->
    <link href="{{URL::to('css/novolayout.css')}}" rel="stylesheet" type="text/css" />
    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->

    <style>
        .crud_space {
            margin-bottom: 5px;
        }
    </style>

    <script src="{{URL::to('plugins/jQuery/jQuery-2.1.4.min.js')}}"></script>
    <script src="{{URL::to('js/custom.js')}}"></script>
    <script src="{{URL::to('js/customJquery.js')}}"></script>
    <script src="{{URL::to('js/customvalidacoes.js')}}"></script>
    <!-- Bootstrap 3.3.2 JS -->
    <script src="{{URL::to('bootstrap/js/bootstrap.min.js')}}" type="text/javascript"></script>
    <!-- AdminLTE App -->
    <script src="{{URL::to('dist/js/app.min.js')}}" type="text/javascript"></script>

    <script src="{{URL::to('plugins/boostrap-table/js/bootstrap-table.min.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('plugins/boostrap-table/locale/bootstrap-table-pt-BR.min.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('plugins/datatables/jquery.dataTables.min.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('plugins/datatables/dataTables.bootstrap.min.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('plugins/slimScroll/jquery.slimscroll.min.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('plugins/fastclick/fastclick.min.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('plugins/tree-multiselect.min.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('plugins/jstree/dist/jstree.min.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('plugins/datepicker1/moment/moment-with-locales.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('plugins/datepicker1/js/bootstrap-datetimepicker.min.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('plugins/custom_utils.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('plugins/bootbox.min.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('plugins/selectize/js/standalone/selectize.min.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('plugins/chosen/chosen.jquery.latin.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('js/jquery.mask.min.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('plugins/colorpicker/bootstrap-colorpicker.js')}}" type="text/javascript"></script>

    <script src="{{URL::to('js/variaveisGlobais.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('js/jqueryMaskMoney.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('js/convenio.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('plugins/input-mask/jquery.inputmask.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('js/shortcut.js')}}"></script>

    <script src="{{URL::to('js/date.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('js/cliente.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('js/checkboxes.min.js')}}" type="text/javascript"></script>
    <script src="{{URL::to('js/clipboard.min.js')}}" type="text/javascript"></script>
    <!-- <script src="http://www.datejs.com/build/date.js"></script> -->
    <script>
        root = '{{url("")}}';
        var urlDataTable = "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}";
    </script>
</head>

<body class="skin-blue layout-top-nav">
    <div class="wrapper">
        <!-- Main Header -->
        <header class="main-header">
            <!-- Header Navbar -->
            <nav class="navbar navbar-static-top">
                <div class="navbar-header">
                    <div class="navbar-brand">
                        <a style="color:#fff;" href="{{ URL::route('home') }}">
                            <img src="{{URL::to('img/logo_dubena.svg')}}" class="img-responsive" />
                        </a>
                    </div>
                    <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar-collapse">
                        <i class="fa fa-bars"></i>
                    </button>
                </div>
                <div class="collapse navbar-collapse pull-left" id="navbar-collapse">
                    <ul class="nav navbar-nav">
                        @if(count(Session::get('menu') ?: []))
                            @foreach(Session::get('menu') as $menu)
                                <?php echo $menu; ?>
                            @endforeach
                        @endif
                    </ul>
                </div>
                <!-- /.navbar-collapse -->
                <!-- Navbar Right Menu -->
                <div class="navbar-custom-menu">
                    <ul class="nav navbar-nav">
                        <li class="dropdown avaliacoes-menu notifications-menu">
                            <a href="#" class="dropdown-toggle label-app-count" data-toggle="dropdown">
                                <i class="fa fa-mobile fa-lg" aria-hidden="true"></i>
                                <span class="label label-blue avaliacoes-count"></span>
                            </a>
                            <ul class="dropdown-menu">
                                <li class="header avaliacoes-header">Você não possui nenhuma notificação de avaliação.</li>
                                <li>
                                    <ul class="menu notification-container avaliacoes-body">
                                    </ul>
                                </li>
                            </ul>
                        </li>
                        <!-- Messages: style can be found in dropdown.less-->
                        <!-- Notifications Menu -->
                        @if( count(Session::get('notificacoes') ?: []) > 0 )
                            <!-- {{$count = Session::get('notificacoes')->where('status','N')->count()}} -->
                            <li class="dropdown notifications-menu">
                                <a id="noti-bell" href="#" class="dropdown-toggle" data-toggle="dropdown">
                                    <i class="fa fa-bell-o fa-lg"></i>
                                    @if($count > 0)
                                        <span class="label label-blue count">{{$count}}</span>
                                    @else
                                        <span class="label label-blue count"></span>
                                    @endif
                                </a>
                                <ul class="dropdown-menu">
                                    @if($count > 0)
                                        <li class="header notificacoes">Voc&ecirc; possui {{$count}} notifica&ccedil;&atilde;o(&otilde;es) novas!</li>
                                    @else
                                        <li class="header notificacoes">Você não possui nenhuma notificação nova.</li>
                                    @endif
                                    <li>
                                        <ul class="menu notification-container custom-notify">
                                            @foreach(Session::get('notificacoes') as $alerta)
                                                @if ( isset($alerta->label) )
                                                    <li id="empresa_{{$alerta->id}}" class="custom-header">{{$alerta->label}}</li>
                                                @elseif ( isset($alerta->descricao) )
                                                    <li><a href="#" class="notify-alert"><?php echo $alerta->descricao;?></a></li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    </li>
                        @else
                            <li class="dropdown notifications-menu">
                                <a href="#" class="dropdown-toggle  label-count" data-toggle="dropdown">
                                    <i class="fa fa-bell-o fa-lg"></i>
                                    <span class="label label-blue count"></span>
                                </a>
                                <ul class="dropdown-menu">
                                    <li class="header notificacoes">Você não possui nenhuma notificação nova.</li>
                                    <li>
                                        <ul class="menu notification-container custom-notify">
                                        </ul>
                                    </li>
                        @endif
                                </ul>
                                </li>
                            </li>
                        </li>
                        <li class="dropdown user user-menu" style="vertical-align:middle;">
                            <!-- Menu Toggle Button -->
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown" style="padding-bottom:0px;padding-top:0px;vertical-align:middle;">
                                <!-- The user image in the navbar-->
                                @if(Session::get('empresa_padrao')->logo != null)
                                    <img id="imgInicial" class="img-circle" style="max-height:50px;" src="data:image/png;base64,{{ Session::get('empresa_padrao')->logo }}" alt="Logotipo" />
                                @else
                                    <img id="imgInicial" class="img-circle" style="max-height:50px;" src="{{URL::to('dist/img/userdefault.png')}}" alt="Logotipo"/>
                                @endif
                                <!-- hidden-xs hides the username on small devices so only the image appears. -->
                                <span class="hidden-xs">
                                    {{ Session::get('empresa_padrao')->nome_informal }}
                                </span>
                            </a>
                            <ul class="dropdown-menu" style="vertical-align:middle;">
                                <!-- The user image in the menu -->
                                <li class="user-header" style="height:auto;">
                                    @foreach (\Auth::user()->empresas as $empresa)
                                    <p>
                                        <!--{!! link_to_route('empresa.change', $empresa->razao_social, [$empresa->id])  !!}-->
                                        <a style="color:#fff;" href="{{ route('empresa.change',['id' => $empresa->id])}}"> {{ $empresa->nome_informal }} </a>
                                    </p>
                                    @endforeach
                                </li>
                                <!-- Menu Footer-->
                                <li class="user-footer">
                                    <div class="pull-right">
                                        Escolha a Empresa
                                    </div>
                                </li>
                            </ul>
                        </li>
                        <li class="dropdown user user-menu" style="vertical-align:middle;">
                            <!-- Menu Toggle Button -->
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown" style="padding-bottom:0px;padding-top:0px;vertical-align:middle;height:50px;">
                                <div class="profile">
                                    <div class="initial"></div>
                                    <!-- hidden-xs hides the username on small devices so only the image appears. -->
                                    <span class="hidden-xs">
                                        {{ \Auth::user()->name }}
                                    </span>
                                </div>
                            </a>
                            <ul class="dropdown-menu">
                                <!-- The user image in the menu -->
                                <li class="user-header">
                                    <div class="show-pic">
                                        <div class="show-profile"></div>
                                    </div>
                                    <p class="paragraph-name">
                                        {{ \Auth::user()->name }}
                                        <!--<small>LOBO</small><div class="show-pic"><div class="show-profile"></div></div>-->
                                    </p>
                                </li>
                                <!-- Menu Footer-->
                                <li class="user-footer">
                                    <div class="pull-left">
                                        {{ link_to_route('changepassword', 'Alterar senha')}}
                                    </div>
                                    <div class="pull-right">
                                        {{ link_to_route('logout', 'Sair')}}
                                    </div>
                                </li>
                            </ul>
                        </li>
                        <!-- Control Sidebar Toggle Button -->
                    </ul>
                </div>
            </nav>
        </header>
        <div class="content-wrapper">
            <!-- Main content -->
            <section class="content">
                @include('template.partials._message_success')
                @include('template.partials._message_info')
                @include('template.partials._message_danger')
                @if($errors->any())
                <div id="saveError" class="alert alert-danger alert-dismissable col-md-4" style="width:90%; margin-left:5%">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <span class="glyphicon glyphicon-remove"></span>
                    @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                    @endforeach
                </div>
                @endif


                <input id="empresa_documento" name="empresa_documento" type="text"
                    value="{{Session::get('empresa_padrao')->id}}" class="hidden" readonly />

                <input id="filtro_url" name="filtro_url" type="text"
                    value="{{\Input::get('index')}}" class="hidden" readonly />

                @yield('content')

            </section>
        </div>
    </div>
    <!-- ./wrapper -->
    @include('layouts.mainmenu_partial_js')
</body>

</html>
