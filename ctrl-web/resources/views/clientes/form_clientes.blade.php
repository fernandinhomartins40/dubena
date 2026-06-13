<ul class="nav nav-tabs">
    <li class="active"><a href="#tab_1" data-toggle="tab" data-focus="nome">Dados Gerais</a></li>
    <li class=""><a href="#tab_2" data-toggle="tab" data-focus="cep">Endereço</a></li>
    <li class=""><a href="#tab_3" data-toggle="tab" data-focus="telefone">Contatos</a></li>
    <li class=""><a href="#tab_4" data-toggle="tab">Histórico</a></li>
    <li class=""><a href="#tab_5" data-toggle="tab" data-focus="btnAddFollowUp">Interações</a></li>
    <li class=""><a href="#tab_6" data-toggle="tab" id="tabConvenio">Convênio</a></li>
    <li class=""><a href="#tab_7" data-toggle="tab" data-focus="condicaopagamento_id">Preços</a></li>
</ul>
@if(isset($cliente))
    {{ Form::model($cliente, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal fmCadCliente', 'files' => true, 'route' => array('cliente.update', $cliente->id))) }}
    <input type="hidden" name="cliente_id" id="cliente_id" value="{{ $cliente->id }}">
@else
    {{ Form::open(['id'=>'fmCadastro', 'route' => 'cliente.store', 'class' => 'form-horizontal fmCadCliente', 'files' => true]) }}
@endif

@include('clientes.modal.promocoes')
<div class="tab-content">
    <div class="tab-pane active" id="tab_1">
        <!-- form start -->
        <div class="row">

            <div id="tabCadastro" class="col-md-10">
                <div class="box-body">
                    <div class="form-group crud_space">
                        {!! Form::label('tipopessoa_id', 'Tipo de Pessoa:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                        <div class="col-sm-4">
                            {!! Form::select('tipopessoa_id', $tipopessoas, @$tipopessoa, ['class' => 'form-control selectChosen', 'style'=>'border-radius: 5px ! important;', 'onchange'=>'mudarTipoPessoa(function (){mostrarIE()});']) !!}
                        </div>
                    </div>
                    <div class='divTipoPessoa' >
                        <div class="form-group crud_space">
                            {!! Form::label('nome', 'Nome/Razão Social:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                            <div class="col-sm-10">
                                @if (! isset($cliente))
                                    {!! Form::text('nome',null,['class'=>'form-control input-sm', 'autofocus']) !!}
                                @else
                                    {!! Form::text('nome',null,['class'=>'form-control input-sm']) !!}
                                @endif
                            </div>
                        </div>
                        <div class="form-group crud_space" >
                            {!! Form::label('fantasia', 'Fantasia/Apelido:', ['class'=>'col-sm-2 control-label input-sm divPessoaJuridica']) !!}
                            <div class="col-sm-6 divPessoaJuridica">
                                {!! Form::text('fantasia',null,['class'=>'form-control input-sm ']) !!}
                            </div>
                            {!! Form::label('segmento_id', 'Segmento:', ['class'=>'col-sm-1 control-label input-sm divPessoaJuridica']) !!}
                            {!! Form::label('segmento_id', 'Segmento:', ['class'=>'col-sm-2 control-label input-sm divPessoaFisica']) !!}
                            <div class="col-sm-2">
                                {!! Form::select('segmento_id', $segmentos, null, ['class' => 'form-control selectChosen ', 'style'=>'border-radius: 5px ! important;']) !!}
                            </div>
                        </div>
                        <div class="divPessoaFisica">
                            <div class="form-group crud_space" >
                                {!! Form::label('cpf', 'CPF:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                <div class="col-sm-2">
                                    {!! Form::text('cpf',null,['class'=>'form-control input-sm cpf ']) !!}
                                </div>
                                {!! Form::label('rg', 'RG:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                <div class="col-sm-2">
                                    {!! Form::text('rg',null,['class'=>'form-control input-sm rg']) !!}
                                </div>
                                {!! Form::label('sexo', 'Sexo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                <div class="col-sm-2">
                                    {!! Form::select('sexo',["" => "Selecione", "F"=>"Feminino","M"=>"Masculino"], null, ['class' => 'form-control selectChosen', 'id' =>'sexo']) !!}
                                </div>
                            </div>
                        </div>
                        <div class="divPessoaFisica">
                            <div class="form-group crud_space">
                                {!! Form::label('nome_app', 'Nome Aplicativo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                <div class="col-sm-10">
                                    {!! Form::text('nome_app',null,['class'=>'form-control input-sm', 'disabled' => 'true']) !!}
                                </div>
                            </div>
                        </div>

                        <div class="form-group crud_space">
                            <div class='divPessoaFisica'>
                                {!! Form::label('datanascimento', 'Nascimento:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                <div class="col-sm-2">
                                    <div class="input-group generalDatePickerDefaultDateFalse">
                                        {!! Form::text('datanascimento',null,['class'=>'form-control input-sm generalDatePickerDefaultDateFalse', 'id' => 'datanascimento']) !!}
                                        <span class="input-group-addon">
                                            <i class="glyphicon glyphicon-calendar"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-2 divPessoaJuridica">
                            </div>
                            <div class="col-sm-2">
                                <div class="input-group">
                                    <button id='btnAddPromocao' type="button" class="btn btn-nw-buscas btn-xs margTop_5" data-toggle="modal" data-target="#modalClientePromocoes">
                                        PROMOÇÕES
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="form-group crud_space" >
                            <div class="divPessoaJuridica">
                                {!! Form::label('cnpj', 'CNPJ:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                <div class="col-sm-2">
                                    {!! Form::text('cnpj',null,['class'=>'form-control input-sm cnpj ']) !!}
                                </div>
                            </div>
                            {!! Form::label('inscricao_estadual', 'Insc. Est.:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                            <div class="col-sm-2">
                                {!! Form::text('inscricao_estadual',null,['class'=>'form-control input-sm ']) !!}
                            </div>
                            <div class="divPessoaJuridica">
                                {!! Form::label('suframa', 'Suframa:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                <div class="col-sm-2">
                                    {!! Form::text('suframa',null,['class'=>'form-control input-sm number']) !!}
                                </div>
                            </div>
                            {!! Form::label('consisa_id', 'Cód Contábil:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                            <div class="col-sm-1">
                                {!! Form::text('consisa_id',null,['class'=>'form-control input-sm ']) !!}
                            </div>
                        </div>
                    </div>
                    <div class="form-group crud_space">
                        {!! Form::label('observacoes', 'Observações:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                        <div class="col-sm-10">
                            {!! Form::text('observacoes',null,['class'=>'form-control input-sm']) !!}
                        </div>
                        {!! Form::label('cliente', 'Cliente:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                        <div class="col-sm-1 checkbox">
                            {{ Form::checkbox('cliente') }}
                        </div>
                        {!! Form::label('fornecedor', 'Fornecedor:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                        <div class="col-sm-1 checkbox">
                            {{ Form::checkbox('fornecedor') }}
                        </div>
                        {!! Form::label('transportador', 'Transportador:', ['class'=>'col-sm-1 control-label input-sm', 'style' => 'margin-left:-1%']) !!}
                        <div class="col-sm-1 checkbox">
                            {{ Form::checkbox('transportador') }}
                        </div>
                        {!! Form::label('simples', 'Simples:', ['class'=>'col-sm-1 control-label input-sm','style' => 'margin-left:-1%'])  !!}
                        <div class="col-sm-1 checkbox" style='margin-left:-1%'>
                            {{ Form::checkbox('simples') }}
                        </div>
                    </div>
                    <div class="form-group crud_space">
                        {!! Form::label('ativo', 'Ativo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                        <div class="col-sm-1 checkbox">
                            {{ Form::checkbox('ativo') }}
                        </div>
                        {!! Form::label('nfemite', 'Emite NFE:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                        <div class="col-sm-1 checkbox">
                            {{ Form::checkbox('nfemite') }}
                        </div>

                        {!! Form::label('indicador_ie', 'Indic. I.E:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                        <div class="col-sm-3">
                            {!! Form::select('indicador_ie',[""=>"Selecione","1"=>"Contribuinte ICMS","2"=>"Contribuinte Isento", "9"=>"Não Contribuinte"], null, ['class' => 'form-control selectChosen']) !!}
                        </div>
                    </div>
                    <div class="form-group crud_space">
                        {!! Form::label('gasdopovo', 'Gás do Povo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                        <div class="col-sm-1 checkbox">
                            {{ Form::checkbox('gasdopovo') }}
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- /.tab-pane -->
    </div><!-- /.tab-pane -->
    <div class="tab-pane" id="tab_2">
        <div class="row">
            <div id="tabCadastro" class="col-md-10">
                <div class="box-body">
                    @include('general.endereco_form_partial')

                    <div class="form-group crud_space">
                        {!! Form::label('endereco_app', 'Endereço Aplicativo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                        <div class="col-sm-10">
                            {!! Form::text('endereco_app',null,['class'=>'form-control input-sm', 'disabled' => 'true']) !!}
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div><!-- /.tab-pane -->
    <div class="tab-pane" id="tab_3">
        <!-- form start -->

        <div class="row">
            <div id="tabCadastro" class="col-md-10">
                <div class="box-body">
                    <div class="form-group crud_space">
                        {!! Form::label('email', 'E-mail:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                        <div class="col-sm-10">
                            {!! Form::email('email',null,['class'=>'form-control input-sm']) !!}
                        </div>
                    </div>
                    <div class="col-md-8 col-md-offset-3 margTop_10">
                        <div class="col-md-4">
                            {!! Form::select('telefonetipo_id', $telefonetipos, null, ['id'=>'telefonetipo_id', 'class' => 'form-control selectChosen', 'style'=>'padding:0px;max-height:24px;']) !!}
                        </div>
                        {!! Form::label('whatsapp', 'WhatsApp:', ['class'=>'col-sm-1 control-label input-sm', 'style' => 'margin-right: 10px;']) !!}
                        <div class="col-sm-1 checkbox">
                            {{ Form::checkbox('whatsapp') }}
                        </div>
                        <div class="col-sm-3">
                            <input type="text" id="telefone" class="input-sm form-control telefone" value="{{@$telefone}}">
                        </div>
                        <button type="button" id='btnAddFone' disabled="disabled" class="btn btn-xs btn-nw-buscas">Adicionar</button>
                    </div>
                    <div class="col-md-8  col-md-offset-3">
                        {{Form::hidden('telefones',"", ['id'=>'telefones'])}}

                        <table id="tblTelefones" class="table table-bordered table-hover table-condensed">
                            <thead>
                                <tr>
                                    <th>codigo</th>
                                    <th></th>
                                    <th style='width: 20%'>Tipo Telefone</th>
                                    <th>Número</th>
                                    <th style='width: 10%'>WhatsApp</th>
                                    <th style='width: 20%'>Operação</th>
                                </tr>
                            </thead>
                            <tbody id="clientes-list">
                                @if(isset($cliente))
                                    @foreach ($cliente->telefones as $telefone)
                                    <tr id="fone{{$telefone->telefonetipo_id}}">
                                        <td>{{$telefone->id}}</td>
                                        <td>{{$telefone->telefonetipo->id}}</td>
                                        <td>{{$telefone->telefonetipo->descricao}}</td>
                                        <td>{{$telefone->telefone}}</td>
                                        <td>{{$telefone->whatsapp == 1 ? 'Sim' : 'Não' }}</td>
                                        <td>
                                            <button type='button' class='btn btn-nw-geral btn-xs' id='btnEditarTelefone'>Editar</button>
                                            <button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverTelefone'>Remover</button>
                                        </td>
                                    </tr>
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div><!-- /.box -->
                </div>
            </div>
        </div>
    </div><!-- /.tab-pane -->
    <div class="tab-pane" id="tab_4">
        <div class="row">
            <div id="tabCadastro" class="col-md-12">
                <div class="box-body">
                    <div class="col-md-10 col-md-offset-1">
                        <table cells class="table table-condensed table-striped table-scroll-horizontal" name="new" data-toolbar="#modal_toolbar_new" data-toggle="table" data-show-footer="false" data-height="250">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Pedido</th>
                                    <th>Forma Pagto</th>
                                    <th>Produto</th>
                                    <th>Status</th>
                                    <th>Quantidade</th>
                                    <th>Valor</th>
                                </tr>
                            </thead>
                            <tbody id="contatos-list">
                                @if(isset($historico))
                                @foreach ($historico as $hist)
                                <tr class="{{$hist->status_tipo}}">
                                    <td>{{$hist->data}}</td>
                                    <td>{{$hist->pedido_id}}</td>
                                    <td>{{$hist->condicao}}</td>
                                    <td>{{$hist->produto}}</td>
                                    <td>{{$hist->status}}</td>
                                    <td>{{$hist->quantidade}}</td>
                                    <td>{{$hist->valor}}</td>
                                </tr>
                                @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div><!-- /.box -->
                </div>
                <br />
                <div class="form-group crud_space">
                    <div class="col-md-10 col-md-offset-2">
                        <div class="col-sm-6">
                            <span class="info-box-icon concluido" style="width:15px;height:15px;"></span>
                            <span class="info-box-text fontSize_11" style="padding-left: 5px !important"> Concluído</span>
                        </div>
                        <div class="col-sm-6">
                            <span class="info-box-icon cancelado" style="width:15px;height:15px;"></span>
                            <span class="info-box-text fontSize_11" style="padding-left: 5px !important"> Cancelado</span>
                        </div>
                    </div>
                </div>
                <br />
            </div>
        </div>
    </div><!-- /.tab-pane -->
    <div class="tab-pane" id="tab_5">
        <!-- form start -->
        <div class="row">
            <div id="tabCadastro7" class="col-md-10">
                <div class="box-body">
                    <div class="col-md-11 col-md-offset-2">
                        {{Form::hidden('contatos',"", ['id'=>'contatos'])}}
                        <button type="button" id='btnAddFollowUp' class="btn btn-xs btn-nw-buscas" onclick="createContato();">Adicionar Interação</button>
                        <table id="tblContatos" class="table table-bordered table-hover table-condensed">
                            <thead>
                                <tr>
                                    <th>codigo</th>
                                    <th>Data</th>
                                    <th>tipooid</th>
                                    <th>Tipo</th>
                                    <th>situacaoid</th>
                                    <th>Situação</th>
                                    <th>Descrição</th>
                                    <th>Ação</th>
                                    <th>Operação</th>
                                </tr>
                            </thead>
                            <tbody id="contatos-list">
                                @if(isset($cliente))
                                @foreach ($cliente->contatos as $contato)
                                <tr id="resp{{$contato->id}}">
                                    <td>{{$contato->id}}</td>
                                    <td>{{Carbon\Carbon::parse($contato->datahora)->format('d/m/Y')}}</td>
                                    <td>{{$contato->contatotipo->id}}</td>
                                    <td>{{$contato->contatotipo->descricao}}</td>
                                    <td>{{$contato->contatosituacao->id}}</td>
                                    <td>{{$contato->contatosituacao->descricao}}</td>
                                    <td>{{$contato->descricao}}</td>
                                    <td>{{$contato->acao}}</td>
                                    <td>
                                        <button type='button' class='btn btn-nw-geral btn-xs btnEditarContato' id='btnEditarContato'>Editar</button>
                                        <button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverContato'>Remover</button>
                                    </td>
                                </tr>
                                @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div><!-- /.box -->
                </div>
            </div>
        </div><!-- /.tab-pane -->
    </div><!-- /.tab-pane -->

    <div class="tab-pane" id="tab_6">
        <div class="row">
            <div class="col-md-12">

                @include('clientes.convenio')

            </div>
        </div>

    </div>

    <div class="tab-pane" id="tab_7">
        <div class="row">
            <div class="col-md-12">
                @include('clientes.precos')
            </div>
        </div>
    </div>

    <div class="box-footer">
        <div class="col-md-4">
            @if(isset($cliente) && ($comodato))
                <a type="button" class="btn btn-nw-registro" id="btnGravarComSenha">Gravar</a>
            @else
                {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
            @endif
            <a type="button" href="{{url('cliente')}}" class="btn btn-nw-geral" id="btnVoltar">Voltar</a>
        </div>
    </div>
</div>
{!! Form::hidden('promocoes',null,['class'=>'form-control','id' => 'promocoes']) !!}
{!! Form::hidden('alltables', null, ['class' => 'form-control', 'id' => 'alltables']) !!}
{!! Form::close() !!}

@include('general.popupbairrocidade_form_partial')
@include('clientes.modal.modal_dadosimpressaoetiquetas')
@include('general.modal_senhamestra')
<script>
    var errorsAny = false;
    @if ($errors -> any())
        errorsAny = true;
    @endif
</script>
<!-- page script -->
<script src="{{URL::to('js/clienteCustom.js')}}"></script>
@include('clientes.js')
