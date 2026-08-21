/* eslint-disable react-native/no-inline-styles */
import React from 'react';
import {Text, View} from 'react-native';
import {connect} from 'react-redux';
import {mapDispatchToProps, mapStateToProps} from '../reducers/Functions';
import {styles} from '../assets/css/style';
import {Porcent} from './Helper';
import {ButtonWhiteError} from '../components/Views';

class ErrorPage extends React.Component {
  constructor(props) {
    super(props);
  }

  render() {
    return (
      <View
        style={[
          styles.containerWhite,
          {
            flexDirection: 'column',
            justifyContent: 'center',
            alignItems: 'center',
          },
        ]}>
        <View style={{width: Porcent(1, 20)}}>
          <Text
            style={{
              color: 'white',
              fontSize: Porcent(1, 94),
              fontWeight: 'bold',
              textAlign: 'center',
            }}>
            Lamentamos mas parece que houve um problema ao realizar a
            comunicação com os nossos servidores, verifique sua conexão com a
            internet e tente novamente..
          </Text>
          <ButtonWhiteError
            name={'Tentar Novamente'}
            clicked={() => {
              this.props.navigation.navigate('AuthLoading');
            }}
          />
        </View>
      </View>
    );
  }
}

export default connect(
  mapStateToProps,
  mapDispatchToProps,
)(ErrorPage);
