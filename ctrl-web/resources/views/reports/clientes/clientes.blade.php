@extends('layouts.mainmenu')
@section('content')
<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Clientes</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Clientes</a></li>
                            <li><a href="#tab_2" data-toggle="tab">Produto/Segmento/Setor</a></li>
                            <li><a href="#tab_3" data-toggle="tab">Clientes Ativos/Inativos</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-sm-12">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'fmFiltros','class'=>'form-horizontal'])}}
                                            <div class="form-group crud_space">
                                                {{ Form::label('uf', 'Estado:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::select('uf',$estados,null,['id' => 'uf','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('cidade_id', 'Cidade:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::select('cidade_id',[],null,['id' => 'cidade_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                <div class="col-sm-2">
                                                    <button type="button" id='btnLimpar' onclick="window.location.href = '{{route('report.clientes')}}'" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                                                    <button id="btnFiltroClientes" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space"> 
                                                {{ Form::label('segmento_id', 'Segmento:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::select('segmento_id',$segmentos,null,['id' => 'segmento_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('tipopessoa_id', 'Tipo de Pessoa:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::select('tipopessoa_id',$tipopessoa,null,['id' => 'tipopessoa_id','class'=>'form-control selectChosen input-sm']) }}
                                                    {{ Form::hidden('cep', @$cep,['id'=>'cep']) }}
                                                </div>
                                                <div id="checkbox">
                                                    {!! Form::label('ativo', 'Ativo', ['class'=>'col-md-1 control-label input-sm']) !!}
                                                    <div class="col-md-1 checkbox">
                                                        {!! Form::checkbox('ativo',1) !!}
                                                    </div>
                                                </div>
                                            </div> 
                                            {{ Form::close() }}
                                        </div>
                                    </div>
                                </div>
                            </div>  
                            <div class="tab-pane" id="tab_2">
                                @include('reports.clientes.segmento_setor_produto.segmento_setor_produto')
                            </div>
                            <div class="tab-pane" id="tab_3">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-sm-12">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'fmFiltrosAtivos','class'=>'form-horizontal'])}}
                                            <div class="form-group crud_space">
                                                {{ Form::label('at_setor_id', 'Setor:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::select('at_setor_id',$setores,null,['id' => 'at_setor_id','class'=>'form-control input-sm selectChosen']) }}
                                                </div>
                                                <div id="checkbox">
                                                    {!! Form::label('at_ativo', 'Ativo:', ['class'=>'col-md-1 control-label input-sm']) !!}
                                                    <div class="col-md-1 checkbox">
                                                        {!! Form::checkbox('at_ativo',1) !!}
                                                    </div>
                                                </div>
                                                <div class="col-sm-2">
                                                    <button type="button" id='at_btnLimpar' onclick="window.location.href = '{{route('report.clientes')}}'" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                                                    <button id="btnFiltroClientesAtivos" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
                                                    <button id="btnFiltroClientesAtivosXls" type="button" class="btn btn-success btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Exportar Relatório"><span class="fa fa-file-excel-o fa-lg"></span></button>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space"> 
                                                {{ Form::label('at_segmento_id', 'Segmento:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::select('at_segmento_id',$segmentos,null,['id' => 'at_segmento_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('at_tipopessoa_id', 'Tipo de Pessoa:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::select('at_tipopessoa_id',$tipopessoa,null,['id' => 'at_tipopessoa_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                            </div> 
                                            {{ Form::close() }}
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

@include('general.modal_report_iframe')

<script type="text/javascript" src="{{URL::to('js/reportclientes.js')}}"></script>
<script type="text/javascript" src="{{URL::to('js/endereco.js')}}"></script>
<script type="text/javascript">
@if(isset($cep) && $cep !== '' && $cep !== null)
    @if($errors -> any())
        var cepEmpresa = false;
    @else
        var cepEmpresa = true;
        @if(str_contains(Request::url(), '/edit') || isset($show))
            cepEmpresa = false;
        @else
            setInputsEnderecoPadrao('#cep', '#cidade_id', '#uf', '#bairro_id', '#rua_id');
            buscarEnderecoPorCep('geral');
        @endif
    @endif
@else
    var cepEmpresa = false;
@endif    
$("#uf").change(function(){
    setInputsEnderecoPadrao('#cep', '#cidade_id', '#uf', '#bairro_id', '#rua_id');
    buscarCidades(null,'geral');
    cepEmpresa = true;
    padraoBusca = 'geral';
});
</script>
@endsection