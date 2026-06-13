require('./base.js');
import VueParticles from 'vue-particles';

Vue.use(VueParticles);

Vue.component(
    'passport-clients',
    require('./components/passport/Clients.vue')
);

Vue.component(
    'passport-authorized-clients',
    require('./components/passport/AuthorizedClients.vue')
);

Vue.component(
    'passport-personal-access-tokens',
    require('./components/passport/PersonalAccessTokens.vue')
);

Vue.component(
    'users',
    require('./components/user/User.vue')
);

Vue.component(
    'produtos-categorias', 
    require('./components/produtos/categorias/ProdutosCategorias.vue')
);

Vue.component('config', require('./components/Config.vue'));

Vue.component('general-config', require('./components/GeneralConfig.vue'));
Vue.component('home', require('./components/Home.vue'));

const app = new Vue({
    el: '#app'
});

