@extends('layouts.mainmenu')
@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
            @if(isset($colaboradorcomissao))
            {{ Form::model($colaboradorcomissao, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('colaboradorcomissoes.update', $colaboradorcomissao->id))) }}
            @else
            {{ Form::open(['id'=>'fmCadastro', 'route' => 'colaboradorcomissoes.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Comissão</h3>
                    </div>
                    <div class="nav-tabs-custom">
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
                                                {{ Form::label('tonelagem', 'Tonelagem:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-1 checkbox">
                                                    @if(isset($colaboradorcomissao))
                                                        {{ Form::checkbox('tonelagem', 1, null, ['id'=>'tonelagem', 'onclick'=>'return false;']) }}
                                                    @else
                                                        {{ Form::checkbox('tonelagem', 1, null, ['id'=>'tonelagem']) }}
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('setor_id', 'Setor:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-4">
                                                    @if(isset($colaboradorcomissao))
                                                        {{ Form::select('setor_id', $setores, $setor, ['id'=>'setor_id', 'class' => 'form-control selectChosen ', 'disabled' => 'disabled', 'style'=>'padding:0px;max-height:24px;']) }}
                                                    @else
                                                        {{ Form::select('setor_id', $setores, $setor, ['id'=>'setor_id', 'class' => 'form-control selectChosen', 'style'=>'padding:0px;max-height:24px;']) }}
                                                    @endif
                                                </div>
                                                {{ Form::label('colaborador_id ', 'Colaborador:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-4">
                                                    {{ Form::hidden('hiddencolaborador_id', $colaborador, ['id'=>'hiddencolaborador_id', 'class' => 'form-control input-sm']) }}
                                                    @if(isset($colaboradorcomissao))
                                                        {{ Form::select('colaborador_id', $colaboradores, $colaborador, ['id'=>'colaborador_id', 'class' => 'form-control selectChosen', 'disabled' => 'disabled',  'style'=>'padding:0px;max-height:24px;']) }}
                                                    @else
                                                        {{ Form::select('colaborador_id', $colaboradores, $colaborador, ['id'=>'colaborador_id', 'class' => 'form-control selectChosen', 'style'=>'padding:0px;max-height:24px;']) }}
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('produto_id', 'Produto:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-4">
                                                    {{ Form::select('produto_id', $produtos, $produto, ['id'=>'produto_id', 'class' => 'form-control selectChosen', 'style'=>'padding:0px;max-height:24px;']) }}
                                                </div>
                                                {{ Form::label('condicaopagamento_id ', 'Condição de Pagamento:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-4">
                                                    @if (isset($colaboradorcomissao))
                                                        {{ Form::select('condicaopagamento_id', $condicaopagamentos, $condicaopagamento, ['id'=>'condicaopagamento_id', 'class' => 'form-control selectChosen', 'style'=>'padding:0px;max-height:24px;']) }}
                                                    @else
                                                        {{ Form::select('condicaopagamento_id[]', $condicaopagamentos, null,['id' => 'condicaopagamento_id','class' => 'selectChosen input-sm form-control', 'multiple','data-placeholder' => 'Selecione']) }}
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('tipocomissao', 'Tipo:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2 radio">&nbsp;&nbsp;&nbsp;
                                                    {{ Form::radio('tipocomissao', '1' , true, ['onclick' => 'mudarTipo(1)']) }} Percentual &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                                    {{ Form::radio('tipocomissao', '2' , false, ['onclick' => 'mudarTipo(2)']) }} Repasse
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('comissao', 'Percentual:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    @if (isset($colaboradorcomissao))
                                                        @if ($colaboradorcomissao->percentual == 0)
                                                            {{ Form::text('percentual', null, ['id'=>'percentual', 'class' => 'form-control input-sm percentagem', 'disabled' => 'disabled']) }}
                                                        @else
                                                            {{ Form::text('percentual', requestPercentualOracle($colaboradorcomissao->percentual) , ['id'=>'percentual', 'class' => 'form-control input-sm percentagem']) }}
                                                        @endif
                                                    @else
                                                        {{ Form::text('percentual', null, ['id'=>'percentual', 'class' => 'form-control input-sm percentagem']) }}
                                                    @endif
                                                </div>
                                                {{ Form::label('empresavalor', 'Valor Retido:', ['class'=>'col-sm-4 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    @if (isset($colaboradorcomissao))
                                                        @if ($colaboradorcomissao->empresavalor == 0)
                                                            {{ Form::text('empresavalor', null, ['id'=>'empresavalor', 'class' => 'form-control input-sm dinheiro', 'disabled' => 'disabled']) }}
                                                        @else
                                                            {{ Form::text('empresavalor', requestNumeroDecimalOracle($colaboradorcomissao->empresavalor), ['id'=>'empresavalor', 'class' => 'form-control input-sm dinheiro']) }}
                                                        @endif
                                                    @else
                                                        {{ Form::text('empresavalor', null, ['id'=>'empresavalor', 'class' => 'form-control input-sm dinheiro']) }}
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('comissaoapp', 'Percentual App:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    @if (isset($colaboradorcomissao))
                                                        @if ($colaboradorcomissao->percentualapp == 0)
                                                            {{ Form::text('percentualapp', null, ['id'=>'percentualapp', 'class' => 'form-control input-sm percentagem', 'disabled' => 'disabled']) }}
                                                        @else
                                                            {{ Form::text('percentualapp', requestPercentualOracle($colaboradorcomissao->percentualapp) , ['id'=>'percentualapp', 'class' => 'form-control input-sm percentagem']) }}
                                                        @endif
                                                    @else
                                                        {{ Form::text('percentualapp', null, ['id'=>'percentualapp', 'class' => 'form-control input-sm percentagem']) }}
                                                    @endif
                                                </div>
                                                {{ Form::label('empresavalorapp', 'Valor Retido App:', ['class'=>'col-sm-4 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    @if (isset($colaboradorcomissao))
                                                        @if ($colaboradorcomissao->empresavalorapp == 0)
                                                            {{ Form::text('empresavalorapp', null, ['id'=>'empresavalorapp', 'class' => 'form-control input-sm dinheiro', 'disabled' => 'disabled']) }}
                                                        @else
                                                            {{ Form::text('empresavalorapp', requestNumeroDecimalOracle($colaboradorcomissao->empresavalorapp), ['id'=>'empresavalorapp', 'class' => 'form-control input-sm dinheiro']) }}
                                                        @endif
                                                    @else
                                                        {{ Form::text('empresavalorapp', null, ['id'=>'empresavalorapp', 'class' => 'form-control input-sm dinheiro']) }}
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('datainicio', 'Data Inícial:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {{ Form::text('datainicio', null, ['id'=>'datainicio', 'class' => 'form-control input-sm generalDatePicker']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {{ Form::label('datafim', 'Data Final:', ['class'=>'col-sm-4 control-label input-sm']) }}
                                                <div class="col-sm-2"> 
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {{ Form::text('datafim', null, ['id'=>'datainicio', 'class' => 'form-control input-sm generalDatePicker']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div> 
                                                </div> 
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('ativo', 'Ativo:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-1 checkbox">
                                                    {{ Form::checkbox('ativo') }}
                                                </div>
                                                @if (! isset($colaboradorcomissao))
                                                    <div class="hidden">
                                                        {{ Form::label('replicar', 'Replicar:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                        <div class="col-sm-1 checkbox">
                                                            {{ Form::checkbox('replicar') }}
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="form-group crud_space margTop_15">
                                                <div class="col-md-10 col-md-offset-2">
                                                    <strong>Exceções</strong>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('segmento_id', 'Segmento:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('segmento_id', $segmentos, null, ['id'=>'segmento_id', 'class' => 'form-control input-sm selectChosen']) }}
                                                </div>
                                                {{ Form::label('tipoexcecao', 'Tipo:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2 radio">&nbsp;&nbsp;&nbsp;
                                                    {{ Form::radio('tipoexcecao', '1' , true, ['onclick' => 'mudarTipoExcecao(1)']) }} Percentual &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                                    {{ Form::radio('tipoexcecao', '2' , false, ['onclick' => 'mudarTipoExcecao(2)']) }} Repasse
                                                </div>
                                                <div class="col-sm-2">
                                                    {{ Form::label('valorexcecao', '%/Valor:', ['class'=>'col-sm-5 control-label input-sm']) }}
                                                    <div class="col-sm-7">
                                                        {{ Form::text('percentualexcecao', null, ['id'=>'percentualexcecao', 'class' => 'form-control input-sm percentagem']) }}
                                                        {{ Form::text('valorexcecao', null, ['id'=>'valorexcecao', 'class' => 'form-control input-sm dinheiro', 'style' => 'display: none']) }}
                                                    </div>
                                                </div>
                                                <div class="col-sm-3">
                                                    {{ Form::label('valorexcecaoapp', '%/Valor App:', ['class'=>'col-sm-5 control-label input-sm']) }}
                                                    <div class="col-sm-5">
                                                        {{ Form::text('percentualexcecaoapp', null, ['id'=>'percentualexcecaoapp', 'class' => 'form-control input-sm percentagem']) }}
                                                        {{ Form::text('valorexcecaoapp', null, ['id'=>'valorexcecaoapp', 'class' => 'form-control input-sm dinheiro', 'style' => 'display: none']) }}
                                                    </div>
                                                    <div class="col-sm-1">
                                                    <button type="button" class="btn btn-xs btn-nw-buscas" id="btnAddExcecao">Adicionar</button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{Form::hidden('excecoes', null, ['id' => 'excecoes'])}}
                                                <div class="col-sm-10 col-sm-offset-2">
                                                    <table class="table-hover table-condensed table table-stripped" id="tblExcecoesComissao">
                                                        <thead>
                                                            <tr>
                                                                <th>Cód. Segmento</th>
                                                                <th>Segmento</th>
                                                                <th>Tipo</th>
                                                                <th>Valor</th>
                                                                <th>Valor App</th>
                                                                <th>Operações</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @if(isset($excecoes))
                                                                @foreach($excecoes as $excecao)
                                                                <tr>
                                                                    <td>{{$excecao->segmento_id}}</td>
                                                                    <td>{{$excecao->segmento->descricao}}</td>
                                                                    <td>{{$excecao->tipoexcecao == 1 ? 'Percentual' : 'Repasse'}}</td>
                                                                    <td>{{$excecao->tipoexcecao == 1 ? requestPercentualOracle($excecao->valorexcecao) : requestNumeroDecimalOracle($excecao->valorexcecao)}}</td>
                                                                    <td>{{$excecao->tipoexcecao == 1 ? requestPercentualOracle($excecao->valorexcecaoapp) : requestNumeroDecimalOracle($excecao->valorexcecaoapp)}}</td>
                                                                    <td><button type="button" class="btn-xs btn-nw-registro btn" id="btnRemoverExcecao">Remover</button></td>
                                                                </tr>
                                                                @endforeach
                                                            @endif
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div><!-- /.tab-pane -->
                            </div><!-- /.tab-pane -->
                        </div><!-- /.tab-content -->
                        <div class="box-footer">
                            <div class="col-md-4">
                                {{ Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) }}
                                @if (! isset($colaboradorcomissao))
                                    <button id="btnReplicar" type="button" class="btn btn-nw-buscas">Replicar</button>
                                @endif
                                <a type="button" href='{{route("colaboradorcomissoes.index")}}' class="btn btn-nw-geral">Voltar</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <input id="redirect" name="redirect" class="hidden" type="text"/>
            {{ Form::close() }}
        </ul><!-- /.col -->
    </div>
</div>
<!-- page script -->
<script type="text/javascript" src="{{URL::to('js/colaboradorcomissao.js')}}"></script>
<script>
    urlBuscaColaboradoresPorSetor = '{{url("colaborador/buscaColaboradorPorSetor/:setor_id")}}';
    setTimeout(function () {
        @if (isset($show))
            desativarInputs();
            var ids = [".btnBuscarEndereco", '#btnBuscarCEP',
            '.novoCadEndereco', '#btnAddFone', '#btnAddExcecao', '.btn-nw-registro'];
            desativarInputsEspecificos(ids);
        @endif
        @if ($errors->any())
            errors = true;
            $("#replicar").prop('checked', false);
        @endif
        @if(isset($colaboradorcomissao))
            mudarTipo({{$colaboradorcomissao->tipocomissao}});
            @if($colaboradorcomissao->tonelagem==1)
                setComissaoTonelagem();
            @endif
        @else
            mudarTipo(1);
            if($('#tonelagem').prop('checked')){
                setComissaoTonelagem();
            }
        @endif
        var urlredirect = window.location.href.split('?redirect=');
        $("#redirect").val(urlredirect[1]);
    }, $(document).ready());
</script>
@endsection
