//TODO alternar entre produção ou homologação

const LARAVEL_KEY = 'f77eda0588f91b9c033c4fd78172384b7d33eafd';

//const origin = 'internal';
const origin = "external";

//const env = 'dev';
const env = "production";

const getEnv = key => {
  let WS_ADDRESS;
  let API_URL;
  let GOOGLE_API_KEY;
  if (origin === 'internal' && env === 'production') {
    // PRODUÇÃO ACESSO INTERNO
    WS_ADDRESS = 'ws://192.168.10.1:8092';
    API_URL = 'http://192.168.10.1/api-app/public/api/';
    GOOGLE_API_KEY = 'AIzaSyDygo66KV3BCnznA_vVG4s63JXpk8Qd0d8';
  } else if (origin === 'external' && env === 'production') {
    // PRODUÇÃO ACESSO EXTERNO
    WS_ADDRESS = 'ws://adm.gasemcasa.com.br:8092';
    API_URL = 'https://adm.gasemcasa.com.br/public/api/';
    GOOGLE_API_KEY = 'AIzaSyDygo66KV3BCnznA_vVG4s63JXpk8Qd0d8';
  } else if (origin === 'external' && env === 'dev') {
    // HOMOLOGAÇÃO/DESENVOLVIMENTO ACESSO EXTERNO
    WS_ADDRESS = 'ws://200.195.154.98:8091';
    API_URL = 'http://qtidevel.ddns.net:8181/ctrl2qti/public/api/';
    GOOGLE_API_KEY = 'AIzaSyA9ql-tGlpFZB_ZdvpiPMsNCvBV-tAMQWg';
  } else {
    // HOMOLOGAÇÃO/DESENVOLVIMENTO ACESSO INTERNO
    WS_ADDRESS = 'ws://192.168.10.7:8091';
    API_URL = 'http://192.168.0.106/ctrl2/public/api/';
    GOOGLE_API_KEY = 'AIzaSyBdAFYJWX9rLnn0LPPDjyvBPTKJdgeHbwA';
  }
  switch (key) {
    case 'WS_ADDRESS':
      return WS_ADDRESS;
    case 'API_URL':
      return API_URL;
    case 'GOOGLE_API_KEY':
      return GOOGLE_API_KEY;
    default:
      return null;
  }
};

export const constants = {
  APP_LANG: 'pt-BR',
  DEBUG_MODE: false,
  LARAVEL_KEY: LARAVEL_KEY,
  WS_ADDRESS: getEnv('WS_ADDRESS') + '?app_key=' + LARAVEL_KEY,
  API_URL: getEnv('API_URL'),
  GOOGLE_API_KEY: getEnv('GOOGLE_API_KEY'),
  DEFAULT_LAT: -25.3862077,
  DEFAULT_LNG: -51.4867962,
};

export const iconsTrack = {
  userIcon: constants.API_URL + 'marker?name=home',
  trackIcon: constants.API_URL + 'marker?name=utilitario_000',
  resellerIcon: constants.API_URL + 'marker?name=resseler',
};

//NO CASO DE IPV6 NO ANDROID MACBOOK: export _JAVA_OPTIONS=-Djava.net.preferIPv4Stack=true
