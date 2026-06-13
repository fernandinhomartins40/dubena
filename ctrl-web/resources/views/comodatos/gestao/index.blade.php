
@extends('layouts.mainmenu')

@section('content')

<link href="{{URL::to('plugins/tabulator/css/tabulator_bootstrap3.min.css')}}" rel="stylesheet" type="text/css" />
<script src="{{URL::to('plugins/tabulator/js/tabulator.min.js')}}" type="text/javascript"></script>
<script src="{{URL::to('js/tabulatorLocalization.js')}}" type="text/javascript"></script>

@php
    $grids = [
        1 => "Vencidos",
        2 => "Giro"
    ];
@endphp

<style>
    .alert-circle {
        border-radius: 28px;
        width: 15px;
        height: 15px;
    }
    .tabulator .tabulator-header .tabulator-col .tabulator-col-title {
        white-space: normal !important;
        word-wrap: break-word;
        vertical-align: bottom;
    }
    .tabulator-col {
        justify-content: flex-end !important;
    }
</style>

<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-sm-12">
            <ul>
                <div class="panel panel-default form-horizontal">
                    <div class="panel-heading">
                        <h3 class="panel-title">Gestão de Comodatos</h3>
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
                                            <div class="row p-b-5">
                                                <div class="col-sm-3">
                                                    <button type="button" id="btnRefresh" class="btn btn-nw-registro">
                                                        <div class="d-flex flex-row align-center justify-center" style="gap: 8px;">
                                                            <span>
                                                                <i class="fa fa-refresh" aria-hidden="true"></i>
                                                            </span>
                                                            <span>
                                                                Atualizar
                                                            </span>
                                                        </div>
                                                    </button>
                                                    <button type="button" id="btnModalImpressao" class="btn btn-nw-registro">
                                                        <div class="d-flex flex-row align-center justify-center" style="gap: 8px;">
                                                            <span>
                                                                <i class="fa fa-file" aria-hidden="true"></i>
                                                            </span>
                                                            <span>
                                                                Imprimir
                                                            </span>
                                                        </div>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-sm-6">
                                                    <div class="row">
                                                        <div class="col-sm-12">
                                                            <div id="tbl_saldos"></div>
                                                        </div>
                                                    </div>

                                                    <div class="row">
                                                        <div class="col-sm-12">
                                                            <div id="tbl_vencimentos"></div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-sm-6">
                                                    <div class="row">
                                                        <div class="col-sm-12">
                                                            <div id="tbl_giro"></div>
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
            </ul>
        </div>
    </div>
</div>

<div class="modal fade" id="modalImpressao" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="modalBuscaConveniadoLabel">Imprimir Tabelas</h4>
            </div>
            <div class="modal-body col-md-12">
                <div class="row">
                    <div class="col-sm-6">
                        {{ Form::select('grid', $grids, null,['class'=>'form-control input-sm selectChosen', 'id' => 'grid']) }}
                    </div>
                    <div class="col-sm-2">
                        <button type="button" id="btnImprimir" class="btn btn-sm btn-nw-registro">
                            Imprimir
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                {{-- <button type="button" class="btn btn-nw-geral" data-dismiss="modal">Cancelar</button> --}}
            </div>
        </div>
    </div>
</div>

<script src="{{URL::to('js/comodatoGestao.js')}}" type="text/javascript"></script>

@endsection