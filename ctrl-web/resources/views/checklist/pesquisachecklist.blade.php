 @extends('layouts.mainmenu') @section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            {{ Form::open(['id'=>'fmCadastro','class' => 'form-horizontal', 'files' => true]) }}
            <ul>
                <div class="panel panel-default">
                    <div class="header panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">Pesquisa de Checklist</h3>
                        </div>
                    </div>
                    <!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Checklist</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-12">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {!! Form::label('pesquisaform', 'Respondido:', ['class'=>'col-md-2 control-label input-sm']) !!}
                                                <div class="col-md-1 checkbox">
                                                    {!! Form::checkbox('pesquisaform',1) !!}
                                                </div>
                                                {{ Form::label('empresa', 'Empresa:', ['class'=>'col-sm-1 control-label input-sm','style'=>'margin-left:-3%;']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('empresa',$empresas, null, ['id'=>'empresa', 'class' => 'form-control selectChosen', 'style'=>'padding:0px;max-height:24px;']) }}
                                                </div>
                                                {{ Form::label('tipochecklist', 'Tipo Checklist:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('tipochecklist',$tipochecklist, null, ['id'=>'tipochecklist', 'class' => 'form-control selectChosen', 'style'=>'padding:0px;max-height:24px;']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;'])}}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datainicio',null,['id'=>'datainicio','class'=>'form-control input-sm generalDatePicker','readonly']) }}
                                                        <span class="input-group-addon">
                                                                <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {{ Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;']) }}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datafim',null,['id'=>'datafim','class'=>'form-control input-sm generalDatePicker','readonly']) }}
                                                        <span class="input-group-addon">
                                                                <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-sm-3">
                                                    <button type="button" id='btnLimpar' onclick="window.location.href = '{{route('checklist.index')}}'" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
													<button id="btnFiltrarCheck" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar Formulários"><span class="fa fa-search fa-lg"></span></button>
                                                </div>
                                                {{Form::close()}}
                                            </div>
                                            <div class="form-group crud_space">
                                                <div class="col-md-10 col-md-push-1">
                                                    <table id="tblCadastro" class="dataTableNoAll table table-bordered table-hover table-condensed">
                                                        <thead>
                                                            <tr>
                                                                <th>ID</th>
                                                                <th>Cód Empresa</th>
                                                                <th>Razão Social</th>
                                                                <th>Respondido por</th>
                                                                <th>Tipo</th>
                                                                <th>Situação</th>
                                                                <th>Operações</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="checklists-list" name="checklist-list">
                                                            @if(isset($checklists))
                                                            @foreach ($checklists as $checklist)
                                                                <tr id="checklist{{$checklist->id}}">
                                                                    <td>{{$checklist->id}}</td>
                                                                    <td>{{$checklist->empresa_id}}</td>
                                                                    <td>{{$checklist->nome_informal}}</td>
                                                                    <td>
                                                                        @if(isset($checklist->empresacadastro))
                                                                            {{$checklist->empresacadastro}}
                                                                        @endif
                                                                    </td>
                                                                    <td>{{$checklist->tipo}}</td>
                                                                    <td>{{$checklist->tipoform == "0" ? "Formulário" : "Respondido"}}</td>
                                                                    <td>
                                                                        @if($checklist->tipoform == "1")
                                                                            <button onclick="window.location.href = '{{route('checklist.show',$checklist->id)}}'" type="button"
                                                                                class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                                                    <span class="fa fa-eye fa-lg"></span>
                                                                            </button>
                                                                        @endif
                                                                        @if($checklist->tipoform == "0")
                                                                            <button class='btn btn-nw-geral btn-xs' id="btnResponder"
                                                                                data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Responder">
                                                                                <span class="fa fa-pencil-square-o fa-lg"></span>
                                                                            </button>
                                                                        @endif
                                                                        <a href="{{URL::to('checklist/impressao/'.$checklist->id)}}" target="_blank"
                                                                            type="button" id='btnPdf' class="btn btn-nw-registro btn-xs" data-toggle='tooltip' data-trigger="hover"
                                                                            data-placement="bottom" title="Gerar PDF">
                                                                                <span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span>
                                                                        </a>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                            @endif
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
            </ul>
        </div>
    </div>
</div>
<script type="text/javascript" src="{{URL::to('js/pesquisachecklist.js')}}"></script>
@endsection