 @extends('layouts.mainmenu') @section('content')
<style>
    table .collapse.in {
        display: table-row;
    }
</style>
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Usuário</h3>
                    </div>
                    <div class="nav-tabs-custom">

                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Dados do Usuário</a></li>
                            <li class=""><a href="#tab_2" data-toggle="tab">Permissões de acesso</a></li>
                            @if(isset($show) && isset($oauthClient))
                                <li class=""><a href="#tab_3" data-toggle="tab">Dados para Call Center</a></li>
                            @endif
                        </ul>
                        @if(isset($user))
                            {{ Form::model($user, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files'=> true, 'route' => array('user.update', $user->id))) }}
                        @else
                            {{ Form::open(['id'=>'fmCadastro','route' => 'user.store', 'class' => 'form-horizontal', 'files' => true]) }}
                         @endif
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {{ Form::label('name', 'Nome:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::text('name',null,['class'=>'form-control input-sm']) }}
                                                </div>
                                                <label for="ativo" class="col-sm-1 control-label input-sm required">Ativo:</label>
                                                <div class="col-sm-1 checkbox">
                                                    {{ Form::checkbox('ativo', 1, null, ['id'=>'ativo']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('colaborador_id', 'Colaborador:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-5">
                                                    {{ Form::select('colaborador_id', $colaboradors, null, ['class' => 'form-control selectChosen']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('email', 'Usuário:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-5">
                                                    {{ Form::text('email',null,['class'=>'form-control input-sm']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('password', 'Senha:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-5">
                                                    <input name='password' id='password' type='password' class='form-control input-sm'>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('password_confirmation', 'Confirme a senha:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-5">
                                                    <input name='password_confirmation' id='password_confirmation' type='password' class='form-control input-sm'>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('impressoratermica', 'Impressora padrão para pedido:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-5">
                                                    {{ Form::text('impressoratermica',null,['class'=>'form-control input-sm']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('usarastreamento', 'Usa Rastreamento de Veículos:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-1 checkbox">
                                                    {{ Form::checkbox('usarastreamento') }}
                                                </div>
                                                {{ Form::label('support', 'Usuário Suporte:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-1 checkbox">
                                                    {{ Form::checkbox('support', 1, null, ['id'=>'support']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('identificadorchamada', 'Identificador de Chamada:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-1 checkbox">
                                                    {{ Form::checkbox('identificadorchamada') }}
                                                </div>
                                                {{ Form::label('usaandroid', 'Usa Android:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-1 checkbox">
                                                    {{ Form::checkbox('usaandroid') }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane" id="tab_2">
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {{ Form::label('tipo_id', 'Tipo de Usuário:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-4">
                                                    {{ Form::select('tipo_id', $tipo, null, ['class' => 'form-control selectChosen']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                <input type="hidden" id="inputMenus" name="inputMenus" value="" /> {{ Form::label('grupos', 'Grupo:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-4">
                                                    {{ Form::select('grupos',$grupos, @$grupo_user,['id'=>'grupos','class'=>'selectChosen form-control input-sm']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('empresa_id', 'Empresa Padrão:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-4">
                                                    {{ Form::select('empresa_id', [], @$empresa_user, ['class' => 'form-control selectChosen']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('empresas', 'Empresa:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-4">
                                                    {{ Form::select('empresas',[], null,['id'=>'empresas','class'=>'selectChosen form-control input-sm']) }}
                                                </div>
                                                <div class="col-sm-1">
                                                    <button id="addEmpresa" class="btn btn-xs btn-nw-buscas" type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom"
                                                        title="Adicionar Empresa">Adicionar</button>
                                                </div>
                                                <div class="hidden" id="empresasconteudo"></div>
                                            </div>
                                            <div class="form-group crud_space">
                                                <div class="col-md-9 col-md-push-3">
                                                    <div class="col-sm-10">
                                                        {{ Form::hidden('financeiros',$financeiros,['id'=>'financeiros']) }}
                                                        {{ Form::hidden('alertas',$alertas,['id'=>'alertas']) }}
                                                        {{ Form::hidden('empresas_list',null,['id'=>'empresas_list']) }}
                                                        {{ Form::hidden('menus_permitidos',@$menus_permitidos,['id'=>'menus_permitidos']) }}
                                                        <table id="tblEmpresas" class="table table-bordered table-striped table-hover table-condensed no-select">
                                                            <thead>
                                                                <th>ID</th>
                                                                <th>Empresa</th>
                                                                <th>Operações</th>
                                                            </thead>
                                                            <tbody>
                                                                @if(isset($empresas_user))
                                                                    @foreach($empresas_user as $empresa)
                                                                        <tr>
                                                                            <td>{{$empresa->id}}</td>
                                                                            <td>{{$empresa->nome_informal}}</td>
                                                                            <td>
                                                                                <a class="btn btn-xs btn-nw-registro" id="btnRemover" data-toggle="tooltip" data-trigger="hover" data-placement="bottom" title="Remover Empresa">Remover</a>&nbsp;&nbsp;
                                                                                <a class="btn btn-xs btn-nw-geral" id="addPermissoes" data-toggle="tooltip" data-trigger="hover" data-placement="bottom" title="Adicionar Permissões">Permissões</a>
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                @endif
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @if(isset($show) && isset($oauthClient))
                            <div class="tab-pane" id="tab_3">
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {{ Form::label('client_id', 'Id:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-1">
                                                    {{ Form::text('client_id', @$oauthClient->id,['class'=>'form-control input-sm', 'id' => 'client_id']) }}
                                                </div>
                                                {{ Form::label('secret', 'Secret:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-5">
                                                    <div class="input-group">
                                                        {{ Form::text('secret', @$oauthClient->secret,['class'=>'form-control input-sm', 'id' => 'secret']) }}
                                                        <a href="#" data-clipboard-target="#secret" class="input-group-addon copyClipboard" id="spanCopy" style="font-size: 13px; padding-top: 0px; padding-bottom: 0px;">
                                                            <i class="glyphicon glyphicon-copy"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('name_oauth', 'Usuário:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::text('name_oauth', @$oauthClient->name,['class'=>'form-control input-sm', 'id' => 'name_oauth']) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                        <!-- /.col -->
                    </div>
                    <div class="box-footer">
                        <div class="col-md-4">
                            {{ Form::submit('Gravar', ['id'=>'btnSubmit','class' => 'btn btn-nw-registro']) }}
                            <a href="{{url('user')}}" class="btn btn-nw-geral">Voltar</a>
                        </div>
                    </div>
                    {{Form::close()}}
                </div>
            </ul>
        </div>
    </div>
</div>
@include('users.user_js')
@include('users.partials.modal_permissoes')
<script type="text/javascript">
    $(document).ready(function(){
        @if(isset($user->empresa_id))
            empresa_usuario = {{$user->empresa_id}};
        @endif
    });
</script>
<script type="text/javascript" src="{{URL::to('js/user.js')}}"></script>
<script type="text/javascript">
@if(isset($show) || isset($edit))
    $(document).ready(function(){
        setTimeout(function(){
            mostrarEditarMenus();
        });
    });
@endif
</script>
@endsection
