let initWs = () => {
    // noinspection ES6ConvertVarToLetConst
    var app_key = "40c20d46182c497aa5147242b91c6923d6a6258e";//APP_KEY do Laravel, criptogravada usando o sha1

//Codigo do pedido, código do veículo que vem na request do pedido, e código do cliente (usuário do app gas em casa)
// noinspection ES6ConvertVarToLetConst
    var ws = new WebSocket("ws://192.168.10.95:8003?app_key=" + app_key + "&client=gasemcasa&pedido_id=55&veiculo_id=5&cliente_id=18");

    /**
     * Todas as mensagens recebidas vem no seguinte formato json:
     *  {
     *      data: "",
     *      event: ,
     *      data_format: "string"
     *  }
     *
     * a chave "data_format" pode vir um dos seguintes parâmetros:
     * string ou json.
     *
     *
     * a chave "event" pode vir um dos seguintes eventos:
     *
     * "POSITION_UPDATED", //posição do veículo atualizadaa
     * "VEHICLE_UPDATED", //código do veículo atualizado - esse aqui provavelmente não virá para o aplicativo
     * "INVALID_PARAMS", //falta algum parâmetro da url
     * "MAX_CONNECTIONS_LIMIT", // atingiu o numero máximo de requisições (100)
     * "UNAUTHENTICATED", //chave da api inválida
     * "INVALID_DATA_FORMAT", //chave data_format incorreta - também não irá usar no app a princípio
     * "JSON_DECODE_ERROR" //quando o "data_format" é um json e deu erro ao decodificar
     * "ORDER_NOT_FOUND" //o ws não conseguiu localizar o pedido
     *
     *
     * a chave "data" virá uma string em caso de "data_format" ser igual a string e uma string json em caso de "data_format" ser igual a json
     */

    /**
     * @param data
     * @type {{data_format: string, data: string, event: string}}
     */
    let validateDataFormat = (data) => {
        if (data.data_format === "json") {
            let json = JSON.parse(data.data);
            console.log(json);
        } else {
            console.log(data.data);
        }
    };

    let validateEvent = (data) => {
        if (data.event === "POSITION_UPDATED") {
            validateDataFormat(data);
            //atualizar posição do veículo no mapa
        } else if (data.event === "ORDER_NOT_FOUND") {
            validateDataFormat(data);
            //verificar o código do pedido
        } else if (data.event === "UNAUTHENTICATED") {
            validateDataFormat(data);
            //tratar app_key inválida
        } else if (data.event === "MAX_CONNECTIONS_LIMIT") {
            validateDataFormat(data);
            //passar a buscar via http
        } else if (data.event === "INVALID_PARAMS") {
            validateDataFormat(data);
            //tratar parâmetros inválidos
        } else {
            validateDataFormat(data);
            //outros erros
        }
    };

    ws.onmessage = (response) => {
        try {
            validateEvent(JSON.parse(response.data));
        } catch (e) {
            alert("Erro resposta inválida: " + e.message);
            console.error("Erro resposta inválida: " + e.message);
        }
    };

    ws.onerror = (error) => {
        console.error(error);
        //tratar para deixar de usar o websocket
    };

    ws.onopen = () => {
        //tratar para deixar de usar a consulta via http
    };

    ws.onclose = (error) => {
        console.error(error);
        //tratar para deixar de usar o websocket
    };
};
initWs();
// let order = {
//     avaliado: 0,
//     cancelado: 1,
//     cliente_id: 207,
//     colaborador_id: null,
//     condicaopagamento_id: 1,
//     created_at: "2019-04-17 08:40:44",
//     datahoracancelamento: null,
//     datahoraentrega: null,
//     datahoraenvioentregador: null,
//     datahoraprevisao: "2019-04-17 08:40:44",
//     delivery_time: "Tempo de entrega é de 5 a 15 min.",
//     ementrega: 0,
//     endereco_id: 357,
//     entregue: 0,
//     erp_id: 701985,
//     id: 1321,
//     ignorado: 0,
//     items: '[{"quantidade":"1.0000","precovendaunitario":"140.0000","precovendatotal":"140.0000","produto_id":8,"codigogb":" "},{"quantidade":"1.0000","precovendaunitario":"140.0000","precovendatotal":"140.0000","produto_id":8,"codigogb":" "}]',
//     latitude: -25.351881839299804,
//     longitude: -51.4885663613677,
//     nao_avaliado: 0,
//     observacoes: null,
//     pedidosituacao_id: 5,
//     pendente: 0,
//     reseller_name: "Distribuidora Dubena",
//     reseller_phone: "(42) 3629-3586",
//     reseller_position: {latitude: -25.3448483, longitude: -51.4904372},
//     status: "Ops! Seu pedido foi cancelado pela revenda!",
//     total_price: "R$ 280,00",
//     track: {motorista: "Sem informações sobre o entregador", placa: " ", location: {"latitude": "", "longitude": ""}},
//     updated_at: "2019-04-17 08:42:03",
//     user_id: 1,
//     veiculo_id: 19
// };