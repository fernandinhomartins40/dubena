@extends('layouts.mainmenu')
@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
            @if(isset($nfoperacao))
            {{ Form::model($nfoperacao, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('nfoperacao.update', $nfoperacao->id))) }}
            @else
            {{ Form::open(['id'=>'fmCadastro', 'route' => 'nfoperacao.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Operação NFe</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                            <li><a href="#tab_2" data-toggle="tab">App NFe</a></li>
                            <li><a href="#tab_3" data-toggle="tab">Convênio NFe</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-11">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-4">
                                                    {!! Form::text('descricao',null,['class'=>'form-control input-sm', 'autofocus' => 'true']) !!}
                                                </div>
                                                {!! Form::label('descricaofiscal', 'Descrição Fiscal:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-4">
                                                    {!! Form::text('descricaofiscal',null,['class'=>'form-control input-sm']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('cfop', 'CFOP:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::text('cfop',null,['class'=>'form-control input-sm number']) !!}
                                                </div>
                                                <div class="col-sm-8">
                                                    <div class="col-sm-3">
                                                        {!! Form::label('cfopie', 'CFOP InterEstadual:', ['class'=>'  control-label input-sm']) !!}
                                                    </div>
                                                    <div class="col-sm-2 ">
                                                        {!! Form::text('cfopie',null,['class'=>'col-sm-12 form-control input-sm number', 'id'=>'cfopie']) !!}
                                                    </div>
                                                    <div class="col-sm-1 ">
                                                        {!! Form::label('tiponf', 'Tipo:', ['class'=>'col-sm-12 control-label input-sm']) !!}
                                                    </div>
                                                    <div class="col-sm-2 ">
                                                        {{ Form::radio('tiponf', '0', true) }} Entrada<br>
                                                        {{ Form::radio('tiponf', '1') }} Saída
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('informacoesadicionalfico', 'Informações Adicionais Fisco:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-10">
                                                    {!! Form::textarea('informacoesadicionalfisco',null,['class'=>'form-control input-sm', 'rows' => 3]) !!}
                                                </div>
                                            </div>
                                            
                                            <div class="form-group crud_space">
                                                {!! Form::label('movimentaestoque', 'Estoque:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-4">
                                                    {!! Form::select('movimentaestoque',$movimentaestoque, null,['class'=>'form-control input-sm selectDisableSearch']) !!}
                                                </div>
                                                {!! Form::label('movimentafinanceiro', 'Financeiro:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-4">
                                                    {!! Form::select('movimentafinanceiro',$movimentafinanceiro, null,['class'=>'form-control input-sm selectDisableSearch']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('cadastronf', 'Emite NF/SAT Para:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-4">
                                                    {!! Form::select('cadastronf', $cadastronf, null, ['class'=>'form-control input-sm selectDisableSearch']) !!}
                                                </div>
                                                {!! Form::label('aparecetela', 'Tipo Tela:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-4">
                                                    {!! Form::select('aparecetela', $aparecetela, null, ['class'=>'form-control input-sm selectDisableSearch']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                <div class="hidden">
                                                    {!! Form::label('spedvenda', 'Venda (SPED):', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                    <div class="col-sm-1 checkbox">   
                                                        {{Form::hidden('spedvenda',0)}}
                                                        {{ Form::checkbox('spedvenda') }}
                                                    </div>
                                                </div>
                                                {!! Form::label('atualizacusto', 'Atualiza Custo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-1 checkbox">   
                                                    {{ Form::checkbox('atualizacusto') }}
                                                </div>
                                                {!! Form::label('deolhonoimposto', 'De Olho no Imposto:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2 checkbox">
                                                    {{Form::hidden('deolhonoimposto',0)}}
                                                    {{ Form::checkbox('deolhonoimposto') }}
                                                </div>
                                                {!! Form::label('usasat', 'Usa SAT:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                                <div class="col-sm-2 checkbox">
                                                    {{ Form::checkbox('usasat') }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div><!-- /.tab-pane -->
                            </div><!-- /.tab-pane -->
                            <div class="tab-pane" id="tab_2">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-11">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {!! Form::label('enviaappnf', 'Envia App NF:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-1 checkbox">   
                                                    {{Form::hidden('enviaappnf',0)}}
                                                    {{ Form::checkbox('enviaappnf') }}
                                                </div>
                                            </div>
                                            <div class="col-md-8 col-md-offset-3 margTop_10">
                                                <div class="col-md-4">
                                                    {!! Form::select('produto_id', $produtos, null, ['id'=>'produto_id', 'class' => 'form-control selectChosen', 'style'=>'padding:0px;max-height:24px;']) !!}
                                                </div>
                                                <div class="col-md-6">
                                                    {!! Form::select('nfoperacaoapp_id', $nfoperacaos, null, ['id'=>'nfoperacaoapp_id', 'class' => 'form-control selectChosen', 'style'=>'padding:0px;max-height:24px;']) !!}
                                                </div>
                                                <button type="button" id='btnAddProd' class="btn btn-xs btn-nw-buscas">Adicionar</button>
                                            </div>
                                            <div class="col-md-8  col-md-offset-3">
                                                {{Form::hidden('produtos',"", ['id'=>'produtos'])}}
                        
                                                <table id="tblProdutos" class="table table-bordered table-hover table-condensed">
                                                    <thead>
                                                        <tr>
                                                            <th>codigo</th>
                                                            <th></th>
                                                            <th style='width: 35%'>Produto</th>
                                                            <th></th>
                                                            <th style='width: 45%'>NF Operação</th>
                                                            <th style='width: 20%'>Operação</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="produtos-list">
                                                        @if(isset($nfoperacao))
                                                            @foreach ($nfoperacao->produtos as $produto)
                                                            <tr id="prod{{$produto->id}}">
                                                                <td>{{$produto->id}}</td>
                                                                <td>{{$produto->produto->id}}</td>
                                                                <td>{{$produto->produto->descricao}}</td>
                                                                <td>{{$produto->nfoperacaoapp->id}}</td>
                                                                <td>{{$produto->nfoperacaoapp->descricao}}</td>
                                                                <td>
                                                                    <button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverProduto'>Remover</button>
                                                                </td>
                                                            </tr>
                                                            @endforeach
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </div><!-- /.box -->
                                        </div>
                                    </div>
                                </div><!-- /.tab-pane -->
                            </div><!-- /.tab-pane -->
                            <div class="tab-pane" id="tab_3">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-11">
                                        <div class="box-body">
                                            <div class="col-md-8 col-md-offset-3 margTop_10">
                                                <div class="col-md-4">
                                                    {!! Form::select('produtoconvenio_id', $produtos, null, ['id'=>'produtoconvenio_id', 'class' => 'form-control selectChosen', 'style'=>'padding:0px;max-height:24px;']) !!}
                                                </div>
                                                <div class="col-md-6">
                                                    {!! Form::select('nfoperacaoconvenio_id', $nfoperacaos, null, ['id'=>'nfoperacaoconvenio_id', 'class' => 'form-control selectChosen', 'style'=>'padding:0px;max-height:24px;']) !!}
                                                </div>
                                                <button type="button" id='btnAddProdConvenio' class="btn btn-xs btn-nw-buscas">Adicionar</button>
                                            </div>
                                            <div class="col-md-8  col-md-offset-3">
                                                {{Form::hidden('produtoconvenios',"", ['id'=>'produtoconvenios'])}}
                        
                                                <table id="tblProdutoconvenios" class="table table-bordered table-hover table-condensed">
                                                    <thead>
                                                        <tr>
                                                            <th>codigo</th>
                                                            <th></th>
                                                            <th style='width: 35%'>Produto</th>
                                                            <th></th>
                                                            <th style='width: 45%'>NF Operação</th>
                                                            <th style='width: 20%'>Operação</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="produtos-list">
                                                        @if(isset($nfoperacao))
                                                            @foreach ($nfoperacao->produtoconvenios as $produto)
                                                            <tr id="prod{{$produto->id}}">
                                                                <td>{{$produto->id}}</td>
                                                                <td>{{$produto->produto->id}}</td>
                                                                <td>{{$produto->produto->descricao}}</td>
                                                                <td>{{$produto->nfoperacaoconvenio->id}}</td>
                                                                <td>{{$produto->nfoperacaoconvenio->descricao}}</td>
                                                                <td>
                                                                    <button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverProdutoConvenio'>Remover</button>
                                                                </td>
                                                            </tr>
                                                            @endforeach
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </div><!-- /.box -->
                                        </div>
                                    </div>
                                </div><!-- /.tab-pane -->
                            </div><!-- /.tab-pane -->
                        </div><!-- /.tab-pane -->
                        <div class="box-footer">
                            <div class="col-md-4">
                                {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
                                <a type="button" href="{{url('nfoperacao')}}" class="btn btn-nw-geral">Voltar</a>
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
<!-- DATA TABES SCRIPT -->
<!-- page script -->
<script type="text/javascript">
    var tblProd;

    $(document).ready(function ($) {
        tblProd = $('#tblProdutos').DataTable({
            "language": {"url": urlDataTable},
            "processing": false,
            "bPaginate": false,
            "bLengthChange": false,
            "bFilter": false,
            "bSort": false,
            "bInfo": false,
            "bAutoWidth": false,
            "columnDefs": [
                {
                    "targets": [0,1,3],
                    "visible": false
                }
            ]
        });
        tblProdConv = $('#tblProdutoconvenios').DataTable({
            "language": {"url": urlDataTable},
            "processing": false,
            "bPaginate": false,
            "bLengthChange": false,
            "bFilter": false,
            "bSort": false,
            "bInfo": false,
            "bAutoWidth": false,
            "columnDefs": [
                {
                    "targets": [0,1,3],
                    "visible": false
                }
            ]
        });
        function errorMsg(msg, error) {
            errorElement.innerHTML += '<p>' + msg + '</p>';
            if (typeof error !== 'undefined') {
                console.error(error);
            }
        }
        setTimeout(function () {

            @if (isset($show))
                desativarInputs();
                var ids = [".btnBuscarEndereco", '#btnBuscarCEP',
                    '.novoCadEndereco', '#btnAddFone'];
                desativarInputsEspecificos(ids);

            @endif
            @if ($errors -> any())
                carregarProdutosErro();
            @endif
        }, $(document).ready());

        $('#tblProdutos').on('click', 'button', function () {
            var trElem = $(this).closest("tr");// grabs the button's parent tr element
            var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id

            if ($(firstTd).text()) {
                if ($(this).context.id === 'btnRemoverProduto') {
                    removeFromTable(tblProd, $(this), "tblProd");
                }
            }
        });
        $("#btnAddProd").on('click', function () {
            if (!$.isNumeric($('#produto_id').val())) {
                bootbox.alert('Preencha o produto.');
                return;
            }
            if (!$.isNumeric($('#nfoperacaoapp_id').val())) {
                bootbox.alert('Preencha a operação.');
                return;
            }
            prodExists = false;
            tblProd.column(1)
                    .data()
                    .each(function (value) {
                        if ($("#produto_id").val() === value) {
                            prodExists = true;
                        }
                    });
            if (prodExists) {
                bootbox.alert('Produto já incluído na lista.');
                return;
            } 
            let $prod = $('#produto_id');
            let $oper = $('#nfoperacaoapp_id');
            tblProd.row.add([
                '',
                $prod.val(),
                $prod.find("option:selected").text(),
                $oper.val(),
                $oper.find("option:selected").text(),
                "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverProduto'>Remover</button>"
            ]).draw(false);
            $tel.val('');
            $wpp.prop('checked', false);
            $btnAdd.prop('disabled', true);
            $telTipo.focus().trigger("chosen:activate");
        });
        //produtos convênio
        $('#tblProdutoconvenios').on('click', 'button', function () {
            var trElem = $(this).closest("tr");// grabs the button's parent tr element
            var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id

            if ($(firstTd).text()) {
                if ($(this).context.id === 'btnRemoverProdutoConvenio') {
                    removeFromTable(tblProdConv, $(this), "tblProdConv");
                }
            }
        });
        $("#btnAddProdConvenio").on('click', function () {
            if (!$.isNumeric($('#produtoconvenio_id').val())) {
                bootbox.alert('Preencha o produto.');
                return;
            }
            if (!$.isNumeric($('#nfoperacaoconvenio_id').val())) {
                bootbox.alert('Preencha a operação.');
                return;
            }
            prodExists = false;
            tblProdConv.column(1)
                    .data()
                    .each(function (value) {
                        if ($("#produtoconvenio_id").val() === value) {
                            prodExists = true;
                        }
                    });
            if (prodExists) {
                bootbox.alert('Produto já incluído na lista.');
                return;
            } 
            let $prod = $('#produtoconvenio_id');
            let $oper = $('#nfoperacaoconvenio_id');
            tblProdConv.row.add([
                '',
                $prod.val(),
                $prod.find("option:selected").text(),
                $oper.val(),
                $oper.find("option:selected").text(),
                "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverProdutoConvenio'>Remover</button>"
            ]).draw(false);
        });

    });
    $("#fmCadastro").on("submit", function (e) {
        putTableInSelector($('#produtos'), tblProd);
        putTableInSelector($('#produtoconvenios'), tblProdConv);
    });
    function putTableInSelector($selector, tbl) {
        let data = [];
        tbl.rows().every(function () {
            var d = this.data();
            data.push(d);
        });
        data = JSON.stringify(data);
        if (data) {
            $selector.val(data);
        } else {
            $selector.val('');
        }
    }
    function removeFromTable(table, $button, strTable) {
        let row = $button.parents('tr');
        let tableRow = table.row(row);
        let data = tableRow.data();
        tableRow.remove().draw();
    }
    function carregarProdutosErro() {
        tblProd.clear();
        var produtos = JSON.parse($('#produtos').val());
        tblProd.rows.add(produtos).draw();
        tblProdConv.clear();
        var produtoconvenios = JSON.parse($('#produtoconvenios').val());
        tblProdConv.rows.add(produtoconvenios).draw();
    }
</script>
@endsection
