<div class="modal fade" id="registro_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-md" role="document" style="width: 65%;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title">Registro 1010: Obrigatoriedade de registros do bloco 1</h4>
            </div>
            <div class="modal-body col-md-12">
                <div class="slim-div">
                    {{ Form::open(['id'=>'fmRegistro', 'class' => 'form-horizontal']) }}
                    <!-- Registro 1100 - Inicio  -->
                    <div class="form-group crud_space">
                        {{ Form::label('reg1100', 'Reg 1100 - Ocorreu averbação (conclusão) de exportação no período:', ['class'=>'left-marg control-label input-sm']) }}
                    </div>
                    <div class="form-group crud_space">
                        <div class="col-sm-4">
                            <div class="col-sm-10 ">
                                {{ Form::label('sim', 'Sim', ['class'=>'col-sm-3 control-label input-sm']) }}
                                <div class="col-sm-1 checkbox">
                                    {{ Form::radio('ind_exp', 'S', false) }}
                                </div>
                                {{ Form::label('nao', 'Não', ['class'=>'col-sm-3 control-label input-sm']) }}
                                <div class="col-sm-1 checkbox">
                                    {{ Form::radio('ind_exp', 'N', false) }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Registro 1100 - Fim  -->
                    <!-- Registro 1200 - Inicio  -->
                    <div class="form-group crud_space">
                        {{ Form::label('reg1200', 'Reg 1200 – Existem informações acerca de créditos de ICMS a 
                            serem controlados, definidos pela Sefaz:', 
                        ['class'=>'left-marg control-label input-sm']) }}
                    </div>
                    <div class="form-group crud_space">
                        <div class="col-sm-4">
                            <div class="col-sm-10">
                                {{ Form::label('sim', 'Sim', ['class'=>'col-sm-3 control-label input-sm']) }}
                                <div class="col-sm-1 checkbox">
                                    {{ Form::radio('ind_ccrf', 'S', false) }}
                                </div>
                                {{ Form::label('nao', 'Não', ['class'=>'col-sm-3 control-label input-sm']) }}
                                <div class="col-sm-1 checkbox">
                                    {{ Form::radio('ind_ccrf', 'N', false) }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Registro 1200 - Fim  -->
                    <!-- Registro 1300 - Inicio  -->
                    <div class="form-group crud_space">
                        {{ Form::label('reg1300', 'Reg 1300 – É comércio varejista de combustíveis com movimentação e/ou
                            estoque no período:', 
                        ['class'=>'left-marg control-label input-sm']) }}
                    </div>
                    <div class="form-group crud_space">
                        <div class="col-sm-4">
                            <div class="col-sm-10">
                                {{ Form::label('sim', 'Sim', ['class'=>'col-sm-3 control-label input-sm']) }}
                                <div class="col-sm-1 checkbox">
                                    {{ Form::radio('ind_comb', 'S', false) }}
                                </div>
                                {{ Form::label('nao', 'Não', ['class'=>'col-sm-3 control-label input-sm']) }}
                                <div class="col-sm-1 checkbox">
                                    {{ Form::radio('ind_comb', 'N', false) }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Registro 1300 - Fim  -->
                    <!-- Registro 1390 - Inicio  -->
                    <div class="form-group crud_space">
                        {{ Form::label('reg1390', 'Reg 1390 – Usinas de açúcar e/álcool – O estabelecimento é
                            produtor de açúcar e/ou álcool carburante com movimentação e/ou estoque no período:', 
                        ['class'=>'left-marg control-label input-sm']) }}
                    </div>
                    <div class="form-group crud_space">
                        <div class="col-sm-4">
                            <div class="col-sm-10">
                                {{ Form::label('sim', 'Sim', ['class'=>'col-sm-3 control-label input-sm']) }}
                                <div class="col-sm-1 checkbox">
                                    {{ Form::radio('ind_usina', 'S', false) }}
                                </div>
                                {{ Form::label('nao', 'Não', ['class'=>'col-sm-3 control-label input-sm']) }}
                                <div class="col-sm-1 checkbox">
                                    {{ Form::radio('ind_usina', 'N', false) }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Registro 1390 - Fim  -->
                    <!-- Registro 1400 - Inicio  -->
                    <div class="form-group crud_space">
                        {{ Form::label('reg1400', 'Reg 1400 – Sendo o registro obrigatório em sua Unidade de
                            Federação, existem informações a serem prestadas neste registro:', 
                        ['class'=>'left-marg control-label input-sm']) }}
                    </div>
                    <div class="form-group crud_space">
                        <div class="col-sm-4">
                            <div class="col-sm-10">
                                {{ Form::label('sim', 'Sim', ['class'=>'col-sm-3 control-label input-sm']) }}
                                <div class="col-sm-1 checkbox">
                                    {{ Form::radio('ind_va', 'S', false) }}
                                </div>
                                {{ Form::label('nao', 'Não', ['class'=>'col-sm-3 control-label input-sm']) }}
                                <div class="col-sm-1 checkbox">
                                    {{ Form::radio('ind_va', 'N', false) }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Registro 1400 - Fim  -->
                    <!-- Registro 1500 - Inicio  -->
                    <div class="form-group crud_space">
                        {{ Form::label('reg1500', 'Reg 1500 – A empresa é distribuidora de energia e ocorreu
                            fornecimento de energia elétrica para consumidores de outra UF:', 
                        ['class'=>'left-marg control-label input-sm']) }}
                    </div>
                    <div class="form-group crud_space">
                        <div class="col-sm-4">
                            <div class="col-sm-10">
                                {{ Form::label('sim', 'Sim', ['class'=>'col-sm-3 control-label input-sm']) }}
                                <div class="col-sm-1 checkbox">
                                    {{ Form::radio('ind_ee', 'S', false) }}
                                </div>
                                {{ Form::label('nao', 'Não', ['class'=>'col-sm-3 control-label input-sm']) }}
                                <div class="col-sm-1 checkbox">
                                    {{ Form::radio('ind_ee', 'N', false) }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Registro 1500 - Fim  -->
                    <!-- Registro 1600 - Inicio  -->
                    <div class="form-group crud_space">
                        {{ Form::label('reg1600', 'Reg 1600 –  Realizou vendas com Cartão de Crédito ou de débito:', 
                        ['class'=>'left-marg control-label input-sm']) }}
                    </div>
                    <div class="form-group crud_space">
                        <div class="col-sm-4">
                            <div class="col-sm-10">
                                {{ Form::label('sim', 'Sim', ['class'=>'col-sm-3 control-label input-sm']) }}
                                <div class="col-sm-1 checkbox">
                                    {{ Form::radio('ind_cart', 'S', false) }}
                                </div>
                                {{ Form::label('nao', 'Não', ['class'=>'col-sm-3 control-label input-sm']) }}
                                <div class="col-sm-1 checkbox">
                                    {{ Form::radio('ind_cart', 'N', false) }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Registro 1600 - Fim  -->
                    <!-- Registro 1700 - Inicio  -->
                    <div class="form-group crud_space">
                        {{ Form::label('reg1700', 'Reg 1700 –  Foram emitidos documentos fiscais em papel no
                            período em unidade da federação que exija o controle de
                            utilização de documentos fiscais:', 
                        ['class'=>'left-marg control-label input-sm']) }}
                    </div>
                    <div class="form-group crud_space">
                        <div class="col-sm-4">
                            <div class="col-sm-10">
                                {{ Form::label('sim', 'Sim', ['class'=>'col-sm-3 control-label input-sm']) }}
                                <div class="col-sm-1 checkbox">
                                    {{ Form::radio('ind_form', 'S', false) }}
                                </div>
                                {{ Form::label('nao', 'Não', ['class'=>'col-sm-3 control-label input-sm']) }}
                                <div class="col-sm-1 checkbox">
                                    {{ Form::radio('ind_form', 'N', false) }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Registro 1700 - Fim  -->
                    <!-- Registro 1800 - Inicio  -->
                    <div class="form-group crud_space">
                        {{ Form::label('reg1800', 'Reg 1800 –  A empresa prestou serviços de transporte aéreo de
                            cargas e de passageiros:', 
                        ['class'=>'left-marg control-label input-sm']) }}
                    </div>
                    <div class="form-group crud_space">
                        <div class="col-sm-4">
                            <div class="col-sm-10">
                                {{ Form::label('sim', 'Sim', ['class'=>'col-sm-3 control-label input-sm']) }}
                                <div class="col-sm-1 checkbox">
                                    {{ Form::radio('ind_aer', 'S', false) }}
                                </div>
                                {{ Form::label('nao', 'Não', ['class'=>'col-sm-3 control-label input-sm']) }}
                                <div class="col-sm-1 checkbox">
                                    {{ Form::radio('ind_aer', 'N', false) }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Registro 1800 - Fim  -->
                </div>
            </div>
            <div class="modal-footer">
                <button id="btnConfirmar" type="button" class="btn btn-nw-registro">Confirmar</button>
                <button id="btnvoltarmod" type="button" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
<style>
    .left-marg {
        margin-left: 12px;
    }
</style>