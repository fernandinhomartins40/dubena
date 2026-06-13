<!DOCTYPE html>
<html>
    <head>
        <link href="{{ public_path('css/font-awesome.min.css') }}" rel="stylesheet">
        <link href="{{ public_path('css/custom.css') }}" rel="stylesheet">
        <!-- <link href="{{URL::to('css/custom.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{URL::to('css/font-awesome.min.css')}}" rel="stylesheet" type="text/css" /> -->
        <title>{{ $titulo }}</title>
        <link rel="icon" href="{{ asset('ico.png') }}" type="image/x-icon" />
        <style>
            html {
                -webkit-print-color-adjust: exact;
            }
            body {
                font-family: Helvetica, Arial, sans-serif !important;
                font-size: 14px;
                line-height: 1.42857143;
                color: #333;
                background-color: #fff;
            }
            hr {
                margin-top: 0px;
                margin-bottom: 0px;
                border: 0;
                border-top: 1px solid #eee;
            }

            .align-center {
                text-align: center !important;
            }
            .align-right {
                text-align: right !important;
            }
            .align-left {
                text-align: left !important;
            }
            .top-style {
                font-weight: bold;
            }
            .negrito {
                font-weight: bold;
            }
            .bordered {
                border: 1px solid;
                font-family: Arial;
                text-align: center;
                page-break-inside: avoid !important;
            }
            .nobord {
                border: none;
            }
            .noborderleftright {
                border-top: 1px solid;
                border-bottom: 1px solid;
                font-family: Arial;
                text-align: center;
            }
            .noborderleft {
                border-top: 1px solid;
                border-bottom: 1px solid;
                border-right: 1px solid;
                font-family: Arial;
                text-align: center;
            }
            .noborderright {
                border-top: 1px solid;
                border-bottom: 1px solid;
                border-left: 1px solid;
                font-family: Arial;
                text-align: center;
            }
            .borderedDashed {
                border: 1px dashed;
                font-family: Arial;
                text-align: center;
            }
            .borderedl {
                border: 1px solid;
                font-family: Arial;
                text-align: left;
            }
            .noborder {
                border-spacing: 0px;
                border-collapse: collapse;
            }
            .noborderspaced {
                border-spacing: 1px;
                border-collapse: separate;
            }
            .fontSize12 {
                font-size: 10px;
            }
            .fontSize11 {
                font-size: 11px;
            }
            .fontSize14 {
                font-size: 13px;
            }
            .fontSize15 {
                font-size: 15px;
            }
            .marginLeft10 {
                margin-left: 10px;
            }
            .fontSize15 {
                font-size: 14.5px;
            }
            .table500 {
                min-width: 625px;
                padding-top: 10px;
            }
            .destaque {
                background-color: lightgray;
            }
            .money {
                text-align: right;
            }
            .header {
                /* max-height: 160px; */
                min-height: 135px;
                width: 100%;
            }
            table {
                border-spacing: 0px;
                border-collapse: collapse;
                margin-left: auto;
                margin-right: auto;
            }
            td,
            th {
                padding: 3px 7px;
            }
            .anchor {
                text-decoration: none;
            }
            .table-report-menor {
                margin-top: 7px;
                margin-left: 230px;
                min-width: 70%;
            }
            .pad-top-bot-5 {
                padding: 7px 0px;
            }
            .destaque-saldos {
                background-color: #c6ccc1 !important;
            }

            p {
                margin: 0;
                padding: 2px;
            }
            @page {
                margin-top: 24px;
                margin-bottom: 15px;
            }
            body {
                margin-top: 15px;
                font-size: 11px;
                font-family: Arial;
                margin-right: 18px;
                margin-left: 15px;
            }
            #imgInicial {
                margin-right: 20px;
            }

            thead:before,
            thead:after,
            tbody:before,
            tbody:after,
            tfoot:before,
            tfoot:after {
                display: none;
            }
            tr,
            td,
            th {
                page-break-inside: avoid !important;
            }
            @media print {
                tr,
                td,
                th {
                    page-break-inside: avoid !important;
                }
                html {
                    -webkit-print-color-adjust: exact;
                }
            }
            .page-break {
                page-break-inside: avoid;
                page-break-after: always;
            }
            @media page {
                header {
                    display: block;
                    position: fixed;
                    height: 140px;
                    top: 0;
                    left: 0;
                    right: 0;
                }
                body {
                    margin-top: 162px;
                    margin-right: 0px;
                    margin-left: 0px;
                    margin-bottom: 35px;
                }
                #imgInicial {
                    margin-right: 0px;
                }
                tr,
                td,
                th {
                    page-break-inside: avoid !important;
                }
                .content-wrapper {
                    padding-top: -30px;
                }
            }
        </style>
    </head>
    <body class="skin-blue layout-top-nav">
        <header class="header">
            <div class="header-content">
                <div style="font-size: 10px; text-align: right;">
                    <p>Emitido em {{Carbon\Carbon::now()->format('d/m/Y H:i:s')}}</p>
                </div>
                <hr>
                <div style="font-size: 20px; text-align: center; padding-top: 0px">
                    {{@$titulo}}
                </div>
                <div style="position: absolute; right: 0; top: 50px">
                    @if(isset($empresa) && $empresa->logo != null)
                        <img id="imgInicial" style="max-height: 90px" src="data:image/png;base64,{{$empresa->logo }}" alt="Logotipo" />
                    @else
                        @if(@Session::get('empresa_padrao')->logo != null)
                        <img id="imgInicial" style="max-height: 90px" src="data:image/png;base64,{{@Session::get('empresa_padrao')->logo }}" alt="Logotipo" />
                        @else
                        <img id="imgInicial" style="max-height: 90px" src="{{URL::to('dist/img/userdefault.png')}}" alt="Logotipo" />
                        @endif
                    @endif
                </div>
                <br />
                <br />
                @if(isset($filtro))
                    <div style="font-size: 15px; text-align: left; padding-top: 0px;">
                        @if(is_array($filtro))
                        @foreach($filtro as $fil)
                        <p>{{ $fil }}</p>
                        @endforeach
                        @else
                        <p>{{ $filtro }}</p>
                        @endif
                    </div>
                @else
                    <br />
                    <br />
                @endif
                <div id="end-header">
                    <hr />
                </div>
                <br />
            </div>
        </header>
        <div class="content-wrapper">
            <!-- Main content -->
            <section class="content">@yield('content')</section>
            <!-- /.content -->
        </div>
        <!-- /.content-wrapper -->
    </body>
</html>
