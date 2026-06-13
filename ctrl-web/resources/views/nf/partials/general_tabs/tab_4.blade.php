<div class="row">
    <div id="tabCadastro" class="col-md-12">
        <div class="box-body">
            <div class="form-group crud_space">
                {!! Form::label('fretemodalidade', 'Modalidade frete:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                <div class="col-sm-4">
                    {!! Form::select('fretemodalidade', $fretemodalidades, null,['class'=>'form-control input-sm selectChosen']) !!}
                </div>
            </div>
            <div class="form-group crud_space">
                {!! Form::label('fretecliente_id', 'Transportadora:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                <div class="col-sm-4">
                    {!! Form::select('fretecliente_id', $transportadoras, null,['class'=>'form-control input-sm selectDisableSearch']) !!}
                </div>
            </div>
            @include('general.campos_frete_nf')
            <div class="form-group crud_space">
                {{ Form::label('totalqtdeprodutos', 'Qtde Produtos:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('totalqtdeprodutos',null,['class'=>'form-control input-sm']) }}
                </div>
                {{ Form::label('totalpesobruto', 'Peso Bruto:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('totalpesobruto',null,['class'=>'form-control input-sm']) }}
                </div>
                {{ Form::label('totalpesoliquido', 'Peso Líquido:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('totalpesoliquido',null,['class'=>'form-control input-sm']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {!! Form::label('fretecondicaopagamento_id', 'Condição de Pagamento:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                <div class="col-sm-3">
                    {!! Form::select('fretecondicaopagamento_id', $condicaopagamentos, null,['class'=>'form-control input-sm selectChosen']) !!}
                    {{ Form::hidden('fretevista', null, ['id'=>'fretevista','class' => 'form-control input-sm']) }}
                    {{ Form::hidden('freteboleto', null, ['id'=>'freteboleto','class' => 'form-control input-sm']) }}
                    {{ Form::hidden('fretecartao', null, ['id'=>'fretecartao','class' => 'form-control input-sm']) }}
                    {{ Form::hidden('fretecondicaoparcelas', null, ['id'=>'fretecondicaoparcelas','class' => 'form-control input-sm']) }}
                    {{ Form::hidden('fretecondicao', null, ['id'=>'fretecondicao','class' => 'form-control input-sm']) }}
                </div>
                <div class="col-sm-3">
                    <button type="button" id="btnFreteVisualizarParcelas" class="btn btn-nw-buscas btn-xs">Visualizar Parcelas</button>
                    {{ Form::hidden('frete_parcelas_financeiro', @$freteparcelasfinanceiro, ['id' => 'frete_parcelas_financeiro']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {!! Form::label('fretecentrocusto_id', 'Centro Custo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                <div class="col-sm-3">
                    {{Form::hidden('fretecentrocusto_id', $fretecentrocusto_id, ['id'=>'fretecentrocusto_id'])}}
                    <div class="input-group">
                        {!! Form::text('fretecentrocusto_descricao', $fretecentrocusto_descricao,['id'=>'fretecentrocusto_descricao', 'class'=>'form-control input-sm', 'readonly']) !!}
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnFreteCcusto">Mudar</button>
                        </span>
                    </div>
                </div>
                {!! Form::label('freteplanoconta_id', 'Plano Conta:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                <div class="col-sm-3">
                    {{Form::hidden('freteplanoconta_id',$freteplanoconta_id, ['id'=>'freteplanoconta_id'])}}
                    <div class="input-group">
                        {!! Form::text('freteplanoconta_descricao', $freteplanoconta_descricao,
                            ['id'=>'freteplanoconta_descricao', 'class'=>'form-control input-sm', 'readonly']
                        ) !!}
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnFretePconta">Mudar</button>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>