<div class="row">
    <div id="tabCadastro" class="col-md-12">
        <div class="col-md-12">
            <div class="col-md-12">
                {{Form::hidden("cliente_config", $cliente_config)}}
                <div class="box-body">
                    <div class="form-group crud_space">
                        {!! Form::label('destrazaosocial', 'Nome:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                        <div class="col-sm-5">
                            {!! Form::text('destrazaosocial',null,['class'=>'form-control input-sm']) !!}
                        </div>
                        {!! Form::label('destcliente_id', 'Cód. Cliente:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                        <div class="col-sm-2">
                            {!! Form::text('destcliente_id', isset($nfemitida) ? $nfemitida->cliente_id : @$nfrecebida->cliente_id,['class'=>'form-control input-sm']) !!}
                        </div>
                        {!! Form::label('destie', 'Inscrição Est.:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                        <div class="col-sm-2">
                            {!! Form::text('destie',null,['class'=>'form-control input-sm']) !!}
                        </div>
                    </div>
                    <div class="form-group crud_space">
                        {!! Form::label('destindicadorietext', 'Indicador IE:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                        <div class="col-sm-2">
                            {{ Form::hidden('destindicadorie', null, array('id' => 'destindicadorie')) }}
                            {!! Form::text('destindicadorietext',null,['class'=>'form-control input-sm']) !!}
                        </div>
                        {!! Form::label('destcnpj', 'CNPJ:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                        <div class="col-sm-2">
                            {!! Form::text('destcnpj',null,['class'=>'form-control input-sm cnpj']) !!}
                        </div>
                        {!! Form::label('destcpf', 'CPF:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                        <div class="col-sm-2">
                            {!! Form::text('destcpf',null,['class'=>'form-control input-sm cpf']) !!}
                        </div>
                    </div>
                    <div class="form-group crud_space">
                        {!! Form::hidden('destpaiscodigoibge',null,['class'=>'form-control input-sm', 'id' => 'destpaiscodigoibge']) !!}
                        {!! Form::label('destpaisnome', 'País:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                        <div class="col-sm-2">
                            {!! Form::text('destpaisnome',null,['class'=>'form-control input-sm']) !!}
                        </div>
                        {!! Form::label('destuf', 'UF:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                        <div class="col-sm-1">
                            {!! Form::text('destuf',null,['class'=>'form-control input-sm']) !!}
                        </div>
                        {!! Form::hidden('destcidadecodigoibge',null,['class'=>'form-control input-sm', 'id' => 'destcidadecodigoibge']) !!}
                        {!! Form::label('destcidadenome', 'Cidade:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                        <div class="col-sm-2">
                            {!! Form::text('destcidadenome',null,['class'=>'form-control input-sm']) !!}
                            {!! Form::hidden('destcidade_id',null,['id' => 'destcidade_id']) !!}
                        </div>
                        {!! Form::label('destbairro', 'Bairro:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                        <div class="col-sm-2">
                            {!! Form::text('destbairro',null,['class'=>'form-control input-sm']) !!}
                        </div>
                    </div>
                    <div class="form-group crud_space">
                        {!! Form::label('destendereco', 'Endereço:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                        <div class="col-sm-5">
                            {!! Form::text('destendereco',null, ['class'=>'form-control input-sm']) !!}
                        </div>
                        {!! Form::label('destnumero', 'Número:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                        <div class="col-sm-1">
                            {!! Form::text('destnumero',null,['class'=>'form-control input-sm']) !!}
                        </div>
                        {!! Form::label('destcep', 'CEP:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                        <div class="col-sm-2">
                            {!! Form::text('destcep',null,['class'=>'form-control input-sm cep']) !!}
                        </div>
                    </div>
                    <div class="form-group crud_space">
                        {!! Form::label('destcomplemento', 'Complemento:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                        <div class="col-sm-2">
                            {!! Form::text('destcomplemento',null,['class'=>'form-control input-sm']) !!}
                        </div>
                        {!! Form::label('desttelefone', 'Telefone:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                        <div class="col-sm-2">
                            {!! Form::text('desttelefone',null,['class'=>'form-control input-sm']) !!}
                        </div>
                        {!! Form::label('destemail', 'E-mail:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                        <div class="col-sm-2">
                            {!! Form::text('destemail',null,['class'=>'form-control input-sm']) !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>