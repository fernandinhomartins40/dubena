<style>
    i.activated::before, a.novoCadEndereco i:hover {
        color: #72afd2 !important;
    }
</style>
<script src="{{URL::to('js/endereco.js')}}"></script>
<div class="form-group crud_space">
    {!! Form::label('cep', 'CEP:', ['class'=>'col-sm-2 control-label input-sm']) !!}
    <div class="col-sm-2">
        <div class="input-group">
            {!! Form::text('cep',@$cep,['class'=>'form-control input-sm']) !!}
            <span class="input-group-addon">
                <a href="#" id="buscarEndereco"><i class="glyphicon glyphicon-search"></i></a>
            </span>
        </div>
    </div>
    {!! Form::label('uf', 'Estado:', ['class'=>'col-sm-1 control-label input-sm']) !!}
    <div class="col-sm-3">
        {!! Form::select('uf', $estados, null, ['class' => 'form-control selectChosenEndereco input-small', 'data-live-search' => 'true', 'data-width' =>'100%']) !!}
    </div>
    {!! Form::label('cidade_id', 'Cidade:', ['class'=>'col-sm-1 control-label input-sm']) !!}
    <div class="col-sm-3">
        {!! Form::select('cidade_id', $cidades, null, ['class' => 'form-control selectChosenEndereco input-small', 'data-live-search' => 'true', 'data-width' =>'80%']) !!}
        <a href="#" class='novoCadEndereco btnNovoCadEnderecoPadrao'  data-toggle="modal" data-target="#popup_cidade" onclick="origemCidade = 'cidade_id';origemUF = 'uf';"><i class="icon fa fa-plus form-control" style="width:10%;border:0px;padding-top:5px;float:right;"></i></a>
    </div>
</div>
<div class="form-group crud_space">
    {!! Form::label('bairro_id', 'Bairro:', ['class'=>'col-sm-2 control-label input-sm']) !!}
    <div class="col-sm-3">
        {!! Form::select('bairro_id', $bairros, null, ['class' => 'form-control selectChosenEndereco', 'data-live-search' => 'true', 'data-width' =>'80%']) !!}
        <a href="#" class='novoCadEndereco btnNovoCadEnderecoPadrao'  data-toggle="modal" data-target="#popup_bairro" onclick="origemCidade = 'cidade_id';origemUF = 'uf';nomeBairro = '';"><i class="icon fa fa-plus form-control" style="width:15%;border:0px;padding-top:5px;"></i></a>
    </div>
    {!! Form::label('rua_id', 'Endereço:', ['class'=>'col-sm-1 control-label input-sm']) !!}
    <div class="col-sm-5">
        {!! Form::select('rua_id',$ruas, null, ['class'=>'form-control input-sm selectChosenEndereco', 'data-live-search' => 'true', 'data-width' =>'80%']) !!}
        <a href="#" class='novoCadEndereco btnNovoCadEnderecoPadrao'  data-toggle="modal" data-target="#popup_rua" onclick="origemCidade = 'cidade_id';origemUF = 'uf';nomeBairro = 'bairro_id'; rua_id = ''"><i class="icon fa fa-plus form-control" style="width:15%;border:0px;padding-top:5px;"></i></a>
    </div>
    <div class="col-sm-1 buscarCep" style="text-align:right; margin-left: -10px;">
        <button id='btnBuscarCEP' type="button" class="btn btn-nw-buscas btn-sm" style="text-align:right;" onclick="buscarCEP('#cidade_id', '#uf', '#rua_id', '#cep', 'geral');">Buscar CEP</button>
    </div>
</div>

<div class="form-group crud_space">
    {!! Form::label('numero', 'Número:', ['class'=>'col-sm-2 control-label input-sm']) !!}
    <div class="col-sm-1">
        {!! Form::text('numero',null,['class'=>'form-control input-sm number ']) !!}
    </div>
    {!! Form::label('complemento', 'Complemento:', ['class'=>'col-sm-1 control-label input-sm']) !!}
    <div class="col-sm-3">
        {!! Form::text('complemento',null,['class'=>'form-control input-sm']) !!}
    </div>
    {!! Form::label('ponto_referencia', 'Ponto Referência:', ['class'=>'col-sm-2 control-label input-sm']) !!}
    <div class="col-sm-3">
        {!! Form::text('ponto_referencia',null,['class'=>'form-control input-sm']) !!}
    </div>
</div>
@if (isset($setors))
<div class="form-group crud_space">
    {!! Form::label('setor_id', 'Setor:', ['class'=>'col-sm-2 control-label input-sm']) !!}
    <div class="col-sm-4">
        {!! Form::select('setor_id', $setors, null, ['class' => 'form-control selectChosenEndereco input-small', 'data-live-search' => 'true']) !!}
    </div>
</div>
@endif
{!! Form::hidden('cidade_erro',null,['class'=>'form-control input-sm', 'id' => 'cidade_erro']) !!}
{!! Form::hidden('bairro_erro',null,['class'=>'form-control input-sm', 'id' => 'bairro_erro']) !!}
{!! Form::hidden('rua_erro',null,['class'=>'form-control input-sm', 'id' => 'rua_erro']) !!}

{!! Form::hidden('rua_descricao',null,['class'=>'form-control input-sm', 'id' => 'rua_descricao']) !!}
<script>
    $('#buscarEndereco').on('click', function () {
        setInputsEnderecoPadrao('#cep', '#cidade_id', '#uf', '#bairro_id', '#rua_id');
        buscarEnderecoPorCep('geral');
    });
    $("#btnBuscarCEP").on('click', function () {
        setInputsEnderecoPadrao('#cep', '#cidade_id', '#uf', '#bairro_id', '#rua_id');
    });
    @if ($errors->any())
        carregarEnderecoErro();
    @endif
    @if (isset($cep) && $cep !== '' && $cep !== null)
        @if ($errors->any())
            var cepEmpresa = false;
        @else
            var undefinedLoadCep = typeof dontLoadEnderecoEmpresa === "undefined";
            var cepEmpresa = (!undefinedLoadCep && !dontLoadEnderecoEmpresa) || undefinedLoadCep;
            @if(str_contains(Request::url(), '/edit') || isset($show))
                cepEmpresa = false;
            @else
                setInputsEnderecoPadrao('#cep', '#cidade_id', '#uf', '#bairro_id', '#rua_id');
                if(cepEmpresa)
                    buscarEnderecoPorCep('geral');
            @endif
        @endif
    @else
        var cepEmpresa = false;
    @endif
</script>
