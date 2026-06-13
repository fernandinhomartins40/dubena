<div class="modal fade" id="modalChamadasEspera" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabelCadastro">Chamadas em Espera</h4>
            </div>
            <div class="modal-body">
                <button type="button" style="margin-right: 10px" class="btn btn-success btn-xs fright" class="close" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Atualizar Chamadas" id="btnUpdateCalls"><span class="fa fa-refresh"></span></button>
                <div class="box-body margTop_20">
                    <table id="tblFonesEspera" class="table table-bordered table-striped table-hover table-condensed">
                        <thead>
                            <tr>
                                <th>Cód</th>
                                <th>Cód Empresa</th>
                                <th>Empresa</th>
                                <th>Telefone</th>
                                <th>Operações</th>
                            </tr>
                        </thead>
                        <tbody>

                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-nw-geral" data-dismiss="modal">Cancelar</button>
                <div id="saveError" class="alert alert-danger alert-dismissable" style="display:none;">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fa fa-ban"></i>Erro</h5>
                    <div id="save_result"></div>
                </div>
            </div>
        </div>
    </div>
</div>
