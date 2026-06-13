<div id="divCadastro" class="row">
    <div class="col-md-12">
        {{ Form::open(['id'=>'fmCadastroR', 'route' => 'caixa.baixar', 'class' => 'form-horizontal', 'files' => true]) }}
        <ul>

            <div class="nav-tabs-custom">
                <div class="header panel-default">
                      <div class="panel-heading">
                          <h3 class="panel-title">
                              Cancelamento de Títulos
                          </h3>
                      </div>
                </div>
                <ul class="nav nav-tabs">
                    <li class="active"><a href="#tab_1" data-toggle="tab">Dados da Baixa</a></li>
                    <li class=""><a href="#tab_2" data-toggle="tab">Detalhamento dos Títulos</a></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane active" id="tab_1">
                        <!-- form start -->

                        <div class="row">
                            <div id="tabCadastro" class="col-md-10">
                                <div class="box-body">
                                    @if($conta_id == -1)
                                    <!--
                                    <div class="form-group crud_space">
                                        {!! Form::label('conta_id', 'Caixa para Registro do Cancelamento:', ['class'=>'col-sm-3 control-label input-sm','style'=>'text-align:right;']) !!}
                                        <div class="col-sm-5">
                                            {!! Form::select('conta_id', $contas, null, ['class' => 'form-control input-sm', 'style'=>'border-radius: 5px ! important;']) !!}
                                        </div>
                                    </div>
                                    -->
                                    @endif
                                    <div class="form-group crud_space">
                                        {{Form::hidden('cliente_id_erro',"", ['id'=>'cliente_id_erro'])}}
                                        {{Form::hidden('cliente_nome_erro',"", ['id'=>'cliente_nome_erro'])}}
                                        {{Form::hidden('recebimentotipo_id',"", ['id'=>'recebimentotipo_id'])}}
                                        <!--
                                        {{Form::hidden('data_pagamento',"", ['id'=>'data_pagamento'])}}
                                        @if($conta_id != -1)
                                        {{Form::hidden('conta_id',$conta_id, ['id'=>'baixar'])}}
                                        @endif
                                        -->
                                        {{Form::hidden('baixarfechado',isset($baixarfechado)?$baixarfechado:0, ['id'=>'baixarfechado'])}}
                                    </div>
                                    <!--
                                    <div class="form-group crud_space">
                                        {!! Form::label('data_pagamentoM', 'Data Cancelamento:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                        <div class="col-sm-3">
                                            <div class="input-group date" id="datetimepicker1">
                                                {!! Form::text('data_pagamentoM',null,['class'=>'form-control input-sm', 'required']) !!}
                                                <span class="input-group-addon">
                                                    <span class="glyphicon glyphicon-calendar"></span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    -->
                                    <div class="form-group crud_space">
                                        {!! Form::label('descricao', 'Motivo:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                        <div class="col-sm-8">
                                            {!! Form::text('descricao',null,['class'=>'form-control input-sm']) !!}
                                        </div>
                                    </div>
                                    <div class="form-group crud_space">
                                        {!! Form::label('valor', 'Valor Total a Cancelar:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                        <div class="col-sm-3">
                                            {!! Form::text('valor_total', number_format($valor_total,2,',','.'),['class'=>'form-control input-sm dinheiro', 'id'=>'valor_total', 'readonly'=>true]) !!}
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
                                                <div class="form-group crud_space">
                                                    <div class="col-sm-9 col-sm-offset-1" style="width:950px;">
                                                        <div id="parcelasGrid" class="scroll-container"></div>
                                                    </div>
                                                </div>
                                            </div><!-- /.box-body -->
                                        </div><!-- /.box -->
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
            {!! Form::close() !!}
        </ul><!-- /.col -->
    </div>
</div>
