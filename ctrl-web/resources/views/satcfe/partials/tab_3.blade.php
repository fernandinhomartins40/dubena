<div class="row">
    <div id="tabCadastro" class="col-sm-12">
        <div class="box-body">
            <div class="form-group crud_space">
                {{ Form::label('destxnome', 'Nome/Razão Social:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-4">
                    {{ Form::text('destxnome', $cupomFiscal->destxnome, ['class'=>'form-control input-sm', 'readonly']) }}
                </div>
                {{ Form::label('cliente_id', 'Cód. Cliente:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1">
                    {{ Form::text('cliente_id', $cupomFiscal->cliente_id, ['class'=>'form-control input-sm', 'readonly', 'id' => 'cliente_id']) }}
                </div>
                {{ Form::label('destcnpj', 'CNPJ:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('destcnpj', $cupomFiscal->destcnpj, ['class'=>'form-control input-sm', 'readonly']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('destcpf', 'CPF:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('destcpf', $cupomFiscal->destcpf, ['class'=>'form-control input-sm', 'readonly']) }}
                </div>
                {{ Form::label('destuf', 'UF:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1">
                    {{ Form::text('destuf', $cupomFiscal->destuf, ['class'=>'form-control input-sm', 'readonly']) }}
                </div>
                {{ Form::label('destxmun', 'Cidade:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-4">
                    {{ Form::text('destxmun', $cupomFiscal->destxmun, ['class'=>'form-control input-sm', 'readonly']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('destxbairro', 'Bairro:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('destxbairro', $cupomFiscal->destxbairro, ['class'=>'form-control input-sm', 'readonly']) }}
                </div>
                {{ Form::label('destxlgr', 'Rua:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-3">
                    {{ Form::text('destxlgr', $cupomFiscal->destxlgr, ['class'=>'form-control input-sm', 'readonly']) }}
                </div>
                {{ Form::label('destnro', 'Número:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('destnro', $cupomFiscal->destnro, ['class'=>'form-control input-sm', 'readonly']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('destxcpl', 'Complemento:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-3">
                    {{ Form::text('destxcpl', $cupomFiscal->destxcpl, ['class'=>'form-control input-sm', 'readonly']) }}
                </div>
            </div>
        </div>
    </div>
</div>