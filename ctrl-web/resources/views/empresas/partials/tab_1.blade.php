
<!-- form start -->
<div class="row">
    <div id="tabCadastro" class="col-md-11">
        <div class="box-body">
            <div class="form-group crud_space">
                @can('support', App\Empresa::class)
                {{ Form::label('grupo_id', 'Grupo:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-10">
                    {{ Form::select('grupo_id', $grupos, null, ['class' => 'form-control selectDisableSearch']) }}
                </div>
                @endcan
            </div>
            <div class="form-group crud_space">
                {{ Form::label('regiao_id', 'Regional:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-6">
                    {{ Form::select('regiao_id',$regioes, null, ['id'=>'regiao_id','class' => 'form-control selectDisableSearch']) }}
                </div>
                <div class="col-sm-2">
                    @if(isset($Empresa) && $Empresa->matriz)
                    <button class="btn btn-nw-buscas btn-sm" id="tornarMatriz" type="button" disabled="disabled">Já é matriz</button>
                    @else
                    <button class="btn btn-nw-buscas btn-sm" id="tornarMatriz" type="button">Tornar esta empresa matriz do grupo</button>
                    @endif
                </div>
                {{ Form::hidden('matriz',null,['class'=>'form-control input-sm', 'id' => 'matriz']) }}
                {{ Form::hidden('foi_matriz',@$Empresa->matriz,['class'=>'form-control input-sm', 'id' => 'foi_matriz']) }}
                {{ Form::label('ativo', 'Ativo:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1 checkbox">
                    {{ Form::checkbox('ativo') }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('razao_social', 'Razão Social:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-10">
                    {{ Form::text('razao_social',null,['class'=>'form-control input-sm']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('nome_fantasia', 'Nome Fantasia:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-4">
                    {{ Form::text('nome_fantasia',null,['class'=>'form-control input-sm']) }}
                </div>
                {{ Form::label('nome_informal', 'Nome Informal:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-4">
                    {{ Form::text('nome_informal',null,['class'=>'form-control input-sm']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('cnpj', 'CNPJ:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('cnpj',null,['class'=>'form-control input-sm cnpj']) }}
                </div>
                {{ Form::label('inscricao_estadual', 'Insc.Estadual:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('inscricao_estadual',null,['class'=>'form-control input-sm']) }}
                </div>
                {{ Form::label('inscricao_municipal', 'Insc. Municipal:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('inscricao_municipal',null,['class'=>'form-control input-sm']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('inscricao_estadual_st', 'Insc.Estadual ST:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('inscricao_estadual_st',null,['class'=>'form-control input-sm']) }}
                </div>
                {{ Form::label('cnae', 'CNAE:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('cnae',null,['class'=>'form-control input-sm']) }}
                </div>
                {{ Form::label('suframa', 'Suframa:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('suframa',null,['class'=>'form-control input-sm']) }}
                </div>
            </div>
            @include('general.endereco_form_partial')
            <div class="form-group crud_space">
                {{ Form::label('telefone1', 'Telefone:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('telefone1',null,['class'=>'form-control input-sm telefone']) }}
                </div>
                {{ Form::label('telefone2', 'Celular:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('telefone2',null,['class'=>'form-control input-sm telefone2']) }}
                </div>
                {{ Form::label('email', 'E-mail:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('email',null,['class'=>'form-control input-sm']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('capacidadearmazenamento', 'Cap. Armazenamento:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('capacidadearmazenamento',null,['class'=>'form-control input-sm maskPesoInteiro']) }}
                </div>
                {{ Form::label('registro_anp', 'Registro ANP:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('registro_anp',null,['class'=>'form-control input-sm']) }}
                </div>
                {{ Form::label('distribuidora', 'Distribuidora:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('distribuidora',null,['class'=>'form-control input-sm']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('depd', 'DEPD:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-1  checkbox">
                    {{ Form::checkbox('depd') }}
                </div>
                {{ Form::label('depr', 'DEPR:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1  checkbox">
                    {{ Form::checkbox('depr') }}
                </div>
                {{ Form::label('prt', 'PRT:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1  checkbox">
                    {{ Form::checkbox('prt') }}
                </div>
                {{ Form::label('prr', 'PRR:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1  checkbox">
                    {{ Form::checkbox('prr') }}
                </div>
                {{ Form::label('prd', 'PRD:', ['class'=>'col-sm-1 control-label input-sm']) }}
                <div class="col-sm-1  checkbox">
                    {{ Form::checkbox('prd') }}
                </div>
            </div>
        </div> <!-- box-body  -->
    </div> <!-- tab-cadastro -->
</div> <!-- row -->