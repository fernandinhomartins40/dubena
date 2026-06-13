<div class="row">
    <div id="tabCadastro" class="col-md-10">
        <div class="box-body">
            <div class="form-group crud_space">
                {{ Form::label('contnome', 'Nome:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-10">
                    {{ Form::text('contnome',null,['class'=>'form-control input-sm']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('contcpf', 'CPF:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('contcpf',null,['class'=>'form-control input-sm cpf','maxlength' => 14]) }}
                </div>
                {{ Form::label('contcnpj', 'CNPJ:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('contcnpj',null,['class'=>'form-control input-sm cnpj','maxlength' => 18]) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('contcrc', 'CRC:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('contcrc',null,['class'=>'form-control input-sm']) }}
                </div>
                {{ Form::label('conttelefone', 'Telefone:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('conttelefone',null,['class'=>'form-control input-sm telefone']) }}
                </div>
                {{ Form::label('contfax', 'Fax:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('contfax',null,['class'=>'form-control input-sm telefone2']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('contemail', 'E-mail:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-10">
                    {{ Form::text('contemail',null,['class'=>'form-control input-sm']) }}
                </div>
            </div>
            @include('empresas.empresa_form_endereco_cont')
        </div>
    </div><!-- /.box-body -->
</div>