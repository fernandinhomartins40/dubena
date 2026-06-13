require('./base.js');

Vue.component(
    'logs',
    require('./components/Logs.vue')
);

const app = new Vue({
    el: '#app'
});