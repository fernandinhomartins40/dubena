import {combineReducers} from 'redux';
import {ApiInfoModel, SmsAuthModel, UserModel} from '../models';

const User = (state = UserModel, action) => {
  if (action.type === 'CHANGE_USER') {
    return action.payload;
  } else {
    return state;
  }
};

const SmsAuth = (state = SmsAuthModel, action) => {
  switch (action.type) {
    case 'CHANGE_SMS_AUTH':
      return action.payload;
    case 'INCREMENT':
      return state.timer + 1;
    case 'DECREMENT':
      return state.timer - 1;
    default:
      return state;
  }
};

const ApiInfo = (state = ApiInfoModel, action) => {
  if (action.type === 'CHANGE_API_INFO') {
    return action.payload;
  } else {
    return state;
  }
};

const Refresh = (state = false, action) => {
  if (action.type === 'REFRESH') {
    return action.payload;
  } else {
    return state;
  }
};

const Veiculos = (state = [], action) => {
  if (action.type === 'CHANGE_VEICULOS') {
    return action.payload;
  } else {
    return state;
  }
};

const Clientes = (state = [], action) => {
  if (action.type === 'CHANGE_CLIENTES') {
    return action.payload;
  } else {
    return state;
  }
};

const Produtos = (state = [], action) => {
  if (action.type === 'CHANGE_PRODUTOS') {
    return action.payload;
  } else {
    return state;
  }
};

const Pedido = (state = null, action) => {
  if (action.type === 'CHANGE_PEDIDO') {
    return action.payload;
  } else {
    return state;
  }
};

const Operacoes = (state = null, action) => {
  if (action.type === 'CHANGE_OPERACOES') {
    return action.payload;
  } else {
    return state;
  }
};

const Pagamentos = (state = null, action) => {
  if (action.type === 'CHANGE_PAGAMENTOS') {
    return action.payload;
  } else {
    return state;
  }
};

const Ruas = (state = null, action) => {
  if (action.type === 'CHANGE_RUAS') {
    return action.payload;
  } else {
    return state;
  }
};

const Bairros = (state = null, action) => {
  if (action.type === 'CHANGE_BAIRROS') {
    return action.payload;
  } else {
    return state;
  }
};

const Cidades = (state = null, action) => {
  if (action.type === 'CHANGE_CIDADES') {
    return action.payload;
  } else {
    return state;
  }
};

const Segmentos = (state = null, action) => {
  if (action.type === 'CHANGE_SEGMENTOS') {
    return action.payload;
  } else {
    return state;
  }
};

const Tipopessoas = (state = null, action) => {
  if (action.type === 'CHANGE_TIPOPESSOAS') {
    return action.payload;
  } else {
    return state;
  }
};

const Estados = (state = null, action) => {
  if (action.type === 'CHANGE_ESTADOS') {
    return action.payload;
  } else {
    return state;
  }
};

const Telefonetipos = (state = null, action) => {
  if (action.type === 'CHANGE_TELEFONETIPOS') {
    return action.payload;
  } else {
    return state;
  }
};

const Cliente = (state = null, action) => {
  if (action.type === 'CHANGE_CLIENTE') {
    return action.payload;
  } else {
    return state;
  }
};


export default combineReducers({
  User,
  SmsAuth,
  ApiInfo,
  Veiculos,
  Refresh,
  Pedido,
  Clientes,
  Produtos,
  Operacoes,
  Pagamentos,
  Ruas,
  Bairros,
  Cidades,
  Segmentos,
  Tipopessoas,
  Estados,
  Telefonetipos,
  Cliente,
});
