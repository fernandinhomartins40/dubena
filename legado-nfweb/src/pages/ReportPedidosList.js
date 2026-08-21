import React from 'react';
import {
  RefreshControl,
  ScrollView,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import {styles} from '../assets/css/style';
import {HeaderSimple} from '../components/Views';
import {connect} from 'react-redux';
import {mapDispatchToProps, mapStateToProps} from '../reducers/Functions';
import BaseComponent from '../components/BaseComponent';
import {getPedidosReport} from '../providers/HttpRequests';
import renderIf from 'render-if';
import {formataDecimal, actualDateFormatted} from '../helper/Helper';

/**
 * @param props {{navigation: {navigate: function}}}
 * @param address
 */
class ReportPedidosListPage extends BaseComponent {
  constructor(props) {
    super(props);
    this.initialDate = this.props.navigation.getParam('initialDate', false);
    this.finalDate = this.props.navigation.getParam('finalDate', false);
    this.state = {
      pedidos: [],
      produtos: [],
      total: 0,
      desconto: 0,
    };
  }

  componentDidMount(): void {
    this.willFocusSubscription = this.props.navigation.addListener(
      'willFocus',
      async () => {
        this._onRefresh();
      },
    );
  }
  componentWillUnmount() {
    this.willFocusSubscription.remove();
  }

  _onRefresh = () => {
    this.props.changeRefresh(true);
    this.getPedidosReport();
  };

  refreshOff = () => this.props.changeRefresh(false);

  getPedidosReport = () => {
    getPedidosReport(this.props.userId, this.initialDate, this.finalDate)
      .then(result => {
        if (typeof result.status === 'string' && result.status === 'OK') {
          this.setState({
            pedidos: result.data,
            produtos: [],
            total: 0,
            desconto: 0,
          });
          this.refreshOff();
        } else {
          this.showErrorAlert(result);
          this.props.navigation.navigate('Home');
        }
      })
      .catch(e => {
        //console.log(e);
        this.props.navigation.navigate('Home');
      });
  };

  consultaPedido = data => {
    data.back = true;
    this.props.changePedido(data);
    this.props.navigation.navigate('PedidoConsulta');
  };

  listPedidos = () => {
    let pedidos = this.state.pedidos;
    this.state.produtos = [];
    this.state.total = 0;
    this.state.desconto = 0;
    return (
      /**
       * @param data {{reseller, produtos}}
       */
      pedidos.map((data, i) => {
        this.state.total += parseFloat(data.valorvenda);
        this.state.desconto += parseFloat(data.valordesconto);
        return (
          <TouchableOpacity
            onPress={async () => {
              this.consultaPedido(data);
            }}>
            <View key={i} style={[styles.padH10, styles.pad5]}>
              <View
                style={[
                  styles.forms,
                  styles.simpleShadow,
                  {flexDirection: 'row'},
                ]}
                elevation={5}>
                <View style={[styles.pad5, {flex: 1}]}>
                  <Text style={styles.textOrderTitle}>
                    {data.datahoraacao +
                      ' - Pedido nº ' +
                      (data.id + '').padStart(6, '0')}
                  </Text>
                  <Text style={styles.textOrderTitle}>{data.nome}</Text>
                  <Text style={styles.textOrder}>{data.endereco}</Text>
                  {this.listProdutos(data.items)}
                  {parseFloat(data.valordesconto) > 0
                    ? this.listDesconto(data)
                    : null}
                </View>
              </View>
            </View>
          </TouchableOpacity>
        );
      })
    );
  };
  listDesconto = data => {
    return (
      <View>
        <View style={[styles.padH10]}>
          <View style={[styles.flexDirRow, {flex: 1}]}>
            <Text style={[styles.textOrder, {flex: 7}]} />
            <Text style={[styles.textOrder, {flex: 60}]}>Desconto</Text>
            <Text style={[styles.textOrderNumber, {flex: 33}]}>
              {formataDecimal(parseFloat(data.valordesconto))}
            </Text>
          </View>
        </View>
        <View style={[styles.padH10]}>
          <View style={[styles.flexDirRow, {flex: 1}]}>
            <Text style={[styles.textOrder, {flex: 7}]} />
            <Text style={[styles.textOrder, {flex: 60}]}>Líquido</Text>
            <Text style={[styles.textOrderNumber, {flex: 33}]}>
              {formataDecimal(parseFloat(data.valorvenda))}
            </Text>
          </View>
        </View>
      </View>
    );
  };
  listProdutos = items => {
    return (
      /**
       * @param data {{reseller, produtos}}
       */
      items.map((data, i) => {
        if (
          this.state.produtos.find(x => x.produto_id === data.produto_id) ===
          undefined
        ) {
          this.state.produtos.push({
            produto_id: data.produto_id,
            produto_descricao: data.produto_descricao,
            quantidade: parseFloat(data.quantidade),
            valortotal: parseFloat(data.valortotal),
          });
        } else {
          this.state.produtos.find(
            x => x.produto_id === data.produto_id,
          ).quantidade += parseFloat(data.quantidade);
          this.state.produtos.find(
            x => x.produto_id === data.produto_id,
          ).valortotal += parseFloat(data.valortotal);
        }
        return (
          <View key={i} style={[styles.padH10]}>
            <View style={[styles.flexDirRow, {flex: 1}]}>
              <Text style={[styles.textOrder, {flex: 7}]}>
                {data.quantidade}
              </Text>
              <Text style={[styles.textOrder, {flex: 60}]}>
                {data.produto_descricao}
              </Text>
              <Text style={[styles.textOrderNumber, {flex: 33}]}>
                {formataDecimal(parseFloat(data.valortotal))}
              </Text>
            </View>
          </View>
        );
      })
    );
  };
  renderTotal = () => {
    return (
      /**
       * @param data {{reseller, produtos}}
       */
      <View style={[styles.padH10, styles.pad5]}>
        <View
          style={[styles.forms, styles.simpleShadow, {flexDirection: 'row'}]}
          elevation={5}>
          <View style={[styles.pad5, {flex: 1}]}>
            <Text style={styles.textOrderTitle}>{'RESUMO'}</Text>
            <View style={[styles.padH10]}>
              {this.state.produtos.map((data, i) => {
                return (
                  <View key={i} style={[]}>
                    <View style={[styles.flexDirRow, {flex: 1}]}>
                      <Text style={[styles.textOrderNumber, {flex: 7}]}>
                        {data.quantidade}
                      </Text>
                      <Text style={[styles.textOrder, {flex: 60}]}>
                        {data.produto_descricao}
                      </Text>
                      <Text style={[styles.textOrderNumber, {flex: 33}]}>
                        {'R$ ' + formataDecimal(data.valortotal, 2)}
                      </Text>
                    </View>
                  </View>
                );
              })}
              <View style={[styles.flexDirRow, {flex: 1, fontWeight: 'bold'}]}>
                <Text style={[styles.textOrder, {flex: 7}]}>{''}</Text>
                <Text
                  style={[styles.textOrder, {flex: 60, fontWeight: 'bold'}]}>
                  {'Valor Produtos'}
                </Text>
                <Text
                  style={[
                    styles.textOrderNumber,
                    {flex: 33, fontWeight: 'bold'},
                  ]}>
                  {'R$ ' +
                    formataDecimal(this.state.desconto + this.state.total, 2)}
                </Text>
              </View>
              <View style={[styles.flexDirRow, {flex: 1, fontWeight: 'bold'}]}>
                <Text style={[styles.textOrder, {flex: 7}]}>{''}</Text>
                <Text
                  style={[styles.textOrder, {flex: 60, fontWeight: 'bold'}]}>
                  {'Valor desconto'}
                </Text>
                <Text
                  style={[
                    styles.textOrderNumber,
                    {flex: 33, fontWeight: 'bold'},
                  ]}>
                  {'R$ ' + formataDecimal(this.state.desconto, 2)}
                </Text>
              </View>
              <View style={[styles.flexDirRow, {flex: 1, fontWeight: 'bold'}]}>
                <Text style={[styles.textOrder, {flex: 7}]}>{''}</Text>
                <Text
                  style={[styles.textOrder, {flex: 60, fontWeight: 'bold'}]}>
                  {'Valor Total'}
                </Text>
                <Text
                  style={[
                    styles.textOrderNumber,
                    {flex: 33, fontWeight: 'bold'},
                  ]}>
                  {'R$ ' + formataDecimal(this.state.total, 2)}
                </Text>
              </View>
            </View>
          </View>
        </View>
      </View>
    );
  };

  render() {
    return (
      <View style={styles.containerAddress}>
        <HeaderSimple
          name={'Consulta de Pedidos'}
          toBack={() => {
            this.props.navigation.goBack();
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
          <View style={[styles.containerAddress, styles.pad5]}>
            {this.listPedidos()}

            {renderIf(
              !this.props.refreshing && this.state.pedidos.length === 0,
            )(
              <Text style={[styles.textConfirmOrder, styles.padH15]}>
                Não existem pedidos finalizados no período selecionado.
              </Text>,
            )}
            {renderIf(!this.props.refreshing && this.state.pedidos.length > 0)(
              <View style={[styles.viewTextConfirmOrder, styles.pad5]}>
                {this.renderTotal()}
              </View>,
            )}
          </View>
        </ScrollView>
      </View>
    );
  }
}

export default connect(
  mapStateToProps,
  mapDispatchToProps,
)(ReportPedidosListPage);
