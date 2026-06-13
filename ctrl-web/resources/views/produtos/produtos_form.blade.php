@extends('layouts.mainmenu')

@section('content')


    <div id="mainContent" class="content">
        <div id="divCadastro" class="row">
            <div class="col-md-12">

                <!-- Custom Tabs -->
                <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
                @if(isset($produto))
                    {{ Form::model($produto, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal','files' => true, 'route' => array('produto.update', $produto->id))) }}
                @else
                    {{ Form::open(['id'=>'fmCadastro','route' => 'produto.store', 'class' => 'form-horizontal', 'files' => true]) }}
                @endif
                <ul>
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">Produto</h3>
                        </div>
                        <div class="nav-tabs-custom">
                            <ul class="nav nav-tabs">
                                <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                                <li class=""><a href="#tab_2" data-toggle="tab">Dados Fiscais</a></li>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane active" id="tab_1">
                                    <!-- form start -->
                                    <div class="row">
                                        <div id="tabCadastro" class="col-md-12">
                                            <div class="box-body">
                                                <div class="form-group crud_space">
                                                    {{ Form::label('descricao', 'Nome Produto: ', ['id' => 'descricao','class'=>'col-sm-2 control-label input-sm']) }}
                                                    <div class="col-md-3">
                                                        {{ Form::text('descricao',null,['class'=>'form-control input-sm']) }}
                                                    </div>
                                                    {{ Form::label('produtoclasse_id', 'Classe do Produto: ', ['class'=>'col-md-2 control-label input-sm']) }}
                                                    <div class="col-md-3">
                                                        {{ Form::select('produtoclasse_id', $produtoclasses,null, ['id'=>'produtoclasse_id', 'class' => 'form-control selectChosen'])}}
                                                        {{ Form::hidden('classeglp',$classeglp,['id'=>'classeglp','class'=>'form-control input-sm']) }}
                                                    </div>
                                                </div>
                                                <div id="tipoglp" class="form-group crud_space hidden">
                                                    {{ Form::label('tipo_glp', 'Tipo de GLP: ', ['class'=>'col-md-2 control-label input-sm']) }}
                                                    <div class="col-md-3">
                                                        {{ Form::select('tipo_glp', $tipoglp,null, ['id'=>'tipo_glp', 'class' => 'form-control selectChosen'])}}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {{ Form::label('pesobruto', 'Peso Bruto:', ['id' => 'pesoliquido','class'=>'col-sm-2 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        {{ Form::text('pesobruto',null,['class'=>'form-control maskPeso input-sm']) }}
                                                    </div>
                                                    {{ Form::label('pesoliquido', 'Peso Líquido:', ['id' => 'pesoliquido','class'=>'col-md-1 control-label input-sm']) }}
                                                    <div class="col-md-2">
                                                        {{ Form::text('pesoliquido',null,['class'=>'form-control maskPeso input-sm']) }}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {{ Form::label('precovenda', 'Preço Venda:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        {{ Form::text('precovenda',null,['id' => 'precovenda','class'=>'form-control dinheiro input-sm']) }}
                                                    </div>
                                                    {{ Form::label('precovendaminimo', 'Preço Mínimo:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        {{ Form::text('precovendaminimo',null,['id' => 'precovendaminimo','class'=>'form-control dinheiro input-sm']) }}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {{ Form::label('precogasdopovo', 'Preço Gás do Povo:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        {{ Form::text('precogasdopovo',null,['id' => 'precogasdopovo','class'=>'form-control dinheiro input-sm']) }}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {{ Form::label('customedio', 'Custo Médio:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                    <div class="col-md-2">
                                                        {{ Form::text('customedio',null,['id' => 'customedio','class'=>'form-control dinheiro input-sm']) }}
                                                    </div>
                                                    {{ Form::label('diasgiro', 'Dias Giro:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-md-2">
                                                        {{ Form::text('diasgiro',null,['id' => 'diasgiro','class'=>'form-control number input-sm']) }}
                                                    </div>
                                                <!-- {{ Form::label('custofrete', 'Custo Frete:', ['id' => 'custofrete','class'=>'col-sm-1 control-label input-sm']) }}
                                                        <div class="col-sm-2">
{{ Form::text('custofrete',null,['class'=>'form-control dinheiro input-sm']) }}
                                                        </div>
-->
                                                </div>
                                                <div class="form-group crud_space">
                                                    {{ Form::label('unidademedida_id', 'Unidade Medida:', ['id' => 'unidademedida_id','class'=>'col-md-2 control-label input-sm']) }}
                                                    <div class="col-md-3">
                                                        {{ Form::select('unidademedida_id',$unidademedida, null, ['class' => 'form-control selectChosen input-sm']) }}
                                                    </div>
                                                    <div id="checkbox">
                                                        {{ Form::label('ativo', 'Ativo', ['class'=>'col-md-1 control-label input-sm']) }}
                                                        <div class="col-md-1 checkbox">
                                                            {{ Form::checkbox('ativo',1) }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {{ Form::label('vasilhameret', 'Vasilha Retornável?', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                    <div class="col-sm-3">
                                                        <div class="col-sm-10">
                                                            {{ Form::label('sim', 'Sim', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                            <div class="col-sm-1 checkbox">
                                                                {{ Form::radio('vasilhameretornavel', '1', false, ['onchange' => 'checkVasilhameSimNao(this.value)']) }}
                                                            </div>
                                                            {{ Form::label('nao', 'Não', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                            <div class="col-sm-1 checkbox">
                                                                {{ Form::radio('vasilhameretornavel', '0', true, ['onchange' => 'checkVasilhameSimNao(this.value)']) }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {{ Form::label('produtoretornavel_id', 'Vasilha:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                    <div class="col-sm-3">
                                                        @if(isset($vasilhame))
                                                            {{ Form::select('produtoretornavel_id', $vasilhame, null, ['id'=>'produtoretornavel_id', 'class' => 'form-control selectChosen'])}}
                                                        @else
                                                            {{ Form::select('produtoretornavel_id', [], null, ['id'=>'produtoretornavel_id', 'class' => 'form-control selectChosen'])}}
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {{ Form::label('ressarcimentoproduto_id', 'Produto de Ressarcimento:', ['id' => 'unidademedida_id','class'=>'col-md-2 control-label input-sm']) }}
                                                    <div class="col-md-3">
                                                        {{ Form::select('ressarcimentoproduto_id',$ressarcimento, null, ['id'=>'ressarcimentoproduto_id','class' => 'form-control selectChosen input-sm']) }}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    <div id="checkbox">
                                                        {{ Form::label('enviappnf', 'Envia App NF', ['class'=>'col-md-2 control-label input-sm']) }}
                                                        <div class="col-md-1 checkbox">
                                                            {{ Form::checkbox('enviaappnf',1) }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {{ Form::label('observacao', 'Observação:', ['class'=>'col-md-2 control-label input-sm']) }}
                                                    <div class="col-md-9">
                                                        {{ Form::textarea('observacao', null, ['size' => '30x3','class'=>'form-control']) }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane" id="tab_2">
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <div class="form-group crud_space">
                                                <div class="col-sm-5 col-md-push-1">
                                                    <h1 class="panel-title">Sped</h1>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('nfetipoitem', 'Tipo Item:', ['id' => 'nfetipoitem','class'=>'col-md-2 control-label input-sm']) }}
                                                <div class="col-md-4">
                                                    {{ Form::select('nfetipoitem',$nfetipoitem, null, ['class' => 'form-control selectChosen']) }}
                                                </div>
                                                {{ Form::label('nfeextipi', 'Cód. Ex. IPI:', ['class'=>'col-md-1 control-label input-sm']) }}
                                                <div class="col-md-4">
                                                    {{ Form::text('nfeextipi',null,['id' => 'nfeextipi','class'=>'form-control input-sm number']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('nfecodlst', 'Cód.Lst:', ['class'=>'col-md-2 control-label input-sm']) }}
                                                <div class="col-md-4">
                                                    {{ Form::select('nfecodlst',$lst, null, ['id' => 'nfecodlst','class' => 'form-control selectChosen']) }}
                                                </div>
                                                {{ Form::label('nfecodgen', 'Cód Gênero:', ['class'=>'col-md-1 control-label input-sm']) }}
                                                <div class="col-md-4">
                                                    {{ Form::select('nfecodgen',$generos, null, ['id' => 'nfecodgen','class' => 'form-control selectChosen']) }}
                                                    {{ Form::hidden('sped',$sped,['id'=>'sped','val'=>$sped,'class'=>'form-control input-sm']) }}
                                                </div>
                                            </div>
                                            <hr style="margin-left:10px;"/>
                                            <div class="form-group crud_space">
                                                <div class="col-sm-5 col-md-push-1">
                                                    <h1 class="panel-title">NFE</h1>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('nfepermite', 'Informar Valores para NFE', ['class'=>'nfepermite col-md-3 control-label input-sm']) }}
                                                <div class="col-md-4 checkbox">
                                                    {{ Form::checkbox('nfepermite',1) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('nfedescricaofiscal', 'Nome Fiscal:', ['id' => 'nfedescricaofiscal','class'=>'col-md-2 control-label input-sm']) }}
                                                <div class="col-md-4">
                                                    {{ Form::text('nfedescricaofiscal',null,['class'=>'form-control input-sm', 'disabled'=>'true']) }}
                                                </div>
                                                {{ Form::label('nfgrupofiscal_id', 'Grupo Fiscal:', ['id' => 'nfgrupofiscal_id','class'=>'col-md-1 control-label input-sm']) }}
                                                <div class="col-md-4">
                                                    {{ Form::select('nfgrupofiscal_id',$nfgrupofiscal_id, null, ['id'=>'nfgrupofiscal_id','class' => 'form-control selectChosen input-sm','disabled'=>'true']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('ean', 'EAN:', ['id' => 'ean','class'=>'col-md-2 control-label input-sm']) }}
                                                <div class="col-md-4">
                                                    {{ Form::text('ean',null,['class'=>'form-control input-sm', 'disabled'=>'true']) }}
                                                </div>
                                                {{ Form::label('eantrib', 'EAN Trib:', ['id' => 'ean','class'=>'col-md-1 control-label input-sm']) }}
                                                <div class="col-md-4">
                                                    {{ Form::text('eantrib',null,['class'=>'form-control input-sm', 'disabled'=>'true']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('nfipi_id', 'CST IPI:', ['id' => 'nfipi_id','class'=>'col-md-2 control-label input-sm']) }}
                                                <div class="col-md-4">
                                                    {{ Form::select('nfipi_id',$nfipi, null, ['id'=>'nfipi_id','class' => 'form-control selectChosen input-sm', 'disabled'=>'true']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('ncm', 'NCM:', ['class'=>'col-md-2 control-label input-sm']) }}
                                                <div class="col-md-4">
                                                    {{ Form::text('ncm',null,['id' => 'ncm','class'=>'form-control input-sm number', 'disabled'=>'true']) }}
                                                </div>
                                                {{ Form::label('nfcest_id', 'CEST:', ['class'=>'col-md-1 control-label input-sm']) }}
                                                <div class="col-md-4">
                                                    @if(@$nfcest == null)
                                                        {{ Form::select('nfcest_id',[], null, ['id'=>'nfcest_id','class' => 'form-control selectChosen input-sm','disabled'=>'true']) }}
                                                    @else
                                                        {{ Form::select('nfcest_id',$nfcest, null, ['id'=>'nfcest_id','class' => 'form-control selectChosen input-sm','disabled'=>'true']) }}
                                                    @endif
                                                    {{ Form::hidden('nfcest',null,['id'=>'nfcest']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('especie', 'Espécie:', ['id' => 'especie','class'=>'col-md-2 control-label input-sm']) }}
                                                <div class="col-md-4">
                                                    {{ Form::text('especie',null,['class'=>'form-control input-sm', 'disabled'=>'true']) }}
                                                </div>
                                                {{ Form::label('marca', 'Marca:', ['id' => 'marca','class'=>'col-md-1 control-label input-sm']) }}
                                                <div class="col-md-4">
                                                    {{ Form::text('marca',null,['class'=>'form-control input-sm', 'disabled'=>'true']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('nfealiqipi', 'Alíq IPI:', ['class'=>'col-md-2 control-label input-sm']) }}
                                                <div class="col-md-1">
                                                    {{ Form::text('nfealiqipi',null,['id'=>'nfealiqipi','class'=>'form-control input-sm percentagemSomenteNumAllowZero','disabled'=>'true']) }}
                                                </div>
                                                {{ Form::label('nfebcipi', 'Base IPI:', ['class'=>'col-md-1 control-label input-sm']) }}
                                                <div class="col-md-1">
                                                    {{ Form::text('nfebcipi',null,['id' => 'nfebcipi','class'=>'form-control input-sm percentagemSomenteNumAllowZero','disabled'=>'true']) }}
                                                </div>
                                                {{ Form::label('nfecodenquadramentoipi', 'Código Enquadramento IPI:', ['class'=>'col-md-2 control-label input-sm']) }}
                                                <div class=" col-md-1">
                                                    {{ Form::text('nfecodenquadramentoipi',null,['id' => 'nfecodenquadramentoipi','class'=>'form-control input-sm number']) }}
                                                </div>
                                            </div>
                                            <hr style="margin-left:10px;"/>
                                            <div class="form-group crud_space">
                                                <div class="col-sm-5 col-md-push-1">
                                                    <h1 class="panel-title">Detalhes Específicos de Combustíveis</h1>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('nfecprodanp', 'Código ANP:', ['id' => 'nfecprodanp','class'=>'col-md-2 control-label input-sm']) }}
                                                <div class="col-md-1">
                                                    {{ Form::text('nfecprodanp',null,['class'=>'form-control input-sm number']) }}
                                                </div>
                                                {{ Form::label('nfedescanp', 'Descrição ANP:', ['class'=>'col-md-1 control-label input-sm']) }}
                                                <div class="col-md-2">
                                                    {{ Form::text('nfedescanp',null,['class'=>'form-control input-sm']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('nfeqbcprod', 'BC da Cide:', ['id' => 'nfeqbcprod','class'=>'col-md-2 control-label input-sm ']) }}
                                                <div class="col-md-1">
                                                    {{ Form::text('nfeqbcprod',null,['class'=>'form-control input-sm percentagemSomenteNumAllowZero']) }}
                                                </div>
                                                {{ Form::label('nfevaliqprod', 'Valor Alíq Cide:', ['id' => 'nfevaliqprod','class'=>'col-md-1 control-label input-sm']) }}
                                                <div class=" col-md-1">
                                                    {{ Form::text('nfevaliqprod',null,['class'=>'form-control input-sm percentagemSomenteNumAllowZero']) }}
                                                </div>
                                                {{ Form::label('nfevcide', 'Valor Cide:', ['id' => 'nfevcide','class'=>'col-md-1 control-label input-sm']) }}
                                                <div class="col-md-1">
                                                    {{ Form::text('nfevcide',null,['class'=>'form-control input-sm percentagemSomenteNumAllowZero']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('pgni', '% GNi:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-1">
                                                    {{ Form::text('pgni', isset($produto) ? number_format($produto->pgni, 4, ",", "") : "0,0000",['class'=>'form-control input-sm pGLP']) }}
                                                </div>
                                                {{ Form::label('pgnn', '% GNn:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-1">
                                                    {{ Form::text('pgnn', isset($produto) ? number_format($produto->pgnn, 4, ",", "") : "0,0000",['class'=>'form-control input-sm pGLP']) }}
                                                </div>
                                                {{ Form::label('pglp', '% GLP:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-1">
                                                    {{ Form::text('pglp', isset($produto) ? number_format($produto->pglp, 4, ",", "") : "0,0000",['class'=>'form-control input-sm pGLP']) }}
                                                </div>
                                            </div>
                                            <hr />
                                            <div class="form-group crud_space">
                                                {{ Form::label('indimport', 'Indicação de Importação:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-1 p-l-0">
                                                    <label class="control-label input-sm">{!! Form::radio('indimport', 0, true) !!} Nacional</label> <br />
                                                    <label class="control-label input-sm">{!! Form::radio('indimport', 1) !!} Importado</label>
                                                </div>
                                                {{ Form::label('cuforig', 'Cód UF Origem:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('cuforig', $estados, null, ['id'=>'cuforig', 'class' => 'form-control selectChosen'])}}
                                                </div>
                                                {{ Form::label('porig', '% Origem:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-1">
                                                    {{ Form::text('porig', isset($produto) ? number_format($produto->porig, 4, ",", "") : "0,0000",['class'=>'form-control input-sm pGLP']) }}
                                                </div>
                                                <div class="col-sm-2">
                                                    <button type="button" id="addOrigem" class='btn btn-nw-buscas btn-xs'>Adicionar</button>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                <div class="col-sm-8 col-sm-offset-1">
                                                    <table id="tblOrigens" class="table table-bordered table-hover table-condensed">
                                                        <thead>
                                                            <tr>
                                                                <th>ID</th>
                                                                <th>indImport</th>
                                                                <th>Indicador de Importação</th>
                                                                <th>Cód UF</th>
                                                                <th>UF</th>
                                                                <th>% Origem</th>
                                                                <th>Operação</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="tbodyTblOrigens" name="tbodyTblOrigens">
                                                            @if (isset($listOrigens))
                                                                @foreach ($listOrigens as $origem)
                                                                <tr id="tr{!! $origem->id !!}">
                                                                    <td>{{$origem->id}}</td>
                                                                    <td>{{$origem->indimport}}</td>
                                                                    <td>{{$origem->indimport == 0 ? "Nacional" : "Importado"}}</td>
                                                                    <td>{{$origem->cuforig}}</td>
                                                                    <td>{{$origem->estado}}</td>
                                                                    <td>{{$origem->porig}}</td>
                                                                    <td>
                                                                        <button type='button' class='btn btn-nw-registro btn-xs' id='btnRemover'>Remover</button>
                                                                    </td>
                                                                </tr>
                                                                @endforeach
                                                            @endif
                                                        </tbody>
                                                        {!! Form::hidden('origensList', null, ['id'=>'origensList']) !!}
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="box-footer">
                                <div class="col-md-4">
                                    {{ Form::submit('Gravar', ['id'=>'btngravar','class' => 'btn btn-nw-registro']) }}
                                    <a type="button" href="{{url('produto')}}" class="btn btn-nw-geral">Voltar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </ul>
            </div>
        </div>
    </div>
    <script type="text/javascript" src="{{URL::to('js/produto.js')}}"></script>
    <script type="text/javascript">
        var error = false;

        @if ($errors->any())
            error = true;
        @endif

        $(document).ready(function () {
            informarValNfe();
            checkVasilhameSimNao($("input[name='vasilhameretornavel']:checked").val());
        });
        $(document).ready(function(){
            setTimeout(function () {
                @if (isset($show))
                desativarInputs();
                        @else
                var url = '{{URL::to("produto/ajaxncmcest/:id")}}';
                $("#ncm").blur(function(){
                    ajaxNcmCest(url);
                });
                        @endif
                var ncmRetorno = $("#ncm").val();
                if (ncmRetorno !== '' && $("#ncm").prop('disabled') !== true) {
                    var url = '{{URL::to("produto/ajaxncmcest/:id")}}';
                    ajaxNcmCest(url);
                }
            });
        });
    </script>
@endsection
