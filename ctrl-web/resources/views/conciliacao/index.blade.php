
@extends('layouts.mainmenu')

@section('content')

<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-sm-12">
            <ul>
                <div class="panel panel-default form-horizontal">
                    <div class="panel-heading">
                        <h3 class="panel-title">Conciliação Contábil x Financeiro</h3>
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
                                                <div class="col-sm-3">
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

                                                <div class="col-sm-3">
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

                                                <div class="col-sm-2">
                                                    <div class="radio">
                                                        <label>
                                                            <input type="radio" name="pagarReceber" id="pagarReceber1" value="P" checked>
                                                            Pagar
                                                        </label>
                                                    </div>
                                                    <div class="radio">
                                                        <label>
                                                            <input type="radio" name="pagarReceber" id="pagarReceber2" value="R">
                                                            Receber
                                                        </label>
                                                    </div>
                                                </div>

                                                <div class="col-sm-2">
                                                    <button class="btn btn-sm btn-nw-buscas" id='btnConsultaDocs' type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar">
                                                        <span class="fa fa-search fa-lg"></span>
                                                    </button>
                                                    <a class="btn btn-sm btn-github" id='btnLimpar' type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar" href="{{ route('conciliacao.index') }}">
                                                        <span class="fa fa-recycle fa-lg"></span>
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                <div class="col-sm-12">
                                                    <div id="tbl_contfin"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </ul>
        </div>
    </div>
</div>

<div class="modal fade popupModal" id="popup_documentos" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" style="min-width: 60%;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="docTitle">Documentos Contábil x Financeiro</h4>
            </div>
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-group crud_space">
                        <div class="col-sm-12">
                            <div id="tbl_documentos"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade popupModal" id="popup_saldo" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" style="min-width: 90%;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="saldoTitle">Saldo Contábil x Financeiro</h4>
            </div>
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-group crud_space">
                        <div class="col-sm-4">
                            <div id="tbl_financeiro"></div>
                        </div>
                        <div class="col-sm-8">
                            <div id="tbl_contabil"></div>
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

<script src="{{URL::to('js/conciliacao.js')}}" type="text/javascript"></script>

@endsection