
@extends('layouts.mainmenu')

@section('content')

<style>
    .cancelado:hover {
        background-color: tomato !important;
        color: #000 !important;
    }
</style>

<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-sm-12">
            <ul>
                <div class="panel panel-default form-horizontal">
                    <div class="panel-heading">
                        <h3 class="panel-title">Fechamento de Malotes</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <div class="row">
                                    <div id="tabCadastro" class="col-sm-12">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {{ Form::hidden("condicoes", @$condicoes, ["id"=>"condicoes"]) }}
                                                {{ Form::hidden("condicoes-valegas", @$condValeGas, ["id"=>"condicoes-valegas"]) }}
                                                {{ Form::hidden("malote_empresa_id", @$empresa_id, ["id"=>"malote_empresa_id"]) }}
                                                {{ Form::hidden("valegaspedido_id", null, ["id"=>"valegaspedido_id"]) }}
                                                <div class="col-sm-4">
                                                    {!! Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-4 control-label input-sm']) !!}
                                                    <div class="col-sm-8">
                                                        <div class="input-group generalDatePicker">
                                                            {!! Form::text('datainicio',null,['class'=>'form-control input-sm generalDatePicker', 'id' => 'datainicio']) !!}
                                                            <span class="input-group-addon">
                                                                <i class="glyphicon glyphicon-calendar"></i>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-sm-4">
                                                    {!! Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-4 control-label input-sm']) !!}
                                                    <div class="col-sm-8">
                                                        <div class="input-group generalDatePicker">
                                                            {!! Form::text('datafim',null,['class'=>'form-control input-sm generalDatePicker', 'id' => 'datafim']) !!}
                                                            <span class="input-group-addon">
                                                                <i class="glyphicon glyphicon-calendar"></i>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-sm-4">
                                                    <button class="btn btn-sm btn-nw-buscas" id='btnConsultaPedidos' type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar">
                                                        <span class="fa fa-search fa-lg"></span>
                                                    </button>
                                                    <a class="btn btn-sm btn-github" id='btnLimpar' type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar" href="{{ route('malotefechamento.index') }}">
                                                        <span class="fa fa-recycle fa-lg"></span>
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                <div class="col-sm-4">
                                                    {{ Form::label('setor_id', 'Setor:', ['class'=>'col-sm-4 control-label input-sm']) }}
                                                    <div class="col-sm-8">
                                                        {{ Form::select('setor_id', @$setores, null,['id' => 'setor_id', 'class'=>'form-control input-sm selectChosen']) }}
                                                    </div>
                                                </div>

                                                <div class="col-sm-4">
                                                    {{ Form::label('colaborador_id', 'Colaborador:', ['class'=>'col-sm-4 control-label input-sm']) }}
                                                    <div class="col-sm-8">
                                                        {{ Form::select('colaborador_id', [], null,['id' => 'colaborador_id', 'class'=>'form-control input-sm selectChosen']) }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                <div class="col-sm-12">
                                                    <div id="tbl_pedidos"></div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                <div id="condicoes-container" class="col-sm-11 col-sm-offset-1">
                                                    {{-- @foreach($condicoes as $cond)
                                                        <div id="{{$cond->id}}-container" class="col-sm-1 p-t-20 hidden condicao-container">
                                                            <div class="row">
                                                                {{$cond->descricao}}
                                                            </div>
                                                            <div id="{{$cond->id}}-value" class="row condicao-values">
                                                                0
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                    <div class="col-sm-1 p-t-20">
                                                        <div class="row">
                                                            Total de Pedidos
                                                        </div>
                                                        <div id="total_value" class="row condicao-values">
                                                            0
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-1 p-t-20">
                                                        <div class="row">
                                                            Valor Total
                                                        </div>
                                                        <div id="valortotal_value" class="row condicao-values">
                                                            0
                                                        </div>
                                                    </div>
                                                        --}}
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <div class="col-md-4">
                            <button type="button" id="btnSubmit" class="btn btn-nw-registro" style="display: none;">Fechar Malote</button>
                        </div>
                    </div>
                </div>
            </ul>
        </div>
    </div>
</div>

@include('pedido.partials.modal_validagasbolso')

<div class="hidden">
    <!-- <form method="post" target="iframeFinanceiro" id="fmAbrirFinanceiro"> -->
    {{ Form::open(['id'=>'fmAbrirFinanceiro', 'name'=>'fmAbrirFinanceiro', 'target' => 'iframeFinanceiro', 'class' => 'form-horizontal']) }}

    <input type="submit" value="Do Stuff!" />
    <input type="text" id="tipo_lancamento" name="tipo_lancamento" />
    <input type="text" id="pedido_id" name="pedido_id" />
    <input type="text" id="conta_id" name="conta_id" />
    <input type="text" id="cliente_id" name="cliente_id" />
    <input type="text" id="nome" name="nome" />
    <input type="text" id="tipo_lancamento" name="tipo_lancamento" value="R"/>
    <input type="text" id="parcelas_financeiro" name="parcelas_financeiro" />
    <!-- </form> -->
    {!! Form::close() !!}
</div>

<div id="popup_financeiro" class="modal fade popupModal modal-wide" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document" id="fundo_popup">
        <div class="modal-content">
            <div id="popup_int" style="text-align:center;">
                <button type="button" id="btnCloseFinanceiro" class="close" data-dismiss="modal" aria-label="Close" style="margin-right: 20px;"><span aria-hidden="true">&times;</span></button>
                <iframe sandbox="allow-same-origin allow-scripts allow-popups allow-forms" id="iframeFinanceiro" name="iframeFinanceiro" style="border: 0; width:100%; height:500px;margin-top:-20px;"></iframe>
            </div>
        </div>
    </div>
</div>

<div class="modal fade popupModal" id="popup_fecharmalote" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
				<h4 class="modal-title" id="myModalLabelFecharMalote">Confirmar fechamento do malote</h4>
			</div>
			<div class="modal-body  center text-center">
				<div class="box-body center text-center">
					<div class="form-group crud_space col-sm-12">
						{!! Form::label('data_fechamento', 'Data de Fechamento:', ['class'=>'col-sm-3 control-label input-sm','style'=>'text-align:right;']) !!}
						<div class="col-sm-9">
							<div class="input-group date generalDateAll" id="datetimepicker1">
								{!! Form::text('data_fechamento',null,['class'=>'form-control input-sm']) !!}
								<span class="input-group-addon">
									<span class="glyphicon glyphicon-calendar"></span>
								</span>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" id="btnCloseFecharMalote" class="btn btn-default" data-dismiss="modal">Cancelar</button>
				<button type="button" id="btnFecharMalote" class="btn btn-primary">Fechar Malote</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade popupModal" id="popup_parcelas" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" style="min-width: 60%;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabelFecharMalote">Confirme o Reparcelamento</h4>
            </div>
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-group crud_space">
                        <div class="col-sm-12 p-b-10">
                            {{ Form::hidden("url_parcelas", null, ["id"=>"url_parcelas"]) }}
                            <button type="button" class="btn btn-nw-registro" id="btnReparcelar">
                                Reparcelar
                            </button>
                        </div>
                    </div>
                    <div class="form-group crud_space">
                        <div class="col-sm-12">
                            <div id="tbl_parcelas"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<link href="{{URL::to('plugins/tabulator/css/tabulator_bootstrap3.min.css')}}" rel="stylesheet" type="text/css" />
<script src="{{URL::to('plugins/tabulator/js/tabulator.min.js')}}" type="text/javascript"></script>
<script src="{{URL::to('js/tabulatorLocalization.js')}}" type="text/javascript"></script>
<script src="{{URL::to('js/maloteFechamento.js')}}" type="text/javascript"></script>

@endsection