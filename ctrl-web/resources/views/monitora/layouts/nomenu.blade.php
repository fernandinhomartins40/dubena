<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Administração | Dashboard</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>

    <!-- Bootstrap 3.3.4 -->
    <link href="{{URL::to('bootstrap/css/bootstrap.css')}}" rel="stylesheet">
    <link href="{{URL::to('bootstrap/css/bootstrap-responsive.css')}}" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="{{URL::to('css/novolayout.css')}}" rel="stylesheet" type="text/css"/>
    <!-- Ionicons -->
    <link href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <!-- Theme style -->
    <link href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css" rel="stylesheet" type="text/css" />
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
    <link href="{{URL::to('css/form.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{URL::to('css/novolayout.css')}}" rel="stylesheet" type="text/css"/>
    <link href="{{URL::to('css/custom.css')}}" rel="stylesheet" type="text/css" />
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
        body{
            height: 250px !important;
        }
    </style>


    </head>
    
    <body class="skin-blue layout-top-nav">
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
        </div><!-- /.content-wrapper -->
    </body>
</html>
