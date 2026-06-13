
@extends('layouts.mainmenu')

@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Consultas de Estoque</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12 ">
                                {{ Form::open(['id'=>'fmFiltros', 'class' => 'form-horizontal']) }}
                                <div class="col-md-12">
                                    {!! Form::label('setor', 'Setor:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;']) !!}
                                    <div class="col-md-3 ">
                                        {!! Form::select('setor_id',$setores, $setor_id,['class'=>'selectChosen', 'id' => 'setor_id']) !!}
                                    </div>
                                    {!! Form::label('estoqueZerado', 'Não Mostrar Estoque Zerado:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;']) !!}
                                </div>
                                <div class="col-md-12" style="margin-top: 8px;">
                                    {!! Form::label('produto', 'Produto:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;']) !!}
                                    <div class="col-md-3 ">
                                        {!! Form::select('produto', $produtos, $produto_id,['class'=>'selectChosen', 'id' => 'produto']) !!}
                                    </div>
                                    <div class="col-md-1" >
                                    </div>
                                    <div class="col-md-1 ">
                                        @if($checked)
                                        {{ Form::checkbox('estoqueZerado', 0, ['id'=>'estoqueZerado', 'checked' => 'checked']) }}
                                        @else
                                        <input type="checkbox" id="estoqueZerado">
                                        @endif
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-sm btn-nw-buscas" id='btnBusca' type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar">
                                            <span class="fa fa-search fa-lg"></span>
                                        </button>
                                        <a class="btn btn-sm btn-github" type="button" href="{{route('consultaestoquesetor.index')}}" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar">
                                            <span class="fa fa-recycle fa-lg"></span>
                                        </a>
                                    </div>
                                </div>
                                {!! Form::close() !!}


                                <div class="col-md-12">

                                    <table id="tblCadastro" class="table table-bordered table-hover table-condensed dataTableSemPaginate">
                                        <thead>
                                            <tr>
                                                <th>Cód Setor</th>
                                                <th>Setor</th>
                                                <th>Cód Produto</th>
                                                <th>Produto</th>
                                                <th>Quantidade</th>
                                                <th>Custo Médio</th>
                                                <th>Preço Venda</th>
                                            </tr>
                                        </thead>
                                        <tbody id="" name="estoquerequisicao-list">
                                            <!-- {{$totalItens = 0}} -->
                                            @foreach ($estoquesetoritens as $estoquesetoritem)
                                            <tr id="estoquerequisicao{{$estoquesetoritem->setor_id}}">
                                                <td class='conteudoTd'>{{$estoquesetoritem->setor_id}}</td>
                                                <td class='conteudoTd'>{{$estoquesetoritem->descricao}}</td>
                                                <td class='conteudoTd'>{{$estoquesetoritem->produto_id}}</td>
                                                <td class='conteudoTd'>{{$estoquesetoritem->produto_descricao}}</td>
                                                <td class='conteudoTd'>{{str_replace("R$ ", "", requestNumeroDecimalOracle($estoquesetoritem->quantidade))}}</td>
                                                <td class='conteudoTd'>{{requestNumeroDecimalOracle($estoquesetoritem->customedio)}}</td>
                                                <td class='conteudoTd'>{{requestNumeroDecimalOracle($estoquesetoritem->precovenda)}}</td>
                                            </tr>
                                            <!-- {{$totalItens += $estoquesetoritem->quantidade}} -->
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th></th>
                                                <th></th>
                                                <th></th>
                                                <th></th>
                                                <th>{{str_replace("R$ ", "", requestNumeroDecimalOracle($totalItens))}}</th>
                                                <th></th>
                                                <th></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div><!-- /.box-body -->
                        </div><!-- /.box -->
                    </div><!-- /.col -->
                    @can('create', App\Estoquesetor::class)
                    <button type="button" id='btnFecharEstoque' data-toggle="modal" data-target="#myModal" class="btn btn-sm btn-nw-geral">Fechar Estoque</button>
                    @endcan
                    @can('update', App\Estoquesetor::class)
                    <button type="button" id='btnReabrirEstoque' class="btn btn-sm btn-nw-registro" data-toggle="modal" data-target="#myModalAbertura">Reabrir Estoque</button>
                    @endcan
                </div><!-- /.row -->
                @include('general.modal_senhamestra')
                <div class="modal fade" id="myModalAbertura" tabindex="-1" role="dialog" aria-labelledby="myModalAberturaLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                                <h4 class="modal-title" id="myModalAberturaLabelCadastro"></h4>
                            </div>
                            {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmAberturaAjax']) }}
                            <div class="modal-body">
                                <div class="box-body">
                                    <div class="col-md-12">
                                        {!! Form::label('data', 'Data:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;']) !!}
                                        <div class="col-sm-9 input-group generalDateTimePicker">
                                            {!! Form::datetime('dataAbertura', null, ['class'=>'form-control input-sm generalDateTimePicker', 'required']) !!}
                                            <span class="input-group-addon">
                                                <span class="glyphicon glyphicon-calendar"></span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-12" style="padding-top: 8px;">
                                        {!! Form::label('motivo', 'Motivo:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;']) !!}
                                        <div class="col-sm-9 input-group ">
                                            {!! Form::textarea('motivo', null, ['class'=>'form-control input-sm ', 'rows' => '3', 'required']) !!}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" id="btnCloseCadastro" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>

                                {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro', 'id' => 'btnCadastro']) !!}
                                <div id="saveError" class="alert alert-danger alert-dismissable" style="display:none;">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                                    <span class="glyphicon glyphicon-remove"></span>
                                    <div id="save_result"></div>
                                </div>
                            </div>
                            {!! Form::close() !!}
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                                <h4 class="modal-title" id="myModalLabelCadastro"></h4>
                            </div>
                            {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmFechamentoAjax']) }}
                            <div class="modal-body">
                                <div class="box-body">
                                    <div class="col-md-12">
                                        {!! Form::label('data', 'Data:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;']) !!}
                                        <div class="col-sm-9 input-group generalDateTimePicker">
                                            {!! Form::datetime('dataFechamento', null, ['class'=>'form-control input-sm generalDateTimePicker', 'required']) !!}
                                            <span class="input-group-addon">
                                                <span class="glyphicon glyphicon-calendar"></span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" id="btnCloseCadastro" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>

                                {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro', 'id' => 'btnCadastro']) !!}
                                <div id="saveError" class="alert alert-danger alert-dismissable" style="display:none;">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                                    <span class="glyphicon glyphicon-remove"></span>
                                    <div id="save_result"></div>
                                </div>
                            </div>
                            {!! Form::close() !!}
                        </div>
                    </div>
                </div>

                <!-- page script -->
                <script type="text/javascript">
                    var urlBuscaProdutosAjax = '{{ url("produto/buscaporsetor/:id") }}';
                    var urlBuscaProdutosSetor = '{{ url("consultaestoquesetor?setor=:setorproduto=:produtoestoqueZerado=:estoqueZerado") }}';
                    var urlOperacaoEstoque = '{{ url("consultaestoquesetor/:operacao") }}';
                    var urlIndex = '{{ url("consultaestoquesetor") }}';
                </script>
                <script src="{{URL::to('js/consultaestoquesetor.js')}}"></script>
            </div><!-- /.content-wrapper -->
        </div>
    </div>
</div>
@endsection
