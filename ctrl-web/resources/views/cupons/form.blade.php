@extends('layouts.mainmenu')

@section('content')

    <div id="mainContent" class="content">
        <div id="divCadastro" class="row">
            <div class="col-sm-12">

                @if(isset($cupom))
                    {{ Form::model($cupom, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('cupons.update', $cupom->id))) }}
                @else
                    {{ Form::open(['id'=>'fmCadastro', 'route' => 'cupons.store', 'class' => 'form-horizontal', 'files' => true]) }}
                @endif

                <ul>
                    <div class="nav-tabs-custom">
                        <div class="header panel-default">
                            <div class="panel-heading">
                                <h3 class="panel-title">
                                    Cupom aplicativo Gás em Casa
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
                                    <div id="tabCadastro" class="col-sm-11 col-sm-offset-1">
                                        <div class="form-group crud_space">
                                            {!! Form::label('tipo', 'Tipo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {{ Form::radio('tipo', '0', @$cupom->tipo === 0 || !isset($cupom), ['onchange' => 'toggleValueClass(false)']) }}
                                                Valor fixo &nbsp;
                                                {{ Form::radio('tipo', '1', @$cupom->tipo === 1, ['onchange' => 'toggleValueClass(false)']) }}
                                                Percentual<br/>
                                            </div>
                                            {!! Form::label('valor_string', 'Valor:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::text('valor_string', @$cupom->tipo == 0 ? @requestNumeroDecimalOracle($cupom->valor) : @requestPercentualOracleSemDigitos($cupom->valor), ['class'=>'form-control']) !!}
                                                {!! Form::text('valor', @$cupom->valor, ['class'=>'form-control hidden', 'id' => 'valor',]) !!}
                                            </div>
                                            {!! Form::label('limiteuso', 'Limite de uso:', ['class'=>'col-sm-1 control-label input-sm' ]) !!}
                                            <div class="col-sm-2">
                                                {!! Form::text('limiteuso',@$cupom->limiteuso,['class'=>'form-control number', 'maxlength' => '7']) !!}
                                            </div>
                                        </div>

                                        <div class="form-group crud_space">
                                            {!! Form::label('codigo', 'Código:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                <div class="input-group">
                                                    {!! Form::text('codigo',@$cupom->codigo,['class'=>'form-control', 'maxlength' => '12', 'style'=>'text-transform:uppercase']) !!}
                                                    <span class="input-group-addon" data-toggle="tooltip"
                                                          data-trigger="hover" data-placement="bottom"
                                                          title="Gerar código aleatório"
                                                          onclick="{{isset($show) || isset($edit) ? '' : 'createRandomCode()'}}">
                                                        <a href="#" disabled="{{isset($show) || isset($edit)}}">
                                                        <i class="glyphicon glyphicon-refresh"></i>
                                                            </a>
                                                    </span>
                                                </div>
                                            </div>
                                            {!! Form::label('datainicio', 'Data inicial:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                <div class="input-group generalDateTimePicker">
                                                    {!! Form::text('datainicio',requestDataOracle(@$cupom->datainicio),['class'=>'form-control generalDateTimePicker']) !!}
                                                    <span class="input-group-addon">
                                                        <span class="glyphicon glyphicon-calendar"></span>
                                                    </span>
                                                </div>
                                            </div>
                                            {!! Form::label('datafim', 'Data final:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                <div class="input-group generalDateTimePicker">
                                                    {!! Form::text('datafim',requestDataOracle(@$cupom->datafim),['class'=>'form-control generalDateTimePicker']) !!}
                                                    <span class="input-group-addon">
                                                    <span class="glyphicon glyphicon-calendar"></span>
                                                </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {!! Form::label('ativo', 'Ativo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-1 checkbox">
                                                {{ Form::checkbox('ativo',1) }}
                                            </div>
                                            @if (@$cupom->notificado != true && !isset($show))
                                                {!! Form::label('notificar', 'Notificar após salvar:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2 checkbox">
                                                    {!! Form::checkbox('notificar', 1, true) !!}
                                                </div>
                                            @else
                                                {!! Form::label('notificado', 'Notificado', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2 checkbox">
                                                    {!! Form::checkbox('notificado', @$cupom->notificado, @$cupom->notificado, ['disabled' => true]) !!}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="box-footer">
                                <div class="col-sm-4">
                                    <button class="btn btn-nw-registro">Gravar</button>
                                    <a type="button" href="{{url('cupons')}}" class="btn btn-nw-geral">Voltar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </ul>
            </div>
        </div>
    </div>
    {!! Form::close() !!}
    <script src="{{URL::to('js/cupom.js')}}"></script>

    <script>
        @if ($errors -> any())
            errorsAny = true;
        @else
            errorsAny = false;
        @endif
                @if (isset($show) && $show)
            onlyRead = true;
        @elseif (isset($edit))
            onlyRead = true;
        @else
            onlyRead = false;
        @endif
        var root = '{{URL("/")}}';
        var gerarCodigoUrl = '{{ url("cupons/gerarcodigo") }}';

        setTimeout(function () {
            @if (isset($show) && $show)
            desativarInputs();
            @elseif (isset($edit))
            var ids = ['#codigo'];
            desativarInputsEspecificos(ids);
            @endif
        }, $(document).ready());
    </script>


@endsection
