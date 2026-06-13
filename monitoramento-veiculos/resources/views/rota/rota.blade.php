
@extends('layouts.mainmenu')

@section('content')

<link href="{{URL::to('plugins/slideMenu/css/base.css')}}" rel="stylesheet">
<link href="{{URL::to('plugins/slideMenu/css/style.css')}}" rel="stylesheet">
<style>
    #floating-panel {
        position: absolute;
        top: 10px;
        left: 25%;
        z-index: 5;
        background-color: #fff;
        padding: 5px;
        border: 1px solid #999;
        text-align: center;
        font-family: 'Roboto','sans-serif';
        line-height: 30px;
        padding-left: 10px;
    }
    .panel {
        margin-bottom: 5px;
    }
    .box-header {
        padding: 0px;
    }
    .col-xs-12 {
        padding-right: 0px;
    }
    .content {
        padding-top: 5px;
    }
    .table-info {
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
        border-bottom-left-radius: 10px;
        border-bottom-right-radius: 10px;
    }
    table { border-collapse: separate; }
</style>
@include('rastreamento.css')
<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Consulta de Rotas por Veículo</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <div id="tabCadastro" class="col-md-12">
                                    <div class="box-body">
                                        {{ Form::open(['id' => 'empresa','class'=>'form-horizontal'])}}
                                        <div class="form-group crud_space">
                                            {{ Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;'])}}
                                            <div class="col-sm-2">
                                                <div class="input-group date generalDateTimePicker" id="datetimepicker1">
                                                    {{ Form::datetime('datainicio',\Carbon\Carbon::now()->startOfDay()->format("d/m/Y H:i"),['id'=>'datainicio','class'=>'form-control input-sm generalDateTimePicker']) }}
                                                    <span class="input-group-addon">
                                                        <span class="glyphicon glyphicon-calendar"></span>
                                                    </span>
                                                </div>
                                            </div>
                                            {{ Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-1 col-sm-offset-1 control-label input-sm','style'=>'text-align:right;'])}}
                                            <div class="col-sm-2">
                                                <div class="input-group date generalDateTimePicker" id="datetimepicker2">
                                                    {{ Form::datetime('datafim',null,['id'=>'datafim','class'=>'form-control input-sm generalDateTimePicker']) }}
                                                    <span class="input-group-addon">
                                                        <span class="glyphicon glyphicon-calendar"></span>
                                                    </span>
                                                </div>
                                            </div>
                                            {{ Form::label('somente_paradas', 'Mostrar Somente Paradas:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;'])}}
                                            <div class="col-sm-2 checkbox">
                                                {{Form::checkbox("somente_paradas", 0, 0, ["id"=>"somente_paradas"])}}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {{ Form::label('empresa_id', 'Empresa:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                            <div class="col-sm-3">
                                                {{ Form::select('empresa_id',$empresas, $empresas->count() == 2 ? $empresas->keys()->last() : null,['id' => 'empresa_id','class'=>'form-control selectChosen input-sm', 'onchange'=>'carregarVeiculos();']) }}
                                            </div>
                                            {{ Form::label('veiculo_id', 'Veículo:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                            <div class="col-sm-3">
                                                {{ Form::select('veiculo_id',[],null,['id' => 'veiculo_id','class'=>'form-control selectChosen input-sm']) }}
                                            </div>
                                            <div class="col-sm-2">
                                                <button type="button" id='btnLimpar' onclick="window.location.href = '{{route('rota.index')}}'" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                                                <button id="btnRota" onclick="carregarRota(1);" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Rota"><span class="fa fa-search fa-lg"></span></button>
                                                <button id="btnRotaSnap" onclick="carregarRota(2);" type="button" class="btn btn-nw-buscas1 btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Rota Alinhada" style="background-color:green;color:#fff;"><span class="fa fa-search fa-lg"></span></button>
                                                <button id="btnRotaAmbas" onclick="carregarRota(3);" type="button" class="btn btn-nw-buscas2 btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Ambas" style="background-color:orange;color:#fff;"><span class="fa fa-search fa-lg"></span></button>
                                            </div>
                                        </div>
                                        {{ Form::close() }}
                                    </div>
                                    <!-- /.box-body -->
                                </div>

                            </div>
                        </div><!-- /.box-body -->
                    </div><!-- /.box -->
                </div><!-- /.col -->
            </div><!-- /.row -->

        </div><!-- /.content-wrapper -->
    </div>
    <div id="divMapa" style="padding-bottom:5px;">
        <div class="row">
            <div class="col-xs-12">
                <div id="mapas2">
                    <div id="map" width="100%" height="500px"></div>
                </div>
            </div>
        </div>
    </div>
    <div id="divCadastro1">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="panel panel-default">
                        <div class="panel-body">
                            <div class="col-md-12">
                                <div id="tabFiltro" class="col-md-12" style="padding-left: 5px; padding-right: 5px;">

                                    <div id="showDivCerca">
                                        <table border="0" align="right" width="100%" height="100%">
                                            <tbody>
                                                <tr>
                                                    <td id="tdControle_animacao" width="25%" nowrap=""></td>
                                                    <td id="tdShowInfoAnimacao" width="50%" style="width: 50%; padding-left: 60px;"><div id="showInfoAnimacao"></div></td>
                                                    <td id="tdShowDivCerca" align="right" width="25%">
                                                        <table border="0" align="right" width="100%" height="100%">
                                                            <tbody>
                                                                <tr>
                                                                    <td align="right" width="90%" style="padding-right:10px;"><b>EXIBIR CERCA ELETRÔNICA: </b></td>
                                                                    <td align="right">
                                                                        <select name="showCerca" id="selCerca" onchange="selectedCerca(this.value);">
                                                                            <option value="0" id="id0" selected="selected">Nenhuma</option>
                                                                        </select>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>

        var geocoder;
        var c_host = '{{url("/")}}';
        var c_caminho = "/";
        var empresa_id = 0;
        var googlemapskey = '{{$maps->keygooglemaps}}';
        var Demo = {
            map: null,
            infoWindow: null
        };
    </script>
    <script src="{{URL::to('plugins/slideMenu/js/jquery.hoveraccordion.js')}}"></script>
    <script src="{{URL::to('plugins/slideMenu/js/jquery.localscroll-min.js')}}"></script>
    <script src="{{URL::to('plugins/slideMenu/js/jquery.scrollTo-min.js')}}"></script>
    <script src="{{URL::to('plugins/slideMenu/js/menu.js')}}"></script>
    @include('rota.config_map_js')
    <script>

        function initMap() {
            geocoder = new google.maps.Geocoder();
            
        }
    </script>

    <script src="https://maps.googleapis.com/maps/api/js?key={{$maps->keygooglemaps}}&callback=initMap"></script>
    <script src="{{URL::to('js/jquery.progressbar.js')}}"></script>
    <script src="{{URL::to('js/jquery.progressbar.min.js')}}"></script>
    <script src="{{URL::to('js/funcoes_rotas.js')}}"></script>
    <script src="{{URL::to('js/funcoes_rota_animada.js')}}"></script>
    <script src="{{URL::to('js/funcoes_maps.js')}}"></script>
    <script src="{{URL::to('js/funcoes_cerca_eletronica.js')}}"></script>
    <script src="{{URL::to('js/ajax.js')}}"></script>
    <script src="{{URL::to('js/functions.js')}}"></script>


    <script>
        $(document).ready(function ($) {
            $("#empresa_id").trigger("change");
            Demo.closeInfoWindow = function() {
                Demo.infoWindow.close();
                if(id_monitorado[0] != null){
                    id_monitorado[0] = 0;
                }
            };
            Demo.openInfoWindow = function(marker, content, tipo, dados) {
                Demo.infoWindow.setContent(content);
                if(tipo == c_dinamico)
                {
                    var handle = function() {
                        google.maps.event.clearListeners(handle);
                        if(id_monitorado[0] != null){
                            id_monitorado[0] = 0;
                        }
                    };
                    google.maps.event.addListener(Demo.infoWindow, 'closeclick', handle);
                }
                id_monitorado[0] = dados.id;
                Demo.infoWindow.open(Demo.map, marker);
            };
            Demo.infoWindow = new google.maps.InfoWindow();
           init();
        });

        function carregarVeiculos(){
            $.get("{{ url('veiculos/dropdown')}}",
		{ option: $("#empresa_id").val() },
		function(data) {
			var veiculos = $('#veiculo_id');
			veiculos.empty();
			$.each(data.veiculos, function(index, element) {
				veiculos.append("<option value='"+ element.id +"'>" + element.placa + " : " + element.descricao + "</option>");
			});
                        veiculos.trigger("chosen:updated");
                        inicia_rotas(config_dados_moveis(), data.empresa.latitude, data.empresa.longitude, config_start_zoom(), config_show_control(),config_overlay(), data.empresa.id);
		});
        }
        
        function carregarRota(snap){
            setTimeout(function(){ 
              gera_rotas($('#veiculo_id').val(), 110, 110, snap);
            }, 500);
            
        }
    </script>
    <script src="{{URL::to('js/markermanager_1.js')}}"></script>
    @endsection
