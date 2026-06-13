<html>

<head>
    <title>{{ @$titulo }}</title>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <style>
        html {
            margin: 0;
            padding-top: 2.7mm;
        }

        body {
            margin: 12.7mm 4.8mm;
        }

        .geral0 {
            font-size: 11px;
        }

        .rasura {
            padding-top: 13px;
        }

        .etiqueta {
            height: 25.4mm;
            width: 66.7mm;
            margin-left: 2.5mm;
            margin-right: 2.5mm;
        }

        .subetiqueta-first {
            margin-left: 2.2mm;
            padding-left: 2mm;
            margin-top: 4.8mm;
            padding-top: 2.3mm;
            margin-bottom: 1.3mm;
            padding-bottom: 2mm;
        }

        .subetiqueta {
            margin-left: 2.2mm;
            padding-left: 2mm;
            margin-bottom: 0.7mm;
            padding-bottom: 2mm;
        }

        .geral1 {
            clear: both;
            position: absolute;
        }

        .geral2 {
            clear: both;
            position: absolute;
            margin-left: 69.6mm;
        }

        .geral3 {
            clear: both;
            position: absolute;
            margin-left: 139.4mm;
        }

        .seq {
            padding-left: 3.5mm;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>

<body>
    <div class="geral0">
    @foreach ($data as $dados)
        @php
            $etiquetas = $dados->chunk(10);
            $x = 0;
        @endphp

        @foreach ($etiquetas as $ets)
            @php
                $x++;
                $first = "-first";
            @endphp

            <div class="geral{{$x}}">
            @foreach ($ets as $etiqueta)
                <div class="etiqueta">
                    <div class="subetiqueta{{$first}}">
                        <div class="nome-conveniado">
                            {{ substr($etiqueta->nome_conveniado, 0, 40) }}
                        </div>
                        <div class="nome-conveniado">
                            {{ substr($etiqueta->cpf, 0, 40) }}
                        </div>
                        <div class="empresa">
                            {{ substr($etiqueta->empresa, 0, 40) }}
                        </div>
                        <div class="parentesco" style="margin:0 auto;">
                            {{ substr($etiqueta->parentesco, 0, 40) }}
                        </div>
                        <br />
                    </div>
                </div>
                @php
                    $first = "";
                @endphp
            @endforeach
            </div>
        @endforeach


        @if (!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
    </div>
</body>

</html>
