@extends('layouts.mainmenu')
@section('content')
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Mapa de Entregas Pendentes</h3>
                        </div>
                        <div class="nav-tabs-custom">
                            <ul class="nav nav-tabs">
                                <li class="active"><a href="#tab_1" data-toggle="tab">Mapa de Entregas Pendentes</a></li>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane active" id="tab_1">
                                    <div class="row">
                                        <div id="tabCadastro" class="col-sm-12">
                                            <div class="box-body">
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
    <script src="{{asset('js/lib/collection.js')}}"></script>
    <script type="text/javascript">
        var tempoentregaConfig;
        intervalAtualizacaoPedido = 0;
        @if(is_null(Session::get('empresa_config')))
            {{Redirect::to('home')->withMessageDanger('Defina o tempo de entrega nas configurações da empresa.')}}
        @else
        $(document).ready(function () {
            tempoentregaConfig = parseInt("{{Session::get('empresa_config')->tempoentrega}}");
            if(isEmpty(tempoentregaConfig))
                bootbox.alert('Não há um tempo de entrega selecionado nas configurações da empresa.');
            else {
                setTimeout(function () {
                    if(typeof google != 'undefined')
                        searchPedidos();
                }, 2000);
                intervalAtualizacaoPedido = setInterval('searchPedidos()', 15000);
            }
        });
        @endif
            iconsLegend = {
            normal: {
                name: "Entrega Normal",
                icon: root + '/img/entrega_normal.png'
            },
            atencao:{
                name: "Entrega Próxima do Atraso",
                icon: root + '/img/entrega_atencao.png'
            },
            atrasada: {
                name: "Entrega Atrasada",
                icon: root + '/img/entrega_atrasada.png'
            }
        };
        function setLatLgtEmpresa(){
            latitude = parseFloat("{{Session::get('empresa_padrao')->latitude}}");
            longitude = parseFloat("{{Session::get('empresa_padrao')->longitude}}");
            if(isEmpty(latitude) || isEmpty(longitude))
                bootbox.alert("Não foi possível localizar a latitude e longitude da empresa.");
        }

        function searchPedidos () {
            var url = root + '/api/searchPedidosPendentesMapaEntregas';
            ajaxGenerator(url, 'GET', function (data) {
                clearAllMarkers();
                if (data.length > 0){
                    var position;
                    var pathImage;
                    var entrega;
                    var table = "<div style='font-size: 11.5px'><table class='table table-hover table-responsive table-condensed'>";
                    table += "<thead><tr><th>Pedido</th><th>Cliente</th><th>Hora Pedido</th><th>Setor - colaborador</th><th>Urgente</th><th>Tempo Entrega</th><th></th></tr></thead><tbody>";
                    var contentTable = '';
                    var unique = data.unique('latlng');
                    $.each(unique, function(index, uniqueEl) {
                        var equalsElements = data.where("latlng", "===", uniqueEl.latlng);
                        position = {
                            lat: parseFloat(uniqueEl.latitude),
                            lng: parseFloat(uniqueEl.longitude)
                        };
                        var qdeEquals = equalsElements.length;
                        $.each(equalsElements, function (i, el) {
                            var typeMarker;
                            if (el.atraso == 0)
                                typeMarker = 'normal';
                            else if (el.atraso == 1)
                                typeMarker = 'atencao';
                            else
                                typeMarker = 'atrasada';
                            entrega = isEmpty(el.entrega) ? 'Sem Registro' : el.entrega;
                            contentTable += putContentTable(el, typeMarker);
                            if (qdeEquals > 1) {
                                typeMarker = 'black';
                            }
                            pathImage = '/img/entrega_' + typeMarker + '.png';
                        });
                        var contentInfo = table + contentTable + "</tbody></table><div>Endereço: " + uniqueEl.endereco + "</div></div>";
                        addMarker(position, pathImage, 28, 'Clique para ver detalhes', contentInfo);
                        contentTable = '';
                    });
                }
            });
        }

        function  putContentTable(el, typeMarker) {
            var contentTable = "<tr><td>" + el.id + "</td><td>" + el.nome + "</td><td>" + el.previsao + "</td>";
            contentTable += "<td>" + el.setorcolaborador + "</td><td>" + el.entregaurgente + "</td><td>" + el.tempo + "</td>";
            return contentTable + "<td><img style='max-height: 20px; max-width: 20px;' src='img/entrega_" + typeMarker + ".png'></img></td></tr>";
        }
    </script>
    <script src="{{asset('js/maps.js')}}"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key={{$keygooglemaps}}&callback=initMap" async defer></script>
@endsection