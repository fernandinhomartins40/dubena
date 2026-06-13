<template>
    <div class="card card-default">
        <div class="card-header">
            <div class="title-display">
                <span>{{this.pageTitle}}</span>
            </div>
        </div>
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link first" href="#general" role="tab" data-toggle="tab">Gerais</a>
            </li>
        </ul>
        <div class="card-body col-md-12">
            <div class="tab-content">
                <div id="general" role="tabpanel" class="tab-pane fade in active">
                    <div class="form-group">
                        <div class="form-row justify-content-center">
                            <div class="form-group col-md-2">
                                <label>Data Inicial:</label>
                                <input type="date" class="input-sm form-control"
                                       id="datestart" name="datestart"
                                       placeholder="Data Inicial" v-model="filter.dateStart">
                            </div>
                            <div class="form-group col-md-2">
                                <label>Data Final:</label>
                                <input type="date" class="input-sm form-control"
                                       id="dateend" name="dateend"
                                       placeholder="Data Final" v-model="filter.dateEnd">
                            </div>
                            <div class="form-group col-md-2">
                                <button class="btn btn-dark" id="btnSave" @click="save">Filtar</button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <table style="font-size: 12.8px !important" class="table table-condensed table-sm table-bordered table-stripped">
                            <tr>
                                <th>Tipo</th>
                                <th>Data</th>
                                <th>Mensagem</th>
                                <th>Parâmetros</th>
                                <th>URI | Metodo</th>
                            </tr>
                            <tr v-for="result in results" style="padding: 5px !important;">
                                <th>{{ result.type }}</th>
                                <th>{{ result.datetime }}</th>
                                <th @click=putContentLog(result.message) :title="result.message">{{ typeof result.message !== "string" ? result.message : result.message.substr(0, 40) }}</th>
                                <th @click=putContentLog(result.parameters) :title="result.parameters">{{ typeof result.parameters !== "string" ? result.parameters : result.parameters.substr(0, 40) }}</th>
                                <th>{{ result.uri + ' | ' + result.method}}</th>
                            </tr>
                        </table>
                        <!--<line-chart :data="results" :options="chartOptions" type="Line"></line-chart>-->
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { helpers } from '../helpers/helpers';

    // import VueChartkick from 'vue-chartkick';
    // Vue.component('vue-chartist', VueChartist);

    export default {
        components: {  },
        props: ['pageTitle', 'dateProp'],
        mixins: [helpers],
        name: 'Logs',

        data() {
            return {
                chartOptions: {
                    fullWidth: true,
                    chartPadding: {
                        right: 40
                    }
                },
                action: "",
                submitted: false,
                tipoPessoas: [],
                show: false,
                results: [],
                filter: {
                    dateStart: "",
                    dateEnd: "",
                }
            }
        },

        mounted: function ()
        {
            this.initialize();
        },

        methods: {
            initialize()
            {
                $(".nav-item > .first").trigger("click");
                this.filter.dateStart = this.dateProp;
                this.filter.dateEnd = this.dateProp;
            },
            putContentLog(content) {
                console.log(content);
            },
            save()
            {
                if (this.submitted) {
                    return;
                }
                if (! this.filter.dateStart) {
                    this.infoAlert("Informe a Data Inicial.");
                    return false;
                }
                if (! this.filter.dateEnd) {
                    this.infoAlert("Informe a Data Final.");
                    return false;
                }
                let url = this.root + "/ajax/getLog?dateStart=" + this.filter.dateStart + "&dateEnd=" + this.filter.dateEnd;
                this.startRequest();
                this.get(url).then(this.successHttpFn((response) => {
                    this.results = response.data.logs;
                    console.log(response.data.reported);
                })).catch(this.errorHttpFn());
            }
        }
    }
</script>