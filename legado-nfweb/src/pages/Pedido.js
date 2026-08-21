/* eslint-disable prettier/prettier */
/* eslint-disable react-native/no-inline-styles */
import React from 'react';
import {
  RefreshControl,
  ScrollView,
  Text,
  TouchableOpacity,
  View,
  CheckBox,
  Alert,
  Keyboard,
  TextInput,
  Modal,
} from 'react-native';
import {styles} from '../assets/css/style';
import {HeaderSimple, HrAddress, InputDate} from '../components/Views';
import {connect} from 'react-redux';
import {
  getUserState,
  mapDispatchToProps,
  mapStateToProps,
} from '../reducers/Functions';
import BaseComponent from '../components/BaseComponent';
import {Input} from '../components/Views';

import {IconsMCI} from '../assets/Icons';
import {storeData} from '../helper/AsyncStore';
import {getParcelasVencidasCliente} from '../providers/HttpRequests';
import SearchableDropdown from 'react-native-searchable-dropdown';
import {formataDecimal, actualDateFormatted} from '../helper/Helper';
import {enviarPedido, enviarEmail} from '../providers/HttpRequests';
import DatePicker from 'react-native-datepicker';
import MultiSelect from 'react-native-multiple-select';
/**
 * @param props {{navigation: {navigate: function}}}
 * @param address
 */
class PedidoPage extends BaseComponent {
  constructor(props) {
    super(props);
    this.state = {
      pedido: {id: ''},
      cliente: {id: '', nome: '', convenio: '', convenio_nome: ''},
      pagamento: {id: '', descricao: '', cartao: '0', tipo: '0'},
      operacao: {id: '', descricao: ''},
      produto: {id: '', descricao: '', preco: ''},
      produtos: [],
      qtde: '',
      totalProdutos: 0,
      desconto: 0,
      expandInfo: false,
      dataVencimento: actualDateFormatted('/'),
      pagamentos: [],
      autorizacao: '',
      selectedProd: [],
      selectedOper: [],
      selectedPagto: [],
      showFinalizar: false,
      permiteNF: false,
      emiteNFC: false,
      emiteNF: false,
      emiteNFCNaoId: false,
      emiteBoleto: false,
      enviandoPedido: false,
      visibleSearchableOption: false,
      pedidoEmailId: -1,
      informacaocomplementar: '',
      informacaocomplementarOriginal: '',
      visibleModal: false,
      observacoes: ""
    };
  }

  componentDidMount() {
    
    this.willFocusSubscription = this.props.navigation.addListener(
      'willFocus',
      () => {
        //this.setState({back: this.props.Pedido.back});
        //this.getPedidoConsulta();
        if(this.props.Cliente !== null && this.props.Cliente != undefined){
          //this.setState({ focus: false, item: this.props.Cliente.id });
            this.setState({visibleSearchableOption : false}, function() {
            var id = this.props.Cliente.id;
            var i;
            var cliaux = null;
            this.props.Clientes.forEach(function(x, index) {
                if (x.id === id) {
                    i = index;
                    cliaux = x;
                }
            });
            if(cliaux !== null){

              var pagtos = [];
              if (cliaux.convenio === '1') {
                pagtos = this.props.Pagamentos.map(function(itemp) {
                  return {
                    id: itemp.id,
                    name: itemp.descricao,
                  };
                });
              } else {
                pagtos = this.props.Pagamentos.filter(e => e.tipo != 4).map(
                  function(itemp) {
                    return {
                      id: itemp.id,
                      name: itemp.descricao,
                    };
                  },
                );
              }
              this.setState({
                cliente: cliaux,
                pagamentos: pagtos,
                permiteNF: (cliaux.nfemite == 1 && cliaux.tipopessoa == 'J' && cliaux.cnpjcpf != '') || cliaux.tipopessoa == 'F',
              });

            }
            this.setState({clienteIndex: i+1}, function() {
              this.setState({visibleSearchableOption : true});
            });
          });
        } else {
          this.setState({visibleSearchableOption : true});
        }
    
      },
    );
  }

  confirmarEnviarPedido = () => {
    this.setState({enviandoPedido: true});
    if (this.state.cliente.tipopessoa == 'J'){
        this.confirmarEmitirBoleto();
    } else {
      if (this.state.emiteNF){

        if (this.state.cliente.nfemite=='1' && this.state.cliente.cnpjcpf != ''){

          Alert.alert(
            'NF',
            'Escolha o tipo de NF',
            [
              {text: 'NFe', onPress: () => {
                this.setState({emiteNF: true, emiteNFC: false, emiteNFCNaoId: false},
                  function(){
                    this.confirmarEmitirBoleto();
                  });
              }},
              {text: 'NFCe', onPress: () => {
                 this.setState({emiteNF: false, emiteNFC: true, emiteNFCNaoId: false},
                  function(){
                    this.confirmarEmitirBoleto();
                  });
              }},
              {text: 'NFCe Não Ident.', onPress: () => {
                this.setState({emiteNF: false, emiteNFC: true, emiteNFCNaoId: true},
                  function(){
                    this.enviarPedido();
                  });
              }},
            ],
            { cancelable: false }
          );
        } else {
          this.setState({emiteNF: false, emiteNFC: true, emiteNFCNaoId: true},
          function(){
            this.confirmarEmitirBoleto();
          });
        }
      } else {
        this.confirmarEmitirBoleto();
      }
    }
  };


  confirmarEmitirBoleto = () => {
    if (this.state.pagamento.tipo == '1'){
        Alert.alert(
          'Boleto',
          'Deseja emitir boleto?',
          [
            {text: 'Sim', onPress: () => {
              this.setState({emiteBoleto: true},
                function(){
                  this.enviarPedido();
                });
            }},
            {text: 'Não', onPress: () => {
               this.setState({emiteBoleto: false},
                function(){
                  this.enviarPedido();
                });
            }},
          ],
          { cancelable: false }
        );
    } else {
        this.enviarPedido();
    }
  };


  confirmarEnviarEmail = () => {
    Alert.alert(
      'e-mail',
      'Deseja enviar boleto/NFe por e-mail?',
      [
        {text: 'Sim', onPress: () => {
            this.enviarEmail();
        }},
        {text: 'Não', onPress: () => {
          this.setState({enviandoPedido: false});
          this.props.changePedido({id: this.state.pedidoEmailId, back: false});
          this.props.navigation.navigate('PedidoConsulta');
        }},
      ],
      { cancelable: false }
    );
  };

  enviarPedido = () => {
    let pedido = this.state;
    enviarPedido(pedido, this.props.userId, this.erroPedido).then(value => {
      if (typeof value.status === 'string' && value.status === 'OK') {
        //Observações de cliente
        let clientes = this.props.Clientes.map(el => el.id == this.state.cliente.id ? {...el, observacoes: this.state.observacoes} : el);
        this.props.changeClientes(clientes);
        storeData({
          key: 'clientes',
          data: JSON.stringify(clientes),
        });
        if ((this.state.emiteBoleto || this.state.emiteNF || (this.state.emiteNFC && !this.state.emiteNFCNaoId)) && this.state.cliente.email != '' && this.state.cliente.email != null){
          this.setState({pedidoEmailId: value.data},
            function(){
              this.confirmarEnviarEmail();
            });
        } else {
          this.setState({enviandoPedido: false});
          this.props.changePedido({id: value.data, back: false});
          this.props.navigation.navigate('PedidoConsulta');
        }
      } else {
        this.showErrorAlert(value);
      }
    });
  };

  enviarEmail = () => {
    enviarEmail(this.state.pedidoEmailId).then(valueemail => {
      this.setState({enviandoPedido: false});
      if (typeof valueemail.status === 'string' && valueemail.status === 'OK') {
        this.props.changePedido({id: this.state.pedidoEmailId, back: false});
        this.props.navigation.navigate('PedidoConsulta');
      } else {
        //this.showErrorAlert(erro ao );
        this.props.changePedido({id: this.state.pedidoEmailId, back: false});
        this.props.navigation.navigate('PedidoConsulta');
      }
    });
  };


  erroPedido = erro => {
    this.setState({enviandoPedido: false},
      function(){
        if(typeof erro === 'object'){
          if(erro.hasOwnProperty('message')){
            Alert.alert('Ops', erro.message);
          } else {
            Alert.alert('Ops', 'ocorreu um erro não identificado ao enviar o pedido.');
          }
        } else if(typeof erro === 'string'){
          Alert.alert('Ops', erro);
        } else {
          Alert.alert('Ops', 'ocorreu um erro não identificado ao enviar o pedido.');
        }
      });
  }

  getParcelasVencidasCliente = cliente_id => {
    getParcelasVencidasCliente(cliente_id).then(value => {
      if (typeof value.status === 'string' && value.status === 'OK') {
        if (value.data.length > 0) {
          let total = 0;
          for (var i = 0; i < value.data.length; i++) {
            total += parseFloat(value.data[i].valor);
          }
          Alert.alert(
            'Atenção!',
            'Existem parcelas vencidas para esse cliente. Valor total: ' +
              formataDecimal(total) +
              '. Parcela mais antiga: ' +
              value.data[0].datavencimento +
              ', ' +
              value.data[0].descricao +
              ', valor de ' +
              formataDecimal(parseFloat(value.data[0].valor)) +
              '.',
          );
        }
      } else {
        this.showErrorAlert(value);
      }
    });
  };

  addItem = () => {
    let prods = this.state.produtos;
    let prod = this.state.produto;
    let qtde = this.state.qtde;
    let preco = this.state.preco;
    if (prod.id === '') {
      Alert.alert('Ops', 'Selecione o produto para continuar');
      return;
    }
    let prodexists = this.state.produtos.filter(e => e.id === prod.id);
    if (prodexists.length > 0) {
      Alert.alert('Ops', 'Produto já informado no pedido');
      return;
    }

    if (qtde === '') {
      Alert.alert('Ops', 'Informe a quantidade para continuar');
      return;
    }
    if (preco === '') {
      Alert.alert('Ops', 'Informe o preço para continuar');
      return;
    }
    prod.qtde = parseFloat(qtde);
    prod.preco = parseFloat(preco);
    prods.push(prod);
    this.setState(
      {
        qtde: '',
        preco: '',
        produto: {id: '', descricao: '', preco: ''},
        totalProdutos: this.state.totalProdutos + qtde * preco,
      },
      function() {
        Keyboard.dismiss();
      },
    );
  };
  removeItem = (produtoId, totalItem) => {
    let newProds = this.state.produtos.filter(e => e.id !== produtoId);
    this.setState({
      produtos: newProds,
      totalProdutos: this.state.totalProdutos - totalItem,
    });
  };

  renderCliente = () => {
    return (
      <View>
        <View
          style={[
            styles.formsAddress,
            styles.simpleShadow,
            styles.flexDirCol,
            {marginLeft: 5},
            this.state.produtos.length == 0 && {display: 'none'},
          ]}
          elevation={5}>
          <View style={!this.state.showFinalizar && {display: 'none'}}>
          <View style={[styles.flexDirRow]}>
            <View style={[styles.viewChoiceAddress, styles.pad5, {flex: 90}]}>
              <Text style={styles.iconActionListChoice}>
                {this.state.cliente.nome}
              </Text>
            </View>
            <View style={[styles.viewChoiceAddress, {flex: 10}]}>
              <TouchableOpacity
                onPress={async () => {
                  if (this.state.expandInfo){
                    this.setState({showFinalizar: false});
                  }
                  this.setState({expandInfo: !this.state.expandInfo});
                }}>
                <View>
                  <IconsMCI
                    style={styles.pedidoItemIcon}
                    name={
                      this.state.expandInfo ? 'arrow-collapse' : 'arrow-expand'
                    }
                  />
                </View>
              </TouchableOpacity>
            </View>
          </View>
          <HrAddress />
          <View style={styles.flexDirRow}>
            <View style={[styles.pad5, styles.padH15]}>
              <View style={[styles.viewPedidoItem, styles.pad5]}>
                <View>
                  <Text
                    style={[
                      styles.textConfirmOrder,
                      styles.padH15,
                      {display: 'none'},
                    ]}>
                    {this.state.cliente.id}
                  </Text>
                  <Text style={[styles.textConfirmOrder, styles.padH15]}>
                    {this.state.cliente.endereco}
                  </Text>
                  <Text
                    style={[
                      styles.textConfirmOrder,
                      styles.padH15,
                      this.state.cliente.cnpjcpf === '' ||
                        (this.state.cliente.cnpjcpf === null && {
                          display: 'none',
                        }),
                    ]}>
                    {this.state.cliente.cnpjcpf}
                  </Text>
                  <Text
                    style={[
                      styles.textConfirmOrder,
                      styles.padH15,
                      this.state.observacoes === '' ||
                        (this.state.observacoes === null && {
                          display: 'none',
                        }),
                    ]}>
                    {this.state.observacoes}
                  </Text>
                  <Text
                    style={[
                      styles.textConfirmOrderBold,
                      styles.padH15,
                      this.state.pagamento.descricao === '' && {
                        display: 'none',
                      },
                    ]}>
                    {this.state.pagamento.descricao +
                      (this.state.cliente.convenio == '1' &&
                      this.state.pagamento.tipo == '4'
                        ? ' (' + this.state.cliente.convenio_nome + ')'
                        : '')}
                  </Text>
                  <Text
                    style={[
                      styles.textConfirmOrderBold,
                      styles.padH15,
                      !this.state.emiteNF && {
                        display: 'none',
                      },
                    ]}>
                    {'NF - ' + this.state.operacao.descricao}
                  </Text>
                  <HrAddress />
                </View>
              </View>
            </View>
          </View>
          </View>
          <View style={[styles.viewPedidoItens, styles.padH15]}>
            {this.listItens()}
            {this.renderTotal()}
          </View>
          <View
            style={[
              styles.pad15,
              {alignItems: 'center'},
              (this.state.cliente.id === '' ||
                this.state.produtos.length === 0 ||
                this.state.pagamento.id === '' ||
                this.state.showFinalizar ||
                (this.state.emiteNF
                  ? this.state.operacao.id === ''
                  : false)) && {
                display: 'none',
              },
            ]}>
            <TouchableOpacity
              onPress={() => {
                this.setState({showFinalizar: true, expandInfo: true});

              }}>
              <Text style={[styles.pedidoEnviarButton]}>Finalizar Pedido</Text>
            </TouchableOpacity>
          </View>


          <View
            style={[
              styles.pad15,
              {alignItems: 'center'},
              (!this.state.showFinalizar || this.state.enviandoPedido) && {display: 'none'},
            ]}>
            <TouchableOpacity
              onPress={async () => {
                this.confirmarEnviarPedido();
              }}>
              <Text style={[styles.pedidoEnviarButton]}>Enviar Pedido</Text>
            </TouchableOpacity>
          </View>
        </View>
      </View>
    );
  };

  renderTotal = () => {
    return (
      <View>
        <View
          style={[
            styles.flexDirRow,
            {flex: 100},
            this.state.totalProdutos === 0 && {display: 'none'},
          ]}>
          <View style={[styles.viewPedidoItem]}>
            <View style={[{flex: 50}]}>
              <Text style={[styles.descValor, styles.textConfirmOrderBold]}>
                Total
              </Text>
            </View>
            <View style={[{flex: 50}]}>
              <Text style={[styles.descValor, styles.textConfirmOrderBold]}>
                {formataDecimal(this.state.totalProdutos, 2)}
              </Text>
            </View>
          </View>
        </View>
        <View
          style={[
            styles.flexDirRow,
            {flex: 100},
            (this.state.totalProdutos === 0 || this.state.desconto === 0) && {
              display: 'none',
            },
          ]}>
          <View style={[styles.viewPedidoItem]}>
            <View style={[{flex: 50}]}>
              <Text style={[styles.descValor, styles.textConfirmOrderBold]}>
                Desconto
              </Text>
            </View>
            <View style={[{flex: 50}]}>
              <Text style={[styles.descValor, styles.textConfirmOrderBold]}>
                {formataDecimal(this.state.desconto, 2)}
              </Text>
            </View>
          </View>
        </View>
        <View
          style={[
            styles.flexDirRow,
            {flex: 100},
            (this.state.totalProdutos === 0 || this.state.desconto === 0) && {
              display: 'none',
            },
          ]}>
          <View style={[styles.viewPedidoItem]}>
            <View style={[{flex: 50}]}>
              <Text style={[styles.descValor, styles.textConfirmOrderBold]}>
                Líquido
              </Text>
            </View>
            <View style={[{flex: 50}]}>
              <Text style={[styles.descValor, styles.textConfirmOrderBold]}>
                {formataDecimal(
                  this.state.totalProdutos - this.state.desconto,
                  2,
                )}
              </Text>
            </View>
          </View>
        </View>
      </View>
    );
  };
  listItens = () => {
    return this.state.produtos.map((value, i) => {
      return this.renderItem(value, i);
    });
  };

  renderItem = (produto, i) => {
    return (
      <View key={i}>
        <View style={[styles.flexDirRow, {flex: 100}]}>
          <View style={{flex: 90}}>
            <View style={[styles.flexDirRow]}>
              <View style={[styles.viewPedidoItem]}>
                <Text style={[styles.iconActionListChoice, {flex: 100}]}>
                  {produto.descricao}
                </Text>
              </View>
            </View>
            <View style={[styles.flexDirRow, {flex: 100}]}>
              <View style={[styles.viewPedidoItem]}>
                <View style={[{flex: 20}]}>
                  <Text style={styles.descValor}>
                    {formataDecimal(parseFloat(produto.qtde), 0)}
                  </Text>
                </View>
                <View style={[{flex: 35}]}>
                  <Text style={styles.descValor}>
                    {formataDecimal(produto.preco, 2)}
                  </Text>
                </View>
                <View style={[{flex: 45}]}>
                  <Text style={styles.descValor}>
                    {formataDecimal(
                      parseFloat(produto.qtde) * parseFloat(produto.preco),
                      2,
                    )}
                  </Text>
                </View>
              </View>
            </View>
          </View>
          <View style={{flex: 10}}>
            <View style={[styles.flexDirRow]}>
              <View style={[styles.viewPedidoItem]}>
                <TouchableOpacity
                  onPress={async () => {
                    this.removeItem(produto.id, produto.preco * produto.qtde);
                  }}
                  style={[styles.padH5, {flex: 10}]}>
                  <View>
                    <IconsMCI
                      style={styles.pedidoItemIcon}
                      name={'minus-circle-outline'}
                    />
                  </View>
                </TouchableOpacity>
              </View>
            </View>
          </View>
        </View>
      </View>
    );
  };
  renderDropdownClientes = () => {
    var items = [{id: -1, name: ''}];
    this.props.Clientes.map(function(item) {
      items.push({
        id: item.id,
        name: item.nome,
      });
    });
    return (
      <View style={[this.state.expandInfo && {display: 'none'}]}>
        <View style={[styles.flexDirRow]}>
        <View style={[{flex: 90}]}>
          {this.state.visibleSearchableOption ?
          <SearchableDropdown
            onItemSelect={item => {
              let cliente = this.props.Clientes.filter(e => e.id === item.id);
              this.getParcelasVencidasCliente(cliente[0].id);
              var pagtos = [];
              if (cliente[0].convenio === '1') {
                pagtos = this.props.Pagamentos.map(function(itemp) {
                  return {
                    id: itemp.id,
                    name: itemp.descricao,
                  };
                });
              } else {
                pagtos = this.props.Pagamentos.filter(e => e.tipo != 4).map(
                  function(itemp) {
                    return {
                      id: itemp.id,
                      name: itemp.descricao,
                    };
                  },
                );
              }
              this.setState({
                cliente: cliente[0],
                observacoes: cliente[0].observacoes,
                pagamentos: pagtos,
                permiteNF: (cliente[0].nfemite == 1 && cliente[0].tipopessoa == 'J' && cliente[0].cnpjcpf != '') || cliente[0].tipopessoa == 'F',
              });

            }}
            containerStyle={{padding: 5}}
            itemStyle={styles.searchableDropDownItem}
            itemTextStyle={styles.searchableDropDownTextItem}
            itemsContainerStyle={styles.searchableDropDownItemsContainer}
            items={items}
            textInputProps={{
              placeholder: 'Selecione o Cliente',
              underlineColorAndroid: 'transparent',
              style: {
                padding: 12,
                borderWidth: 1,
                borderColor: '#ccc',
                borderRadius: 5,
              },
            }}
            listProps={{
              nestedScrollEnabled: true,
            }}
            defaultIndex={this.state.clienteIndex}
          />
          :
          <View/>
        }
        </View>
        <View style={[{flex: 10}]}>
            <TouchableOpacity
              onPress={async () => {
                this.props.navigation.navigate('ClienteBusca');
              }}
              style={(styles.padH5, styles.pedidoAddItem)}>
              <IconsMCI style={styles.pedidoItemIcon} name="map-search-outline" />
            </TouchableOpacity>
          </View>
          </View>
          <View style={[styles.flexDirRow]}>
            <View style={[{flex:92, marginLeft: 5}]}>
              <TextInput style={styles.searchableDropDownItem}
                placeholder={'Obs. Cliente'}
                multiline
                value={this.state.observacoes}
                onChangeText={observacoes => {
                  this.setState({
                    observacoes: observacoes,
                  });
                }}
              />
              
            </View>
            <View style={[{flex:2}]}></View>
          </View>

      </View>
    );
  };

  renderDropdownPagamentos = () => {
    return (
      <View style={[this.state.expandInfo && {display: 'none'}]}>
        <View style={[styles.flexDirRow]}>
          <View style={[{flex: 70}]}>
            <MultiSelect
              hideTags
              items={this.state.pagamentos}
              uniqueKey="id"
              ref={component => {
                this.multiSelect = component;
              }}
              selectedItems={this.state.selectedPagto}
              onSelectedItemsChange={selectedPagto => {
                let pagamento = this.props.Pagamentos.filter(
                  e => e.id === selectedPagto[0],
                );
                let vencto = new Date();
                vencto.setDate(vencto.getDate() + parseInt(pagamento[0].dias));
                this.setState({
                  pagamento: pagamento[0],
                  dataVencimento: actualDateFormatted('/', vencto),
                });
                this.setState({selectedPagto});
              }}
              textInputProps={{editable: false, autoFocus: false}}
              searchInputPlaceholderText=""
              searchIcon={false}
              selectText="    Forma de Pagamento"
              //searchInputPlaceholderText="Pesquise o produto"
              //onChangeInput={ (text)=> console.log(text)}
              altFontFamily="ProximaNova-Light"
              tagRemoveIconColor="#CCC"
              tagBorderColor="#CCC"
              tagTextColor="#CCC"
              selectedItemTextColor="#CCC"
              selectedItemIconColor="#CCC"
              itemTextColor="#000"
              displayKey="name"
              submitButtonColor="#CCC"
              submitButtonText="Submit"
              single={true}
              searchInputStyle={{color: '#CCC', padding: 10, backgroundColor: '#ddd'}}
              styleInputGroup={{marginHorizontal: 10, backgroundColor: '#ddd'}}
              styleTextDropdownSelected={{paddingHorizontal: 10, backgroundColor: '#ddd'}}
              styleDropdownMenu={{paddingHorizontal: 0, backgroundColor: '#ddd'}}
              styleItemsContainer={{marginHorizontal: 0, backgroundColor: '#ddd'}}
              styleTextDropdown={{paddingHorizontal: 0, backgroundColor: '#ddd'}}
              styleRowList={[styles.dropDownItem, {backgroundColor: '#ddd'}]}
              styleSelectorContainer={{backgroundColor: '#ddd'}}
              styleDropdownMenuSubsection={{backgroundColor: '#ddd', marginTop: 10, height:45}}
              styleListContainer={{backgroundColor: '#ddd'}}
              onToggleList={function() {
                Keyboard.dismiss();
              }}

            />
          </View>
          <View style={[{flex: 30, height:48, alignContent: 'space-around', borderWidth: 0}]}>
            <TextInput style={styles.viewPedidoInput}
              placeholder={'Desconto'}
              keyboardType={'numeric'}
              value={this.state.desconto.toString()}
              onChangeText={desconto => {
                this.setState({
                  desconto: desconto == "" ? "" : parseFloat(desconto.replace(',', '.')),
                });
              }}
            />
          </View>
        </View>
        <View style={[styles.flexDirRow]}>
          <View style={[{flex: 25}]}>
            <Text style={[styles.viewPedidoText]}>Vencto:</Text>
          </View>
          <View style={[{flex: 40}]}>
            <DatePicker
              style={[styles.viewPedidoDatePicker]}
              date={this.state.dataVencimento} //initial date from state
              mode="date" //The enum of date, datetime and time
              placeholder="select date"
              format="DD/MM/YYYY"
              //minDate="01/01/1970"
              //maxDate="01/01/2019"
              confirmBtnText="Confirm"
              cancelBtnText="Cancel"
              customStyles={{
                dateIcon: {
                  position: 'absolute',
                  left: 0,
                  top: 4,
                  marginLeft: 0,
                },
                dateInput: {
                  marginLeft: 36,
                },
              }}
              onDateChange={date => {
                this.setState({dataVencimento: date});
              }}
            />
          </View>
          <View style={[{flex: 35}]}>
            <View
              style={[this.state.pagamento.cartao != '1' && {display: 'none'}]}>
                <TextInput style={styles.viewPedidoInput}
                  placeholder={'CV Cartão'}
                  keyboardType={'numeric'}
                  value={this.state.autorizacao}
                  onChangeText={autorizacao => {
                    this.setState({
                      autorizacao: parseFloat(autorizacao),
                    });
                  }}
                />
            </View>
          </View>
        </View>
      </View>
    );
  };

  renderProdutos = () => {
    var items = this.props.Produtos.map(function(item) {
      return {
        id: item.id,
        name: item.descricao,
      };
    });
    const {selectedProd} = this.state;
    return (
      <View style={this.state.expandInfo && {display: 'none'}}>
        <View style={[styles.flexDirRow]}>
          <View style={[{flex: 1}]}>
            <MultiSelect
              hideTags
              items={items}
              uniqueKey="id"
              ref={component => {
                this.multiSelect = component;
              }}
              selectedItems={selectedProd}
              onSelectedItemsChange={selectedProd => {
                let produto = this.props.Produtos.filter(
                  e => e.id === selectedProd[0],
                );
                let preco = parseFloat(produto[0].precovenda);
                if(this.state.cliente.produtos && this.state.cliente.produtos.length > 0){
                  let prodcli = this.state.cliente.produtos.filter(
                    p => p.produto_id == produto[0].id,
                  );
                  if(prodcli.length>0){
                      let precoaux = parseFloat(prodcli[0].preco);
                      let descontoaux = parseFloat(prodcli[0].desconto);

                      if(precoaux > 0){
                        preco = precoaux;
                      }
                      if(descontoaux > 0){
                        if(prodcli[0].tipo == "1"){
                          preco = preco - descontoaux;
                        } else {
                          preco = preco * (1 - (descontoaux));
                        }
                      }
                  }
                }


                this.setState({
                  produto: produto[0],
                  preco: preco.toString(),
                });
                this.setState({selectedProd});
              }}
              textInputProps={{editable: false, autoFocus: false}}
              searchInputPlaceholderText=""
              searchIcon={false}
              selectText="    Selecione o Produto"
              //searchInputPlaceholderText="Pesquise o produto"
              altFontFamily="ProximaNova-Light"
              tagRemoveIconColor="#CCC"
              tagBorderColor="#CCC"
              tagTextColor="#CCC"
              selectedItemTextColor="#CCC"
              selectedItemIconColor="#CCC"
              itemTextColor="#000"
              displayKey="name"
              submitButtonColor="#CCC"
              submitButtonText="Submit"
              single={true}
              searchInputStyle={{color: '#CCC', padding: 10, backgroundColor: '#ddd'}}
              styleInputGroup={{marginHorizontal: 10, backgroundColor: '#ddd'}}
              styleTextDropdownSelected={{paddingHorizontal: 10, backgroundColor: '#ddd'}}
              styleDropdownMenu={{paddingHorizontal: 0, backgroundColor: '#ddd'}}
              styleItemsContainer={{marginHorizontal: 0, backgroundColor: '#ddd'}}
              styleTextDropdown={{paddingHorizontal: 0, backgroundColor: '#ddd'}}
              styleRowList={[styles.dropDownItem, {backgroundColor: '#ddd'}]}
              styleSelectorContainer={{backgroundColor: '#ddd'}}
              styleDropdownMenuSubsection={{backgroundColor: '#ddd', marginTop: 10, height:45}}
              styleListContainer={{backgroundColor: '#ddd'}}
              onToggleList={function() {
                Keyboard.dismiss();
              }}

            />
          </View>
        </View>
        <View style={styles.flexDirRow}>
          <View style={[{flex: 20}]}>
            <Input
              placeText={'Qtde'}
              keyboardType={'numeric'}
              textData={this.state.qtde}
              whenChangeText={qtde => {
                this.setState({
                  qtde: qtde,
                });
              }}
            />
          </View>
          <View style={[{flex: 20}]}>
            <Input
              placeText={'Preço'}
              keyboardType={'numeric'}
              textData={this.state.preco}
              whenChangeText={preco => {
                this.setState({
                  preco: preco.replace(',', '.'),
                });
              }}
            />
          </View>
          <View style={[{flex: 10}]}>
            <TouchableOpacity
              onPress={async () => {
                this.addItem();
              }}
              style={(styles.padH5, styles.pedidoAddItem)}>
              <IconsMCI style={styles.pedidoItemIcon} name="cart-arrow-down" />
            </TouchableOpacity>
          </View>
        </View>
      </View>
    );
  };
  renderOperacoes = () => {
    var items = this.props.Operacoes.map(function(item) {
      return {
        id: item.id,
        name: item.descricao,
      };
    });
    const {selectedOper} = this.state;
    return (
        <View style={(!this.state.permiteNF || this.state.expandInfo) && {display: 'none'}}>
          <View style={styles.flexDirRow}>
            <View style={[{flex: 5}, styles.viewPedidoCheckbox]}>
            { (this.state.permiteNF && !this.state.expandInfo) && (
              <CheckBox
                style={(!this.state.permiteNF || this.state.expandInfo) && { visibility: 'hidden' }}
                value={this.state.emiteNF}
                onValueChange={() =>
                  this.setState({emiteNF: !this.state.emiteNF})
                }
              />
              )}
              </View>
            <View style={[{flex: 8}, styles.viewPedidoCheckbox]}>
              <Text style={[styles.viewPedidoTextCheckbox]}>NF</Text>
            </View>
            <View style={[{flex: 77}]}>
              <View style={[!this.state.emiteNF && {display: 'none'}]}>
              <MultiSelect
                hideTags
                items={items}
                uniqueKey="id"
                ref={component => {
                  this.multiSelect = component;
                }}
                selectedItems={selectedOper}
                onSelectedItemsChange={selectedOper => {
                  let operacao = this.props.Operacoes.filter(
                    e => e.id === selectedOper[0],
                  );
                  this.setState({
                    operacao: operacao[0],
                  });
                  this.setState({selectedOper});
                }}
                textInputProps={{editable: false, autoFocus: false}}
                searchInputPlaceholderText=""
                searchIcon={false}
                selectText="    Selecione a Operação"
                //searchInputPlaceholderText="Pesquise o produto"
                altFontFamily="ProximaNova-Light"
                tagRemoveIconColor="#CCC"
                tagBorderColor="#CCC"
                tagTextColor="#CCC"
                selectedItemTextColor="#CCC"
                selectedItemIconColor="#CCC"
                itemTextColor="#000"
                displayKey="name"
                submitButtonColor="#CCC"
                submitButtonText="Submit"
                single={true}
                searchInputStyle={{color: '#CCC', padding: 10, backgroundColor: '#ddd'}}
                styleInputGroup={{marginHorizontal: 10, backgroundColor: '#ddd'}}
                styleTextDropdownSelected={{paddingHorizontal: 10, backgroundColor: '#ddd'}}
                styleDropdownMenu={{paddingHorizontal: 0, backgroundColor: '#ddd'}}
                styleItemsContainer={{marginHorizontal: 0, backgroundColor: '#ddd'}}
                styleTextDropdown={{paddingHorizontal: 0, backgroundColor: '#ddd'}}
                styleRowList={[styles.dropDownItem, {backgroundColor: '#ddd'}]}
                styleSelectorContainer={{backgroundColor: '#ddd'}}
                styleDropdownMenuSubsection={{backgroundColor: '#ddd', marginTop: 10, height:45}}
                styleListContainer={{backgroundColor: '#ddd'}}
                onToggleList={function() {
                  Keyboard.dismiss();
                }}
  
              />
              </View>
            </View>

            <View style={[{flex: 10}]}>
              <View style={[!this.state.emiteNF && {display: 'none'}]}>
                <TouchableOpacity
                    onPress={async () => {
                      this.setState({visibleModal: !this.state.visibleModal, informacaocomplementarOriginal: this.state.informacaocomplementar});
                    }}>
                    <View>
                      <IconsMCI
                        style={styles.openModalIcon}
                        name={'clipboard-text-outline'}
                      />
                    </View>
                  </TouchableOpacity>
                </View>
              </View>

          </View>
        </View>
    );
  };

  renderModalInfoComplementar() {  
    return (
      <View style={styles.centeredView}>
      <View style={styles.modalView}>
        <Text style={styles.modalText}>Informação Complementar</Text>
        <TextInput
          ref={(input) => { this.textInput = input; }}
          style={styles.viewInfoComplementarInput}
          multiline
          numberOfLines={4}
          //placeholder={'Informação Complementar'}
          returnKeyType='done' 
          value={this.state.informacaocomplementar}
          onChangeText={info => {
            this.setState({
              informacaocomplementar: info
            });
          }}
          onSubmitEditing={() => {
            this.setState({visibleModal: false})
          }}
        />
        <View style={[styles.flexDirRow, {marginTop: 10}]}>
          <View style={[styles.orderButton, {marginRight: 5}]}>
            <TouchableOpacity
              style={{ ...styles.openButton, backgroundColor: "#820300", flex: 1 }}
              onPress={() => {
                this.setState({visibleModal: false})
              }}
            >
              <Text style={styles.modalButtonStyle}>Confirmar</Text>
            </TouchableOpacity>
          </View>
          <View style={[styles.orderButton]}>
            <TouchableOpacity
              style={{ ...styles.openButton, backgroundColor: "#b03500", flex: 1 }}
              onPress={() => {
                this.setState({visibleModal: false, informacaocomplementar: this.state.informacaocomplementarOriginal})
              }}
            >
              <Text style={styles.modalButtonStyle}>Cancelar</Text>
            </TouchableOpacity>
          </View>
        </View>
      </View>
    </View>
    )
  }

  render() {
    // noinspection ThisExpressionReferencesGlobalObjectJS
    return (
      <View style={styles.containerMenu}>
        <HeaderSimple
          name={'Pedido'}
          toBack={() => {
            this.props.navigation.goBack();
          }}
        />
        <ScrollView
          colors={'#830000'}
          keyboardShouldPersistTaps="handled"
          refreshControl={
            <RefreshControl
              refreshing={!!this.props.refreshing}
              onRefresh={this._onRefresh}
            />
          }>
          {this.renderDropdownClientes()}
          <Modal
              transparent={true}
              visible={this.state.visibleModal}
              animationType="slide"
              onShow={() => { this.textInput.focus(); }}
          >
              {this.renderModalInfoComplementar()}
          </Modal>
          {this.renderDropdownPagamentos()}
          {this.renderOperacoes()}
          {this.renderProdutos()}

          <View style={[styles.containerAddress, styles.pad5]} />
          {this.renderCliente()}
        </ScrollView>
      </View>
    );
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(PedidoPage);
