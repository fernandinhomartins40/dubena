/* eslint-disable react-native/no-inline-styles */
import React from 'react';
import {
  RefreshControl,
  ScrollView,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import {styles} from '../assets/css/style';
import {HeaderSimple, HrAddress} from '../components/Views';
import {connect} from 'react-redux';
import {
  getUserState,
  mapDispatchToProps,
  mapStateToProps,
} from '../reducers/Functions';
import BaseComponent from '../components/BaseComponent';
import {IconsMCI} from '../assets/Icons';
import {storeData} from '../helper/AsyncStore';
import {setVeiculoPadrao} from '../providers/HttpRequests';

/**
 * @param props {{navigation: {navigate: function}}}
 * @param address
 */
class VeiculoPage extends BaseComponent {
  setVeiculoPadrao = veiculo_id => {
    setVeiculoPadrao(veiculo_id, this.props.userId).then(value => {
      if (typeof value.status === 'string' && value.status === 'OK') {
        this.props.changeUserState(
          getUserState(this.props, veiculo_id, 'veiculoId'),
        );
        this.props.navigation.navigate('Home');
      } else {
        this.showErrorAlert(value);
      }
    });
  };

  storeUser = (index, id) => {
    let data = {
      userId: this.props.userId,
      userName: this.props.userName,
      userPhone: this.props.userPhone,
      registrationId: this.props.registrationId,
      presencaoComprador: this.props.presencaComprador,
      modalidadeFrete: this.props.modalidadeFrete,
      transportadorId: this.props.transportadorId,
      veiculoId: this.props.veiculoId,
      setorId: this.props.setorId,
      setorDescricao: this.props.setorDescricao,
    };
    data.veiculoId = id;
    this.props.changeUserState(data);

    let dataStore = {
      key: 'userData',
      data: JSON.stringify(data),
    };

    storeData(dataStore).then(() => {
      this.props.navigation.navigate('Home');
    });
  };

  favorite = (index, id) => {
    this.storeUser(index, id);
  };

  listVeiculos = () => {
    let veiculos = this.props.Veiculos;
    return veiculos.map((value, i) => {
      let iconVeiculo =
        value.id === this.props.veiculoId ? 'truck-check' : 'truck';
      return this.renderVeiculo(iconVeiculo, value, i);
    });
  };

  renderVeiculo = (iconVeiculo, veiculo, i) => {
    return (
      <View key={i} style={styles.pad5}>
        <TouchableOpacity
          onPress={async () => {
            this.setVeiculoPadrao(veiculo.id);
          }}
          style={styles.padH5}>
          <View
            style={[
              styles.formsAddress,
              styles.simpleShadow,
              styles.flexDirCol,
            ]}
            elevation={5}>
            <View style={[styles.viewChoiceAddress, styles.pad5]}>
              <Text style={styles.iconActionListChoice}>{veiculo.placa}</Text>
              <View>
                <IconsMCI style={styles.addressIconLIst} name={iconVeiculo} />
              </View>
            </View>
            <HrAddress />
            <View style={styles.flexDirRow}>
              <View style={[styles.pad5, styles.padH15]}>
                <Text style={styles.descOutros}>{veiculo.descricao}</Text>
              </View>
            </View>
          </View>
        </TouchableOpacity>
      </View>
    );
  };

  render() {
    // noinspection ThisExpressionReferencesGlobalObjectJS
    return (
      <View style={styles.containerMenu}>
        <HeaderSimple
          name={'Veículos'}
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
            {this.listVeiculos()}
          </View>
        </ScrollView>
      </View>
    );
  }
}

export default connect(
  mapStateToProps,
  mapDispatchToProps,
)(VeiculoPage);
