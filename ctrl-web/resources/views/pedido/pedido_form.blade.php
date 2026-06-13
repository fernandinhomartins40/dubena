@extends('layouts.mainmenu')
@section('content')
<style>
    .convenio-label {
        border: 1px solid #672290;
        padding: 2px 4px;
        border-radius: 4px;
        cursor: pointer;
    }
</style>
<div id="mainContent" style="margin-left: -2%" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            @if(isset($pedido))
            {{ Form::model($pedido, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true)) }}
            @else
            {{ Form::open(['id'=>'fmCadastro', 'class' => 'form-horizontal']) }}
            @endif
            <ul>
                <div class="nav-tabs-custom">
                    <div class="header panel-default">
                        <div class="panel-heading" style="height:32px;">
                            <h3 class="panel-title" id='page-title' style="margin-top: -2px;">
                                Pedido
                                <div class="fright" style="margin-top: -3px;">
                                    @if(isset($pedido))
                                        {{ !is_null(@$pedido->nfEmitida->nfnumero) ? 'NFCe nº: ' . $pedido->nfEmitida->nfnumero : 'Não gerou NFCe'}}
                                        @if(isset($show))
                                            @can('update', App\Pedido::class)
                                                <a href="{{url('')}}/pedido/{{$pedido->id}}/edit" type="button" id="btnEditaPedido" class="btn btn-nw-geral no-margin btn-xs hidden">Editar </a>
                                            @endcan
                                        @endif
                                    @else
                                        <button type="button" id="btnNovoPedido" class="btn btn-nw-registro no-margin btn-xs hidden">Novo Registro</button>
                                    @endif
                                </div>
                            </h3>
                        </div>
                    </div><!-- /.box-header -->
                    <div class="tab-content">
                        <div class="tab-pane active" id="">
                            <!-- form start -->
                            <div class="row">
                                <div id="tabCadastro" class="col-md-12">
                                    <div class="box-body">
                                        @include('pedido.partials.form')
                                        @include('pedido.partials.grids')
                                        {!! Form::hidden('pedido_id_nf',null,['id' => 'pedido_id_nf']) !!}
                                        {!! Form::hidden('cepEmpresa',null,['id' => 'cepEmpresa']) !!}
                                        {!! Form::hidden('config',$config,['id' => 'config']) !!}
                                        {!! Form::hidden('empresa_id', $empresa_id, ['id' => 'empresa_id']) !!}
                                        {!! Form::hidden('telefonechamada_id',null,['id' => 'telefonechamada_id']) !!}
                                        {!! Form::hidden('vendaativa_id',null,['id' => 'vendaativa_id']) !!}
                                        {!! Form::hidden('arrayStatusFechadoConcluido',@$arrayStatusFechadoConcluido,['id' => 'arrayStatusFechadoConcluido']) !!}
                                        {!! Form::hidden('arrayStatusFechadoCancelado',@$arrayStatusFechadoCancelado,['id' => 'arrayStatusFechadoCancelado']) !!}
                                        {!! Form::hidden('arrayStatusFinalizado',@$arrayStatusFinalizado,['id' => 'arrayStatusFinalizado']) !!}
                                        {!! Form::hidden('allConfigs', $allConfigs, ['id' => 'allConfigs']) !!}
                                    </div>
                                </div>
                            </div>
                            <div class="box-footer" style="margin-top: -25px;">
                                <div class="col-md-9" style="margin-top: -25px;">
                                    {!! Form::button('Gravar', ['class' => 'btn btn-nw-registro btn-sm', 'id' => 'btnGravar']) !!}
                                    <a type="button" href="{{url('pedido')}}" id="btnVoltarForm" class="btn btn-nw-geral btn-sm">Voltar</a>
                                </div>
                                <div class="fright col-md-3 negrito" style="margin-top: -25px;">
                                    <i> Pressione "F1" para lista de atalhos. </i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </ul><!-- /.col -->
            {!! Form::close() !!}
            <!--Rota para um novo cadastro via ajax-->
            <div id='rotaStore' class="hidden">{{route('cliente.store')}}</div>
            <!--Rota para atualizar via ajax-->
            <div id='rotaUpdate' class="hidden">{{url('cliente/update')}}/</div>
            <!--Rota para deletar via ajax-->
        </div>
    </div>
    @include('pedido.partials.modal_busca')
    @include('pedido.partials.modal_cartao')
    @include('pedido.partials.modal_selecionarempresa')
    @include('general.modal_senhamestra')
    @include('pedido.partials.modal_chamadasespera')
    @include('pedido.partials.modal_rejeitaligacoesmotivo')
    @include('pedido.partials.modal_validagasbolso')
    @include('pedido.partials.modal_teclasatalho')
    @include('pedido.partials.modal_tiponf')
    @include('general.modal_report_iframe')
    @include('pedido.partials.js')
    @include('pedido.partials.modal_pedidomotivoatraso')
    @include('pedido.partials.modal_buscaconveniado')
</div>
<script>
    @can("especial", App\Nfemitida::class)
        var autorizadoCriarNf = true;
    @endcan
    @cannot("especial", App\Nfemitida::class)
        var autorizadoCriarNf = false;
    @endcannot
</script>
<!-- page script -->
<script src="{{asset('plugins/qz-tray/js/dependencies/rsvp-3.1.0.min.js')}}"></script>
<script src="{{asset('plugins/qz-tray/js/dependencies/sha-256.min.js')}}"></script>
<script src="{{asset('plugins/qz-tray/js/qz-tray.js')}}"></script>
<script src="{{asset('js/thermalPrint.js')}}"></script>
<script src="{{URL::to('js/lib/collection.js')}}"></script>
<script src="{{URL::to('js/pedidoNf.js')}}"></script>
<script src="{{URL::to('js/pedido.js')}}"></script>
<script src="{{URL::to('js/pedidoGeneral.js')}}"></script>
{{--<script src="{{URL::to('js/clienteCustom.js')}}"></script>--}}

@endsection
