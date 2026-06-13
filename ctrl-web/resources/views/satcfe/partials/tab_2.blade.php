<div class="row">
    <div id="tabCadastro" class="col-sm-12">
        <div class="box-body">
            <div class="form-group crud_space">
                {{ Form::label('razao_social', 'Razão Social:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-4">
                    {{ Form::text('razao_social', $cupomFiscal->emitxnome ? $cupomFiscal->emitxnome : $empresa->razao_social, ['class'=>'form-control input-sm', 'readonly']) }}
                </div>
                {{ Form::label('nome_fantasia', 'Nome Fantasia:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-4">
                    {{ Form::text('nome_fantasia', $cupomFiscal->emitxfant ? $cupomFiscal->emitxfant : $empresa->nome_fantasia, ['class'=>'form-control input-sm', 'readonly']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('emitcnpj', 'CNPJ:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('emitcnpj',  $cupomFiscal->emitcnpj ? $cupomFiscal->emitcnpj : $empresa->cnpj, ['class'=>'form-control input-sm', 'readonly']) }}
                </div>
                {{ Form::label('emitie', 'Inscrição Estadual:', ['class'=>'col-sm-3 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('emitie',  $cupomFiscal->emitie ? $cupomFiscal->emitie : $empresa->inscricao_estadual, ['class'=>'form-control input-sm', 'readonly']) }}
                </div>
                {{ Form::hidden('emituf', $empresa->uf, ['class'=>'form-control input-sm', 'readonly', 'id' => 'emituf']) }}
            </div>
        </div>
    </div>
</div>