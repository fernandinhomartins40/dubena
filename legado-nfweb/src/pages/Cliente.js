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
import {HeaderSimpleEdit, HeaderSimple, HrAddress} from '../components/Views';
import {connect} from 'react-redux';
import {mapDispatchToProps, mapStateToProps} from '../reducers/Functions';
import BaseComponent from '../components/BaseComponent';
import {InputTelefone, InputCPF, InputCEP, InputCNPJ} from '../components/Views';
import {storeData} from '../helper/AsyncStore';
import {IconsMCI} from '../assets/Icons';
import SearchableDropdown from 'react-native-searchable-dropdown';
import {validarCPF, validarCNPJ, validarEmail} from '../helper/Helper';
import {enviarCliente, enviarClienteObs} from '../providers/HttpRequests';
import DatePicker from 'react-native-datepicker';
import MultiSelect from 'react-native-multiple-select';
/**
 * @param props {{navigation: {navigate: function}}}
 * @param address
 */
class ClientePage extends BaseComponent {
  constructor(props) {
    super(props);

    let cid = {id: '', descricao: '', uf: ''};
    let cids = this.props.Cidades.filter(e => e.id == parseInt(this.props.cidade_id));
    if(cids.length > 0){
      cid = cids[0];
    }

    this.state = {
      tipopessoa: {id: '', descricao: '', tipopessoacadastro: ''},
      selectedTipo: [],
      segmento: {id: '', descricao: ''},
      selectedSeg: [],
      sexo: {id: '', descricao: ''},
      selectedSex: [],
      uf: {uf: this.props.uf, descricao: ''},
      selectedUF: [this.props.uf],
      cidade: cid,
      selectedCid: [parseInt(this.props.cidade_id)],
      rua: {id: '', descricao: '', cidade_id: ''},
      selectedRua: [],
      bairro: {id: '', descricao: '', cidade_id: ''},
      selectedBai: [],
      telefonetipo: {id: '', descricao: ''},
      selectedTel: [],
      telefones: [],
      indicadorie: {id: '', descricao: ''},
      selectedInd: [],
      nome: '',
      cpf: '',
      cnpj: '',
      ie: '',
      rg: '',
      cep: '',
      complemento: '',
      pontoreferencia: '',
      observacoes: '',
      email: '',
      telefone: '',
      numero: '',
      data_nascimento: null,
      emiteNF: false,
      consumidorFinal: false,
      expandInfo: false,
      showFinalizar: false,
      enviandoCadastro: false,
      showEdit: false,
      visibleSearchableOption: false,
      clienteEdit: null,
      observacoesEdit: '',
    };
  }

  componentDidMount() {
    
    this.willFocusSubscription = this.props.navigation.addListener(
      'willFocus',
      () => {
        if(this.props.Cliente !== null && this.props.Cliente != undefined){
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
              this.setState({
                clienteEdit: this.props.Cliente,
                observacoesEdit: this.props.Cliente.observacoes
              });
            } else {
              this.setState({showEdit: false, clienteEdit: null});
            }
            this.setState({clienteIndex: i+1}, function() {
              this.setState({visibleSearchableOption : true});
            });
          });
        } else {
          this.setState({visibleSearchableOption : true});
          this.setState({showEdit: false, clienteEdit: null});
        }
    
      },
    );
  }

  enviarCadastro = () => {
    if(this.state.tipopessoa.tipopessoacadastro == 'F' && this.state.cpf != ''){
      if(!validarCPF(this.state.cpf)){
        Alert.alert('Erro', 'CPF informado inválido');
        return;
      }
    }
    if(this.state.tipopessoa.tipopessoacadastro == 'J' && this.state.cnpj != ''){
      if(!validarCNPJ(this.state.cnpj)){
        Alert.alert('Erro', 'CNPJ informado inválido');
        return;
      }
    }
    if(this.state.email != ''){
      if(!validarEmail(this.state.email)){
        Alert.alert('Erro', 'e-mail informado inválido');
        return;
      }
    }
    let cadastro = this.state;
    this.setState({enviandoCadastro: true});

    enviarCliente(cadastro, this.props.userId, this.erroCadastro).then(value => {
      this.setState({enviandoCadastro: false});
      if (typeof value.status === 'string' && value.status === 'OK') {
        let clientes = this.props.Clientes;
        clientes.push(value.data[0]);
        this.props.changeClientes(clientes);
        storeData({
          key: 'clientes',
          data: JSON.stringify(clientes),
        });

        Alert.alert(
          'Sucesso',
          'Cliente ' +  value.data[0].id + ' cadastrado com sucesso.',
          [
            {text: 'OK', onPress: () => {
                this.props.navigation.navigate('Home');
            }},
          ],
          { cancelable: false }
        );
      } else {
        this.showErrorAlert(value);
      }
    });
  };

  erroCadastro = erro => {
    this.setState({enviandoCadastro: false}, function() {
      if (typeof erro === 'object') {
        if (erro.hasOwnProperty('message')) {
          Alert.alert('Ops', erro.message);
        } else {
          Alert.alert(
            'Ops',
            'ocorreu um erro não identificado ao enviar o cliente.',
          );
        }
      } else if (typeof erro === 'string') {
        Alert.alert('Ops', erro);
      } else {
        Alert.alert(
          'Ops',
          'ocorreu um erro não identificado ao enviar o cliente.',
        );
      }
    });
  };

  enviarCadastroObs = () => {
    let cadastro = this.state.clienteEdit;
    cadastro.observacoes = this.state.observacoesEdit;
    this.setState({enviandoCadastro: true});

    enviarClienteObs(cadastro, this.props.userId, this.erroCadastroObs).then(value => {
      this.setState({enviandoCadastro: false});
      if (typeof value.status === 'string' && value.status === 'OK') {
        let cli = value.data[0];
        let clientes = this.props.Clientes.map(el => el.id == cli.id ? {...el, observacoes: cli.observacoes} : el);
        this.props.changeClientes(clientes);
        storeData({
          key: 'clientes',
          data: JSON.stringify(clientes),
        });
        Alert.alert(
          'Sucesso',
          'Cliente ' +  value.data[0].id + ' atualizado com sucesso.',
          [
            {text: 'OK', onPress: () => {
                this.props.navigation.navigate('Home');
            }},
          ],
          { cancelable: false }
        );
      } else {
        this.showErrorAlert(value);
      }
    });
  };

  erroCadastroObs = erro => {
    this.setState({enviandoCadastro: false}, function() {
      if (typeof erro === 'object') {
        if (erro.hasOwnProperty('message')) {
          Alert.alert('Ops', erro.message);
        } else {
          Alert.alert(
            'Ops',
            'ocorreu um erro não identificado ao atualizar as observações.',
          );
        }
      } else if (typeof erro === 'string') {
        Alert.alert('Ops', erro);
      } else {
        Alert.alert(
          'Ops',
          'ocorreu um erro não identificado ao atualizar as observações.',
        );
      }
    });
  };

  

  addTelefone = () => {
    let fones = this.state.telefones;
    let tipo = this.state.telefonetipo;
    let telefone = this.state.telefone;
    if (tipo.id === '') {
      Alert.alert('Ops', 'Selecione o tipo para continuar');
      return;
    }
    let telexists = this.state.telefones.filter(e => e.telefone === telefone);
    if (telexists.length > 0) {
      Alert.alert('Ops', 'Telefone já informado');
      return;
    }

    if (telefone === '') {
      Alert.alert('Ops', 'Informe o telefone para continuar');
      return;
    }
    fones.push({id: tipo.id, telefone: telefone, descricao: tipo.descricao});
    this.setState(
      {
        telefone: '',
      },
      function() {
        Keyboard.dismiss();
      },
    );
  };
  removeTelefone = (telefone) => {
    let newFones = this.state.telefones.filter(e => e.telefone !== telefone);
    this.setState({
      telefones: newFones,
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
            (this.state.telefones.length == 0 && !this.state.showFinalizar) && {display: 'none'},
          ]}
          elevation={5}>
          <View style={!this.state.showFinalizar && {display: 'none'}}>
            <View style={[styles.flexDirRow]}>
              <View style={[styles.viewChoiceAddress, styles.pad5, {flex: 90}]}>
                <Text style={styles.iconActionListChoice}>
                  {this.state.nome}
                </Text>
              </View>
              <View style={[styles.viewChoiceAddress, {flex: 10}]}>
                <TouchableOpacity
                  onPress={async () => {
                    if (this.state.expandInfo) {
                      this.setState({showFinalizar: false});
                    }
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
                      {'Pessoa ' + this.state.tipopessoa.descricao}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        (this.state.cpf === '' ||
                        this.state.cpf === null || this.state.tipopessoa.tipopessoacadastro != 'F')  && {
                            display: 'none',
                        },
                      ]}>
                      {'CPF: ' + this.state.cpf}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        (this.state.rg === '' ||
                        this.state.rg === null || this.state.tipopessoa.tipopessoacadastro != 'F')  && {
                            display: 'none',
                        },
                      ]}>
                      {'RG: ' + this.state.rg}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        (this.state.cnpj === '' ||
                        this.state.cnpj === null || this.state.tipopessoa.tipopessoacadastro != 'J')  && {
                            display: 'none',
                        },
                      ]}>
                      {'CNPJ: ' + this.state.cnpj}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        (this.state.ie === '' ||
                        this.state.ie === null || this.state.tipopessoa.tipopessoacadastro != 'J')  && {
                            display: 'none',
                        },
                      ]}>
                      {'Insc. Estadual: ' + this.state.ie}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                      ]}>
                      {'Segmento: ' + this.state.segmento.descricao}
                    </Text>

                    <Text style={[styles.textConfirmOrderBold, styles.padH15]}>
                      {this.state.rua.descricao + ', ' + this.state.numero + ' - ' + this.state.bairro.descricao + (this.state.complemento==''?'':' - ' + this.state.complemento) + (this.state.cep==''?'':' - ' + this.state.cep) + ' - ' + this.state.cidade.descricao + '/' + this.state.uf.uf}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        (this.state.pontoreferencia === '')  && {
                            display: 'none',
                        },
                      ]}>
                      {'Ponto de Referência: ' + this.state.pontoreferencia}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        (this.state.observacoes === '')  && {
                            display: 'none',
                        },
                      ]}>
                      {'Obs.: ' + this.state.observacoes}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        this.state.email==='' === '' && {
                          display: 'none',
                        },
                      ]}>
                        {this.state.email}
                    </Text>
                    <HrAddress />
                  </View>
                </View>
              </View>
            </View>
          </View>
          <View style={[styles.viewPedidoItens, styles.padH15]}>
            {this.listTelefones()}
          </View>
          <View
            style={[
              styles.pad15,
              {alignItems: 'center'},
              (this.state.nome === '' ||
                this.state.telefones.length === 0 ||
                this.state.segmento.id === '' ||
                this.state.cidade.id === '' ||
                this.state.uf.uf === '' ||
                this.state.bairro.id === '' ||
                this.state.rua.id === '' ||
                this.state.numero === '' ||
                this.state.tipopessoa.id === '' ||
                this.state.showFinalizar)
                && {
                  display: 'none',
                },
            ]}>
            <TouchableOpacity
              onPress={() => {
                this.setState({showFinalizar: true, expandInfo: true});
              }}>
              <Text style={[styles.pedidoEnviarButton]}>Finalizar Cadastro</Text>
            </TouchableOpacity>
          </View>

          <View
            style={[
              styles.pad15,
              {alignItems: 'center'},
              (!this.state.showFinalizar || this.state.enviandoCadastro) && {
                display: 'none',
              },
            ]}>
            <TouchableOpacity
              onPress={async () => {
                this.enviarCadastro();
              }}>
              <Text style={[styles.pedidoEnviarButton]}>Enviar Cadastro</Text>
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
                <View style={[{flex: 25}]}>
                  <Text style={styles.descOutros}>
                    {telefone.descricao}
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
          <View style={{flex: 10}}>
            <View style={[styles.flexDirRow]}>
              <View style={[styles.viewPedidoItem]}>
                <TouchableOpacity
                  onPress={async () => {
                    this.removeTelefone(telefone.telefone);
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
  renderDadosGerais = () => {
    var items = this.props.Tipopessoas.map(function(item) {
      return {
        id: item.id,
        name: item.descricao,
      };
    });
    var itemsseg = this.props.Segmentos.map(function(item) {
      return {
        id: item.id,
        name: item.descricao,
      };
    });
    var itemssex = [{id: 1, name: 'Masculino'}, {id: 2, name: 'Feminino'}];
    const {selectedTipo} = this.state;
    const {selectedSeg} = this.state;
    const {selectedSex} = this.state;

    return (
      <View style={[this.state.expandInfo && {display: 'none'}]}>
        <View style={[styles.flexDirRow]}>
          <TextInput
            style={styles.viewClienteInput}
            placeholder={'Nome/Razão Social'}
            value={this.state.nome.toString()}
            onChangeText={nome => {
              this.setState({
                nome: nome,
              });
            }}
          />
        </View>
        <View style={[styles.flexDirRow]}>
          <View style={[{flex: 60}, {marginRight: 5}]}>
            <MultiSelect
              hideTags
              items={itemsseg}
              uniqueKey="id"
              ref={component => {
                this.multiSelect = component;
              }}
              selectedItems={selectedSeg}
              onSelectedItemsChange={selectedSeg => {
                let segmento = this.props.Segmentos.filter(
                  e => e.id === selectedSeg[0],
                );
                this.setState({
                  segmento: segmento[0],
                });
                this.setState({selectedSeg});
              }}
              textInputProps={{editable: false, autoFocus: false}}
              searchInputPlaceholderText=""
              searchIcon={false}
              selectText="    Segmento"
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
                Keyboard.dismiss();
              }}
            />
          </View>
          <View style={[{flex: 40}]}>
            <MultiSelect
              hideTags
              items={items}
              uniqueKey="id"
              ref={component => {
                this.multiSelect = component;
              }}
              selectedItems={selectedTipo}
              onSelectedItemsChange={selectedTipo => {
                let tipo = this.props.Tipopessoas.filter(
                  e => e.id === selectedTipo[0],
                );
                this.setState({
                  tipopessoa: tipo[0],
                });
                this.setState({selectedTipo});
              }}
              textInputProps={{editable: false, autoFocus: false}}
              searchInputPlaceholderText=""
              searchIcon={false}
              selectText="    Tipo Pessoa"
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
                Keyboard.dismiss();
              }}

            />
          </View>
        </View>
        <View
          style={[
            styles.flexDirRow,
            this.state.tipopessoa.tipopessoacadastro != 'F' && {
              display: 'none',
            },
          ]}>
          <View
            style={[
              {
                flex: 60,
                height: 48,
                alignContent: 'space-around',
                borderWidth: 0,
              },
            ]}>
            <InputCPF
              style={styles.viewClienteInput}
              placeText={'CPF'}
              keyboardType={'numeric'}
              textData={this.state.cpf}
              whenChangeText={cpf => {
                this.setState({
                  cpf: cpf,
                });
              }}
            />
          </View>
          <View
            style={[
              {
                flex: 40,
                height: 48,
                alignContent: 'space-around',
                borderWidth: 0,
              },
            ]}>
            <TextInput
              style={styles.viewClienteInput}
              placeholder={'RG'}
              keyboardType={'numeric'}
              value={this.state.rg.toString()}
              onChangeText={rg => {
                this.setState({
                  rg: rg,
                });
              }}
            />
          </View>
        </View>
        <View
          style={[
            styles.flexDirRow,
            this.state.tipopessoa.tipopessoacadastro != 'F' && {
              display: 'none',
            },
          ]}>
          <View style={[{flex: 40}]}>
            <MultiSelect
              hideTags
              items={itemssex}
              uniqueKey="id"
              ref={component => {
                this.multiSelect = component;
              }}
              selectedItems={selectedSex}
              onSelectedItemsChange={selectedSex => {
                let sexo = itemssex.filter(e => e.id === selectedSex[0]);
                this.setState({
                  sexo: sexo[0],
                });
                this.setState({selectedSex});
              }}
              textInputProps={{editable: false, autoFocus: false}}
              searchInputPlaceholderText=""
              searchIcon={false}
              selectText="    Sexo"
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
                Keyboard.dismiss();
              }}

            />
          </View>
          <View
            style={[
              {
                flex: 60,
                height: 48,
                alignContent: 'space-around',
                borderWidth: 0,
                marginLeft: 10,
                marginTop: 2,
              },
            ]}>
            <DatePicker
              style={{width: 200}}
              date={this.state.data_nascimento} //initial date from state
              mode="date" //The enum of date, datetime and time
              placeholder="Data Nasc."
              format="DD/MM/YYYY"
              confirmBtnText="Confirmar"
              cancelBtnText="Cancelar"
              onDateChange={date => {
                this.setState({
                  data_nascimento: date,
                });
              }}
            />
          </View>
        </View>
        <View
          style={[
            styles.flexDirRow,
            this.state.tipopessoa.tipopessoacadastro != 'J' && {
              display: 'none',
            },
          ]}>
          <View
            style={[
              {
                flex: 60,
                height: 48,
                alignContent: 'space-around',
                borderWidth: 0,
              },
            ]}>
            <InputCNPJ
              style={styles.viewClienteInput}
              placeText={'CNPJ'}
              keyboardType={'numeric'}
              textData={this.state.cnpj}
              whenChangeText={cnpj => {
                this.setState({
                  cnpj: cnpj,
                });
              }}
            />
          </View>
          <View
            style={[
              {
                flex: 40,
                height: 48,
                alignContent: 'space-around',
                borderWidth: 0,
              },
            ]}>
            <TextInput
              style={styles.viewClienteInput}
              placeholder={'Insc. Est.'}
              keyboardType={'numeric'}
              value={this.state.ie.toString()}
              onChangeText={ie => {
                this.setState({
                  ie: ie,
                });
              }}
            />
          </View>
        </View>
      </View>
    );
  };

  renderEndereco = () => {
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

    let bais = this.props.Bairros.filter(e => e.cidade_id == selectedCid[0]);

    var itemsBairro = bais.map(function(item) {
      return {
        id: item.id,
        name: item.descricao,
      };
    });
    const {selectedBai} = this.state;

    var itemsie = [ {id: '', name: 'Não informado'}, {id: 1, name: 'Contribuinte ICMS'}, {id: 2, name: 'Contribuinte Isento'}, {id: 9, name: 'Não Contribuinte'}];
    const {selectedInd} = this.state;


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
                let reg = this.props.Cidades.filter(
                  e => e.id === selectedCid[0],
                );
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
                Keyboard.dismiss();
              }}

            />
          </View>
        </View>
        <View style={[styles.flexDirRow]}>
          <View style={{flex: 80}}>
            <SearchableDropdown
              onItemSelect={item => {
                let rua = this.props.Ruas.filter(e => e.id === item.id);
                this.setState({
                  rua: rua[0],
                });
                this.setState({selectedRua});
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
        </View>
        <View style={[styles.flexDirRow]}>
          <View style={[{flex: 60}]}>
            <MultiSelect
              hideTags
              items={itemsBairro}
              uniqueKey="id"
              ref={component => {
                this.multiSelect = component;
              }}
              selectedItems={selectedBai}
              onSelectedItemsChange={selectedBai => {
                let reg = this.props.Bairros.filter(
                  e => e.id === selectedBai[0],
                );
                this.setState({
                  bairro: reg[0],
                });
                this.setState({selectedBai});
              }}
              textInputProps={{editable: false, autoFocus: false}}
              searchInputPlaceholderText=""
              searchIcon={false}
              selectText="    Bairro"
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
                Keyboard.dismiss();
              }}

            />
          </View>
          <View
          style={[
            {
              flex: 40,
              height: 48,
              alignContent: 'space-around',
              borderWidth: 0,
            },
          ]}>
            <InputCEP
              style={styles.viewClienteInput}
              placeText={'CEP'}
              keyboardType={'numeric'}
              textData={this.state.cep}
              whenChangeText={cep => {
                this.setState({
                  cep: cep,
                });
              }}
            />
          </View>
        </View>
        <View style={[styles.flexDirRow]}>
          <View style={[{flex: 50}]}>
            <TextInput
              style={styles.viewClienteInput}
              placeholder={'Complemento'}
              value={this.state.complemento.toString()}
              onChangeText={complemento => {
                this.setState({
                  complemento: complemento,
                });
              }}
            />          
          </View>
          <View
          style={[
            {
              flex: 50,
              height: 48,
              alignContent: 'space-around',
              borderWidth: 0,
            },
          ]}>
            <TextInput
              style={styles.viewClienteInput}
              placeholder={'Ponto Referência'}
              value={this.state.pontoreferencia.toString()}
              onChangeText={pontoreferencia => {
                this.setState({
                  pontoreferencia: pontoreferencia,
                });
              }}
            />
          </View>
        </View>
        <View style={[styles.flexDirRow]}>
          <View style={[{flex: 100}]}>
            <TextInput
              style={styles.viewClienteInput}
              placeholder={'Observações'}
              value={this.state.observacoes.toString()}
              onChangeText={observacoes => {
                this.setState({
                  observacoes: observacoes,
                });
              }}
            />
          </View>
        </View>
        <View style={styles.flexDirRow}>
            <View style={[{flex: 5}, styles.viewPedidoCheckbox]}>
              <CheckBox
                value={this.state.emiteNF}
                onValueChange={() =>
                  this.setState({emiteNF: !this.state.emiteNF})
                }
              />
              </View>
            <View style={[{flex: 8}, styles.viewPedidoCheckbox]}>
              <Text style={[styles.viewPedidoTextCheckbox]}>NF</Text>
            </View>
            <View style={[{flex: 5}, styles.viewPedidoCheckbox, (!this.state.emiteNF || true) && {display: 'none'}]}>
            { (this.state.emiteNF && false) && (
              <CheckBox
                value={this.state.consumidorFinal}
                onValueChange={() =>
                  this.setState({consumidorFinal: !this.state.consumidorFinal})
                }
                />
            )}
            </View>
            <View style={[{flex: 22}, styles.viewPedidoCheckbox, (!this.state.emiteNF || true) && {display: 'none'}]}>
              <Text style={[styles.viewPedidoTextCheckbox]}>C.Final</Text>
            </View>
            <View style={[{flex: 60}]}>
              <View style={[!this.state.emiteNF && {display: 'none'}]}>
              <MultiSelect
                hideTags
                items={itemsie}
                uniqueKey="id"
                ref={component => {
                  this.multiSelect = component;
                }}
                selectedItems={selectedInd}
                onSelectedItemsChange={selectedInd => {
                  let indie = itemsie.filter(e => e.id === selectedInd[0]);
                  this.setState({
                    indicadorie: indie[0],
                  });
                  this.setState({selectedInd});
                }}
                textInputProps={{editable: false, autoFocus: false}}
                searchInputPlaceholderText=""
                searchIcon={false}
                selectText="    Indicador IE"
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
          </View>
        <View style={[styles.flexDirRow]}>
          <View style={[{flex: 1}]}>
            <TextInput
              style={styles.viewClienteInput}
              placeholder={'e-mail'}
              value={this.state.email.toString()}
              onChangeText={email => {
                this.setState({
                  email: email,
                });
              }}
            />          
          </View>
        </View>

      </View>
    );
  };

  renderTelefones = () => {
    var items = this.props.Telefonetipos.map(function(item) {
      return {
        id: item.id,
        name: item.descricao,
      };
    });
    const {selectedTel} = this.state;
    return (
      <View style={this.state.expandInfo && {display: 'none'}}>
        <View style={[styles.flexDirRow]}>
          <View style={[{flex: 40}]}>
            <MultiSelect
              hideTags
              items={items}
              uniqueKey="id"
              ref={component => {
                this.multiSelect = component;
              }}
              selectedItems={selectedTel}
              onSelectedItemsChange={selectedTel => {
                let reg = this.props.Telefonetipos.filter(
                  e => e.id === selectedTel[0],
                );
                this.setState({
                  telefonetipo: reg[0],
                });
                this.setState({selectedTel});
              }}
              textInputProps={{editable: false, autoFocus: false}}
              searchInputPlaceholderText=""
              searchIcon={false}
              selectText="    Tipo Fone"
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
                Keyboard.dismiss();
              }}

            />
          </View>
          <View style={[{flex: 50}]}>
            <InputTelefone
              style={styles.viewClienteInput}
              placeText={'Telefone'}
              keyboardType={'numeric'}
              textData={this.state.telefone}
              whenChangeText={telefone => {
                this.setState({
                  telefone: telefone,
                });
              }}
            />
          </View>
          <View style={[{flex: 10}]}>
            <TouchableOpacity
              onPress={async () => {
                this.addTelefone();
              }}
              style={(styles.padH5, styles.pedidoAddItem)}>
              <IconsMCI style={styles.pedidoItemIcon} name="plus-circle-outline" />
            </TouchableOpacity>
          </View>
        </View>
      </View>
    );
  };

  renderDropdownClientes = () => {
    return (
      <View style={[this.state.expandInfo && {display: 'none'}]}>
        <View style={[styles.flexDirRow]}>
        <View style={[{flex: 90}]}>
        {this.state.visibleSearchableOption && this.state.clienteEdit ?
          <View style={[styles.viewChoiceAddress, styles.pad5, {flex: 90}]}>
          <Text style={styles.iconActionListChoice}>
            {this.state.clienteEdit.nome}
          </Text>
        </View>
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


      </View>
    );
  };

  renderClienteEdit = () => {
    return (
      <View>
        <View
          style={[
            styles.formsAddress,
            styles.simpleShadow,
            styles.flexDirCol,
            {marginLeft: 5},
            (!this.state.clienteEdit) && {display: 'none'},
          ]}
          elevation={5}>
          <View>
            <View style={[styles.flexDirRow]}>
              <View style={[styles.viewChoiceAddress, styles.pad5, {flex: 90}]}>
                <Text style={styles.iconActionListChoice}>
                  {this.state.clienteEdit.nome}
                </Text>
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
                      {'Segmento: ' + this.state.clienteEdit.segmento_descricao}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        (this.state.clienteEdit.convenio !== '1' ||
                        this.state.clienteEdit.convenio_nome === null || this.state.clienteEdit.convenio_nome === '')  && {
                            display: 'none',
                        },
                      ]}>
                      {'Convênio: ' + this.state.clienteEdit.convenio_nome}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                      ]}>
                      {'Pessoa ' + (this.state.clienteEdit.tipopessoa=='J'?'Jurídica':'Física')}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        (this.state.clienteEdit.fantasia === '' ||
                        this.state.clienteEdit.fantasia === null)  && {
                            display: 'none',
                        },
                      ]}>
                      {'Fantasia: ' + this.state.clienteEdit.fantasia}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        (this.state.clienteEdit.cnpjcpf === '' ||
                        this.state.clienteEdit.cnpjcpf === null || this.state.clienteEdit.tipopessoa != 'F')  && {
                            display: 'none',
                        },
                      ]}>
                      {'CPF: ' + this.state.clienteEdit.cnpjcpf}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        (this.state.clienteEdit.rgie === '' ||
                        this.state.clienteEdit.rgie == null || this.state.clienteEdit.tipopessoa != 'F')  && {
                            display: 'none',
                        },
                      ]}>
                      {'RG: ' + this.state.clienteEdit.rgie}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        (this.state.clienteEdit.cnpjcpf === '' ||
                        this.state.clienteEdit.cnpjcpf === null || this.state.clienteEdit.tipopessoa != 'J')  && {
                            display: 'none',
                        },
                      ]}>
                      {'CNPJ: ' + this.state.clienteEdit.cnpjcpf}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        (this.state.clienteEdit.rgie === '' ||
                        this.state.clienteEdit.rgie === null || this.state.clienteEdit.tipopessoa != 'J')  && {
                            display: 'none',
                        },
                      ]}>
                      {'Insc. Estadual: ' + this.state.clienteEdit.rgie}
                    </Text>
                    <Text style={[styles.textConfirmOrderBold, styles.padH15]}>
                      {this.state.clienteEdit.endereco}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        (this.state.clienteEdit.ponto_referencia === '' || this.state.clienteEdit.ponto_referencia === null)  && {
                            display: 'none',
                        },
                      ]}>
                      {'Referência: ' + this.state.clienteEdit.ponto_referencia}
                    </Text>
                    <Text
                      style={[
                        styles.textConfirmOrder,
                        styles.padH15,
                        (this.state.clienteEdit.email === '' || this.state.clienteEdit.email  === null) && {
                          display: 'none',
                        },
                      ]}>
                        {'e-mail: ' + this.state.clienteEdit.email}
                    </Text>

                  </View>
                </View>
              </View>
            </View>

            <HrAddress />
            
            <Text style={[styles.textConfirmOrderBold, styles.padH15]}>
              Observações
            </Text>
            <View style={[styles.flexDirRow]}>
              <View style={[{flex:1, marginLeft: 15}]}>
                <TextInput style={styles.clienteObservacoes}
                  multiline={true}
                  placeholder={'Obs. Cliente'}
                  value={this.state.observacoesEdit}
                  onChangeText={observacoes => {
                    this.setState({
                      observacoesEdit: observacoes,
                    });
                  }}
                />
                
              </View>
              <View style={[{flex:2}]}></View>
            </View>
          </View>

          <View
            style={[
              styles.pad15,
              {alignItems: 'center'},
              (this.state.clienteEdit.id == '')
                && {
                  display: 'none',
                },
            ]}>
            <View style={[styles.flexDirRow]}>
              <View style={{flex:5}}></View>
              <TouchableOpacity style={{flex:20}}
                onPress={async () => {
                  this.setState({clienteEdit: null, showEdit: false, clienteIndex: 0});
                }}>
                <Text style={[styles.clienteEnviarButton,{margin:2, backgroundColor: '#3366cc'}]}>Cancelar</Text>
              </TouchableOpacity>
              <TouchableOpacity style={{flex:20}}
                onPress={async () => {
                  this.enviarCadastroObs();
                }}>
                <Text style={[styles.clienteEnviarButton,{margin:2}]}>Enviar</Text>
              </TouchableOpacity>
              <View style={{flex:5}}></View>
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
        {!this.state.showEdit ?
        <HeaderSimpleEdit
          name={'Cadastrar Cliente'}
          toBack={() => {
            this.props.navigation.goBack();
          }}
          edit={() => {
            this.setState({showEdit: true}, ()=>{
              this.props.navigation.navigate('ClienteBusca');
            });
          }}
        />
        :
        <HeaderSimple
          name={'Alterar Obs. Cliente'}
          toBack={() => {
            this.props.navigation.goBack();
          }}
        />
        }
        <ScrollView
          colors={'#830000'}
          keyboardShouldPersistTaps='handled'
          >
          {this.state.showEdit && this.state.clienteEdit && this.renderClienteEdit()} 
          {!this.state.showEdit && this.renderDadosGerais()}
          {!this.state.showEdit && this.renderEndereco()}
          {!this.state.showEdit && this.renderTelefones()}
          <View style={[styles.containerAddress, styles.pad5]} />
          {!this.state.showEdit && this.renderCliente()}
        </ScrollView>
      </View>
    );
  }
}

export default connect(
  mapStateToProps,
  mapDispatchToProps,
)(ClientePage);
