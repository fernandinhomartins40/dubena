import {Alert} from 'react-native';
import {constants} from './Constants';
import {retrieveData} from './AsyncStore';
import Toast from 'react-native-simple-toast';
import RNFetchBlob from 'rn-fetch-blob';

export const prepareRequest = (
  _method = 'GET',
  urlAddress,
  data = null,
  callback = null,
) => {
  console.log(urlAddress);
  return new Promise(resolve1 => {
    let promise = new Promise((resolve, reject) => {
      let url = constants.API_URL + urlAddress;
      getHeader()
        .then(value => {
           console.log('url');
          sendRequest(value, _method, url, data)
            .then(responseHttp => {
              console.log(responseHttp);
              treatResponse(responseHttp, resolve, reject);
            })
            .catch(rejected => {
              console.warn(url);
              reject(treatResponseError(rejected));
            });
        })
        .catch(err => {
          reject(err);
        });
    });
    promise.then(resolve1).catch(err => {
      Alert.alert("Lamentamos mas parece que temos um problema..", " " + err.message);
      if (callback == null){
        Toast.show(
          'Lamentamos mas parece que temos um problema..' + err.message,
          Toast.LONG,
          Toast.CENTER,
        );
      }
      if (typeof callback === 'function') {
        callback(err);
      }
    });
  });
};

export const getHeader = () => {
  return retrieveData('tokenKey');
};

export const getToken = (url, _method = 'GET') => {
  console.log(url);

  return new Promise((resolve, reject) => {
    fetch(url, {
      method: _method,
    })
      .then(response => {
        if (response.ok) {
          return response.json();
        } else {
          //console.log(response);
          //console.warn(response.text());
          //console.log(url);
          return Promise.reject({
            message:
              'Ocorreu um erro ao realizar a conexão com os servidores, tente novamente mais tarde (1)',
            status: 'NOK',
          });
        }
      })
      .then(result => {
        if (result.status === 'OK') {
          resolve(result);
        } else {
          reject(result);
        }
      })
      .catch(err => {
        //console.log(err);
        reject(err);
      });
  });
};

export const sendRequest = (header, _method, url, data = {}) => {
  let request;

  switch (_method) {
    case 'PUT':
    case 'POST':
      request = fetch(url, {
        method: _method,
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          Authorization: typeof header !== 'string' ? '' : header,
        },
        body: JSON.stringify(data),
      });
      break;
    case 'GET':
    case 'DELETE':
      request = fetch(url + (data ? data : ''), {
        method: _method,
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          Authorization: typeof header !== 'string' ? '' : header,
        },
      });
      break;
  }

  let wasServerTimeout = false;

  let timeout = setInterval(() => {
    wasServerTimeout = true;
  }, 15000);
  return request.then(response => {
    timeout && clearInterval(timeout); //If everything is ok, clear the timeout
    if (!wasServerTimeout) {
      if (response.ok) {
        return response.json();
      } else {
        return Promise.reject({
          status: response.status,
          message: response.statusText,
        });
      }
    } else {
      return Promise.reject({
        status: 408,
        message: 'Tempo esgotado',
      });
    }
  });
};

export const getPolicyPrivacy = () => {
  return fetch('http://www.gasemcasa.com.br/termos.php?type=json', {
    method: 'GET',
  }).then(response => {
    if (response.ok) {
      return response.json();
    } else {
      return Promise.reject({
        message:
          'Ocorreu um erro ao realizar a conexão com os servidores, tente novamente mais tarde (2)',
      });
    }
  });
};

const genericError =
  'Ops, ocorreu um erro desconhecido, tente novamente mais tarde ou entre em contato com o suporte';

export const treatResponse = (responseHttp, resolve, reject) => {
  console.log('treatResponse');
  console.log(responseHttp);
  if (responseHttp.status === 'OK' || responseHttp.status === 'OPS') {
    responseHttp.rejection =
      responseHttp.status === 'OK' ? 'NO_REJECTION' : responseHttp.rejection;
    resolve(responseHttp);
  } else {
    reject(treatNOKResponse(responseHttp));
  }
};

export const treatResponseError = rejected => {
  console.warn(rejected);
  let error = {
    status: 'NOK',
    httpStatus: '0',
    message: genericError,
  };
  if (typeof rejected === 'object' || rejected instanceof Error) {
    error.httpStatus = rejected.status ? rejected.status : '0';
    if (rejected.error) {
      try {
        let parsed = JSON.parse(rejected.error);
        if (parsed.message) {
          error.message = parsed.message;
        } else {
          error.message = rejected.error;
        }
      } catch (e) {
        error.message = rejected.error;
      }
    } else {
      error.message = rejected.message ? rejected.message : error.message;
    }
  }
  error.message = error.message.substring(0, 250);
  return error;
};

export const treatNOKResponse = responseHttp => {
  let error = {
    status: 'NOK',
    message: genericError,
    httpStatus: '0',
  };
  try {
    error.message = responseHttp.message;
  } catch {}

  try {
    if (
      error.message === genericError ||
      typeof error.message === 'undefined'
    ) {
      // noinspection JSUnresolvedVariable
      error.message = responseHttp.msg;
    }
  } catch {}

  try {
    if (
      error.message === genericError ||
      typeof error.message === 'undefined'
    ) {
      error.message = responseHttp.error;
    }
  } catch {}

  if (
    constants.DEBUG_MODE &&
    (error.message === genericError || typeof error.message === 'undefined')
  ) {
    error.message = stringifyError(responseHttp).substring(0, 500);
  }
  return error;
};

export const stringifyError = (err, filter = null, space = '\t') => {
  let plainObject = {};
  Object.getOwnPropertyNames(err).forEach(function(key) {
    plainObject[key] = err[key];
  });
  return JSON.stringify(plainObject, filter, space);
};

export const baixarPDF = (urlAddress, filename, description) => {
  let url = constants.API_URL + urlAddress;
  getHeader()
  .then(token => {
     let dirs = RNFetchBlob.fs.dirs;
      RNFetchBlob
      .config({
        fileCache : true,
        path : dirs.DownloadDir + '/' + filename + '.pdf',
        addAndroidDownloads : {
            useDownloadManager : true, // <-- this is the only thing required
            notification : true,
            mime : 'application/pdf',
            title: filename + '.pdf',
            description : description,
            path : dirs.DownloadDir + '/' + filename + '.pdf',
          }
      })
      .fetch('GET', url, {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Authorization: typeof token !== 'string' ? '' : token,
      })
      .then((resp) => {
      })
  })
  .catch(err => {
    reject(err);
  });
}
