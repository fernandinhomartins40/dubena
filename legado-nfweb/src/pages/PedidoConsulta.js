/* eslint-disable react-native/no-inline-styles */
import React from 'react';
import {
  Text,
  View,
  ScrollView,
  SafeAreaView,
  RefreshControl,
  Image,
} from 'react-native';
import {styles} from '../assets/css/style';
import {Hr, HeaderSimple} from '../components/Views';
import {connect} from 'react-redux';
import {mapDispatchToProps, mapStateToProps} from '../reducers/Functions';
import BaseComponent from '../components/BaseComponent';
import {Icons, IconsMCI} from '../assets/Icons';
import {
  visualizarBoleto,
  getPedidoConsulta,
  getNfeConsulta,
  getPedidoDuplicata,
  baixarDanfePDF,
  baixarBoletoPDF,
} from '../providers/HttpRequests';
import {Porcent, formataDecimal} from '../helper/Helper';
import {
  Menu,
  MenuOptions,
  MenuOption,
  MenuTrigger,
} from 'react-native-popup-menu';
import {
  PrintDanfe,
  PrintBoleto,
  PrintDuplicata,
} from '../components/PrintComponent';
import { Alert } from 'react-native';

/**
 * @param props {{navigation: {navigate: function}}}
 * @param address
 */
class PedidoConsultaPage extends BaseComponent {
  constructor(props) {
    super(props);
    this.state = {
      pedido: {id: ''},
      back: false,
    };
  }

  componentDidMount(): void {
    this.willFocusSubscription = this.props.navigation.addListener(
      'willFocus',
      () => {
        this.setState({back: this.props.Pedido.back});
        this.getPedidoConsulta();
      },
    );
  }
  componentWillUnmount() {
    this.willFocusSubscription.remove();
  }

  _onRefresh = () => {
    this.props.changeRefresh(true);
    this.getPedidoConsulta();
  };

  refreshOff = () => this.props.changeRefresh(false);

  getPedidoConsulta = () => {
    getPedidoConsulta(this.props.Pedido.id, this.props.userId)
      .then(result => {
        if (typeof result.status === 'string' && result.status === 'OK') {
          //console.log(result.data[0]);
          if(result.data != undefined) {
            if(result.data.length == 0){
              Alert.alert('Pedido não encontrado para consulta!');
              this.props.navigation.navigate('Home');
            } else {
              this.setState({
                pedido: result.data[0],
              });
              this.props.changePedido(result.data[0]);
              this.refreshOff();
            }
          } else {
            Alert.alert('Pedido não encontrado para consulta!');
            this.props.navigation.navigate('Home');
          }
        } else {
          Alert.alert(result);
          this.props.navigation.navigate('Home');
        }
      })
      .catch(e => {
        //console.log(e);
        this.props.navigation.navigate('Home');
      });
  };

  imprimirDanfe = () => {
    getNfeConsulta(this.props.Pedido.nfce_id, this.props.userId)
      .then(result => {
        if (typeof result.status === 'string' && result.status === 'OK') {
          //console.log(result.data[0]);
          PrintDanfe.printDanfe(result.data[0], PrintDanfe.SHORT);
        } else {
          this.showErrorAlert(result);
        }
      })
      .catch(e => {
        //console.log(e);
      });
  };

  baixarDanfe = () => {
    baixarDanfePDF(this.props.Pedido.nfce_id);
  };

  baixarBoleto = () => {
    baixarBoletoPDF(this.props.Pedido.id, 3);
  };


  imprimirBoleto = () => {
    visualizarBoleto(this.props.Pedido.id, 2)
      .then(result => {
        if (typeof result.status === 'string' && result.status === 'OK') {
          //console.log(JSON.stringify(result.data));
          PrintBoleto.printBoleto(result.data, PrintDanfe.SHORT);
        } else {
          this.showErrorAlert(result);
        }
      })
      .catch(e => {
        //console.log(e);
      });
  };

  imprimirDuplicata = () => {
    getPedidoDuplicata(this.props.Pedido.id, this.props.userId)
      .then(result => {
        if (typeof result.status === 'string' && result.status === 'OK') {
          console.log(result.data[0]);
          PrintDuplicata.printDuplicata(result.data[0], PrintDanfe.SHORT);
        } else {
          this.showErrorAlert(result);
        }
      })
      .catch(e => {
        //console.log(e);
      });
  };

  emitirDanfe = tipo => {
    if (tipo == '1') {
      let ped = this.props.Pedido;
      ped.tipopdf = 'NF';
      this.props.changePedido(ped);
      this.props.navigation.navigate('VerPDF');
    } else if(tipo == '2') {
      this.imprimirDanfe();
    } else if(tipo == '3') {
      this.baixarDanfe();
    } 
  };

  emitirDuplicata = tipo => {
    if (tipo == '1') {
      let ped = this.props.Pedido;
      ped.tipopdf = 'DUP';
      this.props.changePedido(ped);
      this.props.navigation.navigate('VerPDF');
    } else {
      this.imprimirDuplicata();
    }
  };

  emitirBoleto = tipo => {
    if (tipo == '1') {
      let ped = this.props.Pedido;
      ped.tipopdf = 'Boleto';
      this.props.changePedido(ped);
      this.props.navigation.navigate('VerPDF');
    } else if(tipo == '2') {
      this.imprimirBoleto();
    } else if(tipo == '3') {
      this.baixarBoleto();
    }
  };

  listProdutos = items => {
    return (
      /**
       * @param data {{reseller, produtos}}
       */
      items.map((data, i) => {
        return (
          <View key={i} style={[styles.padH10]}>
            <View style={[styles.flexDirRow, {flex: 1}]}>
              <Text style={[styles.textOrder, {flex: 7}]}>
                {data.quantidade}
              </Text>
              <Text style={[styles.textOrder, {flex: 65}]}>
                {data.produto_descricao}
              </Text>
              <Text style={[styles.textOrderNumber, {flex: 28}]}>
                {formataDecimal(parseFloat(data.valortotal), 2)}
              </Text>
            </View>
          </View>
        );
      })
    );
  };

  listTotal = () => {
    return (
      /**
       * @param data {{reseller, produtos}}
       */
      <View>
        <View style={[styles.padH10]}>
          <View style={[styles.flexDirRow, {flex: 1}]}>
            <Text style={[styles.textOrder, {flex: 7}]}> </Text>
            <Text style={[styles.textOrder, {flex: 65}]}>Total Produtos</Text>
            <Text style={[styles.textOrderNumber, {flex: 28}]}>
              {formataDecimal(
                parseFloat(this.state.pedido.valorvenda) +
                  parseFloat(this.state.pedido.valordesconto),
                2,
              )}
            </Text>
          </View>
        </View>
        <View style={[styles.padH10]}>
          <View style={[styles.flexDirRow, {flex: 1}]}>
            <Text style={[styles.textOrder, {flex: 7}]}> </Text>
            <Text style={[styles.textOrder, {flex: 65}]}>Desconto</Text>
            <Text style={[styles.textOrderNumber, {flex: 28}]}>
              {formataDecimal(parseFloat(this.state.pedido.valordesconto), 2)}
            </Text>
          </View>
        </View>
        <View style={[styles.padH10]}>
          <View style={[styles.flexDirRow, {flex: 1}]}>
            <Text style={[styles.textOrderBold, {flex: 7}]}> </Text>
            <Text style={[styles.textOrderBold, {flex: 65}]}>
              Valor Líquido
            </Text>
            <Text style={[styles.textOrderNumberBold, {flex: 28}]}>
              {formataDecimal(parseFloat(this.state.pedido.valorvenda), 2)}
            </Text>
          </View>
        </View>
      </View>
    );
  };

  listEmptyProdutos = () => {
    return (
      <View style={[styles.padH10]}>
        <View style={[styles.flexDirRow, {flex: 1}]}>
          <Text style={[styles.textOrder, {flex: 7}]}>{''}</Text>
          <Text style={[styles.textOrder, {flex: 65}]}>{''}</Text>
          <Text style={[styles.textOrder, {flex: 28}]}>{''}</Text>
        </View>
      </View>
    );
  };

  renderFooter = () => {
    return (
      <SafeAreaView style={[styles.menuFooter, styles.pad5]}>
        <View style={styles.homePageViewButtons}>
          <View
            style={{
              flex: 1,
              flexDirection: 'row',
              justifyContent: 'space-around',
              width: Porcent(1, 0),
              alignItems: 'center',
            }}>
            <Menu
              style={styles.orderButtons}
              onSelect={value => this.emitirDuplicata(value)}>
              <MenuTrigger>
                <Image
                  style={styles.orderButton}
                  source={require('../assets/imgs/btnduplicata.png')}
                  resizeMode="cover"
                />
              </MenuTrigger>
              <MenuOptions optionsContainerStyle={[{marginTop: -60}]}>
                <Text style={[styles.menuTitle]}>Duplicata</Text>
                {/*}
                <View style={[styles.padH10]}>
                  <View>
                    <MenuOption value={'1'}>
                      <Text style={styles.menuOptions}>Visualizar</Text>
                    </MenuOption>
                  </View>
                </View>
                */}
                <View style={[styles.padH10]}>
                  <View>
                    <MenuOption value={'2'}>
                      <Text style={styles.menuOptions}>Imprimir</Text>
                    </MenuOption>
                  </View>
                </View>
              </MenuOptions>
            </Menu>
            <Menu
              style={styles.orderButtons}
              onSelect={value => this.emitirDanfe(value)}>
              <MenuTrigger>
                <Image
                  style={styles.orderButton}
                  source={require('../assets/imgs/btnnfe.png')}
                  resizeMode="cover"
                />
              </MenuTrigger>
              <MenuOptions optionsContainerStyle={[{marginTop: -60}]}>
                <Text
                  style={[
                    styles.menuTitle,
                    this.state.pedido.nfnum == null && {display: 'none'},
                  ]}>
                  Nota Fiscal
                </Text>
                <View
                  style={[
                    styles.padH10,
                    this.state.pedido.nfnum == null && {display: 'none'},
                  ]}>
                  <View>
                    <MenuOption value={'1'}>
                      <Text style={styles.menuOptions}>Visualizar Danfe</Text>
                    </MenuOption>
                  </View>
                </View>
                <View
                  style={[
                    styles.padH10,
                    this.state.pedido.nfnum == null && {display: 'none'},
                  ]}>
                  <View>
                    <MenuOption value={'2'}>
                      <Text style={styles.menuOptions}>Imprimir Danfe</Text>
                    </MenuOption>
                  </View>
                </View>
                <View
                  style={[
                    styles.padH10,
                    this.state.pedido.nfnum == null && {display: 'none'},
                  ]}>
                  <View>
                    <MenuOption value={'3'}>
                      <Text style={styles.menuOptions}>Baixar Danfe</Text>
                    </MenuOption>
                  </View>
                </View>
              </MenuOptions>
            </Menu>
            <Menu
              style={styles.orderButtons}
              onSelect={value => this.emitirBoleto(value)}>
              <MenuTrigger>
                <Image
                  style={styles.orderButton}
                  source={require('../assets/imgs/btnboletos.png')}
                  resizeMode="stretch"
                />
              </MenuTrigger>
              <MenuOptions optionsContainerStyle={{marginTop: -60}}>
                <Text
                  style={[
                    styles.menuTitle,
                    this.state.pedido.boleto_id == null && {display: 'none'},
                  ]}>
                  Boleto
                </Text>
                <View
                  style={[
                    styles.padH10,
                    this.state.pedido.boleto_id == null && {display: 'none'},
                  ]}>
                  <View>
                    <MenuOption value={'1'}>
                      <Text style={styles.menuOptions}>Visualizar Boleto</Text>
                    </MenuOption>
                  </View>
                </View>
                <View
                  style={[
                    styles.padH10,
                    this.state.pedido.boleto_id == null && {display: 'none'},
                  ]}>
                  <View>
                    <MenuOption value={'2'}>
                      <Text style={styles.menuOptions}>Imprimir Boleto</Text>
                    </MenuOption>
                  </View>
                </View>
                <View
                  style={[
                    styles.padH10,
                    this.state.pedido.boleto_id == null && {display: 'none'},
                  ]}>
                  <View>
                    <MenuOption value={'3'}>
                      <Text style={styles.menuOptions}>Baixar Boleto</Text>
                    </MenuOption>
                  </View>
                </View>
              </MenuOptions>
            </Menu>
          </View>
        </View>
      </SafeAreaView>
    );
  };

  render() {
    let pedido = this.state.pedido;
    return (
      <View style={[styles.containerMenu]}>
        <HeaderSimple
          name={'Pedido'}
          toBack={() => {
            if (this.state.back) {
              this.props.navigation.goBack();
            } else {
              this.props.navigation.navigate('Home');
            }
          }}
        />
        <ScrollView
          style={styles.scrollViewMenu}
          colors={'#830000'}
          refreshControl={
            <RefreshControl
              refreshing={!!this.props.refreshing}
              onRefresh={this._onRefresh}
            />
          }
          contentContainerStyle={{
            justifyContent: 'space-evenly',
            flexDirection: 'column',
            alignItems: 'center',
          }}>
          <View style={[styles.subContainerOrder, styles.pad15]}>
            <View
              style={[
                styles.formsConfirmOrder,
                styles.simpleShadow,
                styles.pad5,
              ]}
              elevation={5}>
              <View>
                <View
                  style={[
                    styles.viewTitleConfirmOrder,
                    styles.pad5,
                    styles.padH10,
                  ]}>
                  <IconsMCI
                    style={[
                      styles.titleIconConfirmOrder,
                      styles.padH5,
                      {flex: 10},
                    ]}
                    name={'file-document'}
                  />
                  <Text
                    style={[
                      styles.titleConfirmOrder,
                      styles.padH5,
                      {flex: 70},
                    ]}>
                    {this.state.pedido.id == ''
                      ? ''
                      : 'Pedido: ' +
                        (this.state.pedido.id + '').padStart(6, '0')}
                  </Text>
                  <View style={[styles.iconOrderView, {flex: 10}]} />
                  <View style={[styles.iconOrderView, {flex: 10}]} />
                </View>
                <Hr />
                <View style={[styles.viewTextConfirmOrder, styles.pad5]}>
                  <View>
                    <Text style={[styles.titleConfirmOrder, styles.padH15]}>
                      {this.state.pedido.nome}
                    </Text>
                    <Text style={[styles.textConfirmOrder, styles.padH15]}>
                      {this.state.pedido.datahoraacao}
                    </Text>
                    <Text style={[styles.textConfirmOrder, styles.padH15]}>
                      {this.state.pedido.endereco}
                    </Text>
                    <Text style={[styles.textConfirmOrderBold, styles.padH15]}>
                      {this.state.pedido.condicao_pagamento}
                    </Text>
                    <Text style={[styles.textConfirmOrderBold, styles.padH15]}>
                      {this.state.pedido.nfnum}
                    </Text>
                  </View>
                </View>
              </View>
              <View
                style={[
                  styles.viewTextConfirmOrder,
                  styles.pad5,
                  this.state.pedido.cli_obs == null && {display: 'none'},
                ]}>
                <View
                  style={[
                    styles.viewTitleConfirmOrder,
                    styles.pad5,
                    styles.padH10,
                  ]}>
                  <Icons
                    style={[styles.titleIconConfirmOrder, styles.padH5]}
                    name={'clipboard'}
                  />
                  <Text style={[styles.titleConfirmOrder, styles.padH5]}>
                    Observações
                  </Text>
                </View>
                <Hr />
                <View style={[styles.viewTextConfirmOrder, styles.pad5]}>
                  <Text style={[styles.textConfirmOrder, styles.padH15]}>
                    {this.state.pedido.cli_obs}
                  </Text>
                </View>
              </View>
              <View>
                <View>
                  <View
                    style={[
                      styles.viewTitleConfirmOrder,
                      styles.pad5,
                      styles.padH10,
                    ]}>
                    <Icons
                      style={[styles.titleIconConfirmOrder, styles.padH5]}
                      name={'cart'}
                    />
                    <Text style={[styles.titleConfirmOrder, styles.padH5]}>
                      Produtos
                    </Text>
                  </View>
                  <Hr />
                  <View style={[styles.viewTextConfirmOrder, styles.pad5]}>
                    {this.state.pedido.items === undefined
                      ? this.listEmptyProdutos()
                      : this.listProdutos(this.state.pedido.items)}
                  </View>
                  <Hr />
                  <View style={[styles.viewTextConfirmOrder, styles.pad5]}>
                    {this.listTotal()}
                  </View>
                </View>
              </View>
            </View>
          </View>
        </ScrollView>
        {this.renderFooter()}
      </View>
    );
  }
}

export default connect(
  mapStateToProps,
  mapDispatchToProps,
)(PedidoConsultaPage);
