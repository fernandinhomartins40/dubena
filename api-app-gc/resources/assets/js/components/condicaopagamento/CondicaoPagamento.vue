<template>
    <div>
        <div class="">
            <div class="card card-default">
                <div class="card-header">
                    <div class="title-display">
                        <span>Condições de Pagamento</span>
                        <button class="btn btn-geral btn-sm" @click="actionsPayway('create')">Adicionar Nova</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-scroll">
                        <table class="table table-condensed table-bordered mb-0" v-if="payways.length > 0">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Descrição</th>
                                <th>Ativo</th>
                                <th style="width: 150px">Ações</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="payway in payways" :key="payway.id" :id="'paywayRow' + payway.id">
                                <td>{{ payway.id }}</td>
                                <td>{{ payway.descricao }}</td>
                                <td>{{ payway.ativo === "1" ? "Sim" : "Não" }}</td>
                                <td>
                                    <button class="btn btn-xs btn-dark" type="button" @click="edit(payway)">Editar <font-awesome-icon icon="edit" /></button>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" data-backdrop="static" data-keyboard="false" tabindex="-1" role="dialog" id="payway_modal">
            <div class="modal-dialog modal-md" role="document">
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
                                       v-model="editingPayway.descricao">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <label for="tipo">Tipo:</label>
                                <select name="tipo" id="tipo" v-model="selectedType" @change="changeType" class="form-control">
                                    <option disabled value="">Selecione</option>
                                    <option v-for="(value, key) in types" v-bind:value="key" >{{ value }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group form-check md-2">
                                <input type="checkbox" name="ativo" id="ativo" v-on:change="changeActive" :checked='editingPayway.ativo'/>
                                <label for="ativo" class="form-check-label">Ativo</label>
                            </div>
                        </div>
                    </div><!-- /.modal-content -->
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger close-modal">Cancelar</button>
                        <button type="button" class="btn btn-dark" @click="save" id="btnSave"></button>
                    </div>
                </div><!-- /.modal-dialog -->
            </div><!-- /.modal -->
        </div>
    </div>
</template>

<script>
    import '../../helpers/collection'
    import { helpers } from '../../helpers/helpers';
    import { library } from '@fortawesome/fontawesome-svg-core';
    import { faKey, faEdit, faMapMarkerAlt, faQuestion } from '@fortawesome/free-solid-svg-icons';
    import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';


    library.add(faKey);
    library.add(faEdit);
    library.add(faQuestion);
    library.add(faMapMarkerAlt);

    export default {

        props: ["pageTitle", "paywaysServer", "typesServer"],
        mixins: [helpers],
        name: 'payway',
        components: { FontAwesomeIcon },

        data() {
            return {
                editingPayway: {},
                payways: [],
                types: [],
                selectedType: null
            }
        },
        mounted: function ()
        {
            this.initialize();
        },

        methods: {
            changeType() {
                this.editingPayway.tipo = this.selectedType;
            },
            toggleShow() {
                this.show = ! this.show;
            },
            initialize()
            {
                if (typeof this.paywaysServer === "string") {
                    this.payways = JSON.parse(this.paywaysServer);
                    this.types = JSON.parse(this.typesServer);
                    if (this.types) {
                        this.types = this.types.sort();
                    }
                }

            },
            changeActive()
            {
                this.editingPayway.ativo = ! this.editingPayway.ativo;
            },
            edit(payway)
            {
                let obj = {};
                for (let key in payway) {
                    obj[key] = payway[key];
                }
                obj.ativo = obj.ativo === "1";
                this.editingPayway = obj;
                this.selectedType = obj.tipo;
                this.actionsPayway('edit', payway.id);
            },
            save()
            {
                if (this.submitted) {
                    return;
                }
                let callback = () => {
                    this.successAlert("Operação realizada com sucesso!", () => {
                        location.reload();
                    });
                };
                if (this.action === "create") {
                    this.store().then(this.successHttpFn(callback)).catch(this.errorHttpFn());
                } else {
                    this.updatePayway().then(this.successHttpFn(callback)).catch(this.errorHttpFn());
                }
            },
            updatePayway()
            {
                let url = this.getUrl("/" + this.editingPayway.id);
                this.startRequest();
                let data = this.getFormParams();
                data.action = "savePayway";
                return this.patch(url, data);
            },
            getFormParams() {
                let data = this.editingPayway;
                data.ativo = $("#ativo").is(":checked") ? 1 : 0;
                data.tipo = this.selectedType;
                return data;
            },
            store()
            {
                let url = this.getUrl();
                this.startRequest();
                let data = this.getFormParams();
                data.action = "savePayway";
                return this.post(url, data, {
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'}
                });
            },
            actionsPayway(action)
            {
                this.action = action;
                this.notificationOnExit = true;
                if (action === "create") {
                    this.selectedType = null;
                    this.editingPayway = {ativo: true, tipo: null};
                    $("#header-action").html("Nova Condição de Pagamento");
                    $("#btnSave").text("Salvar");
                } else {
                    $("#header-action").html("Editar Condição de Pagamento");
                    $("#btnSave").text("Salvar");
                }
                $("#payway_modal").modal("show");
            }
        }
    }
</script>