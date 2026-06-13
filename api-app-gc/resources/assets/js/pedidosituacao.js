require('./base.js');

Vue.component(
    'pedido-situacao',
    require('./components/pedidosituacao/PedidoSituacao.vue')
);

const app = new Vue({
    el: '#app'
});