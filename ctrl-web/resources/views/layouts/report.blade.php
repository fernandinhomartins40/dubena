<!DOCTYPE html>
<html>
<head>
    <link href="{{ public_path('bootstrap/css/bootstrap.css') }}" rel="stylesheet">
    <link href="{{ public_path('css/custom.css') }}" rel="stylesheet">
    <!-- <link href="{{URL::to('bootstrap/css/bootstrap.css')}}" rel="stylesheet">
    <link href="{{URL::to('css/custom.css')}}" rel="stylesheet" type="text/css" /> -->
    <link rel="icon" href="{{asset('ico.png')}}" type="image/x-icon" />
    <style>
        .bordered { border:1px solid; border-color: black !important; font-family: Arial; text-align:center }
        .bordered-top { border-top:1px solid; border-color: black !important; font-family: Arial; text-align:center }
        .table-bordered { border:1px solid; border-color: black !important; font-family: Arial; text-align:center }
        .borderedDashed { border:1px dashed;border-color: black !important; font-family: Arial; text-align:center }
        .borderedl { border:1px solid;border-color: black !important; font-family: Arial; text-align:left }
        .noborder { border-spacing: 0px; border-collapse: collapse;}
        .noborderspaced { border-spacing: 1px; border-collapse: separate;}

        .th{
            background-color: #cfcfcf;
        }
        p {
            margin: 0;
            padding: 2px;
        }
        @page { margin-top: 10px; }
        body { margin-top: 10px; font-size:11px; font-family: Arial;}
        .page-break {
            page-break-after: always;
        }
        thead:before, thead:after,
        tbody:before, tbody:after,
        tfoot:before, tfoot:after
        {
            display: none;
        }
        tr, td {
            padding: 0px !important;
            margin: 0px !important;
        }
        tr, th {
            padding: 2px !important;
            margin: 2px !important;
        }
    </style>

</head>
<body>
    @if(isset($noHeader) && !$noHeader || (!isset($noHeader)))
    <header class="header">
        <div class="header-content">
            <div style="font-size:18px;text-align:center;padding-top:20px;">
                {{@$titulo}}
            </div>
            <div style="position:absolute;right:0;padding-top:-20px;">
                @if(Session::get('empresa_padrao')->logo != null)
                <img id="imgInicial" style="max-height:70px;" src="data:image/png;base64,{{Session::get('empresa_padrao')->logo }}" alt="Logotipo"/>
                @else
                <img id="imgInicial" style="max-height:60px;" src="{{URL::to('dist/img/userdefault.png')}}" alt="Logotipo"/>
                @endif
            </div>
            <br />
            <br />
            <div style="font-size:10px;text-align:left;padding-top:-20px;">
                <p>Emissão: {{Carbon\Carbon::now('America/Sao_Paulo')->format('d/m/Y H:i:s')}}</p>
            </div>
            @if(isset($filtro))
            <div style="font-size:10px;text-align:left;padding-top:15px;max-width: 80%">
                <p>{{$filtro}}</p>
            </div>
            @else
            <br />
            <br />
            @endif
            <div id="end-header">
                <hr/>
            </div>
            <br />
        </div>
    </header>
    @endif
    <div class="content-wrapper">
        <!-- Main content -->
        <section class="content">
            @yield('content')
        </section><!-- /.content -->
    </div><!-- /.content-wrapper -->
</body>
</html>
