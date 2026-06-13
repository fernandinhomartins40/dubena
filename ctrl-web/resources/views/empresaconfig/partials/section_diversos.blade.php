<div class="form-group crud_space">
    {{ Form::label('setorprincipal_id', 'Setor Principal:', ['class'=>'col-sm-3 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('setorprincipal_id',$setors,null,['id' => 'setorprincipal_id','class'=>'form-control selectChosen input-sm']) }}
    </div>
    {{ Form::label('telacontrolakm', 'Tela Controla Km:', ['class'=>'col-sm-2 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::select('telacontrolakm',$controle,null,['id' => 'telacontrolakm','class'=>'form-control selectChosen input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('diastrabalhadosemana', 'Dias Trabalhados Semana:', ['class'=>'col-sm-3 control-label input-sm']) }}
    <div class="col-sm-1">
        {{ Form::text('diastrabalhadosemana',null,['id' => 'diastrabalhadosemana','class'=>'form-control number input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('qnddiasinativocompra', 'Inativar Clientes Sem Comprar Após (dias):', ['class'=>'col-sm-3 control-label input-sm']) }}
    <div class="col-sm-1">
        {{ Form::text('qnddiasinativocompra',null,['id' => 'qnddiasinativocompra','class'=>'form-control number input-sm']) }}
    </div>
    {{ Form::label('keygooglemaps', 'Chave API do Google:', ['class'=>'col-sm-4 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::text('keygooglemaps',null,['id' => 'keygooglemaps','class'=>'form-control input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('emailkeygoogle', 'E-mail Chave API Google:', ['class'=>'col-sm-3 control-label input-sm']) }}
    <div class="col-sm-3">
        {{ Form::email('emailkeygoogle', null, ['id' => 'emailkeygoogle','class'=>'form-control input-sm']) }}
    </div>
</div>
<div class="form-group crud_space">
    {{ Form::label('fatorpotencialvenda', 'Fator Potencial Venda:', ['class'=>'col-sm-3 control-label input-sm']) }}
    <div class="col-sm-1">
        {{ Form::number('fatorpotencialvenda',null,['id' => 'fatorpotencialvenda','class'=>'form-control input-sm', 'step'=>'any']) }}
    </div>
</div>
<div class="form-group crud_space">
    <div id="boxpedidocontrolatempoligacoes">
        {{ Form::label('pedidocontrolatempoligacoes', 'Controla Ligações Telefônicas', ['class'=>'col-sm-3 control-label input-sm']) }}
        <div class="col-sm-1 checkbox">
            {{ Form::checkbox('pedidocontrolatempoligacoes',1) }}
        </div>
    </div>
    <div class="col-sm-4">
        <div id="boxpermiteestoquenegativo">
            {{ Form::label('permiteestoquenegativo', 'Permite Estoque Negativo', ['class'=>'col-sm-8 control-label input-sm']) }}
            <div class="col-sm-1 checkbox">
                {{ Form::checkbox('permiteestoquenegativo',1) }}
            </div>
        </div>
    </div>
</div>