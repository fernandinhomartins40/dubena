import React from 'react';
import AsyncStorage from '@react-native-community/async-storage';
import {UserModel} from '../models';

/**
 * @param data {{access_token: string, client_id: string, token_type: string}}
 */
export const storeToken = async function(data) {
  return new Promise(resolve => {
    setIfNotNull('access_token', data.access_token).then(() => {
      setIfNotNull('client_id', data.client_id.toString()).then(() => {
        setIfNotNull('token_type', data.token_type).then(() => {
          setIfNotNull(
            'tokenKey',
            data.token_type + ' ' + data.access_token,
          ).then(token => {
            resolve(token);
          });
        });
      });
    });
  });
};

export const storeData = async function(props) {
  return new Promise(resolve => {
    setIfNotNull(props.key, props.data).then(() => {
      resolve(true);
    });
  });
};

export const retrieveData = async props => {
  try {
    const value = await AsyncStorage.getItem(props);
    if (value !== null) {
      return value;
    }
  } catch (error) {}
};

export const retrieveUser = async (props = null) => {
  try {
    const value = await AsyncStorage.getItem('userData');

    /**
     * @type {{userName: string,
     * userPhone: string,
     * userId: string|int}}
     */
    let data = JSON.parse(value);
    if (value !== null) {
      switch (props) {
        case 'userName':
          return data.userName;
        case 'userPhone':
          return data.userPhone;
        case 'userId':
          return data.userId;
        case 'presencaComprador':
          return data.presencaComprador;
        case 'registrationId':
          return data.registrationId
            ? data.registrationId
            : UserModel.registrationId;
        case 'modalidadeFrete':
          return data.modalidadeFrete;
        case 'transportadorId':
          return data.transportadorId;
        case 'setorId':
          return data.setorId;
        case 'setorDescricao':
          return data.setorDescricao;
        case 'veiculoId':
          return data.veiculoId ? data.veiculoId : UserModel.veiculoId;
        default:
          if (props !== null) {
            console.warn(props + ' não está armazenado no store');
          }
          return data;
      }
    }
    return data ? data : UserModel;
  } catch (error) {
    return Promise.reject('Erro ao buscar dados do usuário');
  }
};

export const clearAllData = async () => {
  return new Promise((resolve, reject) => {
    retrieveData('tokenKey')
      .then(async token => {
        await AsyncStorage.clear();
        setIfNotNull('tokenKey', token).then(resolve);
      })
      .catch(reject);
  });
};

const setIfNotNull = async (key, value) => {
  if (value != null) {
    return await AsyncStorage.setItem(key, value);
  } else {
    return Promise.reject('valor nulo');
  }
};
