<div id="popup_relatorio" class="modal fade popupModal modal-wide" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document" id="fundo_popup">
        <div class="modal-content">
            <div id="popup_int" style="text-align:center;">
                <button type="button" id="btnCloseFinanceiro" class="close" data-dismiss="modal" aria-label="Close" style="margin-right: 20px;"><span aria-hidden="true">&times;</span></button>
                <div class="form-group crud_space">
                    <div class="col-sm-1 col-md-push-10">
                        <button type="button" id="btnImprimirIframe" class="btn btn-sm btn-nw-buscas" data-toggle="tooltip" data-trigger="hover" data-placement="bottom" title="Imprimir" style="margin-top:5px;margin-left:60px;"><span class="glyphicon glyphicon-print"></span></button>
                    </div>
                </div>
                <iframe sandbox="allow-same-origin allow-scripts allow-popups allow-forms allow-modals" id="iFrameReport" name="iFrameReport" style="border: 0; width:100%; height:700px;margin-top:-20px;"></iframe>
            </div>
        </div>
    </div>
</div>