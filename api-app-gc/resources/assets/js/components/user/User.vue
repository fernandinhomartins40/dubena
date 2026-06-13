<template>
    <div>
        <div class="">
            <div class="card card-default">
                <div class="card-header">
                    <div class="title-display">
                        <span>Usuários</span>
                        <button class="btn btn-geral btn-sm" @click="actionsUser('create')">Adicionar Novo</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-scroll">
                        <table class="table table-condensed table-bordered mb-0" v-if="users.length > 0">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Administrador</th>
                                <th>Ativo</th>
                                <th style="width: 150px">Ações</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="user in users" :key="user.id" :id="'userRow' + user.id">
                                <td>{{ user.id }}</td>
                                <td>{{ user.name }}</td>
                                <td>{{ user.email }}</td>
                                <td>{{ user.admin ? "Sim" : "Não" }}</td>
                                <td>{{ user.ativo ? "Sim" : "Não" }}</td>
                                <td>
                                    <button class="btn btn-xs btn-dark" type="button" @click="edit(user)">Editar <font-awesome-icon icon="edit" /></button>
                                    <button class="btn btn-xs btn-info" type="button" @click="actionsUser('token', user.id)">
                                        Token <font-awesome-icon icon="key" />
                                    </button>
                                    <button class="btn btn-xs btn-danger" type="button" @click="editPassword(user)">Senha</button>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" data-backdrop="static" data-keyboard="false" tabindex="-1" role="dialog" id="user_modal">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title" id="header-action"></h4>
                        <button type="button" class="close close-modal" aria-label="Close"><span
                                aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body" v-if='action === "create" || action === "edit"'>
                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <label for="name">Nome:</label>
                                <input type="text" autofocus name="name" id="name" placeholder="Nome" class="form-control"
                                       v-model="editingUser.name">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <label for="email">E-mail:</label>
                                <input name="email" id="email" class="form-control"
                                       placeholder="Email" type="email" v-model="editingUser.email"/>
                            </div>
                        </div>
                        <div class="form-row" v-if="this.action === 'create'">
                            <div class="form-group col-md-12">
                                <label for="password">Senha:</label>
                                <input type="password" autofocus name="password" v-model="password" id="password" placeholder="Senha" class="form-control">
                            </div>
                        </div>
                        <div class="form-row" v-if="this.action === 'create'">
                            <div class="form-group col-md-12">
                                <label for="password_repeat">Repita a Senha:</label>
                                <input type="password" autofocus name="password_repeat" v-model="password_repeat" id="password_repeat" placeholder="Senha" class="form-control">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group form-check md-2">
                                <input type="checkbox" name="admin" id="admin" :checked='editingUser.admin'/>
                                <label for="admin" class="form-check-label">Administrador</label>
                            </div>
                            <div class="form-group form-check md-2">
                                <input type="checkbox" name="ativo" id="ativo" :checked='editingUser.ativo'/>
                                <label for="ativo" class="form-check-label">Ativo</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-body" v-if='action === "editPassword"'>
                        <div class="form-group">
                            <label for="old_password">Senha Antiga:</label>
                            <input type="password" autofocus name="old_password" id="old_password" placeholder="Senha Antiga" class="form-control"
                                   v-model="editingPass.old_password">
                        </div>
                        <div class="form-group">
                            <label for="new_password">Nova Senha:</label>
                            <input type="password" name="new_password" id="new_password" placeholder="Nova Senha" class="form-control"
                                   v-model="editingPass.new_password">
                        </div>
                        <div class="form-group">
                            <label for="old_password">Confirmação da Senha:</label>
                            <input type="password" name="repeat_password" id="repeat_password" placeholder="Confirmação da Senha:" class="form-control"
                                   v-model="editingPass.repeat_password">
                        </div>
                    </div>
                    <div class="modal-body" v-if='action === "token"'>
                        <div class="form-group">
                            <label for="token_password">Senha:</label>
                            <input type="password" autofocus name="token_password" id="token_password" placeholder="Senha" class="form-control">
                        </div>
                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" name="new_token" id="new_token" @click="confirmNewToken"/>
                                <label for="new_token" class="form-check-label">Gerar Novo Token</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger close-modal">Cancelar</button>
                        <button type="button" class="btn btn-dark" @click="save" id="btnSave"></button>
                    </div>
                </div><!-- /.modal-content -->
            </div><!-- /.modal-dialog -->
        </div><!-- /.modal -->
    </div>
</template>

<script>
    import { helpers } from '../../helpers/helpers';
    import { library } from '@fortawesome/fontawesome-svg-core';
    import { faKey, faEdit, faMapMarkerAlt, faQuestion } from '@fortawesome/free-solid-svg-icons';
    import { TheMask } from 'vue-the-mask';
    import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';

    library.add(faKey);
    library.add(faEdit);
    library.add(faQuestion);
    library.add(faMapMarkerAlt);

    export default {

        props: ["usersModel", "pageTitle", "ufProp"] ,
        mixins: [helpers],
        name: 'User',
        components: { FontAwesomeIcon, TheMask },

        data() {
            return {
                editingUser: {},
                action: "",
                submitted: false,
                users: [],
                uf: [],
                password: null,
                password_repeat: null,
                userModel: {
                    id: "",
                    uf: "",
                    name: "",
                    email: "",
                    admin: "",
                    fantasia: "",
                    ativo: "",
                    erpempresa_id: "",
                    serviceuser_id: "",
                    horafechamento: "",
                    horaabertura: "",
                    permiteagendamento: false,
                    erpurl: "",
                    erp_authorization: "",
                },
                editingPassModel: {id: "", old_password: "", new_password: "", repeat_password: ""},
                editingPass: {},
            }
        },
        mounted: function ()
        {
            this.initialize();
        },

        methods: {
            getToken() {
                let $pass = $("#token_password");
                if (! $pass.val()) {
                    this.simpleAlert("Digite a senha para confirmar suas credenciais").then(() => {
                        setTimeout(function () {
                            $pass.focus();
                        }, 300);
                    });
                    return;
                }
                this.startRequest();
                let url = this.getUrl("/getToken");
                this.post(url, this.getTokenAttributes()).then(this.successHttpFn((result) => {
                    let token = result.data.authorization;
                    this.successAlert(
                        "<label>Token de acesso:</label>" +
                        "<textarea type='text' class='form-control-plaintext' readonly>" +
                        token + "</textarea>"
                    );
                })).catch(this.errorHttpFn());
            },
            getTokenAttributes() {
                return {
                    id: this.user_id,
                    createNew: $("#new_token").is(":checked") ? "1" : "0",
                    password: $("#token_password").val(),
                    action: "getToken"
                };
            },
            confirmNewToken() {
                let $tokenCheck = $("#new_token");
                if ($tokenCheck.is(":checked")) {
                    let msg = "Se marcar este flag, será gerado um novo Token, " +
                        "isso fará com que o serviço de integração não consiga se comunicar com " +
                        "a API até que o Token seja atualizado na conta do serviço de integração, deseja continuar?";
                    this.confirmAlert(msg, (res) => {
                        if (! res.value) {
                            $tokenCheck.prop("checked", false);
                        }
                    });
                }
            },
            initialize()
            {
                if ( typeof this.usersModel === "string" ) {
                    this.users = JSON.parse(this.usersModel);
                    this.uf = JSON.parse(this.ufProp);
                }

                $(".modal").on("shown.bs.modal", function () {
                    $(this).find('[autofocus]').focus();
                });

            },
            edit(user)
            {
                let obj = {};
                for (let key in user) {
                    obj[key] = user[key];
                }
                this.editingUser = obj;

                this.actionsUser('edit', user.id);
            },
            editPassword(user)
            {
                let obj = this.editingPassModel;
                obj.id = user.id;
                this.editingPass = obj;
                this.actionsUser('editPassword');
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
                if (this.action === "create") {
                    this.store();
                } else if (this.action === "editPassword") {
                    this.updatePassword();
                } else if (this.action === "token") {
                    this.getToken();
                } else {
                    this.updateUser();
                }
            },
            updateUser()
            {
                let url = this.getUrl("/" + this.editingUser.id);
                this.startRequest();
                let data = this.getFormParams();
                data.action = "saveUser";
                this.patch(url, data).then(this.successHttpFn()).catch(this.errorHttpFn());
            },
            getFormParams() {
                let data = this.editingUser;
                data.admin = $("#admin").is(":checked") ? 1 : 0;
                data.ativo = $("#ativo").is(":checked") ? 1 : 0;
                data.permiteagendamento = $("#permiteagendamento").is(":checked") ? 1 : 0;
                return data;
            },
            startRequest() {
                this.$Progress.start();
                this.submitted = true;
            },
            updatePassword()
            {
                let url = this.getUrl("/password/" + this.editingPass.id);
                if (this.validatePasswords()) {
                    this.startRequest();
                    let data = this.editingPass;
                    data.action = "password";
                    this.post(url, data).then(this.successHttpFn()).catch(this.errorHttpFn());
                }
            },
            store()
            {
                if (this.password_repeat !== this.password) {
                    this.infoAlert("As senhas não correspondem.");
                    return;
                }
                if (! this.password) {
                    this.infoAlert("Informe a senha!");
                    return;
                }
                let url = this.getUrl();
                this.startRequest();
                let data = this.getFormParams();
                data.action = "saveUser";
                data.password = this.password;
                this.post(url, data).then(this.successHttpFn()).catch(this.errorHttpFn());
            },
            validatePasswords()
            {
                let pass = true;
                let msg = "";
                if (! this.editingPass.old_password) {
                    msg = "O campo Senha Antiga é obrigatório";
                    pass = false;
                } else if (! this.editingPass.new_password) {
                    msg = "O campo é obrigatório";
                    pass = false;
                } else if (! this.editingPass.repeat_password) {
                    msg = "O campo Confirmação da Senha é obrigatório";
                    pass = false;
                } else if (this.editingPass.repeat_password !== this.editingPass.new_password) {
                    msg = "As senhas não conferem";
                    pass = false;
                }
                if (! pass) {
                    this.infoAlert(msg);
                }
                return pass;
            },
            actionsUser(action, user_id)
            {
                this.user_id = user_id || "";
                this.action = action;
                this.password = null;
                this.password_repeat = null;
                this.notificationOnExit = true;
                if (action === "create") {
                    this.editingUser = {};
                    $("#header-action").html("Novo Usuário");
                    $("#btnSave").text("Salvar");
                } else if (action === "token") {
                    $("#header-action").html("Token de Acesso");
                    this.notificationOnExit = false;
                    $("#btnSave").text("Gerar");
                } else {
                    $("#header-action").html("Editar Usuário");
                    $("#btnSave").text("Salvar");
                }
                $("#user_modal").modal("show");
            }
        }
    }
</script>