<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sistema Monitoramento de Frota - Gás em Casa</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Bootstrap 3.3.4 -->
    <link href="{{URL::to('bootstrap/css/bootstrap.css')}}" rel="stylesheet">
    <link href="{{URL::to('bootstrap/css/bootstrap-responsive.css')}}" rel="stylesheet">
    <link href="{{URL::to('plugins/boostrap-table/css/bootstrap-table.min.css')}}" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link href="{{URL::to('css/font-awesome.min.css')}}" rel="stylesheet" type="text/css" />
    <!-- Ionicons -->
    <link href="{{URL::to('css/ionicons.min.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{URL::to('plugins/datatables/dataTables.bootstrap.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{URL::to('dist/css/AdminLTE.min.css')}}" rel="stylesheet" type="text/css" />
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
    <link href="{{URL::to('css/checkboxes.css')}}" rel="stylesheet" type="text/css"/>
    <!-- CSS novo Layout -->
    <link href="{{URL::to('css/novolayout.css')}}" rel="stylesheet" type="text/css"/>
    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
        <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->

        <style>
            .crud_space {
                margin-bottom:5px;
            }
        </style>

        <script src="{{URL::to('plugins/jQuery/jQuery-2.1.4.min.js')}}"></script>
        <script src="{{URL::to('js/custom.js')}}"></script>
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
        <script src="{{URL::to('plugins/fastclick/fastclick.min.js')}}"></script>
        <script src="{{URL::to('plugins/tree-multiselect.min.js')}}"></script>
        <script src="{{URL::to('plugins/jstree/dist/jstree.min.js')}}"></script>
        <script src="{{URL::to('plugins/datepicker1/moment/moment-with-locales.js')}}" type="text/javascript"></script>
        <script src="{{URL::to('plugins/datepicker1/js/bootstrap-datetimepicker.min.js')}}" type="text/javascript"></script>
        <script src="{{URL::to('plugins/custom_utils.js')}}"></script>
        <script src="{{URL::to('plugins/bootbox.min.js')}}"></script>
        <script src="{{URL::to('plugins/selectize/js/standalone/selectize.min.js')}}"></script>
        <script src="{{URL::to('plugins/chosen/chosen.jquery.latin.js')}}"></script>
        <script src="{{URL::to('js/jquery.mask.min.js')}}"></script>
        <script src="{{URL::to('plugins/colorpicker/bootstrap-colorpicker.js')}}"></script>

        <script src="{{URL::to('js/variaveisGlobais.js')}}"></script>
        <script src="{{URL::to('js/jqueryMaskMoney.js')}}"></script>
        <script src="{{URL::to('plugins/input-mask/jquery.inputmask.js')}}"></script>
        <script src="{{URL::to('js/shortcut.js')}}"></script>

        <script src="{{URL::to('js/checkboxes.min.js')}}"></script>
        <script src="{{URL::to('js/clipboard.min.js')}}"></script>
        <!-- <script src="http://www.datejs.com/build/date.js"></script> -->
        <script>
            root = '{{url("")}}';
            var urlDataTable = "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}";
        </script>
    </head>
    <!--
    BODY TAG OPTIONS:
    =================
    Apply one or more of the following classes to get the
    desired effect
    |---------------------------------------------------------|
    | SKINS         | skin-blue                               |
    |               | skin-black                              |
    |               | skin-purple                             |
    |               | skin-yellow                             |
    |               | skin-red                                |
    |               | skin-green                              |
    |---------------------------------------------------------|
    |LAYOUT OPTIONS | fixed                                   |
    |               | layout-boxed                            |
    |               | layout-top-nav                          |
    |               | sidebar-collapse                        |
    |               | sidebar-mini                            |
    |---------------------------------------------------------|
-->
<body class="skin-blue layout-top-nav">
    <div class="wrapper">
        <!-- Main Header -->
        <header class="main-header">
            <!-- Header Navbar -->
            <nav class="navbar navbar-static-top">
                <div class="navbar-header">
                    <div class="navbar-brand">
                        <a style="color:#fff;" href="{{ URL::route('monitora.home') }}">
                            <img src="{{URL::to('img/logo_ngb.png')}}" class="img-responsive"/>
                            {{-- <div class="logo img-responsive"></div> --}}
                        </a>
                    </div>
                    <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar-collapse">
                        <i class="fa fa-bars"></i>
                    </button>
                </div>
                <div class="collapse navbar-collapse pull-left" id="navbar-collapse">
                    <ul class="nav navbar-nav">

                        @foreach(Session::get('menu') as $item)
                        <?php echo $item; ?>
                        @endforeach

                    </ul>
                </div><!-- /.navbar-collapse -->
                <!-- Navbar Right Menu -->
                <div class="navbar-custom-menu">
                    <ul class="nav navbar-nav">
                        <!-- Messages: style can be found in dropdown.less-->
                        <!-- Notifications Menu -->
                            <!--
                            <li class="dropdown notifications-menu">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                            <i class="fa fa-bell-o"></i>
                            <span class="label label-warning">1</span>
                          </a>
                          <ul class="dropdown-menu">
                          <li class="header">Voc&ecirc; tem 1 notifica&ccedil;&atilde;o(&otilde;es)</li>
                          <li>
                          <ul class="menu">
                          <li>
                          <a href="#">
                          <i class="fa fa-users text-aqua"></i>Teste de Notifica&ccedil;&atilde;o
                        </a>
                      </li>
                    </ul>
                  </li>
                  <li class="footer"><a href="#">Ver todas</a></li>
                </ul>
                </li>
            -->
            <!-- Tasks Menu -->
            <!-- User Account Menu -->
            <li class="dropdown user user-menu" style="vertical-align:middle;">
                <!-- Menu Toggle Button -->
                <a href="#" class="dropdown-toggle" data-toggle="dropdown" style="padding-bottom:0px;padding-top:0px;vertical-align:middle;">
                    <!-- The user image in the navbar-->
                    @if(Session::get('empresa_padrao')->logo != null)
                    <img id="imgInicial" class="img-circle" style="max-height:50px;" src="data:image/png;base64,{{ Session::get('empresa_padrao')->logo }}" alt="Logotipo"/>
                    @else
                    <img id="imgInicial" class="img-circle"  style="max-height:50px;" src="{{URL::to('dist/img/userdefault.png')}}" alt="Logotipo"/>
                    @endif
                    <!-- hidden-xs hides the username on small devices so only the image appears. -->
                    <span class="hidden-xs">
                        {{ Session::get('empresa_padrao')->nome_informal }}
                    </span>
                </a>
                <ul class="dropdown-menu" style="vertical-align:middle;">
                    <!-- The user image in the menu -->
                    <li class="user-header" style="height:auto;">
                                        <!--
                                        <img src="{{URL::to('dist/img/userdefault.png')}}" class="img-circle" alt="Foto"/>
                                    -->
                                    @foreach (\Auth::user()->empresas as $empresa)
                                    <p>
                                            <!--
                                            {!! link_to_route('monitora.empresa.change', $empresa->razao_social, [$empresa->id])  !!}
                                        -->
                                        <a style="color:#fff;" href="{{ route('monitora.empresa.change',['id' => $empresa->id])}}"> {{ $empresa->nome_informal }} </a>
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
                                <!-- The user image in the navbar-->
                                <div class="profile"><div class="initial"></div>
                                    <!--
                                    <img src="{{URL::to('dist/img/userdefault.png')}}" class="user-image" alt="Foto"/> src="{{URL::to('dist/img/userdefault.png')}}" alt="Foto"
                                -->
                                <!-- hidden-xs hides the username on small devices so only the image appears. -->
                                <span class="hidden-xs">
                                    {{ \Auth::user()->name }}
                                </span>
                            </div>
                        </a>
                        <ul class="dropdown-menu">
                            <!-- The user image in the menu -->
                            <li class="user-header">
                                @if(\Auth::user()->foto != null)
                                <img id="imgInicial" class="img-circle" src="data:image/png;base64,{{ base64_encode(\Auth::user()->foto) }}" alt="Foto"/>
                                @else
                                <div class="show-pic"><div class="show-profile"></div></div>
                                @endif
                                <p class="paragraph-name">
                                    {{ \Auth::user()->name }}
                                            <!--
                                            <small>LOBO</small><div class="show-pic"><div class="show-profile"></div></div>
                                        -->
                                    </p>
                                </li>
                                <!-- Menu Footer-->
                                <li class="user-footer">
                                    <div class="pull-left">
                                        {{ link_to_route('monitora.changepassword', 'Alterar senha')}}
                                    </div>
                                    <div class="pull-right">
                                        {{ link_to_route('monitora.logout', 'Sair')}}
                                    </div>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>
        </header>
        <div class="content-wrapper">
            <!-- Main content -->
            <section class="content">

                @include('monitora.template.partials._message_success')
                @include('monitora.template.partials._message_info')
                @include('monitora.template.partials._message_danger')

                @if($errors->any())
                <div id="saveError" class="alert alert-danger alert-dismissable col-md-4" style="width:90%; margin-left:5%">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <span class="glyphicon glyphicon-remove"></span>
                    @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                    @endforeach
                </div>
                @endif

                @yield('content')

            </section><!-- /.content -->
            @if(str_contains(Request::url(), '/home'))
            <div class="botijoes-home"></div>
            @endif
        </div>
        </div><!-- ./wrapper -->
        <script>
            $(".botijoes-home").css('background-image','url('+"{{URL::to('img/home_botijoes.png')}}" + ')');
            {{ $vnativacreate = str_contains(Request::url(), 'vendaativa') ? true : false}}
            @if((!str_contains(Request::url(), 'edit') && !isset($show)) || !$vnativacreate)
                @if(str_contains(Request::url(), '/create'))
                    $("#ativo").prop('checked', true);
                @elseif(str_contains(Request::url(),'report'))
                    $("#ativo").prop('checked', true);
                @endif
            @endif

            var str = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
            var name = "{{ Auth::user()->name }}";
            if(typeof name !== "undefined"){
                var h = parseInt(str.indexOf(name.charAt(0))) * 12;
                var s = 10 + 2.5 * parseInt(str.indexOf(name.charAt(0)));
                var iniciais = {h,s};
                var nome = name.charAt(0);
                $('.initial').append('<div id="initial" style="vertical-align: middle;background-color: #154295;" class="user-initial"><div>' + nome + '</div></div>');
                $('.show-profile').append('<div id="initial" style="background-color: #154295;" class="initial-show"><div>' + nome + '</div></div>');
            }
            @if(isset($show))
            $("#buscarEndereco").attr('disabled', 'disabled');
            $(document).ready(function($) {
                $(".btn-nw-registro").attr('disabled', 'disabled');
                $('input[type=submit]').hide();
            });
            @endif
        </script>
    </body>
    </html>
