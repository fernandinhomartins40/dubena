<template>

    <div class="grid-body-component">
        <div class="card card-default">
            <div class="card-header">
                <div class="title-display">
                    <span> {{ pageTitle }} </span>
                </div>
            </div>
            <div class="card-body">
                <div class="col-sm-12">
                    <label for="keygooglemaps">Key API Google Maps: </label>
                    <div class="col-sm-6">
                        <input type="text" class="input-sm form-control"
                               id="keygooglemaps" name="keygooglemaps"
                                placeholder="Key API Google Maps" v-model="generalConfigModel.keygooglemaps">
                    </div>
                </div>
            </div>
        </div>
        <br />
        <div class="footer">
            <button class="btn btn-dark" @click="save">Salvar</button>
        </div>
    </div>
</template>

<script>
    import { helpers } from '../helpers/helpers';

    export default {
        props: ['pageTitle', 'data-model'],
        mixins: [helpers],
        name: 'GeneralConfig',
        data() {
            return {
                generalConfigModel: {
                    keygooglemaps: ''
                },
                action: "",
                submitted: false,
            }
        },
        created: function ()
        {
            if ( typeof this.dataModel === "string" ) {
                let data = JSON.parse(this.dataModel);
                this.generalConfigModel.keygooglemaps = data.keygooglemaps;
            }
        },
        methods: {
            save()
            {
                if (this.submitted) {
                    return;
                }
                this.startRequest();
                let url = this.getUrl();
                this.post(url, this.generalConfigModel).then(this.successHttpFn((data) => {
                    this.successAlert("Dados atualizados com sucesso!");
                })).catch(this.errorHttpFn());
            }
        }
    }
</script>

<style scoped>

</style>