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
                              Consulta de {{$tipo_lancamento=='P'?'Despesa':'Receita'}}
                          </h3>
                      </div>
                </div>
                <ul class="nav nav-tabs" id="mainNav">
                    <li class="active"><a href="#tab_1" data-toggle="tab">Dados do Documento</a></li>
                    <li class=""><a href="#tab_2" data-toggle="tab">Parcelamento</a></li>
                    <li class=""><a href="#tab_3" data-toggle="tab">Rateio</a></li>
                    @if(count($parcelasagrupadas)>0)
                    <li class=""><a href="#tab_4" data-toggle="tab">Parcelas Originais (agrupadas)</a></li>
                    @endif
                </ul>
                <div class="tab-content">
                    <div class="tab-pane active" id="tab_1">
                        <!-- form start -->

                        <div class="row">
                            <div id="tabCadastro" class="col-md-10">
                                <div class="box-body">
                                    <div class="form-group crud_space">
                                        {!! Form::label('cliente_id', $tipo_lancamento=="P"?"Fornecedor:":"Cliente:", ['class'=>'col-sm-3 control-label input-sm']) !!}
                                        <div class="col-sm-8">
                                            <select id="searchbox" name="cliente_id" placeholder="Buscar {{$tipo_lancamento=='P'?'Fornecedor':'Cliente'}}" class="form-control" style="float:left;width:100%;" value="" data-selectize-value = '[{"id":{{$Financeiro->cliente->id}},"nome":"{!!$Financeiro->cliente->nome!!}"}]' readonly></select>
                                        </div>
                                    </div>
                                    <div class="form-group crud_space">
                                        {!! Form::label('cpf', 'CPF/CNPJ:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                        <div class="col-sm-2">
                                            {!! Form::text('cpf',$Financeiro->cliente->tipopessoa->tipopessoacadastro == "J" ? $Financeiro->cliente->cnpj:$Financeiro->cliente->cpf,['class'=>'form-control input-sm', 'disabled']) !!}
                                        </div>
                                        {!! Form::label('rg', 'RG/Insc.Est.:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                        <div class="col-sm-2">
                                            {!! Form::text('rg',$Financeiro->cliente->tipopessoa->tipopessoacadastro == "J" ? $Financeiro->cliente->inscricao_estadual:$Financeiro->cliente->rg,['class'=>'form-control input-sm', 'disabled']) !!}
                                        </div>
                                    </div>
                                    
                                    <div class="form-group crud_space">
                                        {!! Form::label('dataemissao', 'Emissão:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                        <div class="col-sm-2">
                                            <div class="input-group date" id="datetimepicker1">
                                                {!! Form::text('dataemissao',Carbon\Carbon::parse($Financeiro->dataemissao)->format('d/m/Y'),['class'=>'form-control input-sm', 'required', 'readonly']) !!}
                                                <span class="input-group-addon">
                                                    <span class="glyphicon glyphicon-calendar"></span>
                                                </span>
                                            </div>
                                        </div>
                                        {!! Form::label('datacompetencia', 'Competência:', ['class'=>'col-sm-1 control-label input-sm', 'required']) !!}
                                        <div class="col-sm-2">
                                            <div class="input-group date" id="datetimepicker2">
                                                {!! Form::text('datacompetencia',Carbon\Carbon::parse($Financeiro->datacompetencia)->format('d/m/Y'),['class'=>'form-control input-sm', 'readonly']) !!}
                                                <span class="input-group-addon">
                                                    <span class="glyphicon glyphicon-calendar"></span>
                                                </span>
                                            </div>
                                        </div>
                                        {!! Form::label('id', 'Código:', ['class'=>'col-sm-1 control-label input-sm', 'required']) !!}
                                        <div class="col-sm-2">
                                            {!! Form::text('id',$Financeiro->id,['class'=>'form-control input-sm', 'readonly']) !!}
                                        </div>
                                    </div>
                                    <div class="form-group crud_space">
                                        {!! Form::label('documento', 'Nº Documento:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                        <div class="col-sm-2">
                                            {!! Form::text('documento',null,['class'=>'form-control input-sm', 'readonly']) !!}
                                        </div>
                                        
                                        {!! Form::hidden('voltar', $voltar, null, ['class' => 'form-control']) !!}
                                        {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                        <div class="col-sm-5">
                                            {!! Form::text('descricao',$Financeiro->descricao,['class'=>'form-control input-sm', 'readonly']) !!}
                                        </div>
                                    </div>
                                    <div class="form-group crud_space">
                                        {!! Form::label('valor', 'Valor:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                        <div class="col-sm-2">
                                            {!! Form::text('valor',number_format($Financeiro->valor,2,',','.'),['class'=>'form-control input-sm dinheiro', 'id'=>'valor', 'readonly']) !!}
                                        </div>
                                        {!! Form::label('condicaopagamento_id', 'Condição:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                        <div class="col-sm-5">
                                            {!! Form::select('condicaopagamento_id', $condicaopagamentos, $Financeiro->condicaopagamento_id, ['class' => 'form-control input-sm', 'style'=>'border-radius: 5px ! important;width:100%', 'readonly']) !!}
                                        </div>
                                        
                                    </div>
                                    <div class="form-group crud_space" style="display:none;" id="divCartao">
                                        {!! Form::label('cartaonsu', 'Nº Documento Cartão:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                        <div class="col-sm-2">
                                            {!! Form::text('cartaonsu',@$cartaonsu,['class'=>'form-control input-sm']) !!}
                                        </div>
                                        {!! Form::label('cartaoautorizacao', 'Autorização:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                        <div class="col-sm-2">
                                            {!! Form::text('cartaoautorizacao',@$cartaoautorizacao,['class'=>'form-control input-sm']) !!}
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
                                                    <th style="text-align:right;">Valor</th>
                                                </tr>
                                            </thead>
                                            <tbody id="rateio-list" name="rateio-list">
                                                @foreach ($Financeiro->rateios as $rateio)
                                                <tr id="rateio{{$rateio->id}}">
                                                    <td>{{$rateio->id}}</td>
                                                    <td>{{$rateio->centroCusto->descricao}}</td>
                                                    <td>{{$rateio->id}}</td>
                                                    <td>{{$rateio->planoConta->descricao}}</td>
                                                    <td style="text-align:right;">{{'R$ '.number_format($rateio->valor, 2, ',', '.')}}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div><!-- /.box -->
                                </div>
                            </div>
                        </div>
                    </div><!-- /.tab-pane -->
                    @if(count($parcelasagrupadas)>0)
                    <div class="tab-pane" id="tab_4">
                        <div class="row">
                            <div id="tabCadastro" class="col-md-10">
                                <div class="box-body">
                                    <div class="col-md-12">
                                        {{Form::hidden('agrup',"", ['id'=>'agrup'])}}

                                        <table id="tblAgrup" class="table table-bordered table-hover table-condensed">
                                            <thead>
                                                <tr>
                                                    <th>Cód.</th>
                                                    <th>Lanç.</th>
                                                    <th>Docto</th>
                                                    <th>Parcela</th>
                                                    <th>Descrição</th>
                                                    <th>Emissão</th>
                                                    <th>Vencimento</th>
                                                    <th style="text-align:right;">Valor</th>
                                                </tr>
                                            </thead>
                                            <tbody id="agrup-list" name="agrup-list">
                                                @foreach ($parcelasagrupadas as $parc)
                                                <tr id="agrp{{$parc->id}}">
                                                    <td>{{$parc->id}}</td>
                                                    <td>{{$parc->financeiro->id}}</td>
                                                    <td>{{$parc->financeiro->documento}}</td>
                                                    <td>{{$parc->numero}}</td>
                                                    <td>{{$parc->financeiro->descricao}}</td>
                                                    <td>{{Carbon\Carbon::parse($parc->financeiro->dataemissao)->format('d/m/Y')}}</td>
                                                    <td>{{Carbon\Carbon::parse($parc->financeiro->datavencimento)->format('d/m/Y')}}</td>
                                                    <td style="text-align:right;">{{'R$ '.number_format($parc->valor, 2, ',', '.')}}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div><!-- /.box -->
                                </div>
                            </div>
                        </div>
                    </div><!-- /.tab-pane -->
                    @endif
                </div><!-- /.tab-content -->
                
            </div>
            {!! Form::close() !!}
        </ul><!-- /.col -->
    </div>
</div>
