import React from 'react';
import Loading from 'react-native-loader-overlay';
import {Alert} from 'react-native';

export default class BaseComponent extends React.Component {
  loading;

  _hideLoader = () => {
    Loading.hide(this.loading);
  };

  _showLoader = (type = 'Spinner') => {
    this.loading = Loading.show({
      color: '#FFFFFF',
      size: 25,
      overlayColor: 'rgba(0,0,0,0.85)',
      closeOnTouch: false,
      loadingType: type, // 'Bubbles', 'DoubleBounce', 'Bars', 'Pulse', 'Spinner'
    });
  };

  // noinspection JSUnresolvedVariable
  showErrorAlert = response =>
    Alert.alert('Ops..', response.msg ? response.msg : response.message);
}
