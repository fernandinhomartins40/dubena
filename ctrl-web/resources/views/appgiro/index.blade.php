@extends('layouts.mainmenu')

@section('content')

<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-sm-12">
            <ul>
                <div class="panel panel-default form-horizontal">
                    <div class="panel-heading">
                        <h3 class="panel-title">Controle de Giro</h3>
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
                                                <div class="col-sm-2">
                                                    <button class="btn btn-sm btn-nw-buscas" id='btnNotificar' type="button">
                                                        Enviar Notificar
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                <div class="col-sm-12">
                                                    <div id="tbl_giro"></div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                <div class="col-sm-2">
                                                    <div class="row">
                                                        <div class="col-sm-2">
                                                            <div class="emEntrega" style="width: 20px; height: 20px;"></div>
                                                        </div>
                                                        <div class="col-sm-4">Notificado</div>
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

<div class="modal fade popupModal" id="popup_notificacao" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
	<div class="modal-dialog" style="min-width: 60%;">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
				<h4 class="modal-title" id="myModalLabelFecharMalote">Selecione o Layout para Notificação</h4>
			</div>
			<div class="modal-body">
				<div class="box-body">
					<div class="form-group crud_space col-sm-11">
                        <table id="tbl_notificacao">
                            <thead>
                                <th>Cód</th>
                                <th>Titulo</th>
                                <th>Corpo</th>
                            </thead>
                            <tbody>
                                @isset($notiLayouts)
                                    @foreach ($notiLayouts as $noti)
                                        <tr>
                                            <td>{{$noti->id}}</td>
                                            <td>{{$noti->fcmtitle}}</td>
                                            <td>{{$noti->fcmbody}}</td>
                                        </tr>
                                    @endforeach
                                @endisset
                            </tbody>
                        </table>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" id="btnFechar" class="btn btn-nw-geral" data-dismiss="modal">Cancelar</button>
				<button type="button" id="btnEnviarNoti" class="btn btn-nw-registro">Enviar Notificação</button>
			</div>
		</div>
	</div>
</div>

<link href="{{URL::to('plugins/tabulator/css/tabulator_bootstrap3.min.css')}}" rel="stylesheet" type="text/css" />
<script src="{{URL::to('plugins/tabulator/js/tabulator.min.js')}}" type="text/javascript"></script>
<script src="{{URL::to('js/tabulatorLocalization.js')}}" type="text/javascript"></script>

<script src="{{URL::to('js/appgiro.js')}}" type="text/javascript"></script>

@endsection