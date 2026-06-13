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
            <div class="geral1">
                @for($i=0;$i < 10; $i++)
                <div class="etiqueta">
                    <div class="subetiqueta">
                        <div class="">@if(!empty($dadosgerais[$i]["cliente_id"]))
                            P.V.: {{$dadosgerais[$i]["cliente_id"]}}
                            @endif
                        </div>
                        <div class="">
                            @if(!empty($dadosgerais[$i]["produto_id"]))
                            Prod: {{$dadosgerais[$i]["produto_id"]}}
                            @endif
                            @if(!empty($dadosgerais[$i]["prevendasequencia"]))
                            <span class="seq">Seq.:{{$dadosgerais[$i]["prevendasequencia"]}}</span>
                            @endif
                        </div>
                        @if(!empty($dadosgerais[$i]["codigo"]))
                        <div class="">
                            <?php echo DNS1D::getBarcodeHTML($dadosgerais[$i]["codigo"], "C128") ?>
                        </div>
                        <div class="codigo" style="margin:0 auto;">
                            {{$dadosgerais[$i]["codigo"]}}</div>
                        <div class="rasura">FAVOR NAO RASURAR ESTA ETIQUETA.</div><br/>
                        @endif
                    </div>
                </div>
                @endfor
            </div>
            @if(count($dadosgerais)>10)
            <div class="geral2">
                @for($i=10;$i< 20; $i++)
                <div class="etiqueta">
                    <div class="subetiqueta">
                        <div class="">@if(!empty($dadosgerais[$i]["cliente_id"]))
                            P.V.: {{$dadosgerais[$i]["cliente_id"]}}
                            @endif
                        </div>
                        <div class="">@if(!empty($dadosgerais[$i]["produto_id"]))
                            Prod: {{$dadosgerais[$i]["produto_id"]}}
                            @endif
                            @if(!empty($dadosgerais[$i]["prevendasequencia"]))
                            <span class="seq">Seq.:{{$dadosgerais[$i]["prevendasequencia"]}}</span>
                            @endif
                        </div>
                        @if(!empty($dadosgerais[$i]["codigo"]))
                        <div class="">
                            <?php echo DNS1D::getBarcodeHTML($dadosgerais[$i]["codigo"], "C128") ?>
                        </div>
                        <div class="codigo" style="margin:0 auto;">
                            {{$dadosgerais[$i]["codigo"]}}</div>
                        <div class="rasura">FAVOR NAO RASURAR ESTA ETIQUETA.</div><br/>
                        @endif
                    </div>
                </div>
                @endfor
            </div>
            @endif
            @if(count($dadosgerais)>20)
            <div class="geral3">
                @for($i=20;$i< 30; $i++)
                <div class="etiqueta">
                    <div class="subetiqueta">
                        <div class="">@if(!empty($dadosgerais[$i]["cliente_id"]))
                            P.V.: {{$dadosgerais[$i]["cliente_id"]}}
                            @endif
                        </div>
                        <div class="">@if(!empty($dadosgerais[$i]["produto_id"]))
                            Prod: {{$dadosgerais[$i]["produto_id"]}}
                            @endif
                            @if(!empty($dadosgerais[$i]["prevendasequencia"]))
                            <span class="seq">Seq.:{{$dadosgerais[$i]["prevendasequencia"]}}</span>
                            @endif
                        </div>
                        @if(!empty($dadosgerais[$i]["codigo"]))
                        <div class="">
                            <?php echo DNS1D::getBarcodeHTML($dadosgerais[$i]["codigo"], "C128") ?>
                        </div>
                        <div class="codigo" style="margin:0 auto;">{{$dadosgerais[$i]["codigo"]}}</div>
                        <div class="rasura">FAVOR NAO RASURAR ESTA ETIQUETA.</div><br/>
                        @endif
                    </div>
                </div>
                @endfor
            </div>
            @endif
            <!---------------------------->
            @if(count($dadosgerais)>30)
            <div class="page-break"></div>
            <div class="geral1">
                @for($i=30;$i< 40; $i++)
                <div class="etiqueta">
                    <div class="subetiqueta">
                        <div class="">@if(!empty($dadosgerais[$i]["cliente_id"]))
                            P.V.: {{$dadosgerais[$i]["cliente_id"]}}
                            @endif
                        </div>
                        <div class="">@if(!empty($dadosgerais[$i]["produto_id"]))
                            Prod: {{$dadosgerais[$i]["produto_id"]}}
                            @endif
                            @if(!empty($dadosgerais[$i]["prevendasequencia"]))
                            <span class="seq">Seq.:{{$dadosgerais[$i]["prevendasequencia"]}}</span>
                            @endif
                        </div>
                        @if(!empty($dadosgerais[$i]["codigo"]))
                        <div class="">
                            <?php echo DNS1D::getBarcodeHTML($dadosgerais[$i]["codigo"], "C128") ?>
                        </div>
                        <div class="codigo" style="margin:0 auto;">
                            {{$dadosgerais[$i]["codigo"]}}</div>
                        <div class="rasura">FAVOR NAO RASURAR ESTA ETIQUETA.</div><br/>
                        @endif
                    </div>
                </div>
                @endfor
            </div>
            @endif
            @if(count($dadosgerais)>40)
            <div class="geral2">
                @for($i=40;$i<50; $i++)
                <div class="etiqueta">
                    <div class="subetiqueta">
                        <div class="">@if(!empty($dadosgerais[$i]["cliente_id"]))
                            P.V.: {{$dadosgerais[$i]["cliente_id"]}}
                            @endif
                        </div>
                        <div class="">@if(!empty($dadosgerais[$i]["produto_id"]))
                            Prod: {{$dadosgerais[$i]["produto_id"]}}
                            @endif
                            @if(!empty($dadosgerais[$i]["prevendasequencia"]))
                            <span class="seq">Seq.:{{$dadosgerais[$i]["prevendasequencia"]}}</span>
                            @endif
                        </div>
                        @if(!empty($dadosgerais[$i]["codigo"]))
                        <div class="">
                            <?php echo DNS1D::getBarcodeHTML($dadosgerais[$i]["codigo"], "C128") ?>
                        </div>
                        <div class="codigo" style="margin:0 auto;">
                            {{$dadosgerais[$i]["codigo"]}}</div>
                        <div class="rasura">FAVOR NAO RASURAR ESTA ETIQUETA.</div><br/>
                        @endif
                    </div>
                </div>
                @endfor
            </div>
            @endif
            @if(count($dadosgerais)>50)
            <div class="geral3">
                @for($i=50;$i< 60; $i++)
                <div class="etiqueta">
                    <div class="subetiqueta">
                        <div class="">@if(!empty($dadosgerais[$i]["cliente_id"]))
                            P.V.: {{$dadosgerais[$i]["cliente_id"]}}
                            @endif
                        </div>
                        <div class="">@if(!empty($dadosgerais[$i]["produto_id"]))
                            Prod: {{$dadosgerais[$i]["produto_id"]}}
                            @endif
                            @if(!empty($dadosgerais[$i]["prevendasequencia"]))
                            <span class="seq">Seq.:{{$dadosgerais[$i]["prevendasequencia"]}}</span>
                            @endif
                        </div>
                        @if(!empty($dadosgerais[$i]["codigo"]))
                        <div class="">
                            <?php echo DNS1D::getBarcodeHTML($dadosgerais[$i]["codigo"], "C128") ?>
                        </div>
                        <div class="codigo" style="margin:0 auto;">
                            {{$dadosgerais[$i]["codigo"]}}</div>
                        <div class="rasura">FAVOR NAO RASURAR ESTA ETIQUETA.</div><br/>
                        @endif
                    </div>
                </div>
                @endfor
            </div>
            @endif
            @if(count($dadosgerais)>60)
            <div class="page-break"></div>
            <div class="geral1">
                @for($i=60;$i< 70; $i++)
                <div class="etiqueta">
                    <div class="subetiqueta">
                        <div class="">@if(!empty($dadosgerais[$i]["cliente_id"]))
                            P.V.: {{$dadosgerais[$i]["cliente_id"]}}
                            @endif
                        </div>
                        <div class="">@if(!empty($dadosgerais[$i]["produto_id"]))
                            Prod: {{$dadosgerais[$i]["produto_id"]}}
                            @endif
                            @if(!empty($dadosgerais[$i]["prevendasequencia"]))
                            <span class="seq">Seq.:{{$dadosgerais[$i]["prevendasequencia"]}}</span>
                            @endif
                        </div>
                        @if(!empty($dadosgerais[$i]["codigo"]))
                        <div class="">
                            <?php echo DNS1D::getBarcodeHTML($dadosgerais[$i]["codigo"], "C128") ?>
                        </div>
                        <div class="codigo" style="margin:0 auto;">
                            {{$dadosgerais[$i]["codigo"]}}</div>
                        <div class="rasura">FAVOR NAO RASURAR ESTA ETIQUETA.</div><br/>
                        @endif
                    </div>
                </div>
                @endfor
            </div>
            @endif
            @if(count($dadosgerais)>70)
            <div class="geral2">
                @for($i=70;$i< 80; $i++)
                <div class="etiqueta">
                    <div class="subetiqueta">
                        <div class="">@if(!empty($dadosgerais[$i]["cliente_id"]))
                            P.V.: {{$dadosgerais[$i]["cliente_id"]}}
                            @endif
                        </div>
                        <div class="">@if(!empty($dadosgerais[$i]["produto_id"]))
                            Prod: {{$dadosgerais[$i]["produto_id"]}}
                            @endif
                            @if(!empty($dadosgerais[$i]["prevendasequencia"]))
                            <span class="seq">Seq.:{{$dadosgerais[$i]["prevendasequencia"]}}</span>
                            @endif
                        </div>
                        @if(!empty($dadosgerais[$i]["codigo"]))
                        <div class="">
                            <?php echo DNS1D::getBarcodeHTML($dadosgerais[$i]["codigo"], "C128") ?>
                        </div>
                        <div class="codigo" style="margin:0 auto;">
                            {{$dadosgerais[$i]["codigo"]}}</div>
                        <div class="rasura">FAVOR NAO RASURAR ESTA ETIQUETA.</div><br/>
                        @endif
                    </div>
                </div>
                @endfor
            </div>
            @endif
            @if(count($dadosgerais)>80)
            <div class="geral3">
                @for($i=80;$i< 90; $i++)
                <div class="etiqueta">
                    <div class="subetiqueta">
                        <div class="">@if(!empty($dadosgerais[$i]["cliente_id"]))
                            P.V.: {{$dadosgerais[$i]["cliente_id"]}}
                            @endif
                        </div>
                        <div class="">@if(!empty($dadosgerais[$i]["produto_id"]))
                            Prod: {{$dadosgerais[$i]["produto_id"]}}
                            @endif
                            @if(!empty($dadosgerais[$i]["prevendasequencia"]))
                            <span class="seq">Seq.:{{$dadosgerais[$i]["prevendasequencia"]}}</span>
                            @endif
                        </div>
                        @if(!empty($dadosgerais[$i]["codigo"]))
                        <div class="">
                            <?php echo DNS1D::getBarcodeHTML($dadosgerais[$i]["codigo"], "C128") ?>
                        </div>
                        <div class="codigo" style="margin:0 auto;">
                            {{$dadosgerais[$i]["codigo"]}}</div>
                        <div class="rasura">FAVOR NAO RASURAR ESTA ETIQUETA.</div><br/>
                        @endif
                    </div>
                </div>
                @endfor
            </div>
            @endif
        </div>
    </BODY>

</HTML>
