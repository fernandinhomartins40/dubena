<div id="divCadastro" class="row">
    <div class="col-md-12">
        @if(isset($Financeiro))
        {{ Form::model($Financeiro, array('id'=>'fmCadastroR', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true)) }}
        @else
        {{ Form::open(['id'=>'fmCadastroR', 'route' => 'financeiro.store', 'class' => 'form-horizontal', 'files' => true]) }}
        @endif
        <ul>

            <div class="nav-tabs-custom">
                <div class="header panel-default">
                      <div class="panel-heading">
                          <h3 class="panel-title">
                              Lançamento de {{$tipo_lancamento=='P'?'Despesa':'Receita'}}
                              {{(isset($origemAgrupar)?' (Agrupar/reparcelar)':'')}}
                          </h3>
                      </div>
                </div>
                <ul class="nav nav-tabs" id="mainNav">
                    <li class="active"><a href="#tab_1" data-toggle="tab">Dados do Documento</a></li>
                    @if($tipo_tela=='COMPLEXA')
                    <li class=""><a href="#tab_2" data-toggle="tab">Parcelamento</a></li>
                    <li class=""><a href="#tab_3" data-toggle="tab">Rateio</a></li>
                    @endif
                </ul>
                <div class="tab-content">
                    <div class="tab-pane active" id="tab_1">
                        <div class="row">
                            <div id="tabCadastro" class="col-md-10">
                                <div class="box-body">
                                    <div class="form-group crud_space">
                                        {{Form::hidden('cliente_id_erro',"", ['id'=>'cliente_id_erro'])}}
                                        {{Form::hidden('cliente_nome_erro',"", ['id'=>'cliente_nome_erro'])}}
                                        {{Form::hidden('contamovimentotipo_id',"", ['id'=>'contamovimentotipo_id'])}}
                                        {{Form::hidden('datahorabaixa',"", ['id'=>'datahorabaixa'])}}
                                        {{Form::hidden('baixar',$baixar, ['id'=>'baixar'])}}
                                        {{Form::hidden('conta_id',$conta_id, ['id'=>'baixar'])}}
                                        {{ Form::label('cliente_id', $tipo_lancamento=="P"?"Fornecedor:":"Cliente:", ['class'=>'col-sm-3 control-label input-sm']) }}
                                        <div class="col-sm-8">
                                            @if(isset($Financeiro))
                                            <select id="searchbox" name="cliente_id" placeholder="Buscar {{$tipo_lancamento=='P'?'Fornecedor':'Cliente'}}" class="form-control" style="float:left;width:100%;" value="" data-selectize-value = '[{"id":{{$Financeiro->cliente->id}},"nome":"{{$Financeiro->cliente->nome}}"}]'></select>
                                            @else
                                            <select id="searchbox" name="cliente_id" placeholder="Buscar {{$tipo_lancamento=='P'?'Fornecedor':'Cliente'}}" class="form-control" style="float:left;width:100%;" value="" data-selectize-value = '[]'></select>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="form-group crud_space">
                                        {{ Form::label('cpf', 'CPF/CNPJ:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                        <div class="col-sm-2">
                                            {{ Form::text('cpf',null,['class'=>'form-control input-sm', 'disabled']) }}
                                        </div>
                                        {{ Form::label('rg', 'RG/Insc.Est.:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                        <div class="col-sm-2">
                                            {{ Form::text('rg',null,['class'=>'form-control input-sm', 'disabled']) }}
                                        </div>
                                    </div>
                                    <div class="form-group crud_space">
                                        {{ Form::label('dataemissao', 'Emissão:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                        <div class="col-sm-2">
                                            <div class="input-group date generalDatePicker">
                                                {{ Form::text('dataemissao',null,['id' => 'dataemissao','class'=>'form-control input-sm generalDatePicker', 'required']) }}
                                                <span class="input-group-addon">
                                                    <span class="glyphicon glyphicon-calendar"></span>
                                                </span>
                                            </div>
                                        </div>
                                        {{ Form::label('datacompetencia', 'Competência:', ['class'=>'col-sm-1 control-label input-sm', 'required']) }}
                                        <div class="col-sm-2">
                                            <div class="input-group date" id="datetimepicker2">
                                                {{ Form::text('datacompetencia',null,['class'=>'form-control input-sm']) }}
                                                <span class="input-group-addon">
                                                    <span class="glyphicon glyphicon-calendar"></span>
                                                </span>
                                            </div>
                                        </div>
                                        <div id="divVencimento">
                                            {{ Form::label('datavencimento', 'Vencimento:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                            <div class="col-sm-2">
                                                <div class="input-group date" id="datetimepicker3">
                                                    {{ Form::text('datavencimento',null,['class'=>'form-control input-sm']) }}
                                                    <span class="input-group-addon">
                                                        <span class="glyphicon glyphicon-calendar"></span>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group crud_space">
                                        {{ Form::label('documento', 'Nº Documento:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                        <div class="col-sm-2">
                                            {{ Form::text('documento',@$documento,['class'=>'form-control input-sm']) }}
                                        </div>

                                        @if(isset($origemAgrupar))
                                        {{ Form::hidden('origemAgrupar', $origemAgrupar, null, ['class' => 'form-control']) }}
                                        {{ Form::hidden('parcelasOrigem', implode(',',$parcelas), null, ['class' => 'form-control']) }}
                                        @endif
                                        {{ Form::hidden('voltar', $voltar, null, ['class' => 'form-control']) }}
                                        {{ Form::hidden('pagarreceber', $tipo_lancamento, null, ['id'=>'pagarreceber', 'class' => 'form-control']) }}
                                        {{ Form::hidden('tipo_lancamento', $tipo_lancamento, null, ['class' => 'form-control']) }}
                                        {{ Form::hidden('contafechamento_id',$contafechamento_id, ['id'=>'contafechamento_id'])}}
                                        {{ Form::label('descricao', 'Descrição:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                        <div class="col-sm-5">
                                            {{ Form::text('descricao',null,['class'=>'form-control input-sm']) }}
                                        </div>
                                    </div>
                                    <div class="form-group crud_space">
                                        {{ Form::label('valor', 'Valor:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                        <div class="col-sm-2">
                                            {{ Form::text('valor',(isset($origemAgrupar)?number_format($valor,2,',','.'):null),['class'=>'form-control input-sm dinheiro', 'id'=>'valor', 'onchange'=>($tipo_tela=='COMPLEXA'?'carregarParcelamento();':''), (isset($origemAgrupar)?'readonly':'')]) }}
                                        </div>
                                        {{ Form::label('condicaopagamento_id', 'Condição:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                        <div class="col-sm-5">
                                            {{ Form::select('condicaopagamento_id', $condicaopagamentos, null, ['class' => 'form-control input-sm', 'style'=>'border-radius: 5px ! important;width:100%']) }}
                                        </div>
                                    </div>
                                    @if(isset($origemAgrupar))
                                        <div class="form-group crud_space">
                                            {{ Form::label('desconto', 'Desconto:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                            <div class="col-sm-2">
                                                {{ Form::text('desconto',number_format($desconto,2,',','.'),['class'=>'form-control input-sm dinheiro', 'id'=>'desconto']) }}
                                            </div>
                                        </div>
                                    @endif
                                    <div class="form-group crud_space" style="display:none;" id="divCartao">
                                        {{ Form::label('cartaonsu', 'Nº Documento Cartão:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                        <div class="col-sm-2">
                                            {{ Form::text('cartaonsu',null,['class'=>'form-control input-sm']) }}
                                        </div>
                                        {{ Form::label('cartaoautorizacao', 'Autorização:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                        <div class="col-sm-2">
                                            {{ Form::text('cartaoautorizacao',null,['class'=>'form-control input-sm']) }}
                                        </div>
                                    </div>

                                    <div class="form-group crud_space">

                                    </div>
                                    <div class="form-group crud_space col-sm-12">
                                        {{ Form::label('planoconta_id', 'P.Conta:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                        <div class="col-sm-6">
                                            {{ Form::hidden('planoconta_id',$planoconta_id, ['id'=>'planoconta_id']) }}
                                            <div class="input-group">
                                                {{ Form::text('planoconta_descricao',$planoconta_descricao,['id'=>'planoconta_descricao', 'class'=>'form-control input-sm', 'readonly']) }}
                                                <span class="input-group-btn">
                                                    <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnPConta" onclick="abrirPlanoConta();">Mudar</button>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group crud_space col-sm-12">
                                        {{ Form::label('centrocusto_id', 'C.Custo:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                        <div class="col-sm-6">
                                            {{Form::hidden('centrocusto_id',$centrocusto_id, ['id'=>'centrocusto_id'])}}
                                            <div class="input-group">
                                                {{ Form::text('centrocusto_descricao',$centrocusto_descricao,['id'=>'centrocusto_descricao', 'class'=>'form-control input-sm', 'readonly']) }}
                                                <span class="input-group-btn">
                                                    <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnCcusto" onclick="abrirCentroCusto();">Mudar</button>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div><!-- /.tab-pane -->
                    </div><!-- /.tab-pane -->
                    <div class="tab-pane" id="tab_2">
                        <div class="row">
                            <div id="tabCadastro" class="col-md-10">
                                <div class="box-body">
                                    <div class="row">
                                        {{Form::hidden('parcelas',"", ['id'=>'parcelas'])}}
                                        <div id="tabMetas" class="col-md-10 col-md-offset-1">
                                            <div class="box-body">
                                                <div id="parcelasGrid" class="scroll-container"></div>
                                            </div><!-- /.box-body -->
                                        </div><!-- /.box -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- /.tab-pane -->
                    <div class="tab-pane" id="tab_3">
                        <div class="row">
                            <div id="tabCadastro" class="col-md-10">
                                <div class="box-body">
                                    <div class="col-md-8  col-md-offset-2">
                                        {{Form::hidden('rateio',"", ['id'=>'rateio'])}}

                                        <table id="tblRateio" class="table table-bordered table-hover table-condensed">
                                            <thead>
                                                <tr>
                                                    <th></th>
                                                    <th>C.Custo</th>
                                                    <th></th>
                                                    <th>Pl.Contas</th>
                                                    <th>Valor</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody id="rateio-list" name="rateio-list">
                                                @if(isset($origemAgrupar))
                                                @foreach ($rateiosFinal as $rateio)
                                                <tr id="rateio{{$rateio[0].'_'.$rateio[1]}}">
                                                    <td>{{$rateio[0]}}</td>
                                                    <td>{{$rateio[1]}}</td>
                                                    <td>{{$rateio[2]}}</td>
                                                    <td>{{$rateio[3]}}</td>
                                                    <td>{{'R$ '.number_format($rateio[4], 2, ',', '.')}}</td>
                                                    <td><button type='button' class='btn btn-nw-registro small' id='btnRemoverRateio'>Remover</button></td>
                                                </tr>
                                                @endforeach
                                                @endif
                                            </tbody>
                                        </table>
                                    </div><!-- /.box -->
                                    <div class="col-md-10 col-md-offset-1 panel panel-default">
                                        <div class="panel-heading">Adicionar Rateio</div>
                                        <div class="panel-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="box-header">
                                                        <h3 class="box-title" style="font-size:14px;">Centro de Custo</h3>
                                                    </div><!-- /.box-header -->
                                                    <div class="box">
                                                        <div class="box-body" style="max-height:350px;display: block; overflow: auto;">
                                                            <div id="jstreecc1">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="box-header">
                                                        <h3 class="box-title" style="font-size:14px;">Plano de Contas</h3>
                                                    </div><!-- /.box-header -->
                                                    <div class="box">
                                                        <div class="box-body" style="max-height:350px;display: block; overflow: auto;">
                                                            <div id="jstreepc1">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('valor_rateio', 'Valor:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::text('valor_rateio',null,['class'=>'form-control input-sm dinheiro', 'id'=>'valor_rateio']) }}
                                                </div>
                                                <div class="col-sm-1" style="text-align:center;padding-left:30px;">
                                                    <button type="button" class="btn btn-nw-buscas" onclick="addRateio();">Incluir</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- /.tab-pane -->
                    <div class="box-footer">
                        <div class="col-md-4">
                            <button type="button" class="btn btn-nw-registro" onclick="gravar();">Gravar</button>
                        </div>
                    </div>
                </div><!-- /.tab-content -->
                @if($errors->any())
                <div class="nav-tabs-custom" style="margin-top:5px;">
                    <div class="col-md-12">
                        <div class="box-footer">
                            <div id="saveError" class="alert alert-danger alert-dismissable col-md-4">
                                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                                <span class="glyphicon glyphicon-remove"></span>
                                @foreach($errors->all() as $error)
                                <p>{{ $error }}</p>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
            {{ Form::close() }}
        </ul><!-- /.col -->
    </div>
</div>
<div class="modal fade" id="popup_caixa" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabelCaixa"></h4>
            </div>
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-group crud_space col-sm-12">
                        {{ Form::label('contamovimentotipo_idM', $tipo_lancamento=='P'?'Tipo Pagamento:':'Tipo Recebimento:', ['class'=>'col-sm-3 control-label input-sm','style'=>'text-align:right;']) }}
                        <div class="col-sm-7">
                            {{ Form::select('contamovimentotipo_idM', $contamovimentotipos, null, ['class' => 'form-control input-sm', 'style'=>'border-radius: 5px ! important;']) }}
                        </div>
                    </div>

                    <div class="form-group crud_space col-sm-12">
                        {{ Form::label('datahorabaixaM', 'Data Baixa:', ['class'=>'col-sm-3 control-label input-sm','style'=>'text-align:right;']) }}
                        <div class="col-sm-6">
                            <div class="input-group date" id="datetimepicker4">
                                {{ Form::text('datahorabaixaM',null,['class'=>'form-control input-sm']) }}
                                <span class="input-group-addon">
                                    <span class="glyphicon glyphicon-calendar"></span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCloseCaixa" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
                <button type="button" id="btnGravarCaixa" class="btn btn-nw-registro" onclick="setDadosCaixa();">Gravar</button>
            </div>
        </div>
    </div>
</div>
