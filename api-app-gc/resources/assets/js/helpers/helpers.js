export const helpers = {
    name: "helpers",
    data() {
        return {
            root: "",
            fullUrlResources: "",
            attributes: {},
            routeName: "",
            notificationOnExit: true
        }
    },
    mounted() {
        this.init();
        this.setEvents();
    },
    methods: {
        post(url, data) {
            return axios.post(url, data);
        },
        delete(url, html) {
            return new Promise((resolve, reject) => {
                if (! html) {
                    html = "Deseja realmente excluir o registro?";
                }
                Vue.swal({
                    html: html,
                    type: 'question',
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    confirmButtonText: "Sim",
                    cancelButtonText: "Não",
                }).then((result) => {
                    if (result.value) {
                        return axios.delete(url).then(response => {
                            resolve(response);
                        }, error => {
                            reject(error);
                        });
                    }
                });
            });
        },
        patch(url, data) {
            return axios.patch(url, data);
        },
        get(url) {
            return axios.get(url);
        },
        getUrl(extra)
        {
            return this.url + (typeof extra === "string" ? extra : "");
        },
        init() {
            this.attributes = helpersAttributes;
            this.url = this.attributes.root + "/" + (this.attributes.routeName.replace(".index", ""));
            this.root = this.attributes.root;
        },
        successHttpFn(successCallback) {
            return (response) => {
                let success = response.status === 200;
                if (response.status === 200) {
                    let data = response.data;
                    success = typeof data.status === "string" && data.status === "OK";
                    if (success) {
                        if (typeof successCallback === "function") {
                            successCallback(data);
                        } else {
                            console.log(data);
                        }
                    } else if (typeof data.status === "string" && data.status === "NOK") {
                        this.treatErrorsHttp(data.msg);
                    } else {
                        this.treatErrorsHttp(data);
                    }
                } else {
                    this.treatErrorsHttp(response);
                }
                this.stopRequest(success);
            }
        },
        errorHttpFn() {
            return (error) => {
                this.stopRequest();
                this.treatErrorsHttp(error);
            };
        },
        confirmAlert(message, callback) {
            return Vue.swal({html: message, showCancelButton: true, confirmButtonText: "Sim", cancelButtonText: "Não"}).then(callback);
        },
        inputAlert(title, placeholder, type, callback, attributes = {}) {
            return Vue.swal({
                title: title,
                input: type,
                inputPlaceholder: placeholder,
                showCancelButton: true,
                inputAttributes: attributes,
                confirmButtonText: "Continuar",
                cancelButtonText: "Cancelar"
            }).then(callback);
        },
        onlyNumbers(number) {
            return (typeof number !== "string" ? number.toString() : number).replace("/[^0-9]/g", "");
        },
        passwordConfirm(callback) {
            return this.inputAlert("Senha", "Informe sua senha para continuar", "password", callback);
        },
        errorAlert(message, callback, confirmText) {
            return this.vueSwal(message, callback, confirmText, "error");
        },
        successAlert(message, callback, confirmText) {
            return this.vueSwal(message, callback, confirmText, "success");
        },
        infoAlert(message, callback, confirmText) {
            return this.vueSwal(message, callback, confirmText, "info");
        },
        simpleAlert(message, callback, confirmText) {
            return this.vueSwal(message, callback, confirmText, null);
        },
        warningAlert(message, callback, confirmText) {
            return this.vueSwal(message, callback, confirmText, "warning");
        },
        vueSwal(message, callback, confirmText, type) {
            return Vue.swal({html: message, confirmButtonText: confirmText || "OK", type: type}).then((res) => {
                if (typeof callback === "function") {
                    callback(res);
                }
            });
        },
        startRequest() {
            this.$Progress.start();
            this.submitted = true;
        },
        stopRequest(finished = false) {
            if (finished) {
                this.$Progress.finish();
            } else {
                this.$Progress.fail();
            }
            this.submitted = false;
        },
        setEvents() {
            $(".modal").on("shown.bs.modal", function () {
                $(this).find('[autofocus]').focus();
            });

            let that = this;
            $(".close-modal").on("click", function () {
                let callback = (res) => {
                    if (res.value) {
                        $(this).parents(".modal").modal("hide");
                    }
                };
                if (that.notificationOnExit) {
                    that.confirmAlert("Deseja cancelar?", callback);
                } else {
                    callback({value: true});
                }
            });
        },
        mask(original, format) {
            let value = "";
            original = original.replace(/\D/g,'');
            for (let i = 0, j = 0; i < format.length; i++) {
                if (parseInt(format[i]) || format[i] === "#") {
                    value += original[j++];
                } else {
                    value += format[i];
                }
            }
            return value;
        },
        treatErrorsHttp(response)
        {
            let msg = '';

            if (response.response && response.response.data && typeof response.response.data.errors === "object") {
                let errors = response.response.data.errors;
                for (let key in errors) {
                    msg += '<br />' + errors[key];
                }
            } else if (response.data && typeof response.data.errors === "object") {
                for (let key in response.data.errors) {
                    msg += '<br />' + response.data.errors[key];
                }
            } else if (response.response && response.response.data && typeof response.response.data.message === "string") {
                msg = response.response.data.message;
            } else if (response.data && typeof response.data.message === "string") {
                msg = response.data.message;
            } else if (typeof response === "object") {
                let responseText = '';
                for (let key in response) {
                    if (key === "responseJSON") {
                        for (let key1 in response['responseJSON']) {
                            msg += '<br />' + response['responseJSON'][key1];
                        }
                    }
                    if (key === "responseText") {
                        responseText = response['responseText'];
                    }
                }
                if (msg) {
                    msg = "Erro ao executar a ação: " + msg;
                } else {
                    msg = "Erro ao executar a ação: " + responseText;
                }
            } else if (typeof response === "string") {
                msg = "Erro ao executar a ação: " + response;
            } else {
                msg = "Erro desconhecido ao executar a ação";
            }
            this.errorAlert(msg);
        },
        dateToBr(date) {
            let timeSplit = date.split(" ");
            let splitted = timeSplit[0].split("-");

            return splitted[2] + "/" + splitted[1] + "/" + splitted[0] + (timeSplit[1] ? timeSplit[1] : "");
        },
        dateToUS(date) {
            let timeSplit = date.split(" ");
            let splitted = timeSplit[0].split("/");

            return splitted[2] + "/" + splitted[1] + "/" + splitted[0] + (timeSplit[1] ? timeSplit[1] : "");
        }
    }
};