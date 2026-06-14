
@extends('monitora.layouts.mainmenu')

@section('content')

<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <!--			<div class="box-header">
                                            <h3 class="box-title">Usuário</h3>
                                        </div> /.box-header -->
            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Usuário</h3>
                    </div>
                    <div class="nav-tabs-custom">

                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Dados do Usuário</a></li>
                            <li class=""><a href="#tab_2" data-toggle="tab">Permissões de acesso</a></li>
                        </ul>
                        @if(isset($user))
                        {{ Form::model($user, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('user.update', $user->id))) }}
                        @else
                        {{ Form::open(['id'=>'fmCadastro', 'route' => 'user.store', 'class' => 'form-horizontal', 'files' => true]) }}
                        @endif
                        <div class="tab-content">

                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->

                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {!! Form::label('name', 'Nome:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-3">
                                                    {!! Form::text('name',null,['class'=>'form-control input-sm']) !!}
                                                </div>
                                                <label for="ativo" class="col-sm-1 control-label input-sm required">Ativo:</label>
                                                <div class="col-sm-1 checkbox">
                                                    {{ Form::checkbox('ativo', 1, null, ['id'=>'ativo']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('empresa_id', 'Empresa Padrão:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-5">
                                                    {!! Form::select('empresa_id', $empresas, null, ['class' => 'form-control selectChosen']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('email', 'Usuário:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-5">
                                                    {!! Form::text('email',null,['class'=>'form-control input-sm']) !!}
                                                    {!! Form::hidden('access_token',null,['class'=>'form-control input-sm', 'id'=>'access_token']) !!}
                                                    {!! Form::hidden('client_secret',null,['class'=>'form-control input-sm', 'id'=>'client_secret']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('password', 'Senha:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-5">
                                                    <!--{!! Form::password('password',null,['class'=>'form-control input-sm']) !!}-->
                                                    <input name='password' id='password' type='password' class='form-control input-sm'>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('password_confirmation', 'Confirme a senha:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-5">
                                                    <!--{!! Form::password('password_confirmation',null,['class'=>'form-control input-sm']) !!}-->
                                                    <input name='password_confirmation' id='password_confirmation' type='password' class='form-control input-sm'>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('client_id', 'Cliente Id.:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::text('client_id',null,['class'=>'form-control input-sm']) !!}
                                                </div>
                                                @if(\Auth::user()->support)
                                                    {!! Form::label('support', 'Usuário Suporte:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                    <div class="col-sm-2 checkbox">
                                                        {{ Form::checkbox('support', null) }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div><!-- /.tab-pane -->
                                </div><!-- /.tab-pane -->
                            </div><!-- /.tab-content -->
                            <div class="tab-pane" id="tab_2">
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                <input type="hidden" id="inputMenus" name="inputMenus" value=""/>
                                                {!! Form::label('empresas_list', 'Empresas:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-9" style="height:auto;">
                                                    {!! Form::select('empresas_list[]',$empresas, $empresaslnk,['class'=>'form-control input-sm', 'multiple']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('menus_list', 'Permissões:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                                <div class="col-sm-9">
                                                    <div id="jstree">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div><!-- /.tab-pane -->
                                </div><!-- /.tab-pane -->
                            </div><!-- /.tab-content -->
                        </div><!-- /.col -->
                    </div>
                    <div class="box-footer">
                        <div class="col-md-4">
                            {!! Form::button('Gravar', ['class' => 'btn btn-nw-registro', 'type'=>'button', 'onclick'=>'enviar();']) !!}
                            <a href="{{url('user')}}" class="btn btn-nw-geral">Voltar</a>
                        </div>
                    </div>
                    {!! Form::close() !!}
                    @if(isset($user))
                    @endif

                </div>
        </div>
    </div>
</div>
<meta name="csrf-token" content="{{ csrf_token() }}" />

@include('monitora.users.user_js')
<!-- page script -->
<script type="text/javascript">
    jQuery(document).ready(function () {
        var options = {
            sortable: true
        };

        $(".delete").on("submit", function () {
            return confirm("Quer remover o registro atual?");
        });

        $("select#menus_list").treeMultiselect();

        $('#jstree').jstree({
            'core': {
                'data': $menudata
            },
            "plugins": ["checkbox"],
            "checkbox": {
                "three_state": false
            }
        }).on("select_node.jstree", function (e, data) {
            var selectedNode;
            if (data.selected.length == 1) {
                lastSelected = data.selected.slice(0);
                selectedNode = data.selected[0];
            } else {
// Get the difference between lastselection and current selection
                selectedNode = $.grep(data.selected, function (x) {
                    return $.inArray(x, lastSelected) < 0
                });
                lastSelected = data.selected.slice(0); // trick to copy array
            }
// Select the parent
            var parent = data.instance.get_node(selectedNode).parent;
            data.instance.select_node(parent);
        }).on("deselect_node.jstree", function (e, data) {
// Get the difference between lastselection and current selection
            var deselectedNode = $.grep(lastSelected, function (x) {
                return $.inArray(x, data.selected) < 0
            })
                    , children = data.instance.get_children_dom(deselectedNode);
            if (children.length) {
                children.each(function (i, a) {
                    data.instance.deselect_node(a);
                    lastSelected = data.selected.slice(0);
                });
            } else {
                lastSelected = data.selected.slice(0);
            }
        }).on('loaded.jstree', function () {
            $('#jstree').jstree('open_all');
        });
    });
    
    function enviar(){
        $('#inputMenus').val(JSON.stringify($('#jstree').jstree(true).get_selected()));
        document.getElementById("fmCadastro").submit();
    }
</script>
@endsection
