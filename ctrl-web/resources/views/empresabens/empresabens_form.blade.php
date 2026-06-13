@extends('layouts.mainmenu')
@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
            @if(isset($empresabens))
            {{ Form::model($empresabens, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('empresabens.update', $empresabens->id))) }}
            @else
            {{ Form::open(['id'=>'fmCadastro', 'route' => 'empresabens.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif
            <ul>

                <div class="nav-tabs-custom">
                    <div class="header panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">
                                Bem
                            </h3>
                        </div>
                    </div><!-- /.box-header -->
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab_1">
                            <!-- form start -->
                            <div class="row">
                                <div id="tabCadastro" class="col-md-11">
                                    <div class="box-body">
                                        <div class="form-group crud_space">
                                            {!! Form::label('datacadastro', 'Data Cadastro:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                <div class="input-group generalDatePicker">
                                                    {!! Form::text('datacadastro',null,['class'=>'form-control input-sm generalDatePicker', 'id' => 'datacadastro']) !!}
                                                    <span class="input-group-addon">
                                                        <i class="glyphicon glyphicon-calendar"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-6">
                                                {!! Form::text('descricao',null,['class'=>'form-control input-sm', 'id' => 'descricao']) !!}
                                                {!! Form::hidden('id',null,['id' => 'id']) !!}
                                            </div>
                                            {!! Form::label('numeroserie', 'Nº de Série:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::text('numeroserie',null,['class'=>'form-control input-sm number', 'id' => 'numeroserie']) !!}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {!! Form::label('valororiginal', 'Valor Original:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::text('valororiginal',null,['class'=>'form-control input-sm dinheiro', 'id' => 'valororiginal']) !!}
                                            </div>
                                            {!! Form::label('valor', 'Valor:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::text('valor',null,['class'=>'form-control input-sm dinheiro', 'id' => 'valor', 'disabled' => 'disabled']) !!}
                                            </div>
                                            {!! Form::label('depreciacaoporcentagem', 'Depreciação (%):', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::text('depreciacaoporcentagem',null,['class'=>'form-control input-sm percentagem', 'id' => 'depreciacaoporcentagem']) !!}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {!! Form::label('depreciacaovalor', 'Depreciação (R$):', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::hidden('hiddendepreciacaovalor',null,['id' => 'hiddendepreciacaovalor']) !!}
                                                {!! Form::text('depreciacaovalor',null,['class'=>'form-control input-sm dinheiro', 'id' => 'depreciacaovalor', 'disabled' => 'disabled']) !!}
                                            </div>
                                            {!! Form::label('tipodepreciacao', 'Tipo Depreciação:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::select('tipodepreciacao',["0"=>"Dias","1"=>"Meses", "2" => "Anos"],null,['class'=>'form-control input-sm selectChosen', 'id' => 'tipodepreciacao']) !!}
                                            </div>
                                            {!! Form::label('depreciacaodias', 'Depreciar a cada X Dias:', ['class'=>'col-sm-2 control-label input-sm',]) !!}
                                            <div class="col-sm-2">
                                                {!! Form::text('depreciacaodias',null,['class'=>'form-control input-sm number', 'id' => 'depreciacaodias']) !!}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {!! Form::label('valoratual', 'Valor Atual:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::text('valoratual',null,['class'=>'form-control input-sm dinheiro', 'id' => 'valoratual', 'disabled' => 'disabled']) !!}
                                            </div>
                                            {!! Form::label('ativo', 'Ativo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2 checkbox">
                                                {{Form::checkbox('ativo')}}
                                            </div>
                                        </div>
                                    </div>
                                </div><!-- /.tab-pane -->
                            </div><!-- /.tab-pane -->
                        </div><!-- /.tab-content -->
                        <div class="box-footer">
                            <div class="col-md-4">
                                {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
                                <a type="button" href="{!!url('empresabens')!!}" class="btn btn-nw-geral">Voltar</a>
                            </div>
                        </div>
                    </div>
            </ul><!-- /.col -->
        </div>
    </div>
</div>
</div>
<!-- DATA TABES SCRIPT -->
<!-- page script -->
<script type="text/javascript" src="{{URL::to('js/empresaBens.js')}}"></script>
<script type="text/javascript">
setTimeout(function () {

    @if (isset($show))
    desativarInputs();
    var ids = [".btnBuscarEndereco", '#btnBuscarCEP',
        '.novoCadEndereco', '#btnAddFone'];
    desativarInputsEspecificos(ids);
    @endif
            @if ($errors -> any())
    carregarTelefonesErro();
    @endif
}, $(document).ready());
</script>
@endsection
