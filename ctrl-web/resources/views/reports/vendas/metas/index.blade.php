@extends('layouts.mainmenu')

@section('content')
<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Mapa de Metas x Vendas</h3>
                    </div><!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Mapa de Meta x Venda</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1"><!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-sm-12">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'fmFiltros','class'=>'form-horizontal'])}}
                                            <div class="form-group crud_space">
                                                {{ Form::label('grupo_id', 'Grupo:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('grupo_id', $grupo, null,['id' => 'grupo_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('regiao_id', 'Regional:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('regiao_id', $regionais, null,['id' => 'regiao_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('empresa_id', 'Empresa:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('empresa_id', [], null,['id' => 'empresa_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('produto', 'Produto:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('produto', $produtos, null,['id' => 'produto','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('ano', 'Ano:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('ano', $years, null,['id' => 'ano','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('mes', 'Mês:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('mes', $meses, null,['id' => 'mes','class'=>'form-control selectChosen input-sm']) }}
                                                    {{ Form::hidden('hidden_setor', null,['id' => 'hidden_setor']) }}
                                                </div>
                                                <div class="col-sm-2">
                                                    <button id="btnFiltro" type="button" class="btn btn-nw-geral btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar Metas"><span class="fa fa-search fa-lg"></span></button>
                                                    <button type="button" id='btnLimpar' class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                                                </div>
                                            </div> 
                                            {{ Form::close() }}
                                            <div class="form-group crud_space margTop_30" style="margin-left: 1.5%">
                                                <div id="empresa-container" class="col-sm-5 hidden">
                                                    <div id="chart-container" style="position:relative;height:350px;width:450px;margin-left:8%;">
                                                        <canvas id="chartBairros" style="height:350px;width:450px;"></canvas>
                                                    </div>
                                                    <div id="tableRuas" class="hidden" style="margin-top:15px;">
                                                        <div id="table" style="position:relative;height:300px">
                                                            <table id="tblRuas" class='table table-hover table-responsive table-condensed'>
                                                                <thead>
                                                                    <th>Ruas</th>
                                                                    <th>Quantidade</th>
                                                                </thead>
                                                                <tbody>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div id="mapa-container" class="col-sm-12"> 
                                                    <div id="divMapa" style="height: 700px; max-height: 650px;"></div>
                                                </div>
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
<script src="{{asset('js/lib/collection.js')}}"></script>
@include('reports.vendas.metas.partials_js')
@endsection