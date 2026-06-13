
@extends('layouts.mainmenu')

@section('content')
<style>
    .modal.modal-wide .modal-dialog {
        width: 90%;
    }
    .modal-wide .modal-body {
        overflow-y: auto;
    }
    </style>
    <div id="mainContent" class="content">
        <div id="divCadastro" class="row">
            <div class="col-md-12">
                <ul>
                    <div class="nav-tabs-custom">
                        <div class="header panel-default">
                            <div class="panel-heading">
                                <h3 class="panel-title">
                                    {{$Conta->descricao}}
                                    @if(isset($contafechamento))
                                    {{' - '.requestDataOracle($contafechamento->datahoraabertura).' a '.
								requestDataOracle($contafechamento->datahorafechamento)}}
                                    @endif
                                </h3>
                            </div>
                        </div><!-- /.box-header -->
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <div class="box-body">
                                    @if(!isset($contafechamento))
                                    <div class="form-group crud_space">
                                        {{ Form::label('datafinal', 'Data Final:', ['class'=>'col-sm-1 col-sm-offset-4 control-label input-sm']) }}
                                        <div class="col-sm-2">
                                            <div class="input-group generalDateTimePicker">
                                                {{ Form::text('datafinal',null,['class'=>'form-control input-sm generalDateTimePicker']) }}
                                                <span class="input-group-addon">
                                                    <span class="glyphicon glyphicon-calendar"></span>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <button class="btn btn-sm btn-nw-buscas" id='btnFiltro' type="button" 
                                                    data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar"
                                                    onclick="carregarMovimentoConta();">
                                                <span class="fa fa-search fa-lg"></span>
                                            </button>
                                            <button class="btn btn-sm btn-github" id='btnLimpar' type="button" 
                                                    data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar">
                                                <span class="fa fa-recycle fa-lg"></span>
                                            </button>
                                        </div>
                                    </div>
                                    @endif
                                    <div class="col-md-12">
                                        <iframe class="margTop_20" sandbox="allow-same-origin allow-scripts allow-popups allow-forms allow-modals"
                                                id="iframeTable" style="border: 0; width:100%; height:380px;">
                                        </iframe>
                                    </div>
                                </div>
                                <div class="box-footer clearfix">
                                    <div class="col-sm-5">
                                        <i>
                                            O Saldo Inicial e Saldo Atual são referentes ao dia da abertura e 
                                            @if(isset($contafechamento))
                                            fechamento da conta.
                                            @else
                                            a data atual.
                                            @endif

                                        </i>
                                    </div>
                                    <div class="fright divCheques">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="box-footer">
                            <div class="col-md-12">
                                @if($operar)
                                    @if(!isset($contafechamento))
                                        <button type="button" class="btn btn-warning" onclick="confirmaFecharCaixa();">Fechar Caixa</button>
                                    @endif
                                    @can('pagar', App\Financeiro::class)
                                        <button type="button" class="btn btn-info" onclick="abrirFinanceiro('P');">Pagar</button>
                                    @endcan
                                    @cannot('pagar', App\Financeiro::class)
                                        <button type="button" disabled class="btn btn-info">Pagar</button>
                                    @endcannot

                                    @can('receber', App\Financeiro::class)
                                        <button type="button" class="btn btn-info" onclick="abrirFinanceiro('R');">Receber</button>
                                    @endcan
                                    @cannot('receber', App\Financeiro::class)
                                        <button type="button" disabled class="btn btn-info">Receber</button>
                                    @endcannot
                                    <button type="button" class="btn bg-purple" onclick="confirmaTransferirCaixa();">Transferir</button>
                                @else
                                    <button type="button" class="btn btn-warning" disabled>Fechar Caixa</button>
                                    <button type="button" class="btn btn-info" disabled>Pagar</button>
                                    <button type="button" class="btn btn-info" disabled>Receber</button>
                                    <button type="button" class="btn bg-purple" disabled>Transferir</button>
                                @endif
                                <a href="{{ URL::route('caixa.index') }}" class="btn btn-nw-geral">Voltar</a>
                            </div>
                        </div>
                    </div><!-- /.col -->
                </ul>
            </div>
        </div>
    </div>

    <meta name="csrf-token" content="{{ csrf_token() }}" />
    @include('financeiro.partials.modals')
    <script src="{{asset('js/caixa.js')}}"></script>
    @include('financeiro.partials.caixajs')
    @endsection
