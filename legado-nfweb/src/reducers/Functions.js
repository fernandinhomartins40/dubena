import {UserModel} from '../models';

export const mapStateToProps = state => ({
  // User
  userName: state.User ? state.User.userName : UserModel.userName,
  userPhone: state.User ? state.User.userPhone : UserModel.userPhone,
  userId: state.User ? state.User.userId : UserModel.userId,
  user: state.User ? state.User : UserModel,
  registrationId: state.User.registrationId
    ? state.User.registrationId
    : UserModel.registrationId,
  veiculoId: state.User.veiculoId ? state.User.veiculoId : UserModel.veiculoId,
  presencaComprador: state.User.presencaComprador,
  modalidadeFrete: state.User.modalidadeFrete,
  transportadorId: state.User.transportadorId,
  setorId: state.User.setorId,
  setorNome: state.User.setorNome,
  uf: state.User.uf,
  cidade_id: state.User.cidade_id,
  // SMS firebase Auth
  smsAuth: state.SmsAuth,
  // Refresh
  refreshing: state.Refresh,
  // Veiculos
  Veiculos: state.Veiculos,
  // Clientes
  Clientes: state.Clientes,
  // Produtos
  Produtos: state.Produtos,
  // Pedido
  Pedido: state.Pedido,
  // Operacoes
  Operacoes: state.Operacoes,
  // Pagamentos
  Pagamentos: state.Pagamentos,
  //Ruas
  Ruas: state.Ruas,
  //Cidades
  Cidades: state.Cidades,
  //Bairros
  Bairros: state.Bairros,
  //Segmentos
  Segmentos: state.Segmentos,
  //Tipopessoas
  Tipopessoas: state.Tipopessoas,
  //UFs
  Estados: state.Estados,
  //Tipos telefones
  Telefonetipos: state.Telefonetipos,
  //Cliente
  Cliente: state.Cliente,
});

const changeState = (payload, type) => {
  return {type: type, payload: payload};
};

export const mapDispatchToProps = dispatch => ({
  changeUserState: data => dispatch(changeState(data, 'CHANGE_USER')),
  changeApiInfo: data => dispatch(changeState(data, 'CHANGE_API_INFO')),
  changeSmsAuthState: data => dispatch(changeState(data, 'CHANGE_SMS_AUTH')),
  changeVeiculos: data => dispatch(changeState(data, 'CHANGE_VEICULOS')),
  changeClientes: data => dispatch(changeState(data, 'CHANGE_CLIENTES')),
  changeProdutos: data => dispatch(changeState(data, 'CHANGE_PRODUTOS')),
  changeOperacoes: data => dispatch(changeState(data, 'CHANGE_OPERACOES')),
  changePagamentos: data => dispatch(changeState(data, 'CHANGE_PAGAMENTOS')),
  changeRefresh: data => dispatch(changeState(data, 'REFRESH')),
  changePedido: data => dispatch(changeState(data, 'CHANGE_PEDIDO')),
  changeRuas: data => dispatch(changeState(data, 'CHANGE_RUAS')),
  changeCidades: data => dispatch(changeState(data, 'CHANGE_CIDADES')),
  changeBairros: data => dispatch(changeState(data, 'CHANGE_BAIRROS')),
  changeSegmentos: data => dispatch(changeState(data, 'CHANGE_SEGMENTOS')),
  changeTipopessoas: data => dispatch(changeState(data, 'CHANGE_TIPOPESSOAS')),
  changeEstados: data => dispatch(changeState(data, 'CHANGE_ESTADOS')),
  changeTelefonetipos: data => dispatch(changeState(data, 'CHANGE_TELEFONETIPOS')),
  changeCliente: data => dispatch(changeState(data, 'CHANGE_CLIENTE')),
});

export const assignObjectToProps = (model, props) => {
  let keys = Object.keys(model);
  let state = {};
  for (let i = 0; i < keys.length; i++) {
    let key = keys[i];
    if (props.hasOwnProperty(key)) {
      state[key] = props[key];
    }
  }
  return state;
};

export const getUserState = (props, value, key = 'allData') => {
  let userState = assignObjectToProps(UserModel, props);
  userState[key] = value;
  return userState;
};

