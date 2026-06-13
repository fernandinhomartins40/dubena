<div class="modal fade" id="modalAtalhos" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
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
                                    <th>Tecla de Atalho</th>
                                    <th>Descrição</th>
                                </tr>
                            </thead>
                            <tbody class="hidden" id="teclasAtalho">
                                <tr>
                                    <td>Ctrl + Enter</td>
                                    <td>Ocultar/exibir campos "Observações" e "Referência"</td>
                                </tr>
                                <tr>
                                    <td>Ctrl + Espaço</td>
                                    <td>Busca de cliente por endereço (se o foco estiver no número ou complemento)</td>
                                </tr>
                                <tr>
                                    <td>Ctrl + Espaço</td>
                                    <td>Busca de endereço pelo CEP (se o foco estiver no campo CEP)</td>
                                </tr>
                                <tr>
                                    <td>Ctrl + q</td>
                                    <td>Fechar sobre tela do cliente</td>
                                </tr>
                                <tr>
                                    <td>Esc</td>
                                    <td>Fechar qualquer sobre tela</td>
                                </tr>
                                <tr>
                                    <td>Espaço</td>
                                    <td>Busca de cliente por endereço (se o foco estiver no botão de busca do complemento)</td>
                                </tr>
                                <tr>
                                    <td>Espaço</td>
                                    <td>Busca de cliente por telefone (se o foco estiver no botão de busca do telefone)</td>
                                </tr>
                                <tr>
                                    <td>Espaço</td>
                                    <td>Busca endereço pelo CEP (se o foco estiver no botão de busca do CEP)</td>
                                </tr>
                                <tr>
                                    <td>F2</td>
                                    <td>Novo/Editar Cliente</td>
                                </tr>
                                <tr>
                                    <td>F3</td>
                                    <td>Gravar Pedido/Cliente</td>
                                </tr>
                                <tr>
                                    <td>F4</td>
                                    <td>Novo Pedido</td>
                                </tr>
                                <tr>
                                    <td>Shift + Alt + Setas (cima e baixo)</td>
                                    <td>Navegação entre sub abas da tela (se houver)</td>
                                </tr>
                                <tr>
                                    <td>Shift + Alt + Setas (esquerda e direita)</td>
                                    <td>Navegação entre abas da tela</td>
                                </tr>
                            </tbody>
                             <tbody id="teclasAtalhoShow" class="hidden">
                                <tr>
                                    <td>Ctrl + Enter</td>
                                    <td>Ocultar/exibir campos "Observações" e "Referência"</td>
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
