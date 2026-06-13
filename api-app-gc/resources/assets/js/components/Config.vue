<template>
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3><span class="glyphicon glyphicon-dashboard"></span> Assignment Dashboard </h3> <br>
                        <button @click="showSaveModal('create')" class="btn btn-success " style="padding:5px">
                            Novo Registro
                        </button>
                    </div>
                    <div class="panel-body">
                        <table class="table table-bordered table-striped table-responsive">
                            <thead>
                                <tr>
                                    <th>
                                        #
                                    </th>
                                    <th>
                                        Nome
                                    </th>
                                    <th>
                                        Código Empresa
                                    </th>
                                    <th>
                                        Endereço de IP
                                    </th>
                                    <th>
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody v-if="configs.length > 0">
                                <tr v-for="(config, index) in configs" :key="config.id">
                                    <td>{{ index + 1 }}</td>
                                    <td>
                                        {{ config.name }}
                                    </td>
                                    <td>
                                        {{ config.empresa_id }}
                                    </td>
                                    <td>
                                        {{ config.enderecoip }}
                                    </td>
                                    <td>
                                        <button @click="initUpdate(index)" class="btn btn-success btn-xs" style="padding:8px"><span class="glyphicon glyphicon-edit"></span></button>
                                        <button @click="deleteConfig(index)" class="btn btn-danger btn-xs" style="padding:8px"><span class="glyphicon glyphicon-trash"></span></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" tabindex="-1" role="dialog" id="save_modal">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Novo Usuário</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                                aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="name">Nome:</label>
                            <input type="text" name="name" id="name" placeholder="Nome" class="form-control"
                                   v-model="config.name">
                        </div>
                        <div class="form-group">
                            <label for="empresa_id">Código Empresa:</label>
                            <input name="empresa_id" id="empresa_id" class="form-control"
                                   placeholder="Código Empresa" v-model="config.empresa_id"/>
                        </div>
                        <div class="form-group">
                            <label for="enderecoip">Endereço de IP:</label>
                            <input name="enderecoip" id="enderecoip" class="form-control"
                                   placeholder="Endereço de IP" v-model="config.enderecoip"/>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                        <button type="button" @click="save" class="btn btn-primary">Salvar</button>
                    </div>
                </div><!-- /.modal-content -->
            </div><!-- /.modal-dialog -->
        </div><!-- /.modal -->
    </div>
</template>

<script>
    import { helpers } from '../helpers/helpers';

    export default {
        props: ['pageTitle'],
        mixins: [helpers],
        data() {
            return {
                config: {
                    enderecoip: '',
                    empresa_id: '',
                    name: ''
                },
                configs: [],
                update_config: {},
                operation: "",
                dataInput: {}
            }
        },
        mounted()
        {
            this.readConfigs();
            $(document).on("shown.bs.modal", "#save_modal", function () {
                setTimeout(function () {
                    $("#name").focus();
                }, 100);
            });
        },
        methods: {
            deleteConfig(index)
            {
                let that = this;
                this.delete(that.url('/' + this.configs[index].id), function () {
                    that.configs.splice(index, 1);
                });
            },
            showSaveModal(operation)
            {
                this.resetForm();
                if (!operation) {
                    operation = 'update';
                    this.fillFormData();
                }
                this.operation = operation;
                $("#save_modal").modal("show");
            },
            url(extra)
            {
                return this.getUrlResources() + (extra ? extra : '');
            },
            createConfig()
            {
                let url = this.url('');
                this.post(url, this.dataInput, response => {
                    this.reset();
                    this.configs.push(response.data.config);
                    this.$swal({
                        html: 'Operação realizada com sucesso!',
                        type: 'success'
                    });
                    $("#save_modal").modal("hide");
                });
            },
            reset()
            {
                this.readConfigs();
            },
            readConfigs()
            {
                let that = this;
                this.get(that.url(), function (response) {
                    if (response.status === 200) {
                        that.configs = response.data.configs;
                    } else {
                        let msg = response;
                        if (typeof response.msg === "string") {
                            msg = response.msg;
                        }
                        Vue.swal({html: "Erro ao buscar informações: " + msg, type: 'error'});
                    }
                });
            },
            initUpdate(index)
            {
                this.update_config = this.configs[index];
                this.showSaveModal();
            },
            updateConfig()
            {
                let that = this;
                this.patch(this.url("/" + this.update_config.id), this.dataInput, function () {
                    $("#save_modal").modal("hide");
                    that.readConfigs();
                });
            },
            save()
            {
                this.dataInput = {
                    enderecoip: $("#enderecoip").val(),
                    empresa_id: $("#empresa_id").val(),
                    name: $("#name").val()
                };
                if (this.validate()) {
                    if (this.operation === "create") {
                        this.createConfig();
                    } else {
                        this.updateConfig();
                    }
                }
            },
            validate()
            {
                let msgError = "";
                let validation;
                if (!this.dataInput.name) {
                    validation = false;
                    msgError += 'Informe o Nome <br>';
                }
                if (!this.dataInput.empresa_id) {
                    validation = false;
                    msgError += 'Informe o Código da Empresa <br>';
                }
                if (!this.dataInput.enderecoip) {
                    validation = false;
                    msgError += 'Informe o Endereço de IP <br>';
                }
                if (!validation) {
                    Vue.swal({title: "Ops!",html: msgError});
                }
                return validation;
            },
            adjustFocus($el)
            {
                setTimeout(function () {
                    $el.focus();
                }, 250);
            },
            resetForm()
            {
                $("#enderecoip").val("");
                $("#empresa_id").val("");
                $("#name").val("");
            },
            fillFormData()
            {
                $("#enderecoip").val(this.update_config.enderecoip);
                $("#empresa_id").val(this.update_config.empresa_id);
                $("#name").val(this.update_config.name);
            }
        }
    }
</script>