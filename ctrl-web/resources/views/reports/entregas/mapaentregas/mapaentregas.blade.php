@extends('layouts.mainmenu')
@section('content')
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Mapa de Entregas</h3>
                        </div>
                        <!-- /.box-header -->
                        <div class="nav-tabs-custom">
                            <ul class="nav nav-tabs">
                                <li class="active"><a href="#tab_1" data-toggle="tab">Mapa de Entregas</a></li>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane active" id="tab_1">
                                    <!-- form start -->
                                    <div class="row">
                                        <div id="tabCadastro" class="col-sm-12">
                                            <div class="box-body">
                                                {{ Form::open(['id' => 'fmFiltros','class'=>'form-horizontal'])}}
                                                <div class="form-group crud_space">
                                                    {{ Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        <div class="input-group generalDatePicker">
                                                            {{ Form::text('datainicio',null,['id' => 'datainicio','class'=>'form-control generalDatePicker input-sm']) }}
                                                            <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                        </div>
                                                    </div>
                                                    {{ Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        <div class="input-group generalDatePicker">
                                                            {{ Form::text('datafim',null,['id' => 'datafim','class'=>'form-control generalDatePicker input-sm']) }}
                                                            <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                        </div>
                                                    </div>
                                                    {{ Form::label('setor_id', 'Setor:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        {{ Form::select('setor_id', $setores, null,['id' => 'setor_id','class'=>'form-control selectChosen input-sm']) }}
                                                    </div>
                                                    <div class="col-sm-2 col-sm-offset-1">
                                                        <button id="btnFiltro" type="button" class="btn btn-nw-geral btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar Entregas"><span class="fa fa-search fa-lg"></span></button>
                                                        <button type="button" id='btnLimpar' class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                                                    </div>
                                                </div>
                                                {{ Form::close() }}
                                                <div class="form-group crud_space margTop_30">
                                                    <div class="col-sm-4" id="divChartCanvas">
                                                        <canvas id="chart"></canvas>
                                                    </div>
                                                    <div class="col-sm-8" style='font-size: 12.5px' id="divTblDataChart">
                                                        <table id="tblDataChart" class='table table-hover table-bordered table-responsive table-condensed padding-table-3'>
                                                            <thead>
                                                            <th>Pedido</th>
                                                            <th>Cliente</th>
                                                            <th>Endereço</th>
                                                            <th>Entrega</th>
                                                            </thead>
                                                            <tbody>
                                                            </tbody>
                                                        </table>
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
    <div class='hidden'>
        <div id="legendMaps"><span>Legenda</span></div>
    </div>
    <script src="{{asset('plugins/chartjs.min.js')}}"></script>
    <script src="{{asset('js/lib/collection.js')}}"></script>
    <script type="text/javascript">
        function setLatLgtEmpresa(){
            latitude = parseFloat("{{Session::get('empresa_padrao')->latitude}}");
            longitude = parseFloat("{{Session::get('empresa_padrao')->longitude}}");
            if(isEmpty(latitude) || isEmpty(longitude))
                bootbox.alert("Não foi possível localizar a latitude e longitude da empresa.");
        }
    </script>
    <script src="{{asset('js/reportmapaentrega.js')}}"></script>
    <script src="{{asset('js/maps.js')}}"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key={{$keygooglemaps}}&callback=initMap" async defer></script>
@endsection