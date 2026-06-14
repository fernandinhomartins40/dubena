<!DOCTYPE html>
<html>
<head>
    <link href="{{URL::to('bootstrap/css/bootstrap.css')}}" rel="stylesheet">
    <link href="{{URL::to('css/custom.css')}}" rel="stylesheet" type="text/css" />

    <style>
        .bordered { border:1px solid;font-family: Arial; text-align:center }
        .borderedDashed { border:1px dashed;font-family: Arial; text-align:center }
        .borderedl { border:1px solid;font-family: Arial; text-align:left }
        .noborder { border-spacing: 0px; border-collapse: collapse;}
        .noborderspaced { border-spacing: 1px; border-collapse: separate;}
       
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
    </style>

</head>
<body>
    <div style="position:absolute;float:left;">
        @if(Session::get('empresa_padrao')->logo != null)
        <img id="imgInicial" class="img-circle" style="max-height:50px;" src="data:image/png;base64,{{Session::get('empresa_padrao')->logo }}" alt="Logotipo"/>
        @else
        <img id="imgInicial" class="img-circle"  style="max-height:50px;" src="{{URL::to('dist/img/userdefault.png')}}" alt="Logotipo"/>
        @endif
    </div>
    <div style="font-size:14px;text-align:center;padding-top:20px;">
        {{$titulo}}
    </div>
    <div style="position:absolute;float:right;padding-top:-15px;">
        Emissão: {{Carbon\Carbon::now('America/Sao_Paulo')->format('d/m/Y H:i:s')}}
    </div>
    @if(isset($filtro))
    <div style="width:120%;font-size:10px;text-align:center;padding-top:5px;">
        {{$filtro}}
    </div>
    @endif
    <br />
    <div class="content-wrapper" style="border-top: 1px solid black;">
        <!-- Main content -->
        <section class="content">
            @yield('content')
        </section><!-- /.content -->
    </div><!-- /.content-wrapper -->
</body>
</html>
