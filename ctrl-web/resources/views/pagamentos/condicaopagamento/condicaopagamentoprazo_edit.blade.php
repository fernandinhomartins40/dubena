@extends('layouts.mainmenu')
@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
            @if(isset($condicaopagamento))
            {{ Form::model($condicaopagamento, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('condicaopagamento.update', $condicaopagamento->id))) }}
            @else
            {{ Form::open(['id'=>'fmCadastro', 'route' => 'condicaopagamento.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif
            <ul>
                <div class="nav-tabs-custom">
                    <div class="header panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">
                                Condição de Pagamento
                            </h3>
                        </div>
                        <div class="nav-tabs-custom">
                            <ul class="nav nav-tabs">
                                <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                            </ul>
                            <div class="form-group crud_space">
                                <div class="col-sm-10 col-sm-push-1">
                                    <div class="alert alert-informacao" id="info-alert" style="display: none">
                                        <button type="button" class="close" data-dismiss="alert">x</button>
                                        Nenhuma das parcelas deve ter percentual zero.
                                    </div>
                                </div>
                            </div>
                            <div class="tab-content">
                                <div class="tab-pane active" id="tab_1">
                                    <!-- form start -->
                                    <div class="row">
                                        <div id="tabCadastro" class="col-md-10">
                                            <div class="box-body">
                                                <div class="form-group crud_space">
                                                    {!! Form::label('tipo', 'Tipo:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                    <div class="col-sm-3">
                                                        {!! Form::select('tipos',$tipos, $tipo, ['class'=>'form-control input-sm selectChosen ', 'disabled' => 'disabled']) !!}
                                                        {!! Form::text('tipo',$tipo,['class'=>'form-control input-sm hidden']) !!}
                                                    </div>
                                                    {!! Form::label('nfc_tpag', 'Tipo Pagamento NF:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                    <div class="col-sm-3">
                                                        {!! Form::select('nfc_tpag', $nfc_tpag, null, ['class'=>'form-control input-sm selectChosen']) !!}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {!! Form::label('contamovimentotipo_id', 'Tipo Recebimento:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                    <div class="col-sm-3">
                                                        {!! Form::select('contamovimentotipo_id', $contamovimentotipos, null, ['class'=>'form-control input-sm selectChosen']) !!}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                    <div class="col-sm-5">
                                                        {!! Form::text('descricao',null,['class'=>'form-control input-sm']) !!}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {!! Form::label('enviaappnf', 'Envia App NF:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                    <div class="col-sm-2 checkbox">

                                                        {{ Form::checkbox('enviaappnf') }}
                                                    </div>
                                                    {!! Form::label('pedidosituacaoappnf_id', 'Situação App NF:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                    <div class="col-sm-3">
                                                        {!! Form::select('pedidosituacaoappnf_id', $pedidosituacaos, null, ['class'=>'form-control input-sm selectChosen']) !!}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {!! Form::label('appnfceauto', 'App Emite NFCe Auto:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                    <div class="col-sm-2 checkbox">
                                                       {{ Form::checkbox('appnfceauto') }}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {!! Form::label('ativo', 'Ativo:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                    <div class="col-sm-2 checkbox">

                                                        {{ Form::checkbox('ativo') }}
                                                    </div>
                                                </div>
                                                {!! Form::text('num_parcelas',$num_parcelas,['class'=>'form-control input-sm number hidden', 'id'=>'num_parcelas']) !!}
                                                {!! Form::text('inputParcelasRetorno',null,['class'=>'form-control input-sm hidden', 'id'=>'inputParcelasRetorno']) !!}
                                                {!! Form::text('inputDiasParcelasRetorno',null,['class'=>'form-control input-sm hidden', 'id'=>'inputDiasParcelasRetorno']) !!}
                                                {!! Form::text('inputPercentualParcelasRetorno',null,['class'=>'form-control input-sm hidden', 'id'=>'inputPercentualParcelasRetorno']) !!}
                                                @if(!$errors->any())
                                                <!--{!!$i = 1 !!}-->
                                                <script>
                                                    $(document).ready(function () {
                                                        $("#parcelas").show();
                                                        $("#inputParcelasRetorno").val('');
                                                        num_parcelas = parseInt($("#num_parcelas").val());
                                                    });
                                                    retornoPtoPrazo = true;
                                                </script>
                                                @foreach ($condicaopagamentoparcelas as $condicaopagamentoparcela)
                                                @if ($errors -> any())
                                                {{dd()}}
                                                @endif
                                                <div class="form-group crud_space">
                                                    <label class="col-sm-3 control-label input-sm required">Dias para Parcela {{$i}}:</label>
                                                    <div class="col-sm-2">
                                                        <input name="idparcela{{$i}}" id="idparcela{{$condicaopagamentoparcela->id}}" type="text" class="hidden" value="{{$condicaopagamentoparcela->id}}">
                                                        <input name="dias{{$i}}" id="id{{$i}}" onkeyup="changeDias(this.id)" type="text" class="input-sm form-control number" value="{{$condicaopagamentoparcela->dias}}">
                                                    </div>
                                                    <label class="col-sm-2 control-label input-sm required">Percentual para Parcela {{$i}}:</label>
                                                    <div class="col-sm-2">
                                                        <input name="percentual{{$i}}" id="idPercentual{{$i}}" onkeyup="changePercentual(this.id)" type="text" class="input-sm form-control percentagem" value="{{requestPercentualOracle($condicaopagamentoparcela->percentualvalor)}}">
                                                    </div>
                                                </div>
                                                <script>
                                                    $("#parcelas").append('conteudoParcelas');
                                                    $("#inputParcelasRetorno").val($("#inputParcelasRetorno").val() + 'conteudoParcelas');
                                                    $("#inputDiasParcelasRetorno").val($("#inputDiasParcelasRetorno").val() + '{{$condicaopagamentoparcela->dias}}||');
                                                    $("#inputPercentualParcelasRetorno").val($("#inputPercentualParcelasRetorno").val() + '{{requestPercentualOracle($condicaopagamentoparcela->percentualvalor)}}||');
                                                </script>
                                                <!--{!!$i++ !!}-->
                                                @endforeach
                                                @else
                                                <script>
                                                    retornoPtoPrazo = false;
                                                </script>
                                                @endif
                                                <div id='parcelas'>
                                                </div>
                                                <div class="form-group crud_space margTop_25">
                                                    <div class="col-sm-10 col-sm-offset-1">
                                                        <i>O campo "Dias para parcela X" deve ser em relação ao vencimento da parcela anterior</i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div><!-- /.tab-pane -->
                                </div><!-- /.tab-pane -->
                            </div>
                            <div class="box-footer">
                                <div class="col-md-4">
                                    {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
                                    <a type="button" href="{{route('condicaopagamento.index')}}" class="btn btn-nw-geral">Voltar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                {!! Form::close() !!}
            </ul><!-- /.col -->
        </div>
    </div>
</div>
<!-- page script -->
<script src="{{URL::to('js/condicaopagamento.js')}}"></script>
<script>
    @if (isset($show))
        desativarInputs();
    @endif

    @if ($errors -> any())
        carregarParcelas()
    @endif
</script>
@endsection
