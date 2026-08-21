import {getToken, prepareRequest, baixarPDF} from '../helper/Http';
import {constants} from '../helper/Constants';
import {actualDate, urlBuilder} from '../helper/Helper';
import Toast from 'react-native-simple-toast';

//Default Requests

export const rootRequest = (telephone, callback = () => {}) => {
  return prepareRequest('GET', 'nfweb/init?phone=', telephone, callback);
};

export const getApiToken = () => {
  return new Promise((resolve, reject) => {
    getToken(constants.API_URL + 'getToken?app_key=' + constants.LARAVEL_KEY)
      .then(value => {
        resolve(value);
      })
      .catch(err => {
        Toast.show(err.message, Toast.LONG, Toast.BOTTOM);
        reject();
      });
  });
};

export const storePushRegistration = (token, id) => {
  return new Promise(resolve => {
    prepareRequest(
      'POST',
      'nfweb/changeRegistrationId',
      {
        pushregistration_id: token,
        collaborator_id: id,
      },
      resolve,
    ).then(resolve);
  });
};

export const verifyToken = callback => {
  return prepareRequest('GET', 'testTokenNfweb', null, callback);
};

export const getPedidoConsulta = (id, colaborador_id) => {
  return prepareRequest(
    'GET',
    'nfweb/pedidoConsulta?pedido_id=' +
      id +
      '&colaborador_id=' +
      colaborador_id,
  );
};

export const getNfeConsulta = (id, colaborador_id) => {
  return prepareRequest(
    'GET',
    'nfweb/nfeConsulta?nfce_id=' + id + '&colaborador_id=' + colaborador_id,
  );
};

export const getPedidoDuplicata = (id, colaborador_id) => {
  return prepareRequest(
    'GET',
    'nfweb/pedidoDuplicata?pedido_id=' +
      id +
      '&colaborador_id=' +
      colaborador_id,
  );
};

export const enviarEmail = (id) => {
  return prepareRequest('GET', 'nfweb/enviarEmail?pedido_id=' + id);
};


export const getOrders = id => {
  return prepareRequest('GET', 'nfweb/orders?collaborator_id=' + id);
};

export const setOrderRead = (id, callback = null) => {
  return prepareRequest('POST', 'nfweb/order/read', {id: id}, callback);
};

export const setOrderAccept = (id, collaborator_id, callback = null) => {
  return prepareRequest(
    'POST',
    'nfweb/order/accept',
    {id: id, collaborator_id: collaborator_id},
    callback,
  );
};

export const setOrderFinish = (id, payment_id, callback = null) => {
  return prepareRequest(
    'POST',
    'nfweb/order/finish',
    {id: id, payment_id: payment_id},
    callback,
  );
};

export const setOrderCancel = (id, cancelreason_id, callback = null) => {
  return prepareRequest(
    'POST',
    'nfweb/order/cancel',
    {id: id, cancel_id: cancelreason_id},
    callback,
  );
};

export const setOrderTransfer = (id, callback = null) => {
  return prepareRequest('POST', 'nfweb/order/transfer', {id: id}, callback);
};

export const getAccount = (telephone, callback = null) => {
  console.log('getAccountReq');
  return prepareRequest(
    'POST',
    'nfweb/login',
    {
      phone: telephone,
    },
    callback,
  );
};

export const setVeiculoPadrao = (
  veiculo_id,
  colaborador_id,
  callback = null,
) => {
  return prepareRequest(
    'POST',
    'nfweb/changeVeiculo',
    {veiculo_id: veiculo_id, colaborador_id: colaborador_id},
    callback,
  );
};

export const enviarPedido = (pedido, colaborador_id, callback = null) => {
  return prepareRequest(
    'POST',
    'nfweb/savePedido',
    {pedido: pedido, colaborador_id: colaborador_id},
    callback,
  );
};

export const enviarCliente = (cliente, colaborador_id, callback = null) => {
  return prepareRequest(
    'POST',
    'nfweb/saveCliente',
    {cliente: cliente, colaborador_id: colaborador_id},
    callback,
  );
};

export const enviarClienteObs = (cliente, colaborador_id, callback = null) => {
  return prepareRequest(
    'POST',
    'nfweb/saveClienteObs',
    {cliente: cliente, colaborador_id: colaborador_id},
    callback,
  );
};

export const getParcelasVencidasCliente = (cliente_id, callback = null) => {
  return prepareRequest(
    'POST',
    'nfweb/getParcelasVencidasCliente',
    {cliente_id: cliente_id},
    callback,
  );
};

export const getPedidosReport = (id, initialDate, finalDate) => {
  return prepareRequest(
    'GET',
    'nfweb/pedidosReport?colaborador_id=' +
      id +
      '&initial_date=' +
      initialDate +
      '&final_date=' +
      finalDate,
  );
};

export const visualizarDanfe = id => {
  return prepareRequest('GET', 'nfweb/visualizarDanfe?id=' + id);
};

export const visualizarBoleto = (id, tipo) => {
  return prepareRequest(
    'GET',
    'nfweb/visualizarBoleto?id=' + id + '&tipo=' + tipo,
  );
};

export const visualizarDuplicata = id => {
  return prepareRequest('GET', 'nfweb/visualizarDuplicata?id=' + id);
};

export const getCadastros = (telephone, callback = () => {}) => {
  return prepareRequest(
    'GET',
    'nfweb/getCadastros?phone=',
    telephone,
    callback,
  );
};

export const getCliente = (cliente_id, callback = null) => {
  return prepareRequest(
    'POST',
    'nfweb/getCliente',
    {cliente_id: cliente_id},
    callback,
  );
};

export const baixarDanfePDF = id => {
  return baixarPDF('nfweb/baixarDanfe?id=' + id, 'danfe_' + (id + '').padStart(6, '0'), 'Danfe referente a NF '  + (id + '').padStart(6, '0'));
};

export const baixarBoletoPDF = id => {
  return baixarPDF('nfweb/visualizarBoleto?id=' + id + '&tipo=3', 'boleto_pedido_' + (id + '').padStart(6, '0'), 'Boleto referente ao pedido '  + (id + '').padStart(6, '0'));
};

