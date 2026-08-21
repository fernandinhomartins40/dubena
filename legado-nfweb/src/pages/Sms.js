import React from 'react';
import {Alert, BackHandler, Platform, Text, View} from 'react-native';

import firebase, * as Firebase from 'react-native-firebase';

import {storeData} from '../helper/AsyncStore';
import {ButtonOliva, Header, Input} from '../components/Views';
import {styles} from '../assets/css/style';
import {getAccount, getCadastros} from '../providers/HttpRequests';
import {mapDispatchToProps, mapStateToProps} from '../reducers/Functions';
import {connect} from 'react-redux';
import {onlyNumbers} from '../helper/Helper';
import {SmsAuthModel} from '../models';
import {KeyboardAwareScrollView} from 'react-native-keyboard-aware-scroll-view';
import BaseComponent from '../components/BaseComponent';
import {constants} from '../helper/Constants';

class SmsPage extends BaseComponent {
  /** @type {{confirm: function}}*/
  confirmResult = null;
  firebaseApp: Firebase = null;
  stopped: false;
  unsubscribe;

  async componentDidMount() {
    this.props.changeSmsAuthState(this.changeState());

    if (
      !constants.DEBUG_MODE ||
      (!this.props.userName === 'Usuario' &&
        this.props.userPhone === '(99) 99999-9999')
    ) {
      this.stopped = false;
      // noinspection JSUnresolvedFunction
      this.firebaseApp = firebase.app();

      try {
        // noinspection JSUnresolvedFunction
        await firebase.auth().signOut();
      } catch (e) {
        //console.log(e);
      }
      this.firebaseApp.auth.languageCode = constants.APP_LANG;
      console.log('vai chamar SendSms');
      this.sendSms();
      BackHandler.addEventListener('hardwareBackPress', this.handleBackPress);

      if (Platform.OS === 'android') {
        // noinspection JSUnresolvedFunction
        this.unsubscribe = firebase.auth().onAuthStateChanged(async user => {
          if (user) {
            await this.loginApi();
          }
        });
      }
    }
  }

  componentWillUnmount(): void {
    if (this.unsubscribe) this.unsubscribe();
    this.resetTimer();
    if (
      !constants.DEBUG_MODE ||
      (this.props.userName === 'Usuario' &&
        this.props.userPhone === '(99) 99999-9999')
    ) {
      BackHandler.removeEventListener(
        'hardwareBackPress',
        this.handleBackPress,
      );
    }
  }

  resetTimer = (goBack = true) => {
    this.stopped = true;
    this.props.changeSmsAuthState(this.changeState());
    if (goBack) {
      this.props.navigation.goBack();
    }
  };

  handleBackPress = () => {
    this.resetTimer();
    return true;
  };

  changeState(value, key = null) {
    let state;
    if (key === null) {
      state = SmsAuthModel;
    } else {
      state = {
        message: this.props.smsAuth.message,
        smsCode: this.props.smsAuth.smsCode,
        timer: this.props.smsAuth.timer,
      };
      state[key] = value;
    }
    return state;
  }

  changeTimer = () => {
    if (this.stopped) {
      return;
    }
    this.props.changeSmsAuthState(
      this.changeState(this.props.smsAuth.timer - 1, 'timer'),
    );
    if (this.props.smsAuth.timer > 0 && !this.stopped) {
      setTimeout(this.changeTimer, 1000);
    }
  };

  sendSms = () => {
    if (
      constants.DEBUG_MODE ||
      (this.props.userName === 'Usuario' &&
        this.props.userPhone === '(99) 99999-9999')
    )
      return;

    if (this.props.smsAuth.timer > 0) {
      Alert.alert(
        'Ops..',
        'Aguarde ' + this.props.smsAuth.timer + ' antes de reenviar o código',
      );
      return;
    }
    this.props.changeSmsAuthState(this.changeState(30, 'timer'));

    let callback = message => {
      this.props.changeSmsAuthState(this.changeState(message, 'message'));
    };

    const phoneNumber = '+55' + onlyNumbers(this.props.userPhone);
    this.firebaseApp
      .auth()
      .signInWithPhoneNumber(phoneNumber)
      .then(confirmResult => {
        this.confirmResult = confirmResult;
        let message =
          'Um SMS com o código de verificação foi enviado para o número ' +
          this.props.userPhone +
          '. Aguarde o recebimento e informe o código abaixo.';
        this.changeTimer();
        callback(message);
      })
      .catch(error => {
        this.resetTimer(false);
        console.log(error);
        callback(`Erro ao enviar sms: ${error.message}`)
        //callback(
        //  'Erro ao enviar sms: Verifique sua conexão com a internet e tente novamente mais tarde!',
        //);
      });
  };

  checkCode = () => {
    console.log('checkCode');
    if (
      constants.DEBUG_MODE ||
      (this.props.userName === 'Usuario' &&
        this.props.userPhone === '(99) 99999-9999')
    ) {
      this.loginApi().catch(e => {
        Alert.alert('Erro: ' + e.message);
      });
    } else {
      console.log('1');
      if (this.confirmResult && this.props.smsAuth.smsCode) {
        console.log('22');
        this._showLoader();
        console.log('333');
        console.log(this.props.smsAuth.smsCode);
        this.confirmResult
          .confirm(this.props.smsAuth.smsCode)
          .then(this.loginApi)
          .catch(error => {
            console.log('erro no ssm');
            console.log('error');
            this._hideLoader();
            let message;
            switch (error.code) {
              case 'auth/invalid-phone-number':
                message = 'Número de telefone inválido';
                break;
              case 'auth/unknown':
                message =
                  'Você foi bloqueado por muitas tentativas de verificação do SMS, pedimos desculpas pelo inconveniente.';
                break;
              case 'auth/session-expired':
                message =
                  'Código de verificação expirado. Isso ocorre quando você demora muito tempo para informar o código do sms.';
                break;
              case 'auth/invalid-verification-code':
                message =
                  'Código de verificação inválido. Informe o código correto ou clique em "Reenviar"';
                break;
              default:
                message = `Erro desconhecido ao confirmar código: ${
                  error.message
                }`;
            }
            this.resetTimer(false);
            this.props.changeSmsAuthState(this.changeState(message, 'message'));
          });
      }
    }
  };

  loginApi = () => {
    console.log('loginApi');
    console.log(this.props.userPhone);
    getAccount(this.props.userPhone, this._hideLoader).then(response => {
      console.log(response);
      this._hideLoader();
      this.switchLoginResponse(response);
    });
  }

  switchLoginResponse = response => {
    console.log('switchLoginResponse');
    this.resetTimer(false);
    //console.log('login');
    console.log(response);
    //console.log(response);
    switch (response.status) {
      case 'OK':
        let data = {
          userName: response.data.nome,
          userPhone: response.data.telefone,
          userId: response.data.id,
          registrationId: '1',
        };

        storeData({
          key: 'userData',
          data: JSON.stringify(data),
        }).then(() => {
          // noinspection JSIgnoredPromiseFromCall
          getCadastros(this.props.userPhone).then(result => {
            this.props.changeClientes(result.data.clientes);
            this.props.changeSegmentos(result.data.segmentos);
            this.props.changeTipopessoas(result.data.tipopessoas);
            this.props.changeEstados(result.data.estados);
            this.props.changeCidades(result.data.cidades);
            this.props.changeBairros(result.data.bairros);
            this.props.changeRuas(result.data.ruas);
            this.props.changeTelefonetipos(result.data.telefonetipos);
            storeData({
              key: 'clientes',
              data: JSON.stringify(result.data.clientes),
            });
            storeData({
              key: 'segmentos',
              data: JSON.stringify(result.data.segmentos),
            });
            storeData({
              key: 'tipopessoas',
              data: JSON.stringify(result.data.tipopessoas),
            });
            storeData({
              key: 'estados',
              data: JSON.stringify(result.data.estados),
            });
            storeData({
              key: 'cidades',
              data: JSON.stringify(result.data.cidades),
            });
            storeData({
              key: 'bairros',
              data: JSON.stringify(result.data.bairros),
            });
            storeData({
              key: 'ruas',
              data: JSON.stringify(result.data.ruas),
            });
            storeData({
              key: 'telefonetipos',
              data: JSON.stringify(result.data.telefonetipos),
            });
          });

          this.props.navigation.navigate('AuthLoading', {redirect: true});
        });
        break;
      case 'OPS':
        // noinspection JSIgnoredPromiseFromCall
        Alert.alert('Ops..', response.msg ? response.msg : response.message);
        this.props.navigation.navigate('Login');
        break;
      default:
        // noinspection JSUnresolvedVariable
        Alert.alert('Ops..', response.msg ? response.msg : response.message);
    }
  };

  smsRender = () => {
    return (
      <View
        style={{
          flex: 1,
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center',
          backgroundColor: '#e3e3e3',
        }}>
        <View style={styles.pad15}>
          <Text style={styles.infoText}>{this.props.smsAuth.message}</Text>
        </View>
        <View style={styles.pad10}>
          <View style={[styles.forms, styles.simpleShadow]} elevation={5}>
            <Input
              placeText={'000000'}
              keyboardType={'numeric'}
              textData={this.props.smsAuth.smsCode}
              whenChangeText={data =>
                this.props.changeSmsAuthState(this.changeState(data, 'smsCode'))
              }
              sizeText={6}
            />
          </View>
        </View>
        <View style={styles.pad15}>
          <ButtonOliva name={'Confirmar'} clicked={this.checkCode} />
        </View>
        <View style={[styles.pad15, {justifyContent: 'center'}]}>
          <Text style={styles.resendText}>
            Não recebeu o código?
            <Text style={styles.resendButton} onPress={this.sendSms}>
              {' '}
              Reenviar
            </Text>{' '}
            em {this.props.smsAuth.timer}
          </Text>
        </View>
      </View>
    );
  };

  render() {
    let device = Platform.OS;

    if (device === 'ios') {
      // noinspection ThisExpressionReferencesGlobalObjectJS
      return (
        <View style={{backgroundColor: '#e3e3e3'}}>
          <Header
            name={'Confirmação por Sms'}
            toBack={() => () => {
              this.resetTimer(Platform.OS === 'ios');
            }}
          />
          <KeyboardAwareScrollView
            contentContainerStyle={{
              backgroundColor: '#e3e3e3',
              width: '100%',
              height: '100%',
              justifyContent: 'center',
              alignItems: 'center',
            }}
            scrollEnabled={true}>
            {this.smsRender()}
          </KeyboardAwareScrollView>
        </View>
      );
    } else {
      // noinspection ThisExpressionReferencesGlobalObjectJS
      return (
        <>
          <Header
            name={'Confirmação por Sms'}
            toBack={() => () => {
              this.resetTimer(Platform.OS === 'ios');
            }}
          />
          <KeyboardAwareScrollView
            style={{flex: 1, backgroundColor: '#e3e3e3'}}
            contentContainerStyle={{
              flex: 1,
              backgroundColor: '#e3e3e3',
              justifyContent: 'center',
              alignItems: 'center',
            }}
            resetScrollToCoords={{x: 0, y: 0}}
            scrollEnabled={true}>
            {this.smsRender()}
          </KeyboardAwareScrollView>
        </>
      );
    }
  }
}

export default connect(
  mapStateToProps,
  mapDispatchToProps,
)(SmsPage);
