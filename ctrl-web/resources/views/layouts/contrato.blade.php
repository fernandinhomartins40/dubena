<!DOCTYPE html>
<html>
<head>
    <link href="{{ public_path('css/custom.css') }}" rel="stylesheet">
    <!-- <link href="{{asset('css/custom.css')}}" rel="stylesheet"/> -->
    <title>{{@$titulo}}</title>
    <style>
        .bordered { border:1px solid;font-family: Arial; text-align:center }
        .borderedl { border:1px solid;font-family: Arial; text-align:left }
        .noborder { border-spacing: 0px; border-collapse: collapse;}
        .noborderspaced { border-spacing: 1px; border-collapse: separate;}
        .text-center{text-align: center}
        table { border-spacing: 0px; border-collapse: collapse; margin-left: auto; margin-right: auto;}
        td, th {border: solid 1px; padding: 2.5px;}
        th{background-color: #ccc}
        p {
            margin: 0;
            padding: 2px;
        }
        @page { margin-top: 35px; }
        body { margin-top: 10px; font-size:11px; font-family: Arial !important;}
        .page-break {
            page-break-after: always;
        }
        thead:before, thead:after,
        tbody:before, tbody:after,
        tfoot:before, tfoot:after
        {
            display: none;
        }
    </style>
</head>
<body class="skin-blue layout-top-nav ">
    <div class="content-wrapper">
        <!-- Main content -->
        <section class="content">
            @yield('content')
        </section><!-- /.content -->
    </div><!-- /.content-wrapper -->
</body>
</html>
