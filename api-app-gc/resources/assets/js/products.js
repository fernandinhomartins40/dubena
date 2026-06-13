require('./base.js');
import myUpload from 'vue-image-crop-upload';
Vue.component('my-upload', myUpload);

Vue.component(
    'products',
    require('./components/produtos/Product.vue')
);

const app = new Vue({
    el: '#app'
});