
@extends('layouts.mainmenu')

@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-md-10 col-md-offset-1">
                <div class="box-header">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Escolha o Fechamento de Caixa para Lançamento Retroativo</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12 ">
                                <div class="col-md-12">
                                    <div class="col-md-4">
                                        {!! Form::label('filtro', 'Data Inicial:', ['class'=>'col-sm-4 control-label input-sm','style'=>'text-align:right;']) !!}
                                        <div class="col-sm-7 input-group generalDatePicker">
                                            {!! Form::text('dataInicial',$dataInicial,['class'=>'form-control input-sm generalDatePicker', 'id' => 'dataInicial']) !!}
                                            <span class="input-group-addon">
                                                <span class="glyphicon glyphicon-calendar"></span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        {!! Form::label('filtro', 'Data Final:', ['class'=>'col-sm-4 control-label input-sm','style'=>'text-align:right;']) !!}
                                        <div class="col-sm-7 input-group generalDatePicker">
                                            {!! Form::text('dataFinal',$dataFinal,['class'=>'form-control input-sm generalDatePicker', 'id' => 'dataFinal']) !!}
                                            <span class="input-group-addon">
                                                <span class="glyphicon glyphicon-calendar"></span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <button class="btn btn-nw-buscas btn-sm" type='button' id='btnFitroDate'>Filtrar</button>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12">

                                <table id="tblCadastro" btnClick='false' class="table table-bordered table-hover table-condensed dataTableSemFilter">
                                    <thead>
                                        <tr>
                                            <th>C&oacute;digo</th>
                                            <th>Data e Hora Abertura</th>
                                            <th>Data e Hora Fechamento</th>
                                            <th>Saldo Inicial</th>
                                            <th>Saldo Final</th>
                                            <th>Operações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="contafechamento-list" name="contafechamento-list">
                                        @foreach ($dados as $contafechamento)
                                        <tr id="contafechamento{{$contafechamento->id}}">
                                            <td class='conteudoTd'>{{$contafechamento->id}}</td>
                                            <td class='conteudoTd'>{{requestDataOracle($contafechamento->datahoraabertura)}}</td>
                                            <td class='conteudoTd'>{{requestDataOracle($contafechamento->datahorafechamento)}}</td>
                                            <td class='conteudoTd'>{{requestNumeroDecimalOracle($contafechamento->saldoinicial)}}</td>
                                            <td class='conteudoTd'>{{requestNumeroDecimalOracle($contafechamento->saldofinal)}}</td>
                                            <td class="exportar">
                                                <a href="{{URL::to('financeiro.abrirTelaCaixaFechado/'.$contafechamento->id)}}" type="button" id="btnEditar" class="btn btn-xs btn-nw-buscas">Abrir</a>
                                            </td> 
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div><!-- /.box-body -->
                        
                    </div><!-- /.box -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-5">
                                <a href="{{ URL::route('caixa.index') }}" class="btn btn-nw-geral">Voltar</a>
                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->

            <!-- page script -->
            <script type="text/javascript">
                var exportarClick = false;
                var operacoesClick = false;

                $('.exportar').on('click', function () {
                    exportarClick = true;
                });

                $('.operacoes').on('click', function () {
                    operacoesClick = true;
                });
                $('#btnFitroDate').on('click', function () {
                    var urlFiltro = '{{url("contafechamento/filter/".$Conta->id."/:dataInicial/:dataFinal")}}';
                    var dataInicial = $("#dataInicial").val();
                    var dataFinal = $("#dataFinal").val();
                    dataInicial = insertDataOracle(dataInicial);
                    dataFinal = insertDataOracle(dataFinal);
                    urlFiltro = urlFiltro.replace(':dataInicial', dataInicial);
                    urlFiltro = urlFiltro.replace(':dataFinal', dataFinal);
                    window.location.href = urlFiltro;
                });
            </script>
        </div><!-- /.content-wrapper -->
    </div>
    @endsection
