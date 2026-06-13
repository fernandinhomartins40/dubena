<div class="row">
    <div id="tabCadastro" class="col-md-12">
        <div class="box-body">
            <div class="form-group crud_space">
                {!! Form::label('emitrazaosocial', 'Razão Social:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                <div class="col-sm-5">
                    {!! Form::text('emitrazaosocial', isset($show) ? null : $empresa->razao_social,['class'=>'form-control input-sm']) !!}
                </div>
                {!! Form::label('emitnomefantasia', 'Fantasia:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                <div class="col-sm-3">
                    {!! Form::text('emitnomefantasia', isset($show) ? null : $empresa->nome_fantasia,['class'=>'form-control input-sm']) !!}
                </div>
            </div>
            <div class="form-group crud_space">
                {!! Form::label('emitie', 'Inscrição Estadual:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                <div class="col-sm-2">
                    {!! Form::text('emitie', isset($show) ? null : $empresa->inscricao_estadual,['class'=>'form-control input-sm']) !!}
                </div>
                {!! Form::label('emitinscricaomunicipal', 'Inscrição Mun.:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                <div class="col-sm-2">
                    {!! Form::text('emitinscricaomunicipal', isset($show) ? null : $empresa->inscricao_municipal,['class'=>'form-control input-sm']) !!}
                </div>
                {!! Form::label('emitcnpj', 'CNPJ:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                <div class="col-sm-2">
                    {!! Form::text('emitcnpj', isset($show) ? null : $empresa->cnpj,['class'=>'form-control input-sm']) !!}
                    {!! Form::hidden('emitcpf',null,['id' => 'emitcpf']) !!}
                </div>
            </div>
            <div class="form-group crud_space">
                {!! Form::label('emitcnae', 'CNAE:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                <div class="col-sm-2">
                    {!! Form::text('emitcnae', isset($show) ? null : $empresa->cnae,['class'=>'form-control input-sm']) !!}
                </div>
                {!! Form::label('codcrt', 'CRT:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                <div class="col-sm-2">
                    {!! Form::text('codcrt', isset($show) ? null : $empresa->nfecrt,['class'=>'form-control input-sm']) !!}
                </div>
                {!! Form::hidden('emitpaiscodigoibge', isset($show) ? null : $empresa->codigoibgepais, ['class'=>'form-control input-sm', 'id' => 'emitpaiscodigoibge']) !!}
                {!! Form::label('emitpaisnome', 'País:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                <div class="col-sm-2">
                    {!! Form::text('emitpaisnome',"Brasil",['class'=>'form-control input-sm']) !!}
                </div>
            </div>
            <div class="form-group crud_space">
                {!! Form::hidden('emitufcodigoibge', isset($show) ? null : $empresa->estado_cod_ibge,['class'=>'form-control input-sm', 'id' => 'emitufcodigoibge']) !!}
                {!! Form::label('emituf', 'UF:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                <div class="col-sm-1">
                    {!! Form::text('emituf', isset($show) ? null : $empresa->uf,['class'=>'form-control input-sm']) !!}
                </div>
                {!! Form::hidden('emitcidadecodigoibge', isset($show) ? null : $empresa->cidade_cod_ibge,['class'=>'form-control input-sm', 'id' => 'emitcidadecodigoibge']) !!}
                {!! Form::label('emitcidadenome', 'Cidade:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                <div class="col-sm-2">
                    {!! Form::text('emitcidadenome', isset($show) ? null : $empresa->cidade_descricao,['class'=>'form-control input-sm']) !!}
                    {!! Form::hidden('emitcidade_id', isset($show) ? null : $empresa->cidade_id,['id' => 'emitcidade_id']) !!}
                </div>
                {!! Form::label('emitbairro', 'Bairro:', ['class' => 'col-sm-1 control-label input-sm']) !!}
                <div class="col-sm-2">
                    {!! Form::text('emitbairro', isset($show) ? null : $empresa->bairro_descricao,['class'=>'form-control input-sm']) !!}
                </div>
            </div>
            <div class="form-group crud_space">
                {!! Form::label('emitendereco', 'Endereço:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                <div class="col-sm-5">
                    {!! Form::text('emitendereco', isset($show) ? null : $empresa->rua_descricao,['class'=>'form-control input-sm']) !!}
                </div>
                {!! Form::label('emitnumero', 'Número:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                <div class="col-sm-1">
                    {!! Form::text('emitnumero', isset($show) ? null : $empresa->numero,['class'=>'form-control input-sm']) !!}
                </div>
                {!! Form::label('emitcep', 'CEP:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                <div class="col-sm-1">
                    {!! Form::text('emitcep', isset($show) ? null : $empresa->cep,['class'=>'form-control input-sm']) !!}
                </div>
            </div>
            <div class="form-group crud_space">
                {!! Form::label('emitcomplemento', 'Complemento:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                <div class="col-sm-2">
                    {!! Form::text('emitcomplemento', isset($show) ? null : $empresa->complemento,['class'=>'form-control input-sm']) !!}
                </div>
                {!! Form::label('emittelefone', 'Telefone:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                <div class="col-sm-2">
                    {!! Form::text('emittelefone', isset($show) ? null : $empresa->telefone1,['class'=>'form-control input-sm']) !!}
                </div>
            </div>
        </div>
    </div>
</div>