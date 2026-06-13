<div class="modal fade" id="modal-upload-file" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close btnCloseModalUpload" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabel">Carregar Arquivo</h4>
            </div>
            {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmUpload', 'files' => 'true']) }}
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-group crud_space col-sm-12hidden" id="uploadInfo" >
                    </div>
                    <br />
                    <div class="form-group crud_space col-sm-12">
                        <label class="mousehover-pointer">
                            <span class="btn btn-sm btn-nw-registro fa fa-upload fa-lg">
                                <input type="file" id="file-upload" name="file-upload" class="btn-file" style="display: none;">
                            </span>
                            &nbsp;&nbsp;&nbsp;&nbsp;<span id='upload-filename'>Nenhum arquivo selecionado..</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCloseModalUpload" class="btn btn-nw-geral btnCloseModalUpload" data-dismiss="modal">Fechar</button>
                {!! Form::submit('Finalizar', ['class' => 'btn btn-nw-registro', 'id' => 'btnSave']) !!}
                <div id="saveErrorDel" class="alert alert-danger alert-dismissable" style="display:none;">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <span class="glyphicon glyphicon-remove"></span>
                    <div id="save_result"></div>
                </div>
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).on('change', ':file', function() {
        var input = $(this);
        numFiles = input.get(0).files ? input.get(0).files.length : 1,
        label = input.val().replace(/\\/g, '/').replace(/.*\//, '');
        $.each(validFormatUpload, function (i, el) {
            if(label.toUpperCase().substr(label.length - el.length, label.length) != el.toUpperCase()) {
                bootbox.alert("Tipo de arquivo inválido");
                input.val('');
                input.parents('label').children('#upload-filename').text("Formato de arquivo inválido.");
                return;
            } else {
                input.parents('label').children('#upload-filename').text(label);
            } 
        });
    });
    $("#btnSave").on('click', function () {
        if(typeof callbackUpload == 'function')
            callbackUpload(); 
    });
</script>