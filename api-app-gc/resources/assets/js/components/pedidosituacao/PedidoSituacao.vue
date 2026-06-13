<template>
    <div>
        <div class="">
            <div class="card card-default">
                <div class="card-header">
                    <div class="title-display">
                        <span></span>
                        <button class="btn btn-geral btn-sm" @click="actionsPedidoSituacao('create')">Adicionar Novo</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-scroll">
                        <table class="table table-condensed table-bordered mb-0" v-if="pedidosituacaos.length > 0">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Descrição</th>
                                <th>Descrição Informal</th>
                                <th>Tipo</th>
                                <th>Ativo</th>
                                <th style="width: 150px">Ações</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="pedidosituacao in pedidosituacaos" :key="pedidosituacao.id" :id="'pedidosituacaoRow' + pedidosituacao.id">
                                <td>{{ pedidosituacao.id }}</td>
                                <td>{{ pedidosituacao.descricao }}</td>
                                <td>{{ pedidosituacao.info }}</td>
                                <td v-if="pedidosituacao.pendente">Pendente</td>
                                <td v-if="pedidosituacao.ementrega">Motorista Saiu Para Entrega</td>
                                <td v-if="pedidosituacao.entregue">Entregue</td>
                                <td v-if="pedidosituacao.cancelado">Cancelado</td>
                                <td v-if="! pedidosituacao.pendente && ! pedidosituacao.ementrega && ! pedidosituacao.entregue && ! pedidosituacao.cancelado ">
                                    Status não definido
                                </td>
                                <td>{{ pedidosituacao.ativo ? "Sim" : "Não" }}</td>
                                <td>
                                    <button class="btn btn-xs btn-dark" type="button" @click="edit(pedidosituacao)">Editar <font-awesome-icon icon="edit" /></button>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" data-backdrop="static" data-keyboard="false" tabindex="-1" role="dialog" id="pedidosituacao_modal">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title" id="header-action"></h4>
                        <button type="button" class="close close-modal" aria-label="Close"><span
                                    aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <label for="descricao">Descrição:</label>
                                <input type="text" autofocus name="descricao" id="descricao" placeholder="Descrição" class="form-control"
                                       v-model="editingPedidoSituacao.descricao">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <label for="info">Descrição Informal:</label>
                                <input type="text" name="info" id="info" placeholder="Descrição Informal" class="form-control"
                                       v-model="editingPedidoSituacao.info">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <label for="tipo">Tipo:</label>
                                <select name="tipo" id="tipo" v-model="selectedStatusDef" @change="changeDef" class="form-control">
                                    <option disabled value="">Selecione</option>
                                    <option v-for="(value, key) in statusDef" v-bind:value="key" >{{ value }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group form-check md-2">
                                <input type="checkbox" name="ativo" id="ativo" v-on:change="changeActive" :checked='editingPedidoSituacao.ativo'/>
                                <label for="ativo" class="form-check-label">Ativo</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger close-modal">Cancelar</button>
                        <button type="button" class="btn btn-dark" @click="save" id="btnSave">Salvar</button>
                    </div>
                </div><!-- /.modal-content -->
            </div><!-- /.modal-dialog -->
        </div><!-- /.modal -->
    </div>
</template>

<script>
    import { helpers } from '../../helpers/helpers';
    import { library } from '@fortawesome/fontawesome-svg-core';
    import { faEdit } from '@fortawesome/free-solid-svg-icons';
    import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';

    library.add(faEdit);

    export default {

        props: ["statusServer", "pageTitle", "statusDefServer"],
        mixins: [helpers],
        name: 'PedidoSituacao',
        components: { FontAwesomeIcon },

        data() {
            return {
                editingPedidoSituacao: {},
                action: "",
                submitted: false,
                selectedStatusDef: false,
                pedidosituacaos: [],
                statusDef: {},
                pedidosituacaoModel: {},
            }
        },
        mounted: function ()
        {
            this.initialize();
        },

        methods: {
            initialize()
            {
                if ( typeof this.statusServer === "string" ) {
                    this.pedidosituacaos = JSON.parse(this.statusServer);
                }
                if ( typeof this.statusDefServer === "string" ) {
                    this.statusDef = JSON.parse(this.statusDefServer);
                }

            },
            changeActive()
            {
                this.editingPedidoSituacao.ativo = ! this.editingPedidoSituacao.ativo;
            },
            changeDef() {
                this.editingPedidoSituacao.tipo = this.selectedStatusDef;
            },
            edit(pedidosituacao)
            {
                let obj = {};
                for (let key in pedidosituacao) {
                    obj[key] = pedidosituacao[key];
                }
                this.editingPedidoSituacao = obj;

                this.actionsPedidoSituacao('edit', pedidosituacao.id);
            },
            errorHttpFn() {
                return (error) => {
                    this.submitted = false;
                    this.treatErrorsHttp(error);
                };
            },
            successHttpFn(successCallback) {
                return (response) => {
                    if (response.status === 200) {
                        let data = response.data;
                        if (typeof data.status === "string" && data.status === "OK") {
                            if (typeof successCallback === "function") {
                                successCallback(data);
                            } else {
                                this.editingPedidoSituacao = data.data;
                                this.successAlert("Operação realizada com sucesso!", () => {
                                    location.reload();
                                });
                            }
                            this.$Progress.finish();
                        } else if (typeof data.status === "string" && data.status === "NOK") {
                            this.treatErrorsHttp(data.msg);
                            this.$Progress.fail();
                        } else {
                            this.treatErrorsHttp(data);
                            this.$Progress.fail();
                        }
                    } else {
                        this.treatErrorsHttp(response);
                    }
                    this.submitted = false;
                }
            },
            save()
            {
                if (this.submitted) {
                    return;
                }
                if (this.selectedStatusDef === 0) {
                    this.editingPedidoSituacao.pendente = 1;
                    this.editingPedidoSituacao.cancelado = 0;
                    this.editingPedidoSituacao.ementrega = 0;
                    this.editingPedidoSituacao.entregue = 0;
                } else if (this.selectedStatusDef === 1) {
                    this.editingPedidoSituacao.ementrega = 1;
                    this.editingPedidoSituacao.pendente = 0;
                    this.editingPedidoSituacao.entregue = 0;
                    this.editingPedidoSituacao.cancelado = 0;
                } else if (this.selectedStatusDef === 2) {
                    this.editingPedidoSituacao.ementrega = 0;
                    this.editingPedidoSituacao.entregue = 1;
                    this.editingPedidoSituacao.pendente = 0;
                    this.editingPedidoSituacao.cancelado = 0;
                } else {
                    this.editingPedidoSituacao.ementrega = 0;
                    this.editingPedidoSituacao.cancelado = 1;
                    this.editingPedidoSituacao.pendente = 0;
                    this.editingPedidoSituacao.entregue = 0;
                }
                if (this.action === "create") {
                    this.storePedidoSituacao();
                } else {
                    this.updatePedidoSituacao();
                }
            },
            updatePedidoSituacao()
            {
                let url = this.getUrl() + "/" + this.editingPedidoSituacao.id;
                this.startRequest();
                let data = this.editingPedidoSituacao;

                this.patch(url, data).then(this.successHttpFn()).catch(this.errorHttpFn());
            },
            startRequest() {
                this.$Progress.start();
                this.submitted = true;
            },
            storePedidoSituacao()
            {
                let url = this.getUrl();
                this.startRequest();
                let data = this.editingPedidoSituacao;

                let obj = {};
                for (let key in data) {
                    obj[key] = data[key];
                }
                obj.selectedStatusDef = this.selectedStatusDef;
                this.post(url, obj).then(this.successHttpFn()).catch(this.errorHttpFn());
            },
            actionsPedidoSituacao(action, pedidosituacao_id)
            {
                this.pedidosituacao_id = pedidosituacao_id || "";
                this.action = action;
                this.notificationOnExit = true;
                if (action === "create") {
                    this.editingPedidoSituacao = {};
                    this.selectedStatusDef = 0;
                    $("#header-action").html("Novo");
                } else {
                    if (this.editingPedidoSituacao.pendente) {
                        this.selectedStatusDef = 0;
                    } else if (this.editingPedidoSituacao.ementrega) {
                        this.selectedStatusDef = 1;
                    } else if (this.editingPedidoSituacao.entregue) {
                        this.selectedStatusDef = 2;
                    } else {
                        this.selectedStatusDef = 3;
                    }
                    $("#header-action").html("Editar");
                }
                $("#pedidosituacao_modal").modal("show");
            }
        }
    }
</script>