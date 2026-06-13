<template>
    <div>
        <div class="">
            <div class="card card-default">
                <div class="card-header">
                    <div class="title-display">
                        <span>Produtos</span>
                        <button class="btn btn-geral btn-sm" @click="actionsProduct('create')">Adicionar Novo</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-scroll">
                        <table class="table table-condensed table-bordered mb-0" v-if="products.length > 0">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Descrição</th>
                                <th>Ativo</th>
                                <th style="width: 150px">Ações</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="product in products" :key="product.id" :id="'productRow' + product.id">
                                <td>{{ product.id }}</td>
                                <td>{{ product.descricao }}</td>
                                <td>{{ product.ativo === "1" ? "Sim" : "Não" }}</td>
                                <td>
                                    <button class="btn btn-xs btn-dark" type="button" @click="edit(product)">Editar <font-awesome-icon icon="edit" /></button>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" data-backdrop="static" data-keyboard="false" tabindex="-1" role="dialog" id="product_modal">
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
                                       v-model="editingProduct.descricao">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group form-check md-2">
                                <input type="checkbox" name="ativo" id="ativo" v-on:change="changeActive" :checked='editingProduct.ativo'/>
                                <label for="ativo" class="form-check-label">Ativo</label>
                            </div>
                        </div>
                        <div class="form-row">
                            <label>Imagem:</label>
                        </div>
                        <div class="form-row justify-content-center">
                            <div class="form-group md-12" style="position: relative">
                                <my-upload field="img" @crop-success="cropSuccess" v-model="show" lang-type="pt-br"
                                           :width="100" :height="100" :params="params" :headers="headers" img-format="png">
                                </my-upload>
                                <img :src="editingProduct.base64Img" height="100" width="100">
                            </div>
                        </div>
                        <div class="form-row justify-content-center">
                            <div class="form-group md-12" style="position: relative">
                                <button @click="toggleShow" class="img-upload btn btn-dark btn-xs">Selecionar</button>
                                <button @click="editingProduct.base64Img = ''" class="img-upload btn btn-danger btn-xs">Remover</button>
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
    import { helpers } from '../../helpers/helpers';
    import { library } from '@fortawesome/fontawesome-svg-core';
    import { faKey, faEdit, faMapMarkerAlt, faQuestion } from '@fortawesome/free-solid-svg-icons';
    import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';


    library.add(faKey);
    library.add(faEdit);
    library.add(faQuestion);
    library.add(faMapMarkerAlt);

    export default {

        props: ["pageTitle", "productsServer"],
        mixins: [helpers],
        name: 'Produtos',
        components: { FontAwesomeIcon },

        data() {
            return {
                editingProduct: {},
                products: [],
                show: false,
                params: {
                    token: '123456798',
                    name: 'img'
                },
                headers: {
                    smail: '*_~'
                },
                imgDataUrl: '',
                model: {
                    ativo: true,
                    id: 0,
                    descricao: "",
                    data: null
                }
            }
        },
        mounted: function ()
        {
            this.initialize();
        },

        methods: {
            toggleShow() {
                this.show = ! this.show;
            },
            cropSuccess(imgDataUrl){
                this.editingProduct.base64Img = imgDataUrl;
            },
            initialize()
            {
                if (typeof this.productsServer === "string") {
                    this.products = JSON.parse(this.productsServer);
                }
                $("#fileChange").on("click",() => $("#img").trigger("click"));

            },
            changeActive()
            {
                this.editingProduct.ativo = ! this.editingProduct.ativo;
            },
            edit(product)
            {
                let obj = {};
                for (let key in product) {
                    obj[key] = product[key];
                }
                obj.ativo = obj.ativo === "1";
                this.editingProduct = obj;

                this.actionsProduct('edit', product.id);
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
                    this.updateProduct().then(this.successHttpFn(callback)).catch(this.errorHttpFn());
                }
            },
            updateProduct()
            {
                let url = this.getUrl("/" + this.editingProduct.id);
                this.startRequest();
                let data = this.getFormParams();
                data.append("_method", "PATCH");
                data.append('action', "saveProduct");
                return this.post(url, data);
            },
            getFormParams() {
                let data = this.editingProduct;
                data.ativo = $("#ativo").is(":checked") ? 1 : 0;
                const formData = new FormData();
                if (this.editingProduct.base64Img) {
                    formData.append('img', this.editingProduct.base64Img);
                }
                for (let key in data) {
                    if (data.hasOwnProperty(key)) {
                        formData.set(key, data[key]);
                    }
                }
                return formData;
            },
            store()
            {
                let url = this.getUrl();
                this.startRequest();
                let data = this.getFormParams();
                data.append('action', "saveProduct");
                return this.post(url, data, {
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'}
                });
            },
            actionsProduct(action)
            {
                this.action = action;
                this.notificationOnExit = true;
                if (action === "create") {
                    this.editingProduct = {ativo: true};
                    $("#header-action").html("Novo Produto");
                    $("#btnSave").text("Salvar");
                } else {
                    $("#header-action").html("Editar Produto");
                    $("#btnSave").text("Salvar");
                }
                $("#product_modal").modal("show");
            }
        }
    }
</script>

<style>
    .img-upload {
        cursor: pointer;
    }
    .vue-image-crop-upload .vicp-wrap {
        left: -50px !important;
    }
</style>