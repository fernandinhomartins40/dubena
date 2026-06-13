@extends('layouts.mainmenu')

@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Inconsistências Cadastrais</h3>
                    </div>

                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Ruas</a></li>
                            <li class=""><a href="#tab_2" data-toggle="tab">Bairros</a></li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-11">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                <div class="col-md-12">
                                                    <div class="form-group crud_space">
                                                        {!! Form::label('uf', 'UF:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                        <div class="col-sm-2">
                                                            {!! Form::select('uf', $estados, $uf_empresa,['class'=>'selectChosen uf', 'id'=>'uf']) !!}
                                                        </div>
                                                        {!! Form::label('cidade_id', 'Cidade:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                        <div class="col-sm-3">
                                                            {!! Form::select('cidade_id', ["" => "Selecione"], null,['class'=>'selectChosen', 'id'=>'cidade_id']) !!}
                                                        </div>
                                                        <div class="col-sm-2">
                                                            <button type="button" id="btnBuscar" class="btn btn-sm btn-nw-buscas"
                                                                    data-toggle="tooltip" data-trigger="hover" data-placement="bottom" title="Filtar">
                                                                <span class="fa fa-search fa-lg"></span>
                                                            </button>
                                                            <button type="button" onclick="window.location.href = '{{route('inconsistencia.index')}}'" id="btnImprimirIframe" class="btn btn-sm btn-github"
                                                                    data-toggle="tooltip" data-trigger="hover" data-placement="bottom" title="Limpar">
                                                                <span class="fa fa-recycle fa-lg"></span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                <div class="col-sm-12">
                                                    <div id="tbl_ruainc"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane" id="tab_2">
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-11">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                <div class="col-md-12">
                                                    <div class="form-group crud_space">
                                                        {!! Form::label('uf_bairro', 'UF:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                        <div class="col-sm-2">
                                                            {!! Form::select('uf_bairro', $estados, $uf_empresa,['class'=>'selectChosen', 'id'=>'uf_bairro']) !!}
                                                        </div>
                                                        {!! Form::label('cidade_id_bairro', 'Cidade:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                        <div class="col-sm-3">
                                                            {!! Form::select('cidade_id_bairro', ["" => "Selecione"], null,['class'=>'selectChosen', 'id'=>'cidade_id_bairro']) !!}
                                                        </div>
                                                        <div class="col-sm-2">
                                                            <button type="button" id="btnBuscarBairro" class="btn btn-sm btn-nw-buscas"
                                                                    data-toggle="tooltip" data-trigger="hover" data-placement="bottom" title="Filtar">
                                                                <span class="fa fa-search fa-lg"></span>
                                                            </button>
                                                            <button type="button" onclick="window.location.href = '{{route('inconsistencia.index')}}'" id="btnImprimirIframe" class="btn btn-sm btn-github"
                                                                    data-toggle="tooltip" data-trigger="hover" data-placement="bottom" title="Limpar">
                                                                <span class="fa fa-recycle fa-lg"></span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                <div class="col-sm-12">
                                                    <div id="tbl_bairroinc"></div>
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
</div>

<div class="modal fade popupModal" id="popup_registros" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md" style="min-width: 90%;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="ruaTitle">Registros que utilizam as Ruas</h4>
            </div>
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-group crud_space">
                        <div id="tocomp_title" class="col-sm-6">
                        </div>
                        <div id="comp_title" class="col-sm-6">
                        </div>
                    </div>
                    <div class="form-group crud_space">
                        <div class="col-sm-6">
                            <div id="tbl_tocomp"></div>
                        </div>
                        <div class="col-sm-6">
                            <div id="tbl_comp"></div>
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

<script>
    const defaultCidadeId = {{@$cidade_empresa}};
    const urlIgnorarRuas = "{{route('inconsistencia.ignorarRua')}}";
    const urlIgnorarBairros = "{{route('inconsistencia.ignorarBairro')}}";

    const urlRuaEdit = "{{route('rua.index', ['id' => 'rua_id'])}}";
    const urlGetRuas = "{{route('inconsistencia.getInconsistencias')}}?tipo=1";
    const urlRegistrosRuas = "{{route('inconsistencia.getRegistros')}}?tipo=1";

    const urlBairroEdit = "{{route('bairro.index', ['id' => 'bairro_id'])}}";
    const urlGetBairros = "{{route('inconsistencia.getInconsistencias')}}?tipo=2";
    const urlRegistrosBairros = "{{route('inconsistencia.getRegistros')}}?tipo=2";
</script>

<script src="{{URL::to('js/inconsistencia.js')}}" type="text/javascript"></script>
@endsection
