import React from 'react';
import {
  Alert,
  LayoutAnimation,
  Platform,
  SafeAreaView,
  Text,
  TouchableOpacity,
  TouchableWithoutFeedback,
  View,
  ImageBackground,
  Image,
} from 'react-native';
import {styles} from '../assets/css/style';
import {Icons} from '../assets/Icons';
import {connect} from 'react-redux';
import {mapDispatchToProps, mapStateToProps} from '../reducers/Functions';
import {Porcent} from '../helper/Helper';
import renderIf from 'render-if';
import BaseComponent from '../components/BaseComponent';
import {clearAllData, retrieveData, storeData} from '../helper/AsyncStore';
import {ApiInfoModel, SmsAuthModel, UserModel} from '../models';
import {getCadastros} from '../providers/HttpRequests';
import Toast from 'react-native-simple-toast';

class HomePage extends BaseComponent {
  pQuantity;
  reload;
  open = false;

  /** @param props {{navigation: {getParam: function}}}*/
  constructor(props) {
    super(props);
    this.props.changeRefresh(false);
    this.reload = this.props.navigation.getParam('reload', false);
    this.getStoreData();

    this.state = {
      opacity: 1,
      opacityInfo: 0,
      fontSize: Porcent(1, 94),
    };
    // StatusBar.setBackgroundColor('#830000');
  }

  getStoreData = () => {
    retrieveData('clientes')
      .then(value => {
        if (value !== null && value !== undefined) {
          this.props.changeClientes(JSON.parse(value));
        }
      })
      .catch(err => {
        console.warn(err);
      });
      retrieveData('tipopessoas')
      .then(value => {
        if (value !== null && value !== undefined) {
          this.props.changeTipopessoas(JSON.parse(value));
        }
      })
      .catch(err => {
        console.warn(err);
      });
      retrieveData('segmentos')
      .then(value => {
        if (value !== null && value !== undefined) {
          this.props.changeSegmentos(JSON.parse(value));
        }
      })
      .catch(err => {
        console.warn(err);
      });
      retrieveData('estados')
      .then(value => {
        if (value !== null && value !== undefined) {
          this.props.changeEstados(JSON.parse(value));
        }
      })
      .catch(err => {
        console.warn(err);
      });
      retrieveData('cidades')
      .then(value => {
        if (value !== null && value !== undefined) {
          this.props.changeCidades(JSON.parse(value));
        }
      })
      .catch(err => {
        console.warn(err);
      });
      retrieveData('bairros')
      .then(value => {
        if (value !== null && value !== undefined) {
          this.props.changeBairros(JSON.parse(value));
        }
      })
      .catch(err => {
        console.warn(err);
      });
      retrieveData('ruas')
      .then(value => {
        if (value !== null && value !== undefined) {
          this.props.changeRuas(JSON.parse(value));
        }
      })
      .catch(err => {
        console.warn(err);
      });
      retrieveData('telefonetipos')
      .then(value => {
        if (value !== null && value !== undefined) {
          this.props.changeTelefonetipos(JSON.parse(value));
        }
      })
      .catch(err => {
        console.warn(err);
      });

  };

  componentDidMount(): void {
    this.refreshOff();
    if (this.reload) {
      this.refreshOff();
    }
  }

  _onRefresh = () => {
    this.props.changeRefresh(true);
    this.refreshOff;
  };

  refreshOff = () => this.props.changeRefresh(false);

  logout = (prompt = true) => {
    let callback = () => {
      this.resetReducers();
      clearAllData()
        .then(async () => {
          await this.props.navigation.navigate('AuthLoading');
        })
        .catch(() => {
          Alert.alert(
            'Erro!',
            'Ocorreu um erro desconhecido ao sair, tenta novamente?',
          );
        });
    };
    if (prompt) {
      Alert.alert(
        'Tem certeza?',
        'Deseja realmente sair desta conta?',
        [
          {text: 'Não', style: 'negative'},
          {
            text: 'Sim',
            onPress: callback,
          },
        ],
        {cancelable: false},
      );
    } else {
      callback();
    }
  };
  modal = () => {
    let name = this.props.userName.split(' ');

    let padding = Platform.OS === 'ios' ? {paddingVertical: 0} : styles.pad5;

    // noinspection ThisExpressionReferencesGlobalObjectJS
    return (
      <SafeAreaView
        style={{backgroundColor: '#820300'}}
        forceInset={{bottom: 'never'}}>
        <View
          style={[
            styles.padH15,
            padding,
            {
              flexDirection: 'row',
              justifyContent: 'space-between',
              alignItems: 'center',
            },
          ]}>
          <TouchableOpacity
            onPress={async () => {
              await this.props.navigation.navigate('History');
            }}
            style={styles.padH5}>
            <Icons
              style={[styles.menuHeaderIcon, {opacity: 0.0}]}
              name="book"
              color="white"
              size={25}
            />
          </TouchableOpacity>
          <TouchableOpacity
            style={[{alignItems: 'center'}]}
            onPress={() => {
              this.modalUser();
            }}>
            <View style={{flexDirection: 'row'}}>
              <Icons
                style={[
                  styles.menuHeaderName,
                  {fontSize: this.state.fontSize},
                  styles.padH5,
                ]}
                name={'person'}
              />
              <Text
                style={[
                  styles.menuHeaderName,
                  {fontSize: this.state.fontSize},
                ]}>
                {name[0]}
              </Text>
            </View>
            {renderIf(this.open)(
              <View
                style={[
                  {flexDirection: 'column', opacity: this.state.opacityInfo},
                  styles.pad10,
                ]}>
                <Text
                  style={{
                    fontSize: Porcent(1, 96),
                    color: 'white',
                    textAlign: 'center',
                  }}>
                  {' '}
                  {this.props.userName}
                </Text>
                <Text
                  style={{
                    fontSize: Porcent(1, 96),
                    color: 'white',
                    textAlign: 'center',
                  }}>
                  {' '}
                  {this.props.userPhone}
                </Text>
              </View>,
            )}
          </TouchableOpacity>
          <TouchableOpacity onPress={this.logout} style={styles.padH5}>
            <Icons
              style={[styles.menuHeaderIcon, {opacity: this.state.opacity}]}
              name="exit"
              color="white"
              size={25}
            />
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  };

  resetReducers = () => {
    this.props.changeUserState(UserModel);
    this.props.changeApiInfo(ApiInfoModel);
    this.props.changeSmsAuthState(SmsAuthModel);
    this.props.changeRefresh(false);
    this.props.changeVeiculos(null);
    this.props.changeClientes(null);
    this.props.changeProdutos(null);
    this.props.changeOperacoes(null);
    this.props.changePagamentos(null);
    this.props.changePedido(null);
    this.props.changeRuas(null);
    this.props.changeBairros(null);
    this.props.changeCidades(null);
    this.props.changeCliente(null);
  };

  modalUser = () => {
    LayoutAnimation.spring();

    if (this.open) {
      this.open = false;
      this.setState({
        opacity: 1,
        opacityInfo: 0,
        fontSize: Porcent(1, 94),
      });
    } else {
      this.open = true;
      this.setState(
        {
          opacity: 0,
          opacityInfo: 1,
        },
        () => {
          this.setState({
            fontSize: Porcent(1, 91),
          });
        },
      );
    }
  };

  render() {
    // noinspection ThisExpressionReferencesGlobalObjectJS
    return (
      <View style={styles.containerMenu}>
        {this.modal()}

        <ImageBackground
          style={styles.homePage}
          source={require('../assets/imgs/homebackground.png')}
          resizeMode="stretch">
          <Text style={[styles.pad5, styles.menuHeaderName]}>
            Escolha o veículo
          </Text>
          <View style={[styles.formsMenu, styles.simpleShadow]} elevation={5}>
            <View style={[styles.addressInfoMenu, styles.pad5, styles.padH10]}>
              <Text style={styles.addressInfoTitle}>
                {this.props.Veiculos && this.props.veiculoId
                  ? this.props.Veiculos.find(x => x.id === this.props.veiculoId)
                      .placa
                  : ''}
              </Text>
              <Text style={styles.addressInfo}>
                {this.props.Veiculos && this.props.veiculoId
                  ? this.props.Veiculos.find(x => x.id === this.props.veiculoId)
                      .descricao
                  : ''}
              </Text>
            </View>
            <TouchableWithoutFeedback
              onPress={async () => {
                await this.props.navigation.navigate('Veiculo');
              }}>
              <View style={styles.addressAlterMenu}>
                <Icons name="repeat" color="black" size={25} />
                <Text style={styles.addressInfo}>Alterar</Text>
              </View>
            </TouchableWithoutFeedback>
          </View>
          <View style={[styles.homePageViewLogo, styles.versionInfo]}>
            <Image
              style={styles.homePageLogo}
              source={require('../assets/imgs/homelogo.png')}
              resizeMode="stretch"
            />
            <Text style={styles.addressInfo}>V2.9</Text>
          </View>
          <View style={styles.homePageViewButtons}>
            <View
              // eslint-disable-next-line react-native/no-inline-styles
              style={{
                flex: 1,
                flexDirection: 'row',
                justifyContent: 'space-around',
                width: Porcent(1, 0),
                alignItems: 'center',
              }}>
              <TouchableOpacity
                style={styles.homePageButtons}
                onPress={async () => {
                  getCadastros(this.props.userPhone).then(result => {
                    this.props.changeClientes(result.data.clientes);
                    this.props.changeSegmentos(result.data.segmentos);
                    this.props.changeTipopessoas(result.data.tipopessoas);
                    this.props.changeEstados(result.data.estados);
                    this.props.changeCidades(result.data.cidades);
                    this.props.changeBairros(result.data.bairros);
                    this.props.changeRuas(result.data.ruas);
                    this.props.changeTelefonetipos(result.data.telefonetipos);
                    Toast.show(
                      'Cadastros carregados com sucesso!',
                      Toast.LONG,
                      Toast.BOTTOM,
                    );
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
                }}>
                <Image
                  style={styles.homePageButton}
                  source={require('../assets/imgs/btnrecarregar.png')}
                  resizeMode="stretch"
                />
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.homePageButtons}
                onPress={async () => {
                  //console.log('aaa');
                  //console.log(this.props);
                  await this.props.navigation.navigate('Cliente');
                }}>
                <Image
                  style={styles.homePageButton}
                  source={require('../assets/imgs/btnclientes.png')}
                  resizeMode="stretch"
                />
              </TouchableOpacity>
            </View>
          </View>
          <View style={styles.homePageViewButtons}>
            <View
              style={{
                flex: 1,
                flexDirection: 'row',
                justifyContent: 'space-around',
                width: Porcent(1, 0),
                alignItems: 'center',
              }}>
              <TouchableOpacity
                style={styles.homePageButtons}
                onPress={async () => {
                  this.props.changeCliente(null);
                  await this.props.navigation.navigate('Pedido');
                }}>
                <Image
                  style={styles.homePageButton}
                  source={require('../assets/imgs/btnpedidos.png')}
                  resizeMode="stretch"
                />
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.homePageButtons}
                onPress={async () => {
                  await this.props.navigation.navigate('ReportPedidos');
                }}>
                <Image
                  style={styles.homePageButton}
                  source={require('../assets/imgs/btnconsultar.png')}
                  resizeMode="stretch"
                />
              </TouchableOpacity>
            </View>
          </View>
        </ImageBackground>
      </View>
    );
  }
}

export default connect(
  mapStateToProps,
  mapDispatchToProps,
)(HomePage);
