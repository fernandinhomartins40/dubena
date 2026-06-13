<template>
    <div>
        <div class="grid-body-component">
            <div class="card card-default">
                <div class="card-header">
                    <div class="title-display">
                        <span>Categorias de Produtos</span>

                        <button class="btn-registro btn btn-sm" @click="actionsCategory('create')">Adicionar Novo</button>
                    </div>
                </div>
                <div class="card-body">
                    <span v-if="categories.length === 0">Nenhuma categoria cadastrada</span>

                    <table class="table table-borderless mb-0" v-if="categories.length > 0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Descrição</th>
                                <th>Ativo</th>
                                <th>Operações</th>
                            </tr>
                        </thead>
                        <tbody v-for="category in this.categories" :key="category.id">
                            <tr>
                                <td>{{ category.id }}</td>
                                <td>{{ category.descricao }}</td>
                                <td>{{ category.ativo === 1 ? "Sim" : "Não" }}</td>
                                <td>
                                    <button class="btn btn-xs btn-geral" type="button" @click="actionsCategory('edit')"></button>
                                    <button class="btn btn-xs btn-buscas" type="button" @click="actionsCategory('delete')"></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" tabindex="-1" role="dialog" id="category_modal">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Nova Categoria</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                                aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="name">Descrição:</label>
                            <input type="text" name="name" id="name" placeholder="Descrição" class="form-control"
                                    v-model="categoryModel.name">
                        </div>
                        <div class="form-group hidden">
                            <label for="id">id:</label>
                            <input name="id" id="empresa_id" cols="30" rows="5" class="form-control"
                                    placeholder="Código Empresa" v-model="categoryModel.id"/>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-geral" data-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-registro">Salvar</button>
                    </div>
                </div><!-- /.modal-content -->
            </div><!-- /.modal-dialog -->
        </div><!-- /.modal -->
    </div>
</template>

<script>
export default {

    props: ['categories'],

    data() {
        return {
            categoryModel: {
                id: '',
                descricao: '',
                ativo: '',
            },
            action: "",
        }
    },

    methods: {
        actionsCategory(action) {
            this.action = action;
            $("#category_modal").modal("show");
        }
    }
}
</script>
