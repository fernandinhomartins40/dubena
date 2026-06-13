<div class="modal fade" id="modalHelp" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog" style="width: 90%">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabelCadastro">Lista de Teclas de Atalho</h4>
            </div>
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-group crud_space col-sm-12">
                        <table class="table table-bordered table-hover table-condensed" style="padding:0px; margin:0px">
                            <thead>
                                <tr>
                                    <th>Funcionalidades</th>
                                    <th>Descrição</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Edição Simples</td>
                                    <td>Clique simples na coluna do código do pedido</td>
                                </tr>
                                <tr>
                                    <td>Edição Múltipla</td>
                                    <td>
                                        Selecionar uma empresa e marcar os checks dos pedidos e clicar no botão "Editar Selecionados"
                                        <br />
                                        Obs.: A edição de vários pedidos é feita somente com os pedidos que estão aparecendo na página atual.
                                    </td>
                                </tr>
                                <tr>
                                    <td>Visualizaçao do Pedido</td>
                                    <td>Duplo clique ou "ctrl" + duplo clique em qualquer coluna exceto código</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCloseModalClientes" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
                <div id="saveError" class="alert alert-danger alert-dismissable" style="display:none;">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fa fa-ban"></i>Erro</h5>
                    <div id="save_result"></div>
                </div>
            </div>
        </div>
    </div>
</div>
