/* eslint-disable react-native/no-inline-styles */
import React from 'react';
import {Alert, Animated, View} from 'react-native';
import {connect} from 'react-redux';
import firebase from 'react-native-firebase';
import {styles} from '../assets/css/style';
import {checkInternetConnection, Porcent} from '../helper/Helper';
import {
  retrieveData,
  retrieveUser,
  storeData,
  storeToken,
} from '../helper/AsyncStore';
import {
  getApiToken,
  rootRequest,
  storePushRegistration,
  verifyToken,
  getCadastros,
} from '../providers/HttpRequests';
import {
  getUserState,
  mapDispatchToProps,
  mapStateToProps,
} from '../reducers/Functions';

// ? Ideia, criar as funções de pesquisa aqui, e fazer loop na animação
// ? Criar um state onde ira controlar tudo se chegou a informação espera o callback
// ? se não deixa a animação rolar, se der erro exibe na tela e manda para um retry
class RedirectMiddleware extends React.Component {
  /** @param props {{navigation: {getParam: function}}}*/
  constructor(props) {
    super(props);
    this.redirect = this.props.navigation.getParam('redirect', false);
    let delay = 500;
    if (this.redirect) {
      delay = 100;
    }
    this.animationLogo = new Animated.Value(0.5);
    this.animationConfig = {
      toValue: 1,
      friction: 1,
      delay: delay,
      useNativeDriver: true,
    };
  }

  async componentDidMount(): void {
    checkInternetConnection(
      () => {
        let callback = () => {
          //Permissions.request('location').then(response => {
          // O retorno pode vir:: , 'denied', 'restricted', or 'undetermined'
          //    if (response !== 'authorized') {
          //getUserMaps(false).catch((e) => console.log(e));
          //    }
          //});

          Animated.spring(this.animationLogo, this.animationConfig).start(
            () => {
              if (this.redirect) {
                this.login();
              } else {
                this.isLoggedIn();
              }
            },
          );
        };
        this.hasNotificationPermission()
          .then(callback)
          .catch(callback);
      },
      () => {
        this.props.navigation.navigate('Error');
      },
    );
  }

  login = () => {
    retrieveUser().then(user => {
      if (
        user &&
        user.hasOwnProperty('userName') &&
        user.hasOwnProperty('userId') &&
        user.userName &&
        user.userId
      ) {
        this.props.changeUserState(user);
        storePushRegistration(this.props.registrationId, user.userId);
        this.getAllData();
      } else {
        this.props.navigation.navigate('Login');
      }
    });
  };

  isLoggedIn = () => {
    retrieveData('tokenKey')
      .then(value => {
        if (value !== null && value !== undefined) {
          verifyToken(error => {
            if (error.httpStatus === 401) {
              this.getNewToken();
            } else {
              console.warn(error);
              this.props.navigation.navigate('Error');
            }
          }).then(this.login);
        } else {
          this.getNewToken();
        }
      })
      .catch(err => {
        console.warn(err);
      });
  };

  storeToken = result => {
    storeToken(result.data)
      .then(token => {
        this.props.changeApiInfo(token);
      })
      .catch(err => {
        console.warn(err.message);
      });
  };

  getNewToken = () => {
    getApiToken()
      .then(result => {
        if (result.status === 'OK') {
          this.storeToken(result);
          this.login();
        } else {
          this.props.navigation.navigate('Error');
        }
      })
      .catch(() => {
        this.props.navigation.navigate('Error');
      });
  };

  getAllData() {
    rootRequest(this.props.userPhone).then(result => {
      switch (result.status) {
        case 'OK':
          this.props.changeVeiculos(result.data.veiculos);
          this.props.changeProdutos(result.data.produtos);
          this.props.changePagamentos(result.data.pagamentos);
          this.props.changeOperacoes(result.data.operacoes);
          this.props.changeUserState(
            getUserState(
              this.props,
              result.data.colaborador.veiculo_id,
              'veiculoId',
            ),
          );
          this.props.changeUserState(
            getUserState(
              this.props,
              result.data.revenda.transportador_id,
              'transportadorId',
            ),
          );
          this.props.changeUserState(
            getUserState(
              this.props,
              result.data.revenda.presenca_comprador,
              'presencaComprador',
            ),
          );
          this.props.changeUserState(
            getUserState(
              this.props,
              result.data.revenda.modalidade_frete,
              'modalidadeFrete',
            ),
          );
          this.props.changeUserState(
            getUserState(this.props, result.data.revenda.setor_id, 'setorId'),
          );
          this.props.changeUserState(
            getUserState(
              this.props,
              result.data.revenda.setor_descricao,
              'setorDescricao',
            ),
          );
          this.props.changeUserState(
            getUserState(this.props, result.data.colaborador.uf, 'uf'),
          );
          this.props.changeUserState(
            getUserState(
              this.props,
              result.data.colaborador.setors[0].cidade_id,
              'cidade_id',
            ),
          );
          this.props.navigation.navigate('Home');
          break;
        case 'OPS':
          if (result.rejection === 'NOT_FOUND') {
            this.props.navigation.navigate('Login');
          } else if (result.rejection === 'DUPLICATED') {
            // noinspection JSIgnoredPromiseFromCall
            this.props.navigation.navigate('FormUser');
          } else {
            // noinspection JSUnresolvedVariable
            Alert.alert('Ops..', result.msg ? result.msg : result.message);
          }
          break;
        default:
          // noinspection JSUnresolvedVariable
          Alert.alert('Ops..', result.msg ? result.msg : result.message);
      }
    });
  }

  async hasNotificationPermission() {
    // noinspection JSUnresolvedFunction
    const enabled = await firebase.messaging().hasPermission();
    if (enabled) {
      return this.getToken();
    } else {
      return this.requestPermission();
    }
  }

  async getToken() {
    return new Promise(resolve => {
      retrieveUser('registrationId').then(storedToken => {
        // noinspection JSUnresolvedFunction
        const channel = new firebase.notifications.Android.Channel(
          'allDevicesApp',
          'allDevicesApp',
          firebase.notifications.Android.Importance.Max,
        ).setDescription('allDevicesApp');
        firebase.notifications().android.createChannel(channel);
        firebase
          .messaging()
          .getToken()
          .then(newToken => {
            this.savePushRegistration(storedToken, newToken, resolve);
          })
          .catch(e => {
            Alert.alert(
              'Erro',
              'Erro ao iniciar aplicação, verifique sua conexão e tente novamente: ' +
                e.message,
            );
            resolve();
          });
      });
    });
  }

  savePushRegistration = (storedToken, newToken, resolve) => {
    retrieveUser().then(user => {
      user.registrationId = newToken;
      storeData({key: 'userData', data: JSON.stringify(user)})
        .then(resolve)
        .catch(resolve);
    });
  };

  async requestPermission() {
    try {
      // noinspection JSUnresolvedFunction
      await firebase.messaging().requestPermission();
      return this.getToken();
    } catch (error) {
      //console.log('permission rejected');
      return Promise.reject(error.message);
    }
  }

  setBackGroundLocation = () => {};

  render = () => {
    const truckStyle = {
      flex: 1,
      justifyContent: 'center',
      alignItems: 'center',
      transform: [
        {
          scale: this.animationLogo,
        },
      ],
    };
    return (
      <View
        style={[
          {flexDirection: 'column', alignItems: 'center'},
          styles.containerWhite,
        ]}>
        <Animated.View style={[truckStyle]}>
          <Animated.Image
            source={require('../assets/imgs/home-logo.png')}
            style={[
              {
                // justifyContent: 'center',
                resizeMode: 'contain',
                width: Porcent(1, 20),
                height: Porcent(1, 45),
              },
            ]}
          />
          <Animated.Text
            style={{
              fontSize: Porcent(1, 95),
              fontWeight: 'bold',
              color: 'white',
              // justifyContent: 'center',
            }}
          />
        </Animated.View>
      </View>
    );
  };
}

export default connect(
  mapStateToProps,
  mapDispatchToProps,
)(RedirectMiddleware);
