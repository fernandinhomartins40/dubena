@extends('layouts.mainmenu')
@section('content')
<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Boleto</h3>
                    </div>
                    <!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Boleto</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-sm-12">
                                        <div class="box-body">
                                            <div class="form-horizontal">
                                                <div class="form-group crud_space margTop_20">
                                                    <div class="col-sm-12">
                                                        {!! Form::label('cliente_id', 'Cliente:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                        <div class="col-sm-6">
                                                            {!! Form::hidden('cliente_id_reload', $cliente_id, ['id'=>'cliente_id_reload', 'disabled']) !!}
                                                            {!! Form::hidden('cliente_nome_reload', $cliente_nome, ['id'=>'cliente_nome_reload', 'disabled']) !!}
                                                            <select id="cliente_id" name="cliente_id" placeholder="Buscar cliente" class="form-control" value="" 
                                                            data-selectize-value = '[]'></select>
                                                        </div>
                                                        {!! Form::label('gerouboleto', 'Gerou Boleto:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                        <div class="col-sm-1 checkbox"> 
                                                            {{ Form::checkbox('gerouboleto') }}
                                                        </div>
                                                        <div class="col-sm-2">
                                                            {!! Form::label('gerouremessa', 'Gerou Remessa:', ['class'=>'col-sm-8 control-label input-sm']) !!}
                                                            <div class="col-sm-1 checkbox"> 
                                                                {{ Form::checkbox('gerouremessa') }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    <div class="col-sm-12">
                                                        {!! Form::label('tipofiltro', 'Tipo de Data:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                        <div class="col-sm-1" style="margin-top: 3.5px">
                                                            {{ Form::radio('tipofiltro', '0', Input::get('tipofiltro', "0") === "0") }} Nenhum
                                                        </div>
                                                        <div class="col-sm-1" style="margin-top: 3.5px">
                                                            {{ Form::radio('tipofiltro', '1', Input::get('tipofiltro', "1") === "1", ['zero' => 'true']) }} Emissão 
                                                        </div>
                                                        <div class="col-sm-1" style="margin-top: 3.5px">
                                                            {{ Form::radio('tipofiltro', '2', Input::get('tipofiltro', "2") === "2") }} Vencto 
                                                        </div>
                                                        {{ Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                        <div class="col-sm-2">
                                                            <div class="input-group generalDatePicker">
                                                                {{ Form::text('datainicio',null,['id' => 'datainicio','class'=>'form-control generalDatePicker input-sm']) }}
                                                                <span class="input-group-addon">
                                                                    <span class="glyphicon glyphicon-calendar"></span>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        {{ Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-1 control-label input-sm', 'id' => 'labelData']) }}
                                                        <div class="col-sm-2">
                                                            <div class="input-group generalDatePicker">
                                                                {{ Form::text('datafim',null,['id' => 'datafim','class'=>'form-control generalDatePicker input-sm']) }}
                                                                <span class="input-group-addon">
                                                                    <span class="glyphicon glyphicon-calendar"></span>
                                                                </span>    
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space margTop_20">
                                                    <div class="col-sm-3 col-sm-offset-5">
                                                        <button class="btn btn-nw-buscas btn-sm" id="btnFiltrar" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Filtrar"><span class="fa fa-search fa-lg"></span></button>
                                                        <button class="btn btn-github btn-sm" id="btnLimpar" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                                                        @can('create', App\Boleto::class)
                                                            <button class="btn btn-nw-geral btn-sm" id="btnGerarBoleto" disabled data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar/Atualizar Boleto(s)"><span class="fa fa-barcode fa-lg"></span></button>
                                                        @endcan
                                                        @cannot('create', App\Boleto::class)
                                                            <button class="btn btn-nw-geral btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar/Atualizar Boleto(s)" disabled><span class="fa fa-barcode fa-lg"></span></button>
                                                        @endcannot
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group crud_space" style="margin-left: 2.5%">
                                            <div class="col-sm-12">
                                                <table class="table no-select table-bordered table-condensed padding-table-5" style="font-size: 13.5px;" id="tblParcelas">
                                                    <thead>
                                                        <tr>
                                                            <th>Cód.</th>
                                                            <th>Nº</th>
                                                            <th>Emissão</th>
                                                            <th>Vencto</th>
                                                            <th>Cliente</th>
                                                            <th>Valor</th>
                                                            <th>Juros</th>
                                                            <th>Multa</th>
                                                            <th>Valor Líquido</th>
                                                            @if(isset($_GET['gerouboleto']) && $_GET['gerouboleto'] == 1)
                                                                <!-- {{$hidden = ''}} -->
                                                            @else
                                                                <!-- {{$hidden = 'hidden'}} -->
                                                            @endif
                                                            <th class="{{$hidden}}">Gerou Boleto</th>
                                                            <th class="hidden">cliente_id</th>
                                                            <th class="hidden">conta_id</th>
                                                            <th class="{{$hidden}}">Gerou Remessa</th>
                                                            <th class="{{$hidden}}">Operações</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($parcelas as $parcela)
                                                            <tr>
                                                                <td>{{$parcela->parcela_id}}</td>
                                                                <td>{{$parcela->numero}}</td>
                                                                <td>{{requestDataOracle($parcela->dataemissao, false)}}</td>
                                                                <td>{{requestDataOracle($parcela->datavencimento, false)}}</td>
                                                                <td>{{substr($parcela->cliente, 0, 55)}}</td>
                                                                <td>{{requestNumeroDecimalOracle($parcela->valor)}}</td>
                                                                <td>{{requestNumeroDecimalOracle($parcela->juros)}}</td>
                                                                <td>{{requestNumeroDecimalOracle($parcela->multa)}}</td>
                                                                <td>{{requestNumeroDecimalOracle($parcela->valorefetivado)}}</td>
                                                                <td class="{{$hidden}}">{{$parcela->boletogerado == 0 ? 'Não' : 'Sim'}}</td>
                                                                <td class="hidden">{{$parcela->cliente_id}}</td>
                                                                <td class="hidden">{{@$parcela->boleto->conta_id}}</td>
                                                                <td class="{{$hidden}}">{{!is_null($parcela->boleto) && $parcela->boleto->gerouremessa == 1 ? 'Sim' : 'Não'}}</td>
                                                                <td class="{{$hidden}}">
                                                                    @if(!is_null($parcela->boleto))
                                                                        @if($parcela->boleto->gerouremessa)
                                                                            @can('update', App\Boleto::class)
                                                                                <button onclick="openModalEdit({{$parcela->boleto->id}})"
                                                                                        type="button" id='btnLimpar-tab_2' class="btn btn-sm btn-nw-geral btn-xs" 
                                                                                        data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar Boleto">
                                                                                    <span class="fa fa-pencil fa-lg"></span>
                                                                                </button>
                                                                            @endcan
                                                                            @cannot('update', App\Boleto::class)
                                                                                <button type="button" id='btnLimpar-tab_2' class="btn btn-sm btn-nw-geral btn-xs" disabled
                                                                                        data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar Boleto">
                                                                                    <span class="fa fa-pencil fa-lg"></span>
                                                                                </button>
                                                                            @endcannot
                                                                        @else
                                                                            @can('delete', App\Boleto::class)
                                                                                <button onclick="removeRegister({
                                                                                                    'id': '{{$parcela->boleto->id}}',
                                                                                                    'descricao': 'Boleto referente a parcela: {{$parcela->parcela_id}}',
                                                                                                })"
                                                                                        type="button" id='btnLimpar-tab_2' class="btn btn-sm btn-nw-registro btn-xs" 
                                                                                        data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Cancelar Boleto">
                                                                                    <span class="fa fa-remove fa-lg"></span>
                                                                                </button>
                                                                            @endcan
                                                                            @cannot('delete', App\Boleto::class)
                                                                                <button disabled type="button" id='btnLimpar-tab_2' class="btn btn-sm btn-nw-registro btn-xs" 
                                                                                        data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Cancelar Boleto">
                                                                                    <span class="fa fa-remove fa-lg"></span>
                                                                                </button>
                                                                            @endcannot
                                                                        @endif
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                                {{ $parcelas->links() }}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space col-sm-12">
                                            <div class="col-sm-4 margTop_15">
                                                <div id="totalParcelas">
                                                    {{$totais->count}} parcela(s) encontradas.
                                                </div>    
                                            </div>
                                            <div class="col-sm-4 fright margTop_15 text-right">
                                                <div id="totalSelecionados">
                                                    0 de 90 parcelas selecionados.
                                                </div>    
                                            </div>
                                        </div>
                                        <div class="form-group crud_space col-sm-12 form-horizontal margTop_15">
                                            {{Form::label('totalparcelasfiltro', 'Valor Total:', ['class' => 'col-sm-1 input-sm control-label'])}}
                                            <div class="col-sm-2">
                                                {{Form::text('totalparcelasfiltro', requestNumeroDecimalOracle($totais->valorefetivado), ['id' => 'totalparcelasfiltro', 'class' => 'col-sm-2 input-sm dinheiro form-control', 'disabled' => 'disabled'])}}
                                            </div>
                                            <div class="col-sm-4 fright margTop_15 text-right">
                                                 <i>
                                                    Para selecionar mais de uma parcela, pressione a tecla "Ctrl". <br /> 
                                                    Para selecionar vários de uma vez, pressione a tecla "Shift"
                                                </i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- /.panel-default -->
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalGerarBoleto" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog sizeModalDialog" role="document" style="width:96%">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title">Gerar/Atualizar Boleto</h4>
            </div>
            <div class="modal-body col-md-12">
                <form action="" class="form-horizontal" id="fmGeraBoleto" method='POST' target="_blank">
                    <div class="form-group crud_space">
                        <div class="col-md-12">
                            <div class="divConta">
                                {{Form::label('conta_id', 'Conta: ', ['class' => 'input-sm control-label col-sm-2'])}}
                                <div class="col-sm-3">
                                    {{Form::select('conta_id', $contas, null, ['class' => 'input-sm selectChosen', 'id' => 'conta_id'])}}
                                </div>
                            </div>
                            {{Form::label('inseredescparcela', 'Informar a Descrição da Parcela no Boleto: ', ['class' => 'input-sm control-label col-sm-3'])}}
                            <div class="col-sm-1 checkbox">
                                {{Form::checkbox('inseredescparcela')}}
                            </div>
                            <input type="hidden" class="hidden" value="" id="parcelas"  name="parcelas">
                            <input type="hidden" class="hidden" value="" id="inseredescparcelaInput" name="inseredescparcela">
                            <div class="col-sm-1">
                                <button class="btn btn-sm btn-nw-registro">Gerar/Atualizar Boleto</button>                                
                            </div>
                        </div>
                        {{ csrf_field() }}
                    </div>
                </form>
                <div class="col-md-12">
                    <table id="tblParcelasGerar" class="table table-bordered table-condensed no-select" style="width:100%">
                        <thead>
                            <tr>
                                <th>Cód.</th>
                                <th>Nº</th>
                                <th>Emissão</th>
                                <th>Vencto</th>
                                <th>Cliente</th>
                                <th>Valor</th>
                                <th>Juros</th>
                                <th>Multa</th>
                                <th>Valor Líquido</th>
                                <th>Cód. Cliente</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                  </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-nw-geral" data-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_editaparcela" tabindex="-1" role="dialog" data-keyboard="false" data-backdrop="static" aria-labelledby="myModalLabel">
    <div class="modal-dialog sizeModalDialog" role="document" style="width:50%;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title">Editar Parcela</h4>
            </div>
                <div class="modal-body col-md-12">
                    <form action="" class="form-horizontal">
                    <div class="col-md-12">
                        <div class="form-group crud_space">
                            <div class="col-sm-12">
                                <i><div id="divDescricao"></div>
                                    Atenção! As alterações na parcela só serão efetivadas no momento em que o boleto for gerado.
                                </i>
                            </div>
                        </div>
                        <div class="form-group crud_space margTop_20">
                            {{ Form::label('datavencimento', 'Vencimento:', ['class'=>'col-sm-2 control-label input-sm', 'id' => 'labelData']) }}
                            <div class="col-sm-4">
                                <div class="input-group generalDatePicker">
                                    {{ Form::text('datavencimento',null,['id' => 'datavencimento','class'=>'form-control generalDatePicker input-sm']) }}
                                    <span class="input-group-addon">
                                        <span class="glyphicon glyphicon-calendar"></span>
                                    </span>    
                                </div>
                            </div>
                            {{ Form::label('juros', 'Juros:', ['class'=>'col-sm-2 control-label input-sm']) }}
                            <div class="col-sm-4">
                                {{ Form::text('juros',null,['id' => 'juros','class'=>'form-control input-sm dinheiro']) }}
                            </div>
                        </div>
                        <div class="form-group crud_space">
                            {{ Form::label('multa', 'Multa:', ['class'=>'col-sm-2 control-label input-sm']) }}
                            <div class="col-sm-4">
                                {{ Form::text('multa',null,['id' => 'multa','class'=>'form-control input-sm dinheiro']) }}
                            </div>
                            {{ Form::label('total', 'Total:', ['class'=>'col-sm-2 control-label input-sm']) }}
                            <div class="col-sm-4">
                                {{ Form::text('total',null,['id' => 'total','class'=>'form-control input-sm dinheiro', 'disabled']) }}
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-nw-geral btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-nw-registro btn-sm" id="salvarParcela">Salvar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_editBoleto" tabindex="-1" role="dialog" data-keyboard="false" data-backdrop="static" aria-labelledby="myModalLabel">
    <div class="modal-dialog sizeModalDialog" role="document" style="width:50%;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title">Editar Boleto</h4>
            </div>
                <form action="" class="form-horizontal" id="fmEditBoleto">
                    <div class="modal-body col-md-12">
                        <div class="col-md-10">
                            <div class="form-group crud_space margTop_20">
                                {{ Form::label('ocorrencia_id', 'Ocorrência:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                <div class="col-sm-8">
                                    {{ Form::select('ocorrencia_id',[], null,['id' => 'ocorrencia_id','class'=>'selectChosen']) }}
                                </div>
                            </div>
                            <div class="divFielsEditBoleto">
                                <div class="form-group crud_space consessaoAAbatimento">
                                    {{ Form::label('valor_abatimento', 'Abatimento:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                    <div class="col-sm-4">
                                        {{ Form::text('valor_abatimento',null,['id' => 'valor_abatimento','class'=>'form-control input-sm dinheiro']) }}
                                    </div>
                                </div>
                                <div class="form-group crud_space alteracaoPrazoProtesto">
                                    {{ Form::label('prazo_protesto', 'Prazo Protesto:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                    <div class="col-sm-4">
                                        {{ Form::text('prazo_protesto',null,['id' => 'prazo_protesto','class'=>'form-control input-sm number']) }}
                                    </div>
                                </div>
                                <div class="form-group crud_space alteracaoPrazoDevolucao">
                                    {{ Form::label('prazo_devolucao', 'Prazo Devolução:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                    <div class="col-sm-4">
                                        {{ Form::text('prazo_devolucao',null,['id' => 'prazo_devolucao','class'=>'form-control input-sm number']) }}
                                    </div>
                                </div><!-- 
                                    {{ Form::label('datavencimento', 'Vencimento:', ['class'=>'col-sm-2 control-label input-sm', 'id' => 'labelData']) }}
                                    <div class="col-sm-4">
                                        <div class="input-group generalDatePicker">
                                            {{ Form::text('datavencimento',null,['id' => 'datavencimento','class'=>'form-control generalDatePicker input-sm']) }}
                                            <span class="input-group-addon">
                                                <span class="glyphicon glyphicon-calendar"></span>
                                            </span>    
                                        </div>
                                    </div> -->
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-nw-geral btn-sm" data-dismiss="modal">Cancelar</button>
                        <button class="btn btn-nw-registro btn-sm" id="btnUpdateBoleto">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDel" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabel">Cancelar Boleto</h4>
            </div>
            {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmCadastroDel']) }}
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-group crud_space col-sm-12">
                    </div>
                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('id_del', 'Código:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                        <div class="col-sm-9">
                            {!! Form::text('id',null,['class'=>'form-control input-sm', 'id'=>'id_del', 'readonly','tabindex'=>'-1']) !!}
                        </div>
                    </div>
                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                        <div class="col-sm-9">
                            {!! Form::text('descricao_del',null,['class'=>'form-control input-sm', 'id'=>'descricao_del', 'readonly','tabindex'=>'-1']) !!}
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCloseCadastroDel" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
                {!! Form::submit('Cancelar', ['class' => 'btn btn-nw-registro']) !!}
                <div id="saveErrorDel" class="alert alert-danger alert-dismissable" style="display:none;">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <span class="glyphicon glyphicon-remove"></span>
                    <div id="save_result"></div>
                </div>
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>
<!--Rota para um novo cadastro via ajax-->
<div id='rotaStore' class="hidden">{{route('boleto.store')}}</div>
<!--Rota para atualizar via ajax-->
<div id='rotaUpdate' class="hidden">{{url('boleto')}}/</div>
<!--Rota para deletar via ajax-->
<div id='rotaDel' class="hidden">{{url('boleto')}}/</div>
<!--Rota para redirecionar via ajax-->
<div id='rotaIndex' class="hidden">{{request()->fullUrl()}}</div>

<script type="text/javascript" src="{{asset('js/boletos.js')}}"></script>
@endsection