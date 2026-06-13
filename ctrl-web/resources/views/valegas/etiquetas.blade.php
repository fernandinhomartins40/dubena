<HTML>
    <HEAD>
        <title>{{ $titulo }}</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <STYLE>
            html{
                margin: 0;
            }
            body{
                margin: 12.7mm 4.8mm;
            }
            .geral0{
                font-size: 11px;
            }

            .rasura{
                padding-top:11px;
            }

            .codigo{
                letter-spacing: 16px;
                padding-left: 3.3mm;
            }

            .etiqueta{
                height:25.4mm;
                width: 66.7mm;
                margin-left:2mm;
                margin-right: 2.2mm;
            }

            .subetiqueta{
                margin-left: 1mm;
                padding-left: 1.5mm;
                padding-top: 8mm;
            }

            .geral1{
                clear:both;
                position:absolute;
            }

            .geral2{
                clear:both;
                position:absolute;
                margin-left:69mm;
            }

            .geral3{
                clear:both;
                position:absolute;
                margin-left:138mm;
            }

            .seq{
                padding-left: 3.5mm;
            }

            .page-break {
                page-break-after: always;
            }

        </STYLE>
    </HEAD>
    <BODY>
        <div class="geral0">
            @for($i=0;$i<count($valegas);$i++)
                @if($i>0)
                    <div class="page-break"></div>
                @endif
                @for($j=0;$j < count($valegas[$i]); $j++)
                    <div class="geral{{($j+1)}}">      
                        @for($k=0;$k < count($valegas[$i][$j]); $k++)
                            <div class="etiqueta">
                                <div class="subetiqueta">
                                    <div class="">@if(!empty($valegas[$i][$j][$k]["cliente_id"]))
                                        P.V.: {{$valegas[$i][$j][$k]["cliente_id"]}}
                                        @endif
                                    </div>
                                    <div class="">
                                        @if(!empty($valegas[$i][$j][$k]["produto_id"]))
                                        Prod: {{$valegas[$i][$j][$k]["produto_id"]}}
                                        @endif
                                        @if(!empty($valegas[$i][$j][$k]["prevendasequencia"]))
                                        <span class="seq">Seq.:{{$valegas[$i][$j][$k]["prevendasequencia"]}}</span>
                                        @endif
                                    </div>
                                    @if(!empty($valegas[$i][$j][$k]["codigo"]))
                                    <div class="">
                                        <?php echo DNS1D::getBarcodeHTML($valegas[$i][$j][$k]["codigo"], "C128") ?>
                                    </div>
                                    <div class="codigo" style="margin:0 auto;">
                                        {{$valegas[$i][$j][$k]["codigo"]}}</div>
                                    <div class="rasura">FAVOR NAO RASURAR ESTA ETIQUETA.</div><br/>
                                    @endif
                                </div>
                            </div>
                        @endfor
                    </div>
                @endfor
            @endfor
        </div>
    </BODY>

</HTML>
