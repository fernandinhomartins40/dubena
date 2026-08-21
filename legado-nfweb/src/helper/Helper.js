/* eslint-disable prettier/prettier */
import {Alert, Dimensions, Platform} from 'react-native';
import NetInfo from '@react-native-community/netinfo';

export const {height, width} = Dimensions.get('window');

export const Porcent = (key, value) => {
  let percentage;
  if (key === 1) {
    percentage = parseInt(width - (width * value) / 100);
  } else {
    percentage = parseInt(height - (height * value) / 100);
  }

  return isNaN(percentage) ? value : percentage;
};

export const formataDecimal = (valor, decimais = 2) => {
  let n = valor;
  let c = isNaN((decimais = Math.abs(decimais))) ? 2 : decimais;
  let d = ',';
  let t = '.';
  let s = n < 0 ? '-' : '';
  let i = parseInt((n = Math.abs(+n || 0).toFixed(c))) + '';
  let j;
  j = (j = i.length) > 3 ? j % 3 : 0;
  return (
    s +
    (j ? i.substr(0, j) + t : '') +
    i.substr(j).replace(/(\d{3})(?=\d)/g, '$1' + t) +
    (c
      ? d +
        Math.abs(n - i)
          .toFixed(c)
          .slice(2)
      : '')
  );
};

export const validateFormAddress = data => {
  let fields = [];
  if (!data.rua) {
    fields.push('Rua');
  }
  if (!data.bairro) {
    fields.push('Bairro');
  }
  if (!data.uf) {
    fields.push('UF');
  }
  if (!data.cidade) {
    fields.push('Cidade');
  }
  if (fields.length > 0) {
    return 'O(s) campo(s) ' + fields.join(', ') + ' é/são obrigatório(s)';
  }
  return true;
};

export const removeEmoji = (value: any) => {
  return new Promise((resolve, reject) => {
    if (typeof value !== 'string') {
      for (let i in value) {
        if (value.hasOwnProperty(i) && !validateEmoji(value[i])) {
          reject(i);
          return;
        }
      }
    } else {
      if (!validateEmoji(value)) {
        reject();
        return;
      }
    }
    resolve();
  });
};

export const validateEmoji = (input: any) => {
  if (
    input === null ||
    input === true ||
    input === false ||
    input === undefined
  ) {
    return true;
  }
  let ranges = [
    '\ud83c[\udf00-\udfff]', // U+1F300 to U+1F3FF
    '\ud83d[\udc00-\ude4f]', // U+1F400 to U+1F64F
    '\ud83d[\ude80-\udeff]', // U+1F680 to U+1F6FF
  ];

  let str = input;
  str = str.replace(new RegExp(ranges.join('|'), 'g'), '');

  return input === str;
};

export const urlBuilder = (params: any, start = '?') => {
  let paramsStr = start;
  for (let key in params) {
    if (params.hasOwnProperty(key)) {
      paramsStr += key + '=' + params[key] + '&';
    }
  }
  return paramsStr;
};

export const actualDate = () => {
  let data = new Date();

  return (
    (data.getFullYear() < 10 ? '0' + data.getFullYear() : data.getFullYear()) +
    '-' +
    (data.getMonth() + 1 < 10
      ? '0' + (data.getMonth() + 1)
      : data.getMonth() + 1) +
    '-' +
    (data.getDate() < 10 ? '0' + data.getDate() : data.getDate()) +
    ' ' +
    (data.getHours() < 10 ? '0' + data.getHours() : data.getHours()) +
    ':' +
    (data.getMinutes() < 10 ? '0' + data.getMinutes() : data.getMinutes()) +
    ':' +
    (data.getSeconds() < 10 ? '0' + data.getSeconds() : data.getSeconds())
  );
};

export const actualDateFormatted = (separator = '-', data = new Date()) => {
  //let data = new Date();
  return (
    (data.getDate() < 10 ? '0' + data.getDate() : data.getDate()) +
    separator +
    (data.getMonth() + 1 < 10
      ? '0' + (data.getMonth() + 1)
      : data.getMonth() + 1) +
    separator +
    (data.getFullYear() < 10 ? '0' + data.getFullYear() : data.getFullYear()) +
    ' ' +
    (data.getHours() < 10 ? '0' + data.getHours() : data.getHours()) +
    ':' +
    (data.getMinutes() < 10 ? '0' + data.getMinutes() : data.getMinutes()) +
    ':' +
    (data.getSeconds() < 10 ? '0' + data.getSeconds() : data.getSeconds())
  );
};

export const onlyNumbers = string => {
  return string.replace(/^\d+$/, '');
};

export const checkInternetConnection = (onSuccess, onError) => {
  NetInfo.getConnectionInfo().then(info => {
    if (info.type === 'none' || info.type === 'unknown') {
      onError();
    } else {
      onSuccess();
    }
  });
};

export const locationAlert = () => {
  if (Platform.OS !== 'ios') {
    Alert.alert(
      'Atenção...',
      '...para ter uma melhor experiência ao utilizar o mapa, ative sua localização',
    );
  } else {
    Alert.alert(
      'Atenção...',
      '...para ter uma melhor experiência ao utilizar o mapa, permita o aplicativo visualizar sua localização.' +
        ' Para habilitar a localização em seu iPhone, basta ir em "Ajustes -> Gás em Casa -> Localização -> Durante Uso do App".',
    );
  }
};

export const validarCPF = cpf => {
  cpf = cpf.replace(/[^\d]+/g, '');
  if (cpf == '') {
    return false;
  }
  // Elimina CPFs invalidos conhecidos
  if (
    cpf.length != 11 ||
    cpf == '00000000000' ||
    cpf == '11111111111' ||
    cpf == '22222222222' ||
    cpf == '33333333333' ||
    cpf == '44444444444' ||
    cpf == '55555555555' ||
    cpf == '66666666666' ||
    cpf == '77777777777' ||
    cpf == '88888888888' ||
    cpf == '99999999999'
  ) {
    return false;
  }
  // Valida 1o digito
  let add = 0;
  for (i = 0; i < 9; i++) {
    add += parseInt(cpf.charAt(i)) * (10 - i);
  }
  let rev = 11 - (add % 11);
  if (rev == 10 || rev == 11) {
    rev = 0;
  }
  if (rev != parseInt(cpf.charAt(9))) {
    return false;
  }
  // Valida 2o digito
  add = 0;
  for (var i = 0; i < 10; i++) {
    add += parseInt(cpf.charAt(i)) * (11 - i);
  }
  rev = 11 - (add % 11);
  if (rev == 10 || rev == 11) {
    rev = 0;
  }
  if (rev != parseInt(cpf.charAt(10))) {
    return false;
  }
  return true;
};

export const validarCNPJ = cnpj => {
  cnpj = cnpj.replace(/[^\d]+/g, '');

  if (cnpj == '') {
    return false;
  }

  if (cnpj.length != 14) {
    return false;
  }

  // Elimina CNPJs invalidos conhecidos
  if (
    cnpj == '00000000000000' ||
    cnpj == '11111111111111' ||
    cnpj == '22222222222222' ||
    cnpj == '33333333333333' ||
    cnpj == '44444444444444' ||
    cnpj == '55555555555555' ||
    cnpj == '66666666666666' ||
    cnpj == '77777777777777' ||
    cnpj == '88888888888888' ||
    cnpj == '99999999999999'
  ) {
    return false;
  }

  // Valida DVs
  let tamanho = cnpj.length - 2;
  let numeros = cnpj.substring(0, tamanho);
  let digitos = cnpj.substring(tamanho);
  let soma = 0;
  let pos = tamanho - 7;
  for (var i = tamanho; i >= 1; i--) {
    soma += numeros.charAt(tamanho - i) * pos--;
    if (pos < 2) {
      pos = 9;
    }
  }
  let resultado = soma % 11 < 2 ? 0 : 11 - (soma % 11);
  if (resultado != digitos.charAt(0)) {
    return false;
  }

  tamanho = tamanho + 1;
  numeros = cnpj.substring(0, tamanho);
  soma = 0;
  pos = tamanho - 7;
  for (i = tamanho; i >= 1; i--) {
    soma += numeros.charAt(tamanho - i) * pos--;
    if (pos < 2) {
      pos = 9;
    }
  }
  resultado = soma % 11 < 2 ? 0 : 11 - (soma % 11);
  if (resultado != digitos.charAt(1)) {
    return false;
  }

  return true;
};

export const validarEmail = field => {
  let usuario = field.substring(0, field.indexOf('@'));
  let dominio = field.substring(
    field.indexOf('@') + 1,
    field.length,
  );

  if (
    usuario.length >= 1 &&
    dominio.length >= 3 &&
    usuario.search('@') == -1 &&
    dominio.search('@') == -1 &&
    usuario.search(' ') == -1 &&
    dominio.search(' ') == -1 &&
    dominio.search('.') != -1 &&
    dominio.indexOf('.') >= 1 &&
    dominio.lastIndexOf('.') < dominio.length - 1
  ) {
    return true;
  } else {
    return false;
  }
};
