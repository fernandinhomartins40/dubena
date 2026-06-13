<div id="divCadastro" class="row">
    <div class="col-md-12">
        {{ Form::open(['id'=>'fmCadastroR', 'route' => 'caixa.baixar', 'class' => 'form-horizontal', 'files' => true]) }}
        <ul>

            <div class="nav-tabs-custom">
                <div class="header panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">
                            Baixa de Lançamento no Caixa
                        </h3>
                    </div>
                </div>
                <ul class="nav nav-tabs">
                    <li class="active"><a href="#tab_1" data-toggle="tab">Dados da Baixa</a></li>
                    <li class=""><a href="#tab_2" data-toggle="tab">Detalhamento dos Títulos</a></li>
                    <li class=""><a href="#tab_3" data-toggle="tab">Tipos de Recebimentos</a></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane active" id="tab_1">
                        <!-- form start -->

                        <div class="row">
                            <div id="tabCadastro" class="col-md-10">
                                <div class="box-body">
                                    @if($conta_id == -1)
                                    <div class="form-group crud_space">
                                        {{ Form::label('conta_id', 'Caixa:', ['class'=>'col-sm-3 control-label input-sm','style'=>'text-align:right;']) }}
                                        <div class="col-sm-6">
                                            {{ Form::select('conta_id', $contas, null, ['class' => 'form-control input-sm', 'style'=>'border-radius: 5px ! important;']) }}
                                        </div>
                                    </div>
                                    @endif
                                    <div class="form-group crud_space">
                                        {{Form::hidden('cliente_id_erro',"", ['id'=>'cliente_id_erro'])}}
                                        {{Form::hidden('cliente_nome_erro',"", ['id'=>'cliente_nome_erro'])}}
                                        {{Form::hidden('recebimentotipo_id',"", ['id'=>'recebimentotipo_id'])}}
                                        {{Form::hidden('data_pagamento',"", ['id'=>'data_pagamento'])}}
                                        @if($conta_id != -1)
                                        {{Form::hidden('conta_id',$conta_id, ['id'=>'baixar'])}}
                                        @endif
                                        {{Form::hidden('baixarfechado',isset($baixarfechado)?$baixarfechado:0, ['id'=>'baixarfechado'])}}
                                        {{ Form::label('contamovimentotipo_idM', 'Tipo Recebimento:', ['class'=>'col-sm-3 control-label input-sm','style'=>'text-align:right;']) }}
                                        <div class="col-sm-6">
                                            {{ Form::select('contamovimentotipo_idM', $recebimentotipos, null, ['class' => 'form-control input-sm', 'style'=>'border-radius: 5px ! important;']) }}
                                        </div>
                                    </div>
                                    <div class="form-group crud_space">
                                        {{ Form::label('data_pagamentoM', 'Data Pagamento:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                        <div class="col-sm-3">
                                            <div class="input-group date" id="datetimepicker1">
                                                {{ Form::text('data_pagamentoM',null,['class'=>'form-control input-sm', 'required']) }}
                                                <span class="input-group-addon">
                                                    <span class="glyphicon glyphicon-calendar"></span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group crud_space">
                                        {{ Form::label('valor', 'Valor Total:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                        <div class="col-sm-2">
                                            {{ Form::text('valor_total', number_format($valor_total,2,',','.'),['class'=>'form-control input-sm dinheiro', 'id'=>'valor_total', 'readonly'=>true]) }}
                                        </div>
                                        @if($encontrocontas)
                                        {{ Form::label('valor_credito', 'Crédito:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                        <div class="col-sm-2">
                                            {{ Form::text('valor_credito', number_format($valor_credito,2,',','.'),['class'=>'form-control input-sm dinheiro', 'id'=>'valor_credito', 'readonly'=>true]) }}
                                            {{ Form::hidden('parcelasEncontroContas', $parcelasEncontroContas,['id'=>'parcelasEncontroContas']) }}
                                        </div>
                                        @endif
                                    </div>
                                    <div class="form-group crud_space">
                                        {{ Form::label('valor_desconto', 'Desconto:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                        <div class="col-sm-2">
                                            {{ Form::text('valor_desconto',$valor_desconto,['class'=>'form-control input-sm dinheiro', 'id'=>'valor_desconto', 'onblur'=>'setTotal();']) }}
                                        </div>
                                        {{ Form::label('valor_multa', 'Multa:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                        <div class="col-sm-2">
                                            {{ Form::text('valor_multa',$valor_multa,['class'=>'form-control input-sm dinheiro', 'id'=>'valor_multa','onblur'=>'setTotal();']) }}
                                        </div>
                                        {{ Form::label('valor_juros', 'Juros:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                        <div class="col-sm-2">
                                            {{ Form::text('valor_juros',$valor_juros,['class'=>'form-control input-sm dinheiro', 'id'=>'valor_juros', 'onblur'=>'setTotal();']) }}
                                        </div>
                                    </div>
                                    <div class="form-group crud_space">
                                        {{ Form::label('valor_liquido', 'Valor a baixar:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                        <div class="col-sm-2">
                                            {{ Form::text('valor_liquido',number_format($valor_liquido,2,',','.'),['class'=>'form-control input-sm dinheiro', 'id'=>'valor_liquido', 'readonly'=>true]) }}
                                        </div>
                                        <label for="parcial" class="col-sm-1 control-label input-sm required">Parcial:</label>
                                        <div class="col-sm-1 checkbox">
                                            {{Form::hidden('parcial',0)}}
                                            {{ Form::checkbox('parcial', 1, null, ['id'=>'parcial', $encontrocontas ? 'disabled' : '']) }}
                                        </div>
                                        <label for="valor_parcial" class="col-sm-2 control-label input-sm required">Valor Parcial:</label>
                                        <div class="col-sm-2">
                                            {{ Form::text('valor_parcial',null,['class'=>'form-control input-sm dinheiro', 'id'=>'valor_parcial', $encontrocontas ? 'disabled' : '']) }}
                                        </div>
                                    </div>
                                    <div class="form-group crud_space">
                                        {{ Form::label('descricao', 'Observação:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                        <div class="col-sm-8">
                                            {{ Form::text('descricao',$descricao,['class'=>'form-control input-sm', 'id'=>'descricao']) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div><!-- /.tab-pane -->
                    </div><!-- /.tab-pane -->
                    <div class="tab-pane" id="tab_2">
                        <div class="row">
                            <div id="tabCadastro" class="col-md-12">
                                <div class="box-body">
                                    <div class="row">
                                        {{Form::hidden('parcelas',"", ['id'=>'parcelas'])}}
                                        <div id="tabMetas" class="col-md-12">
                                            <div class="box-body">
                                                <div id="parcelasGrid" class="scroll-container col-md-push-2"></div>
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
                                                    <th>Tipo Recebimento</th>
                                                    <th>Valor</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody id="rateio-list" name="rateio-list">
                                            </tbody>
                                        </table>
                                    </div><!-- /.box -->
                                    <div class="col-md-10 col-md-offset-1 panel panel-default">
                                        <div class="panel-heading">Adicionar Rateio</div>
                                        <div class="panel-body">
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="box">
                                                        <div class="box-body">
                                                            <div class="form-group crud_space">
                                                                <div class="col-sm-5">
                                                                    {{ Form::select('recebimentotipo_idR', $recebimentotipos, null, ['id'=>'recebimentotipo_idR', 'class' => 'form-control input-sm', 'style'=>'border-radius: 5px ! important;']) }}
                                                                </div>
                                                                {{ Form::label('valor_rateio', 'Valor:', ['class'=>'col-sm-1 control-label input-sm']) }}
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
                            <div id="saveError" class="alert alert-danger alert-dismissable col-md-6">
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
