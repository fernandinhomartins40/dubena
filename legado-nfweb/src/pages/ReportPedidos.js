import React from 'react';
import {
  RefreshControl,
  ScrollView,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import {styles} from '../assets/css/style';
import {HeaderSimple, HrAddress, ButtonOliva} from '../components/Views';
import {connect} from 'react-redux';
import {mapDispatchToProps, mapStateToProps} from '../reducers/Functions';
import BaseComponent from '../components/BaseComponent';
import {IconsMCI} from '../assets/Icons';
import {actualDateFormatted} from '../helper/Helper';
import DatePicker from 'react-native-datepicker';

/**
 * @param props {{navigation: {navigate: function}}}
 * @param address
 */
class ReportPedidosPage extends BaseComponent {
  constructor(props) {
    super(props);
    this.state = {
      initialDate: actualDateFormatted('/').substr(0, 10),
      finalDate: actualDateFormatted('/').substr(0, 10),
    };
  }

  printReport = () => {
    let dti = this.state.initialDate.split('/');
    let dtf = this.state.finalDate.split('/');
    this.props.navigation.navigate('ReportPedidosList', {
      initialDate: dti[2] + '-' + dti[1] + '-' + dti[0],
      finalDate: dtf[2] + '-' + dtf[1] + '-' + dtf[0],
    });
  };

  render() {
    // noinspection ThisExpressionReferencesGlobalObjectJS
    return (
      <View style={[styles.containerMenu]}>
        <HeaderSimple
          name={'Consulta de Pedidos'}
          toBack={() => {
            this.props.navigation.goBack();
          }}
        />
        <View style={[styles.containerAddress, styles.pad5]}>
          <Text
            style={[
              styles.reportFilter,
              {flex: 20, justifyContent: 'flex-end', alignContent: 'flex-end'},
            ]}>
            Selecione o período desejado e clique em buscar.
          </Text>
          <View
            style={[
              {flex: 60, flexDirection: 'column', textAlign: 'center'},
              styles.padH10,
            ]}>
            <View
              style={[
                styles.formsAddress,
                styles.simpleShadow,
                {flex: 30},
                styles.flexDirCol,
                styles.pad15,
              ]}
              elevation={5}>
              <Text style={styles.reportFilter}>Data inicial</Text>
              <View style={[styles.viewChoiceAddress, styles.pad5]}>
                <DatePicker
                  style={{width: 200}}
                  date={this.state.initialDate} //initial date from state
                  mode="date" //The enum of date, datetime and time
                  placeholder="Data Inicial"
                  format="DD/MM/YYYY"
                  confirmBtnText="Confirmar"
                  cancelBtnText="Cancelar"
                  onDateChange={date => {
                    this.setState({
                      initialDate: date,
                      finalDate: this.state.finalDate,
                    });
                  }}
                />
              </View>
            </View>
            <View style={{flex: 15}} />
            <View
              style={[
                styles.formsAddress,
                styles.simpleShadow,
                {flex: 30},
                styles.flexDirCol,
                styles.pad15,
              ]}
              elevation={5}>
              <Text style={styles.reportFilter}>Data final</Text>
              <View style={[styles.viewChoiceAddress, styles.pad5]}>
                <DatePicker
                  style={{width: 200}}
                  date={this.state.finalDate} //initial date from state
                  mode="date" //The enum of date, datetime and time
                  placeholder="Data Final"
                  format="DD/MM/YYYY"
                  confirmBtnText="Confirmar"
                  cancelBtnText="Cancelar"
                  onDateChange={date => {
                    this.setState({
                      initialDate: this.state.initialDate,
                      finalDate: date,
                    });
                  }}
                />
              </View>
            </View>
            <View style={{flex: 25}} />
          </View>
          <View style={[styles.padH10, {flex: 20}]}>
            <ButtonOliva
              name={'Buscar'}
              clicked={() => {
                this.printReport();
              }}
            />
          </View>
        </View>
      </View>
    );
  }
}

export default connect(
  mapStateToProps,
  mapDispatchToProps,
)(ReportPedidosPage);
