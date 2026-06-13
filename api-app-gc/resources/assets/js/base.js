import VueSweetalert2 from 'vue-sweetalert2';
import * as Vue from "vue";
import BootstrapVue from 'bootstrap-vue'
import VueProgressBar from 'vue-progressbar';
import 'bootstrap/dist/css/bootstrap.css'
import 'bootstrap-vue/dist/bootstrap-vue.css'
import vSelect from 'vue-select';

Vue.component('v-select', vSelect);

Vue.use(BootstrapVue);

Vue.use(VueSweetalert2);

Vue.use(VueProgressBar, {
    color: 'blue',
    failedColor: 'red',
    autoFinish: false,
    thickness: '3.5px',
    transition: {speed: '0.2s', opacity: '0.9s', termination: 500}
});

require('./bootstrap');

window.Vue = require('vue');

Vue.swal.setDefaults({
    onOpen: () => {
        console.log();
        if (! $(document).find(".swal2-input").first().is(":visible")) {
            $(document).off('focusin.modal');
            $(document).find(".swal2-actions button").first().focus();
        }
    },
    cancelButtonText: "Cancelar"
});
