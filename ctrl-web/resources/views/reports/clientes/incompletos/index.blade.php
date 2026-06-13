@extends('layouts.mainmenu') 

@section('content')

<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Clientes Incompletos</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Cliente Incompleto</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-11">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'fornecedores','class'=>'form-horizontal'])}}
                                            <div class="form-group crud_space">
                                                {!! Form::label('incompleto', 'Dados Incompletos:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {{Form::select('incompleto', [""=>"Selecione","01"=>"Data de Nascimento","02"=>"Setor","03"=>"Telefone"], null, ['id' => 'incompleto', 'class' => 'selectChosen input-sm form-control'])}}
                                                </div>
                                                <div class="col-sm-2">
                                                    <button type="button" id='btnLimpar' onclick="window.location.href = '{{route('report.incompletos')}}'" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                                                    <button id="btnFiltroIncompletos" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
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
@include('general.modal_report_iframe')
<script type="text/javascript" src="{{URL::to('js/reportclientes.js')}}"></script>
@endsection