require('./base.js');

Vue.component(
    'payway',
    require('./components/condicaopagamento/CondicaoPagamento.vue')
);

const app = new Vue({
    el: '#app'
});