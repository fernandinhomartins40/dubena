import React from 'react';
import {KeyboardAwareScrollView} from 'react-native-keyboard-aware-scroll-view';
import {connect} from 'react-redux';
import {Alert, Image, Platform, Text, View} from 'react-native';
import {
  getUserState,
  mapDispatchToProps,
  mapStateToProps,
} from '../reducers/Functions';
import {
  ButtonBlack,
  InputMaskTel,
  ModalPolicyPrivacy,
} from '../components/Views';
import {getPolicyPrivacy} from '../helper/Http';
import {styles} from '../assets/css/style';
import {constants} from '../helper/Constants';

class LoginPage extends React.Component {
  policyText = [];

  constructor(props) {
    super(props);

    this.state = {policyPrivacy: false};
  }

  async componentDidMount() {
    this.props.changeUserState(getUserState(this.props, '', 'userName'));
    this.props.changeUserState(getUserState(this.props, '', 'userPhone'));

    getPolicyPrivacy().then(result => {
      this.policyText = result;
    });
  }

  login = () => {
    if (
      !this.props.userPhone ||
      (this.props.userPhone && this.props.userPhone.length < 15)
    ) {
      Alert.alert(
        'Ops...',
        'Parece que esse número de telefone tá errado, tenta novamente?',
      );
      return;
    }
    if (!constants.DEBUG_MODE) {
      this.goSms();
    } else {
      this.statePolicyPrivacy(true);
    }
  };

  statePolicyPrivacy = (visible = false, callback = () => {}) =>
    this.setState({policyPrivacy: visible}, callback);

  goSms = () => this.props.navigation.navigate('Sms');

  renderModal() {
    return (
      <ModalPolicyPrivacy
        active={this.state.policyPrivacy}
        objectPolicy={this.policyText.map((value, i, j, k) => {
          return (
            <View key={i}>
              <Text style={styles.policyTitle} key={j}>
                {value.title}
              </Text>
              <Text style={styles.policyDescription} key={k}>
                {value.description}
              </Text>
            </View>
          );
        })}
        accepted={() => this.statePolicyPrivacy(false, this.goSms)}
        refused={this.statePolicyPrivacy}
      />
    );
  }

  homePage = () => {
    // noinspection ThisExpressionReferencesGlobalObjectJS
    return (
      <View
        style={{
          flex: 1,
          flexDirection: 'column',
          backgroundColor: '#ffffff',
          alignItems: 'center',
          justifyContent: 'space-evenly',
        }}>
        {this.renderModal()}
        <View style={{flex: 1, alignItems: 'center', justifyContent: 'center'}}>
          <View style={[styles.pad15, {justifyContent: 'center'}]}>
            <Image
              style={styles.homeLogo}
              source={require('../assets/imgs/home-logo.png')}
              resizeMode="stretch"
            />
          </View>

          <View style={styles.pad15}>
            <View style={[styles.forms, styles.simpleShadow]} elevation={5}>
              <InputMaskTel
                placeText={'Digite o n° deste telefone com DDD'}
                textData={this.props.userPhone}
                whenChangeText={phone => {
                  this.props.changeUserState(
                    getUserState(this.props, phone, 'userPhone'),
                  );
                }}
              />
            </View>
          </View>
          <View style={[styles.pad15, styles.simpleShadow]} elevation={5}>
            <ButtonBlack name={'Entrar'} clicked={this.login} />
          </View>
        </View>
      </View>
    );
  };

  render() {
    let device = Platform.OS;

    if (device === 'ios') {
      // noinspection ThisExpressionReferencesGlobalObjectJS
      return (
        <View style={{backgroundColor: '#ffffff'}}>
          <KeyboardAwareScrollView
            contentContainerStyle={{
              backgroundColor: '#ffffff',
              width: '100%',
              height: '100%',
              justifyContent: 'center',
              alignItems: 'center',
            }}
            scrollEnabled={true}>
            {this.homePage()}
          </KeyboardAwareScrollView>
        </View>
      );
    } else {
      // noinspection ThisExpressionReferencesGlobalObjectJS
      return (
        <KeyboardAwareScrollView
          style={{flex: 1, backgroundColor: 'black'}}
          contentContainerStyle={{height: '100%'}}
          resetScrollToCoords={{x: 0, y: 0}}
          scrollEnabled={true}>
          {this.homePage()}
        </KeyboardAwareScrollView>
      );
    }
  }
}

export default connect(
  mapStateToProps,
  mapDispatchToProps,
)(LoginPage);
