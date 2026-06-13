<div id="popup_cidade" class="modal fade popupModal dontHideEsc" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document" id="fundo_popup">
        <div class="modal-content">
            <div class="modal-header" id="popup_int">
                <button type="button" class="close btnCloseCidade" id="btnCloseCidade"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabel" style="text-align: center;">ADICIONAR CIDADE</h4>
            </div>
            <div id="popup_int" style="text-align:center;">
                {{ Form::open(['class' => 'form-horizontal', 'files' => true, 'id' => 'fmCidade']) }}
                <div id="contato_empreendimento">
                    <br><br>
                    <div class="form-group" style="margin: 0 auto; max-width: 400px; text-align: center">
                        <input type="hidden" id="uf_cidade" name="uf">
                        <input type="hidden" id="grupo_id_cidade" name="grupo_id">
                        <input type="text" name="descricao" id="descricao_cidade" class="form-control" placeholder="Nome da Cidade" required="required" style="color: #000;">
                        <br />
                        <input type="text" name="cod_ibge" id="cod_ibge" class="form-control number" placeholder="Código IBGE" required="required" style="color: #000;">
                    </div>

                    <div class="form-group" style="max-width: 200px; margin: 0 auto">
                        <button id="saveCidade" type="submit" class="btn btn-md margTop_10 btn-nw-registro">GRAVAR</button>
                    </div>
                    <br>
                    <div id="divErroContato" style="display:none;"></div>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
</div>
<div id="popup_bairro" class="modal fade popupModal dontHideEsc" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document" id="fundo_popup">
        <div class="modal-content">
            <div class="modal-header" id="popup_int">
                <button type="button" class="close btnCloseBairro" id="btnCloseBairro"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabel" style="text-align: center;">ADICIONAR BAIRRO</h4>
            </div>
            <div id="popup_int" style="text-align:center;">
                {{ Form::open(['class' => 'form-horizontal', 'files' => true, 'id' => 'fmBairro']) }}
                <div>
                    <br><br>
                    <div class="form-group" style="margin: 0 auto; max-width: 400px; text-align: center">
                        <input type="hidden" id="cidade_id_bairro" name="cidade_id">
                        <input type="hidden" id="uf_bairro" name="uf">
                        <input type="hidden" id="grupo_id_bairro" name="grupo_id">
                        <input type="text" name="descricao" id="descricao_bairro" class="form-control" placeholder="Nome do Bairro" required="required" style="color: #000;">
                    </div>

                    <div class="form-group" style="max-width: 200px; margin: 0 auto">
                        <button id="saveBairro" type="submit" class="btn btn-md margTop_10 btn-nw-registro">GRAVAR</button>
                    </div>
                    <br>
                    <div id="divErroContato" style="display:none;"></div>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
</div>
<div id="popup_rua" class="modal fade popupModal dontHideEsc" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document" id="fundo_popup">
        <div class="modal-content">
            <div class="modal-header" id="popup_int">
                <button type="button" id="btnCloseRua" class="close btnCloseRua"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel" style="text-align: center;">ADICIONAR RUA</h4>
            </div>
            <div id="popup_int" style="text-align:center;">
                {{ Form::open(['class' => 'form-horizontal', 'files' => true, 'id' => 'fmRua']) }}
                <div>
                    <br><br>
                    <div class="form-group" style="margin: 0 auto; max-width: 400px; text-align: center">
                        <input type="hidden" id="cidade_id_rua" name="cidade_id">
                        <input type="hidden" id="grupo_id_rua" name="grupo_id">
                        <input type="hidden" id="bairro_id_rua" name="bairro_id">
                        <input type="hidden" id="cep_rua" name="cep">
                        <input type="hidden" id="importacaocep_id_rua" name="importacaocep_id">
                        <input type="hidden" id="nfecompl_rua" name="nfecompl">
                        <input type="text" name="descricao" id="descricao_rua" class="form-control" placeholder="Nome da Rua" required="required" style="color: #000;">
                    </div>

                    <div class="form-group" style="max-width: 200px; margin: 0 auto">
                        <button id="enderecoSaveRua" type="submit" class="btn btn-md margTop_10 btn-nw-registro">GRAVAR</button>
                    </div>
                    <br>
                    <div id="divErroContato" style="display:none;"></div>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
</div>
<div id="popup_cep" class="modal fade popupModal dontHideEsc" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" style="width: 80%" role="document" id="fundo_popup">
        <div class="modal-content">
            <div class="modal-header" id="popup_int">
                <button type="button" id="btnCloseCEP" class="close btnCloseCEP"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel" style="text-align: center;">ESCOLHER CEP</h4>
            </div>
            <div id="popup_int" style="text-align:center;">
                <div>
                    <div class="box">
                        <div class="box-body" style="padding-left: 20px;">
                            <table id="tblCEP" class="table table-bordered table-condensed" style="padding:0; margin:0">
                                <thead>
                                    <tr>
                                        <th>Bairro</th>
                                        <th>CEP</th>
                                        <th>Complemento</th>
                                        <th>Logradouro</th>
                                        <th>Cidade</th>
                                        <th>Selecionar</th>
                                    </tr>
                                </thead>
                            </table>
                        </div><!-- /.box-body -->
                    </div><!-- /.box -->
                </div>
            </div>
        </div>
    </div>
</div>
