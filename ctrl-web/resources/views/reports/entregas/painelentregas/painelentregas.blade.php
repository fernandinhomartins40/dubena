@extends('layouts.mainmenu')
@section('content')
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Mapa de Entregas Por Coordenadas</h3>
                        </div>
                        <!-- /.box-header -->
                        <div class="nav-tabs-custom">
                            <ul class="nav nav-tabs">
                                <li class="active"><a href="#tab_1" data-toggle="tab">Mapa de Entregas Por Coordenadas</a></li>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane active" id="tab_1">
                                    <div class="row">
                                        <div id="tabCadastro" class="col-sm-12">
                                            <div class="box-body">
                                                {{ Form::open(['id' => 'fmFiltros','class'=>'form-horizontal'])}}
                                                <div class="form-group crud_space col-sm-10">
                                                    {{ Form::label('data', 'Data:', ['class'=>'col-sm-1 col-sm-offset-4 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        <div class="input-group generalDatePicker">
                                                            {{ Form::text('data',null,['id' => 'data','class'=>'form-control generalDatePicker input-sm']) }}
                                                            <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-2 col-sm-offset-1">
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
                                                <div class="form-group crud_space margTop_30" style="margin-left: 1.5%">
                                                    <div class="col-sm-12" id="divMapa" style="height: 650px; max-height: 650px"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- /.content-wrapper -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="{{asset('plugins/chartjs.min.js')}}"></script>
    <script src="{{asset('js/lib/collection.js')}}"></script>
    <script type="text/javascript">
        $(document).ready(function () {
            updateChart([0,0]);
        });
        function setLatLgtEmpresa(){
            latitude = parseFloat("{{Session::get('empresa_padrao')->latitude}}");
            longitude = parseFloat("{{Session::get('empresa_padrao')->longitude}}");
            if(isEmpty(latitude) || isEmpty(longitude))
                bootbox.alert("Não foi possível localizar a latitude e longitude da empresa.");
        }
        $("#btnLimpar").on('click', function() {
            $(".selectChosen").val('').trigger('chosen:updated');
            $("#datainicio, #datafim").val(dataAtual());
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
            var url = root + '/api/searchPedidosMapaEntregasCoordenadas?data=' + data;
            ajaxGenerator(url, 'GET', function (data) {
                clearAllMarkers();
                $("#divChartCanvas").html('<canvas id="chart"></canvas>');
                if(data.length > 0){
                    insertChartsAndMaps(data, data.unique('latlng'));
                } else {
                    bootbox.alert('Nenhum pedido encontrado para estes filtros.');
                }
            });
        }

        function insertChartsAndMaps(data, unique) {
            var tempo1 = 0;
            var tempo2 = 0;
            var tempo3 = 0;
            var tempo4 = 0;
            var tempoSem = 0;
            var position;
            var pathImage;
            var entrega;
            var table = "<div style='font-size: 11.5px'><table class='table table-hover table-responsive table-condensed'><thead>"
            table += "<tr><th>Pedido</th><th>Cliente</th><th>Hora Pedido</th><th>Hora Entrega</th><th>Distância</th><th></th></tr></thead>";
            var contentTable = '';
            $.each(unique, function (index, uniqueEl) {
                var equalsElements = data.where("latlng", "===", uniqueEl.latlng);
                position = {
                    lat: parseFloat(uniqueEl.entregalatitude),
                    lng: parseFloat(uniqueEl.entregalongitude)
                };
                var qdeEquals = equalsElements.length;
                $.each(equalsElements, function (i, el) {
                    var typeMarker;
                    var distancia = parseFloat(el.distancia);
                    if (el.entregalatitude === null) {
                        tempoSem++;
                    } else if (distancia <= 100) {
                        typeMarker = 'green';
                        tempo1++;
                    } else if (distancia <= 500) {
                        typeMarker = 'yellow';
                        tempo2++;
                    } else if (distancia <= 1000) {
                        typeMarker = 'orange';
                        tempo3++;
                    } else {
                        typeMarker = 'red';
                        tempo4++;
                    }
                    distancia = isNaN(distancia) ? "Sem Registro" : distancia + ' m.';
                    entrega = isEmpty(el.entrega) ? 'Sem Registro' : el.entrega;
                    contentTable += putContentTable(el, entrega, distancia, typeMarker);
                    if (qdeEquals > 1) {
                        typeMarker = 'black';
                    }
                    pathImage = '/img/marker_' + typeMarker + '.png';
                });
                var contentInfo = table + contentTable + "<tbody></tbody></table><div>Endereço: " + uniqueEl.endereco + "</div></div>";
                addMarker(position, pathImage, 40, 'Clique para ver detalhes', contentInfo);
                contentTable = '';
            });
            updateChart([tempo1, tempo2, tempo3, tempo4, tempoSem]);
        }

        function putContentTable(el, entrega, distancia, typeMarker){
            var contentTable = "<tr><td>" + el.id + "</td><td>" + el.nome + "</td><td>" + el.previsao + "</td><td>";
            contentTable += entrega + "</td><td>" + distancia + "</td>";
            return contentTable + "<td><img style='max-height: 25px; max-width: 25px;' src='img/marker_" + typeMarker + ".png'></img></td></tr>";
        }

        function updateChart (data) {
            var divChart = document.getElementById("chart").getContext("2d");
            var background = ["#B7EF56","#F4FD2B","#FDB72C","#D54A40", "#000000"];
            var datasets = [{ backgroundColor: background, data: data }];
            var labels = [" Até 100m", " 100.01m a 500m", " 500.01m a 1000m", " Acima de 1000", "Sem Coordenadas"];
            chart = new Chart(divChart, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    title: {
                        text: 'Entrega por Coordenadas.'
                    }
                }
            });
        }
    </script>
    <script src="{{asset('js/maps.js')}}"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key={{$keygooglemaps}}&callback=initMap" async defer></script>
@endsection