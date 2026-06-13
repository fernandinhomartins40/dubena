<div class="form-group crud_space">
    {!! Form::label('contcep', 'CEP:', ['class'=>'col-sm-2 control-label input-sm']) !!}
    <div class="col-sm-2">
        <div class="input-group">
            {!! Form::text('contcep',null,['class'=>'form-control input-sm']) !!}
            <span class="input-group-addon">
                <a href="#" id="btnContBuscarEndereco"><i class="glyphicon glyphicon-search"></i></a>
            </span>
        </div>
    </div>
    {!! Form::label('contuf', 'Estado:', ['class'=>'col-sm-1 control-label input-sm']) !!}
    <div class="col-sm-3">
        {!! Form::select('contuf', $contestado, null, ['class' => 'form-control selectChosen', 'style' => 'width:100%;']) !!}
    </div>
    {!! Form::label('contcidade_id', 'Cidade:', ['class'=>'col-sm-1 control-label input-sm']) !!}
    <div class="col-sm-3">
        @if (isset($contcidades))
        {!! Form::select('contcidade_id', $contcidades, null, ['class' => 'form-control selectChosenEndereco', 'style' => 'width:88%;float:left;']) !!}
        @else
        {!! Form::select('contcidade_id', $cidades, null, ['class' => 'form-control selectChosenEndereco', 'style' => 'width:88%;float:left;']) !!}
        @endif
        <a href="#" id='btnContNovoCadCidade' class='novoCadEndereco btnNovoCadEnderecoCont' data-toggle="modal" data-target="#popup_cidade" onclick="origemcontcidade = 'contcidade_id';origemcontuf = 'contuf';"><i class="icon fa fa-plus form-control" style="width:10%;border:0px;padding-top:5px;float:right;"></i></a>
    </div>
</div>
<div class="form-group crud_space">
    {!! Form::label('contbairro_id', 'Bairro:', ['class'=>'col-sm-2 control-label input-sm']) !!}
    <div class="col-sm-3">
        @if (isset($contbairros))
        {!! Form::select('contbairro_id', $contbairros, null, ['class' => 'form-control selectChosenEndereco', 'style' => 'width:88%;float:left;']) !!}
        @else
        {!! Form::select('contbairro_id', $bairros, null, ['style' => 'width:80%;float:left;','class' => 'form-control selectChosenEndereco']) !!}
        @endif
        <a href="#" id='btnContNovoCadBairro' class='novoCadEndereco btnNovoCadEnderecoCont' data-toggle="modal" data-target="#popup_bairro" onclick="origemcontcidade = 'contcidade_id';origemcontuf = 'contuf';nomecontbairro = '';"><i class="icon fa fa-plus form-control" style="width:15%;border:0px;padding-top:5px;"></i></a>
    </div>
    {!! Form::label('contrua_id', 'Endereço:', ['class'=>'col-sm-1 control-label input-sm']) !!}
    <div class="col-sm-5">
        {!! Form::select('contrua_id',$ruas, null, ['class'=>'form-control input-sm selectChosenEndereco ']) !!}
        <a href="#" id='btnContNovoCadEndereco' class='novoCadEndereco btnNovoCadEnderecoCont' data-toggle="modal" data-target="#popup_rua" onclick="origemcidade = 'cidade_id';origemUF = 'uf';nomebairro = '#contcidade_id'; contrua_id = ''"><i class="icon fa fa-plus form-control" style="width:15%;border:0px;padding-top:5px;"></i></a>
    </div>
    <div class="col-sm-1 buscarcontcep" style="text-align:right;">
        <button id='btnBuscarcontcep' type="button" class="btn btn-nw-buscas btn-sm" style="text-align:right;" onclick="buscarCEP('#contcidade_id', '#contuf', '#contrua_id', '#contcep', 'contabilista');">Buscar CEP</button>
    </div>
</div>

<div class="form-group crud_space">
    {!! Form::label('contnumero', 'Número:', ['class'=>'col-sm-2 control-label input-sm']) !!}
    <div class="col-sm-1">
        {!! Form::text('contnumero',null,['class'=>'form-control input-sm']) !!}
    </div>
    {!! Form::label('contcomplemento', 'Complemento:', ['class'=>'col-sm-1 control-label input-sm']) !!}
    <div class="col-sm-3">
        {!! Form::text('contcomplemento',null,['class'=>'form-control input-sm']) !!}
    </div>
</div>
{!! Form::hidden('cidade_erro_cont',@$Empresa->contcidade_id,['class'=>'form-control input-sm', 'id' => 'cidade_erro_cont']) !!}
{!! Form::hidden('bairro_erro_cont',@$Empresa->contbairro_id,['class'=>'form-control input-sm', 'id' => 'bairro_erro_cont']) !!}
{!! Form::hidden('rua_erro_cont',@$Empresa->contrua_id,['class'=>'form-control input-sm', 'id' => 'rua_erro_cont']) !!}
<script>
    $(document).ready(function() {
        setInputsEnderecoContabilista('#contcep', '#contcidade_id', '#contuf', '#contbairro_id', '#contrua_id');
    });
    $('#btnContBuscarEndereco').on('click', function () {
        setInputsEnderecoContabilista('#contcep', '#contcidade_id', '#contuf', '#contbairro_id', '#contrua_id');
        buscarEnderecoPorCep('contabilista');
    });
    @if($errors -> any())
        carregarEnderecoErroContabilista();
    @endif
</script>