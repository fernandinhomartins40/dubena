@extends('layouts.mainmenu')
@section('content')
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Mapa de Atrasos de Pedidos</h3>
                        </div>
                        <div class="nav-tabs-custom">
                            <ul class="nav nav-tabs">
                                <li class="active"><a href="#tab_1" data-toggle="tab">Mapa de Atrasos de Pedidos</a></li>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane active" id="tab_1">
                                    <div class="row">
                                        <div id="tabCadastro" class="col-sm-12">
                                            <div class="box-body">
                                                {{ Form::open(['id' => 'fmFiltros','class'=>'form-horizontal'])}}
                                                    <div class="form-group crud_space col-sm-10">
                                                        {{ Form::label('data', 'Data:', ['class'=>'col-sm-1 col-sm-offset-3 control-label input-sm']) }}
                                                        <div class="col-sm-2">
                                                            <div class="input-group generalDatePicker">
                                                                {{ Form::text('data',null,['id' => 'data','class'=>'form-control generalDatePicker input-sm']) }}
                                                                <span class="input-group-addon">
                                                                <span class="glyphicon glyphicon-calendar"></span>
                                                            </span>
                                                            </div>
                                                        </div>
                                                        {{ Form::label('somenteatrasadas', 'Buscar somente atrasadas:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                        <div class="col-sm-1 checkbox">
                                                            {{Form::checkbox('somenteatrasadas')}}
                                                        </div>
                                                        <div class="col-sm-2">
                                                            <button id="btnFiltro" type="button" class="btn btn-nw-geral btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar Pedidos"><span class="fa fa-search fa-lg"></span></button>
                                                            <button type="button" id='btnLimpar' class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                                                        </div>
                                                    </div>
                                                {{ Form::close() }}
                                                <div class="form-group crud_space margTop_50">
                                                    <div class="col-sm-4 col-sm-offset-4" id="divChartCanvas">
                                                        <canvas id="chart"></canvas>
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space" style="margin-left: 1.5%">
                                                    <div class="col-sm-12" id="divMapa" style="height: 600px; max-height: 650px"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class='hidden'>
        <div id="legendMaps"><span>Legenda</span></div>
    </div>
    <script src="{{asset('plugins/chartjs.min.js')}}"></script>
    <script src="{{asset('js/lib/collection.js')}}"></script>
    <script type="text/javascript">
        var tempoentregaConfig;
        var tempoentregaConfigUrgente;
        $(document).ready(function () {
            updateChart();
            tempoentregaConfig = parseInt("{{Session::get('empresa_config')->tempoentrega}}");
            if (isEmpty(tempoentregaConfig))
                bootbox.alert('Não há um tempo de entrega selecionado nas configurações da empresa.');
            tempoentregaConfigUrgente = parseInt("{{Session::get('empresa_config')->tempourgente}}");
            if (isEmpty(tempoentregaConfigUrgente))
                bootbox.alert('Não há um tempo de entrega urgente selecionado nas configurações da empresa.');
        });
        function setLatLgtEmpresa(){
            latitude = parseFloat("{{Session::get('empresa_padrao')->latitude}}");
            longitude = parseFloat("{{Session::get('empresa_padrao')->longitude}}");
            if(isEmpty(latitude) || isEmpty(longitude))
                bootbox.alert("Não foi possível localizar a latitude e longitude da empresa.");
        }
        var iconsLegend = {
            red: {
                name: "Com Motivo",
                icon: root + '/img/marker_red.png'
            },
            yellow:{
                name: "Sem motivo",
                icon: root + '/img/marker_yellow.png'
            },
            green:{
                name: "Sem atraso",
                icon: root + '/img/marker_green.png'
            },
            black:{
                name: "Vários Registros",
                icon: root + '/img/marker_black.png'
            }
        };
        $("#btnLimpar").on('click', function() {
            $(".selectChosen").val('').trigger('chosen:updated');
            $("#data").val(dataAtual());
            $("#divChartCanvas").html('<canvas id="chart"></canvas>');
            clearAllMarkers();
        });
        $("#btnFiltro").on('click', function () {
            if(isEmpty($("#data").val())){
                bootbox.alert('O campo Data é obrigatório.');
                return;
            }
            searchPedidos($("#data").val());
        });

        function searchPedidos (data) {
            var url = root + '/api/searchPedidosMapaEntregasAtrasadas?data=' + data;
            if ($("#somenteatrasadas").is(':checked')) {
                url += '&somenteatrasadas=1';
            }
            ajaxGenerator(url, 'GET', function (data) {
                clearAllMarkers();
                $("#divChartCanvas").html('<canvas id="chart"></canvas>');
                if (data.mapa.length > 0) {
                    insertChartsAndMaps(data, data.mapa.unique('latlng'));
                } else {
                    bootbox.alert('Nenhum pedido encontrado para estes filtros.');
                }
            });
        }

        function insertChartsAndMaps(data, unique) {
            var position;
            var pathImage;
            var contentInfo;
            var table = "<div style='font-size: 11.5px'><table class='table table-hover table-responsive table-condensed'><thead>";
            table += "<tr><th>Pedido</th><th>Cliente</th><th>Tempo Entrega</th><th>Urgente</th><th>Motivo de Atraso</th><th></th></tr></thead>";
            var contentTable = '';
            $.each(unique, function(index, uniqueEl){
                var equalsElements = data.mapa.where("latlng", "===", uniqueEl.latlng);
                position = {
                    lat: parseFloat(uniqueEl.entregalatitude),
                    lng: parseFloat(uniqueEl.entregalongitude)
                };
                var qdeEquals = equalsElements.length;
                $.each(equalsElements, function (i, el) {
                    var typeMarker;
                    var tempoentrega = parseFloat(el.tempoentrega);
                    var entregaConfig = parseInt(el.entregaurgente == 1 ? tempoentregaConfigUrgente : tempoentregaConfig);
                    var atrasado = tempoentrega > entregaConfig;
                    if (parseInt(el.atraso_id) === -1 && atrasado) {
                        typeMarker = 'yellow';
                    } else if (atrasado) {
                        typeMarker = 'red';
                    } else {
                        typeMarker = 'green';
                    }
                    console.log(atrasado, el);
                    var atraso = atrasado ? el.atraso : '';
                    var urgente = el.entregaurgente == 1 ? "Sim" : "Não";
                    tempoentrega = isNaN(tempoentrega) ? "Sem Registro" : tempoentrega + ' m.';
                    contentTable += putContentTable(atraso, el, tempoentrega, urgente, typeMarker);
                    if (qdeEquals > 1) {
                        typeMarker = 'black';
                    }
                    pathImage = '/img/marker_' + typeMarker + '.png'
                });
                contentInfo = table + contentTable + "<tbody></tbody></table><div>Endereço: " + uniqueEl.endereco + "</div></div>";
                addMarker(position, pathImage, 40, 'Clique para ver detalhes', contentInfo);
                contentTable = '';
            });
            updateChart(data.chart, data.pedidosAtrasosDiff);
        }

        function putContentTable(atraso, el, tempoentrega, urgente, typeMarker) {
            return "<tr><td>" + el.id + "</td><td>" + el.nome + "</td><td>" + tempoentrega +
                "</td><td>" + urgente + "</td><td>" + atraso + "</td><td>" +
                "<img style='max-height: 25px; max-width: 25px;' src='img/marker_" + typeMarker + ".png'></img>" +
                "</td></tr>";
        }

        function updateChart (pedidos, pedidosAtrasosDiff) {
            var background = [];
            var labels = [];
            var dataChart = [];
            var divChart = document.getElementById("chart").getContext("2d");

            if (typeof pedidos != 'undefined') {
                $.each(pedidosAtrasosDiff, function (i, el) {
                    background.push(getBackgroundColor());
                    labels.push(el.atraso_id > 0 ? el.atraso : 'Sem Atraso');
                    dataChart.push(collect(pedidos).where('atraso_id', el.atraso_id).length);
                });
            }
            var datasets = [{ backgroundColor: background, data: dataChart }];

            chart = new Chart(divChart, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    title: {
                        text: 'Entregas Atrasadas.'
                    }
                }
            });
        }
        function getBackgroundColor () {
            var color = "#" + Math.floor(Math.random() * 16777215).toString(16);
            if (color.length < 7) {
                return getBackgroundColor();
            }
            return color;
        }

    </script>
    <script src="{{asset('js/maps.js')}}"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key={{$keygooglemaps}}&callback=initMap" async defer></script>
@endsection