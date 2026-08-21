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
} from 'react-native';
import {styles} from '../assets/css/style';
import {HeaderSimple, HrAddress} from '../components/Views';
import {connect} from 'react-redux';
import {mapDispatchToProps, mapStateToProps} from '../reducers/Functions';
import BaseComponent from '../components/BaseComponent';
import {IconsMCI} from '../assets/Icons';
import SearchableDropdown from 'react-native-searchable-dropdown';
import {formataDecimal} from '../helper/Helper';
import {getCliente} from '../providers/HttpRequests';
import MultiSelect from 'react-native-multiple-select';
/**
 * @param props {{navigation: {navigate: function}}}
 * @param address
 */
class ClienteBuscaPage extends BaseComponent {
  constructor(props) {
    super(props);

    let cid = {id: '', descricao: '', uf: ''};
    let cids = this.props.Cidades.filter(e => e.id == parseInt(this.props.cidade_id));
    if (cids.length > 0){
      cid = cids[0];
    }

    this.state = {
      clientesRua: [],
      hideDropdownCliente: true,
      hideCliente: true,
      cliente: {id: '', nome: '', convenio: '', convenio_nome: '', cnpjcpf: '', complemento: '', segmento_descricao: '', 
                endereco: '', observacoes: '', tipopessoa: '', email: '', fantasia: '', ponto_referencia: '', rgie: ''},
      historico: [],
      telefones: [],

      uf: {uf: this.props.uf, descricao: ''},
      selectedUF: [this.props.uf],
      cidade: cid,
      selectedCid: [parseInt(this.props.cidade_id)],
      rua: {id: '', descricao: '', cidade_id: ''},
      selectedRua: [],
      expandInfo: false,
      numero: ''
    };
  }

  buscaClientesRua = () => {
    if(this.state.rua != null && this.state.rua.id != null){
      let clientes = this.props.Clientes.filter(e => e.rua_id == this.state.rua.id);
      this.setState({clientesRua: clientes, hideDropdownCliente: false, numero: '', hideCliente: true});
    }
  }

  buscaClientesNumero = () => {
    if(this.state.rua != null && this.state.rua.id != null && this.state.numero != null && this.state.numero != ''){
      let clientes = this.props.Clientes.filter(e => e.rua_id == this.state.rua.id && e.numero == this.state.numero);
      this.setState({clientesRua: clientes, hideDropdownCliente: false, hideCliente: true});
    } else if (this.state.rua != null && this.state.rua.id != null){
      let clientes = this.props.Clientes.filter(e => e.rua_id == this.state.rua.id);
      this.setState({clientesRua: clientes, hideDropdownCliente: false, numero: '', hideCliente: true});
    }
  }

  getCliente = cliente_id => {
    getCliente(cliente_id).then(value => {
      if (typeof value.status === 'string' && value.status === 'OK') {
        this.setState({
          cliente: value.data.cliente,
          historico: value.data.historico,
          telefones: value.data.telefones,
          hideCliente: false,
        });

        if (value.data.parcelas.length > 0) {
          let total = 0;
          for (var i = 0; i < value.data.parcelas.length; i++) {
            total += parseFloat(value.data.parcelas[i].valor);
          }
          Alert.alert(
            'Atenção!',
            'Existem parcelas vencidas para esse cliente. Valor total: ' +
              formataDecimal(total) +
              '. Parcela mais antiga: ' +
              value.data.parcelas[0].datavencimento +
              ', ' +
              value.data.parcelas[0].descricao +
              ', valor de ' +
              formataDecimal(parseFloat(value.data.parcelas[0].valor)) +
              '.',
          );
        }
      } else {
        this.showErrorAlert(value);
      }
    });
  };

  setCliente = () => {
    if (this.state.cliente.id == ''){
        Alert.alert('Erro', 'Cliente não encontrado. Por favor, tente novamente.');
        return;
    }
    console.log('setCliente', this.state.cliente);
    this.props.changeCliente({id: this.state.cliente.id, nome: this.state.cliente.nome, segmento_descricao: this.state.cliente.segmento_descricao,
                              fantasia: this.state.cliente.fantasia, ponto_referencia: this.state.cliente.ponto_referencia, 
                              observacoes: this.state.cliente.observacoes, endereco: this.state.cliente.endereco,
                              email: this.state.cliente.email, back: false});
    //this.props.navigation.navigate('Pedido');
    this.props.navigation.goBack();
  }

  renderRuaBusca = () => {
    var itemsUF = this.props.Estados.map(function(item) {
      return {
        id: item.uf,
        name: item.uf,
      };
    });
    const {selectedUF} = this.state;

    let cids = this.props.Cidades.filter(e => e.uf === selectedUF[0]);

    var itemscid = cids.map(function(item) {
      return {
        id: item.id,
        name: item.descricao,
      };
    });
    const {selectedCid} = this.state;

    let r = this.props.Ruas.filter(e => e.cidade_id == selectedCid[0]);

    var itemsRua = r.map(function(item) {
      return {
        id: item.id,
        name: item.descricao,
      };
    });
    const {selectedRua} = this.state;

    return (
      <View style={[this.state.expandInfo && {display: 'none'}]}>
        <View style={[styles.flexDirRow]}>
          <View style={[{flex: 30}, {marginRight: 5}]}>
            <MultiSelect
              hideTags
              items={itemsUF}
              uniqueKey="id"
              ref={component => {
                this.multiSelect = component;
              }}
              selectedItems={selectedUF}
              onSelectedItemsChange={selectedUF => {
                let uf = this.props.Estados.filter(e => e.uf === selectedUF[0]);
                this.setState({
                  uf: uf[0],
                });
                this.setState({selectedUF}, function() {
                  //console.log(this.state);
                });
              }}
              textInputProps={{editable: false, autoFocus: false}}
              searchInputPlaceholderText=""
              searchIcon={false}
              selectText="    UF"
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
              searchInputStyle={{
                color: '#CCC',
                padding: 10,
                backgroundColor: '#ddd',
              }}
              styleInputGroup={{marginHorizontal: 10, backgroundColor: '#ddd'}}
              styleTextDropdownSelected={{
                paddingHorizontal: 10,
                backgroundColor: '#ddd',
              }}
              styleDropdownMenu={{
                paddingHorizontal: 0,
                backgroundColor: '#ddd',
              }}
              styleItemsContainer={{
                marginHorizontal: 0,
                backgroundColor: '#ddd',
              }}
              styleTextDropdown={{
                paddingHorizontal: 0,
                backgroundColor: '#ddd',
              }}
              styleRowList={[styles.dropDownItem, {backgroundColor: '#ddd'}]}
              styleSelectorContainer={{backgroundColor: '#ddd'}}
              styleDropdownMenuSubsection={{
                backgroundColor: '#ddd',
                marginTop: 10,
                height: 45,
              }}
              styleListContainer={{backgroundColor: '#ddd'}}
              onToggleList={function() {
                //console.log('toggle');
                Keyboard.dismiss();
              }}

            />
          </View>
          <View style={[{flex: 70}]}>
            <MultiSelect
              hideTags
              items={itemscid}
              uniqueKey="id"
              ref={component => {
                this.multiSelect = component;
              }}
              selectedItems={selectedCid}
              onSelectedItemsChange={selectedCid => {
                //console.log(selectedCid);
                let reg = this.props.Cidades.filter(
                  e => e.id === selectedCid[0],
                );
                //console.log(reg);
                this.setState({
                  cidade: reg[0],
                });
                this.setState({selectedCid});
              }}
              textInputProps={{editable: false, autoFocus: false}}
              searchInputPlaceholderText=""
              searchIcon={false}
              selectText="    Cidade"
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
              searchInputStyle={{
                color: '#CCC',
                padding: 10,
                backgroundColor: '#ddd',
              }}
              styleInputGroup={{marginHorizontal: 10, backgroundColor: '#ddd'}}
              styleTextDropdownSelected={{
                paddingHorizontal: 10,
                backgroundColor: '#ddd',
              }}
              styleDropdownMenu={{
                paddingHorizontal: 0,
                backgroundColor: '#ddd',
              }}
              styleItemsContainer={{
                marginHorizontal: 0,
                backgroundColor: '#ddd',
              }}
              styleTextDropdown={{
                paddingHorizontal: 0,
                backgroundColor: '#ddd',
              }}
              styleRowList={[styles.dropDownItem, {backgroundColor: '#ddd'}]}
              styleSelectorContainer={{backgroundColor: '#ddd'}}
              styleDropdownMenuSubsection={{
                backgroundColor: '#ddd',
                marginTop: 10,
                height: 45,
              }}
              styleListContainer={{backgroundColor: '#ddd'}}
              onToggleList={function() {
                //console.log('toggle');
                Keyboard.dismiss();
              }}

            />
          </View>
        </View>
        <View style={[styles.flexDirRow]}>
          <View style={{flex: 70}}>
            <SearchableDropdown
              onItemSelect={item => {
                let rua = this.props.Ruas.filter(e => e.id === item.id);
                this.setState({
                  rua: rua[0],
                });
                this.setState({selectedRua}, function() {
                  this.buscaClientesRua();
                });
              }}
              containerStyle={{padding: 0}}
              itemStyle={styles.searchableDropDownItem}
              itemTextStyle={styles.searchableDropDownTextItem}
              itemsContainerStyle={styles.searchableDropDownItemsContainer}
              items={itemsRua}
              textInputProps={{
                placeholder: 'Selecione a Rua',
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
            />
          </View>
          <View style={{flex: 20}}>
            <TextInput
              style={styles.viewClienteInput}
              keyboardType={'numeric'}
              placeholder={'Nº'}
              value={this.state.numero.toString()}
              onChangeText={numero => {
                this.setState({
                  numero: numero,
                });
              }}
            />
          </View>
          <View style={[{flex: 10}, {justifyContent: 'center'}]}>
            <TouchableOpacity
                onPress={async () => {
                  this.buscaClientesNumero();
                }}>
            <IconsMCI
                style={styles.clienteBuscaIcon}
                name={'magnify'}
              />
              </TouchableOpacity>
          </View>
        </View>
      </View>
    );
  };

  renderClienteBusca = () => {
    var itemsClientes = this.props.Clientes.map(function(item) {
      return {
        id: item.id,
        name: item.nome,
      };
    });

    return (
      <View style={[this.state.expandInfo && {display: 'none'}]}>
        <View style={[styles.flexDirRow]}>
          <View style={{flex: 100}}>
            <SearchableDropdown
              onItemSelect={item => {
                let cliente = this.props.Clientes.filter(e => e.id === item.id);
                this.getCliente(cliente[0].id);
              }}
              containerStyle={{padding: 0}}
              itemStyle={styles.searchableDropDownItem}
              itemTextStyle={styles.searchableDropDownTextItem}
              itemsContainerStyle={styles.searchableDropDownItemsContainer}
              items={itemsClientes}
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
            />
          </View>
        </View>
      </View>
    );
  };


  renderDropdownClientes = () => {
    var items = this.state.clientesRua.map(function(item) {
      return {
        id: item.id,
        name: item.nome,
      };
    });
    return (
      <View style={[(this.state.hideDropdownCliente || this.state.expandInfo) && {display: 'none'}]}>
        <View style={[items.length==0 && {display: 'none'}]}>
          <SearchableDropdown
            onItemSelect={item => {
              let cliente = this.props.Clientes.filter(e => e.id === item.id);
              this.getCliente(cliente[0].id);
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
          />
          </View>
          <View style={[items.length>0 && {display: 'none'}]}>
            <Text
              style={[
                styles.reportFilter,
              ]}>
              Não há clientes para a rua escolhida
            </Text>
          </View>
      </View>
    );
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
            (this.state.hideCliente) && {display: 'none'},
          ]}
          elevation={5}>
          <View>
            <View style={[styles.flexDirRow]}>
              <View style={[styles.viewChoiceAddress, styles.pad5, {flex: 90}]}>
                <Text style={styles.iconActionListChoice}>
                  {this.state.cliente.nome}
                </Text>
              </View>
              <View style={[styles.viewChoiceAddress, {flex: 10}]}>
                <TouchableOpacity
                  onPress={async () => {
                    this.setState({expandInfo: !this.state.expandInfo});
                  }}>
                  <View>
                    <IconsMCI
                      style={styles.pedidoItemIcon}
                      name={
                        this.state.expandInfo
                          ? 'arrow-collapse'
                          : 'arrow-expand'
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
                      ]}>
                      {'Segmento: ' + this.state.cliente.segmento_descricao}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        (this.state.cliente.convenio !== '1' ||
                        this.state.cliente.convenio_nome === null || this.state.cliente.convenio_nome === '')  && {
                            display: 'none',
                        },
                      ]}>
                      {'Convênio: ' + this.state.cliente.convenio_nome}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                      ]}>
                      {'Pessoa ' + (this.state.cliente.tipopessoa=='J'?'Jurídica':'Física')}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        (this.state.cliente.fantasia === '' ||
                        this.state.cliente.fantasia === null)  && {
                            display: 'none',
                        },
                      ]}>
                      {'Fantasia: ' + this.state.cliente.fantasia}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        (this.state.cliente.cnpjcpf === '' ||
                        this.state.cliente.cnpjcpf === null || this.state.cliente.tipopessoa != 'F')  && {
                            display: 'none',
                        },
                      ]}>
                      {'CPF: ' + this.state.cliente.cnpjcpf}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        (this.state.cliente.rgie === '' ||
                        this.state.cliente.rgie == null || this.state.cliente.tipopessoa != 'F')  && {
                            display: 'none',
                        },
                      ]}>
                      {'RG: ' + this.state.cliente.rgie}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        (this.state.cliente.cnpjcpf === '' ||
                        this.state.cliente.cnpjcpf === null || this.state.cliente.tipopessoa != 'J')  && {
                            display: 'none',
                        },
                      ]}>
                      {'CNPJ: ' + this.state.cliente.cnpjcpf}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        (this.state.cliente.rgie === '' ||
                        this.state.cliente.rgie === null || this.state.cliente.tipopessoa != 'J')  && {
                            display: 'none',
                        },
                      ]}>
                      {'Insc. Estadual: ' + this.state.cliente.rgie}
                    </Text>
                    <Text style={[styles.textConfirmOrderBold, styles.padH15]}>
                      {this.state.cliente.endereco}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        (this.state.cliente.ponto_referencia === '' || this.state.cliente.ponto_referencia === null)  && {
                            display: 'none',
                        },
                      ]}>
                      {'Referência: ' + this.state.cliente.ponto_referencia}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        (this.state.cliente.email === '' || this.state.cliente.email  === null) && {
                          display: 'none',
                        },
                      ]}>
                        {'e-mail: ' + this.state.cliente.email}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        (this.state.cliente.observacoes==='' || this.state.cliente.observacoes  === null) && {
                          display: 'none',
                        },
                      ]}>
                        {'Obs.: ' + this.state.cliente.observacoes}
                    </Text>
                  </View>
                </View>
              </View>
            </View>
          </View>
          <HrAddress />
          <View style={[styles.viewPedidoItens, styles.padH15]}>
            {this.listTelefones()}
          </View>
          <HrAddress />
          <View style={[styles.viewPedidoItens, styles.padH15]}>
            {this.listHistorico()}
          </View>
          <View
            style={[
              styles.pad15,
              {alignItems: 'center'},
              (this.state.cliente.id == '')
                && {
                  display: 'none',
                },
            ]}>
            <TouchableOpacity
              onPress={async () => {
                this.setCliente();
              }}>
              <Text style={[styles.pedidoEnviarButton]}>Escolher Cliente</Text>
            </TouchableOpacity>
          </View>
        </View>
      </View>
    );
  };


  listTelefones = () => {
    return this.state.telefones.map((value, i) => {
      return this.renderFone(value, i);
    });
  };

  renderFone = (telefone, i) => {
    return (
      <View key={i}>
        <View style={[styles.flexDirRow, {flex: 100}]}>
          <View style={{flex: 90}}>
            <View style={[styles.flexDirRow, {flex: 100}]}>
              <View style={[styles.viewPedidoItem]}>
              <View style={[{flex: 10}]}>
                  <Text style={styles.descOutros}>
                    {' '}
                  </Text>
                </View>
                <View style={[{flex: 25}]}>
                  <Text style={styles.descOutros}>
                    {telefone.tipo}
                  </Text>
                </View>
                <View style={[{flex: 35}]}>
                  <Text style={styles.descOutros}>
                    {telefone.telefone}
                  </Text>
                </View>
              </View>
            </View>
          </View>
        </View>
      </View>
    );
  };

  listHistorico = () => {
    return this.state.historico.map((value, i) => {
      return this.renderHistorico(value, i);
    });
  };

  renderHistorico = (historico, i) => {
    return (
      <View key={i}>

            <View style={[styles.flexDirRow, {flex: 100}]}>
              <View style={[styles.viewPedidoItemBold]}>
                <View style={[{flex: 10}]}>
                  <Text style={styles.descOutros}>
                    {' '}
                  </Text>
                </View>
                <View style={[{flex: 25}]}>
                  <Text style={[styles.descOutros, {fontWeight: 'bold'}]}>
                    {historico.pedido_id}
                  </Text>
                </View>
                <View style={[{flex: 35}]}>
                <Text style={[styles.descOutros, {fontWeight: 'bold'}]}>
                    {historico.data}
                  </Text>
                </View>
                <View style={[{flex: 40}]}>
                <Text style={[styles.descOutros, {fontWeight: 'bold'}]}>
                    {historico.condicao}
                  </Text>
                </View>
              </View>
            </View>
            <View style={[styles.flexDirRow, {flex: 100}]}>
              <View style={[styles.viewPedidoItem]}>
                <View style={[{flex: 15}]}>
                  <Text style={styles.descOutros}>
                    {' '}
                  </Text>
                </View>
                <View style={[{flex: 15}]}>
                  <Text style={styles.descOutros}>
                    {historico.quantidade}
                  </Text>
                </View>
                <View style={[{flex: 50}]}>
                  <Text style={styles.descOutros}>
                    {historico.produto}
                  </Text>
                </View>
                <View style={[{flex: 30}]}>
                  <Text style={styles.descOutros}>
                    {historico.valor}
                  </Text>
                </View>
              </View>
            </View>

      </View>
    );
  };


  render() {
    // noinspection ThisExpressionReferencesGlobalObjectJS
    return (
      <View style={styles.containerMenu}>
        <HeaderSimple
          name={'Pesquisar Cliente'}
          toBack={() => {
            this.props.changeCliente(null);
            this.props.navigation.goBack();
          }}
        />
        <ScrollView
          colors={'#830000'}
          keyboardShouldPersistTaps='handled'
          >
          {this.renderClienteBusca()}
          {this.renderRuaBusca()}
          {this.renderDropdownClientes()}
          {this.renderCliente()}
        </ScrollView>
      </View>
    );
  }
}

export default connect(
  mapStateToProps,
  mapDispatchToProps,
)(ClienteBuscaPage);
