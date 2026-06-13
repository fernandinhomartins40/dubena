<!-- Modal -->
<div id="modal_permissoes" class="modal fade popupModal modal-wide" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document" style="width:70%">
        <div class="modal-content">
            {{ Form::open(['id'=>'gerarOcorrencia','class' => 'form-horizontal']) }}
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button>
                    <h4 class="modal-title">Permissões para o Usuário</h4>
                </div>
                <div class="modal-body col-md-12">
                    <div class="form-group crud_space">
                        <div class="panel-heading">
                            <h1 class="panel-title">Permissões ao usuário para a empresa</h1>
                        </div>
                    </div>
                    <div class="form-group crud_space">
                        {{ Form::label('visualizar', 'Empresa:', ['class'=>'col-md-1 control-label input-sm']) }}
                        <div class="col-md-4">
                            {{ Form::text('empresa_desc',null,['id'=>'empresa_desc','class'=>'form-control input-sm','readonly']) }}
                            {{ Form::hidden('emp_id',null,['id'=>'emp_id','class'=>'form-control input-sm']) }}
                        </div>
                    </div>
                    <div class="form-group crud_space">
                        <div id="checkbox">
                            {{ Form::label('visualizar', 'Visualizar:', ['class'=>'col-md-1 control-label input-sm']) }}
                            <div class="col-md-1 checkbox">
                                {{ Form::checkbox('visualizar',0) }}
                            </div>
                            {{ Form::label('criar', 'Criar:', ['class'=>'col-md-1 control-label input-sm']) }}
                            <div class="col-md-1 checkbox">
                                {{ Form::checkbox('criar',0) }}
                            </div>
                            {{ Form::label('editar', 'Editar:', ['class'=>'col-md-1 control-label input-sm']) }}
                            <div class="col-md-1 checkbox">
                                {{ Form::checkbox('editar',0) }}
                            </div>
                            </div>
                            {{ Form::label('baixar', 'Baixar:', ['class'=>'col-md-1 control-label input-sm']) }}
                            <div class="col-md-1 checkbox">
                                {{ Form::checkbox('baixar',0) }}
                            </div>
                            {{ Form::label('alerta', 'Alerta:', ['class'=>'col-md-1 control-label input-sm']) }}
                            <div class="col-md-1 checkbox">
                                {{ Form::checkbox('alerta',0) }}
                            </div>
                            {{ Form::label('deletar', 'Deletar:', ['class'=>'col-md-1 control-label input-sm']) }}
                            <div class="col-md-1 checkbox">
                                {{ Form::checkbox('deletar',0) }}
                        </div>
                    </div>
                    <div class="form-group crud_space">
                        <div class="col-md-8 col-md-push-4">
                            <div class="col-md-3">
                                <button id="btnUnCheckAll" type="button" class="btn btn-xs btn-nw-geral"><i class="fa fa-window-close-o" aria-hidden="true" 
                                        data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Desmarcar Todos"></i></button>
                                <button id="btnCheckAll" type="button" class="btn btn-xs btn-excel"><i class="fa fa-check-circle-o" aria-hidden="true" 
                                        data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Marcar Todos"></i></button>
                                <button id="btnAddMenu" type="button" class="btn btn-xs btn-nw-buscas"><i class="fa fa-plus"
                                        data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Adicionar Permissão"></i></button>
                            </div>
                            <div class="col-md-3">
                                <button id="btnRemoveMenu" type="button" class="btn btn-xs btn-nw-registro"><i class="fa fa-remove"
                                        data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Remover Permissão"></i></button>
                                <button id="btnCheckAllGiven" type="button" class="btn btn-xs btn-excel"><i class="fa fa-check-circle-o" aria-hidden="true"
                                        data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Marcar Todos"></i></button>
                                <button id="btnUnCheckAllGiven" type="button" class="btn btn-xs btn-nw-geral"><i class="fa fa-window-close-o" aria-hidden="true"
                                        data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Desmarcar Todos"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group crud_space">
                        <div class="col-md-5">
                            <table id="tblPermissions" class="table table-bordered table-striped table-hover table-condensed no-select" style="padding-left:20px;">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Parent</th>
                                        <th>Menu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if(isset($menus) && count($menus) > 0)
                                        @foreach($menus as $menu)
                                            <tr id="permission_{{$menu->id}}">
                                                <td>{{$menu->id}}</td>
                                                <td>{{$menu->parent_id}}</td>
                                                <td>{{$menu->titulo}}</td>
                                            </tr>
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-7">
                            <table id="tblPermissionsGiven" class="table table-bordered table-striped table-hover table-condensed no-select">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Parent</th>
                                        <th>empresa_id</th>
                                        <th>Menu</th>
                                        <th>V</th>
                                        <th>C</th>
                                        <th>E</th>
                                        <th>B</th>
                                        <th>A</th>
                                        <th>D</th>
                                    </tr>
                                </thead>
                                <tbody>

                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <div class="modal-footer">
                <button id="btnSalvarPermissoes" type="button" class="btn btn-nw-registro">OK</button>
                <button id="btnDismiss" type="button" class="btn btn-nw-geral" data-dismiss="modal">Voltar</button>
            </div>
        </div>
        {{Form::close()}}
    </div>
</div>