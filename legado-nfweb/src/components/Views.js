/* eslint-disable react-native/no-inline-styles */
import React from 'react';
import {
  Modal,
  Platform,
  SafeAreaView,
  ScrollView,
  Text,
  TextInput,
  TouchableOpacity,
  TouchableWithoutFeedback,
  View,
} from 'react-native';
import {TextInputMask} from 'react-native-masked-text';
import {styles} from '../assets/css/style';
import {Icons} from '../assets/Icons';

const isIos = Platform.OS === 'ios';
const wFlex = Platform.OS === 'ios' ? 1 : 0;

export const Header = props => {
  const iconIOS = () => {
    if (isIos) {
      return (
        <TouchableOpacity style={styles.padH5} onPress={props.toBack}>
          <View style={styles.backButtonHeader}>
            <Icons
              style={styles.menuHeaderIcon}
              name="arrow-back"
              color="white"
              size={25}
            />
          </View>
        </TouchableOpacity>
      );
    } else {
      return <View style={[styles.padH5, styles.backButtonHeader]} />;
    }
  };

  return (
    <SafeAreaView style={[styles.menuHeader, styles.pad5]}>
      <View style={{flex: wFlex}} />
      <View style={[styles.headerMenu, styles.padH5]}>
        <View>{iconIOS()}</View>
        <View>
          <Text style={styles.menuHeaderName}>{props.name}</Text>
        </View>
        <View style={[styles.padH5, styles.ViewHeaderTutorial]}>
          {/*<Text style={{ fontSize: Porcent(1, 93), color: 'black' }}>?</Text>*/}
        </View>
      </View>
    </SafeAreaView>
  );
};

export const HeaderSimple = props => {
  const iconIOS = () => {
    if (isIos || !isIos) {
      return (
        <TouchableOpacity style={styles.padH5} onPress={props.toBack}>
          <View style={styles.backButtonHeader}>
            <Icons
              style={styles.menuHeaderIcon}
              name="arrow-back"
              color="white"
              size={25}
            />
          </View>
        </TouchableOpacity>
      );
    } else {
      return <View style={[styles.padH5, styles.backButtonHeader]} />;
    }
  };

  return (
    <SafeAreaView style={[styles.menuHeader, styles.pad5]}>
      <View style={{flex: wFlex}} />
      <View style={[styles.headerMenu, styles.padH5]}>
        <View>{iconIOS()}</View>
        <View>
          <Text style={styles.menuHeaderName}>{props.name}</Text>
        </View>
        <View style={[styles.padH5, styles.ViewHeaderTutorial]}>
          {/*<Text style={{ fontSize: Porcent(1, 93), color: 'black' }}>?</Text>*/}
        </View>
      </View>
    </SafeAreaView>
  );
};
export const HeaderSimpleEdit = props => {
  const iconIOS = () => {
    if (isIos || !isIos) {
      return (
        <TouchableOpacity style={styles.padH5} onPress={props.toBack}>
          <View style={styles.backButtonHeader}>
            <Icons
              style={styles.menuHeaderIcon}
              name="arrow-back"
              color="white"
              size={25}
            />
          </View>
        </TouchableOpacity>
      );
    } else {
      return <View style={[styles.padH5, styles.backButtonHeader]} />;
    }
  };

  const iconIOSEdit = () => {
    if (isIos || !isIos) {
      return (
        <TouchableOpacity style={styles.padH5} onPress={props.edit}>
          <View style={styles.backButtonHeader}>
            <Icons
              style={styles.menuHeaderIcon}
              name="clipboard"
              color="white"
              size={25}
            />
          </View>
        </TouchableOpacity>
      );
    } else {
      return <View style={[styles.padH5, styles.backButtonHeader]} />;
    }
  };

  return (
    <SafeAreaView style={[styles.menuHeader, styles.pad5]}>
      <View style={{flex: wFlex}} />
      <View style={[styles.headerMenu, styles.padH5]}>
        <View>{iconIOS()}</View>
        <View>
          <Text style={styles.menuHeaderName}>{props.name}</Text>
        </View>
        <View>
          {iconIOSEdit()}
        </View>
      </View>
    </SafeAreaView>
  );
};

export const HeaderTrack = props => {
  return (
    <SafeAreaView style={[styles.menuHeader, styles.pad5]}>
      <View style={{flex: wFlex}} />
      <View style={[styles.headerMenu, styles.padH5]}>
        <View>
          <View style={[styles.padH5, styles.backButtonHeader]} />
        </View>
        <View>
          <Text style={styles.menuHeaderName}>{props.name}</Text>
        </View>
        <View style={[styles.padH5, styles.ViewHeaderTutorial]}>
          {/*<Text style={{ fontSize: Porcent(1, 93), color: 'black' }}>?</Text>*/}
        </View>
      </View>
    </SafeAreaView>
  );
};

/**
 *
 * @param props {{toBack, clicked}}
 * @returns {*}
 * @constructor
 */
export const MapHeader = props => {
  const iconIOS = () => {
    if (isIos) {
      return (
        <TouchableOpacity style={styles.padH5} onPress={props.toBack}>
          <View style={styles.backButtonHeader}>
            <Icons style={styles.mapHeaderIcon} name="arrow-back" size={25} />
          </View>
        </TouchableOpacity>
      );
    } else {
      return (
        <View>
          <TouchableOpacity style={styles.padH5}>
            <Icons style={styles.mapHeaderIconMD} name="arrow-back" size={25} />
          </TouchableOpacity>
        </View>
      );
    }
  };

  return (
    <SafeAreaView style={[styles.mapHeader, styles.padH10]}>
      <View style={{flex: wFlex}} />
      <View style={[styles.headerMenu, styles.padH5]}>
        <View>{iconIOS()}</View>
        <TouchableOpacity onPress={props.clicked}>
          <View style={styles.viewMapSelectButton}>
            <Text style={[styles.menuHeaderNameMD, styles.padH5]}>
              Selecionar manualmente
            </Text>
          </View>
        </TouchableOpacity>
        <View style={[styles.padH15, styles.mapViewHeaderTutorial]}>
          <Icons style={styles.mapHeaderIconMD} name="arrow-back" size={25} />
        </View>
      </View>
    </SafeAreaView>
  );
};

export const MapHeaderCancel = props => {
  return (
    <View style={styles.cancelMapManualView}>
      <TouchableOpacity onPress={props.clicked}>
        <View
          style={[styles.cancelMapManualButton, styles.simpleShadow]}
          elevation={5}>
          <Text style={styles.buttonNative}>Cancelar</Text>
        </View>
      </TouchableOpacity>

      <View
        style={[styles.viewTextCancelMap, styles.simpleShadow]}
        elevation={5}>
        <Text style={styles.inputTextCancelMap}>{props.address}</Text>
      </View>
    </View>
  );
};

/**
 *
 * @param props {{placeText, whenChangeText, keyboardType, textData, sizeText}}
 * @returns {*}
 * @constructor
 */
export const Input = props => {
  return (
    <TextInput
      style={styles.input}
      placeholder={props.placeText}
      onChangeText={props.whenChangeText}
      keyboardType={props.keyboardType ? props.keyboardType : 'default'}
      value={props.textData}
      maxLength={props.sizeText}
    />
  );
};
/**
 *
 * @param props {{finisTyping, style, textData, whenChangeText}}
 * @returns {*}
 * @constructor
 */
export const InputDate = props => {
  return (
    <TextInputMask
      style={props.style}
      value={props.textData}
      type={'custom'}
      keyboardType={'numeric'}
      options={{
        mask: '99/99/9999',
      }}
      onChangeText={props.whenChangeText}
      onFinishEditing={props.finisTyping}
    />
  );
};

export const InputCPF = props => {
  return (
    <TextInputMask
      style={props.style}
      value={props.textData}
      placeholder={props.placeText}
      type={'custom'}
      keyboardType={'numeric'}
      options={{
        mask: '999.999.999-99',
      }}
      onChangeText={props.whenChangeText}
      onFinishEditing={props.finisTyping}
    />
  );
};

export const InputCEP = props => {
  return (
    <TextInputMask
      style={props.style}
      value={props.textData}
      placeholder={props.placeText}
      type={'custom'}
      keyboardType={'numeric'}
      options={{
        mask: '99999-999',
      }}
      onChangeText={props.whenChangeText}
      onFinishEditing={props.finisTyping}
    />
  );
};

export const InputCNPJ = props => {
  return (
    <TextInputMask
      style={props.style}
      value={props.textData}
      placeholder={props.placeText}
      type={'custom'}
      keyboardType={'numeric'}
      options={{
        mask: '99.999.999/9999-99',
      }}
      onChangeText={props.whenChangeText}
      onFinishEditing={props.finisTyping}
    />
  );
};

export const InputTelefone = props => {
  return (
    <TextInputMask
      style={props.style}
      value={props.textData}
      type={'cel-phone'}
      keyboardType={'numeric'}
      placeholder={props.placeText}
      options={{
        maskType: 'BRL',
        withDDD: true,
        dddMask: '(99) ',
      }}
      onChangeText={props.whenChangeText}
      onFinishEditing={props.finisTyping}
    />
  );
};


export const InputMaskTel = props => {
  return (
    <TextInputMask
      refInput={ref => (props.tel = ref)}
      style={styles.input}
      value={props.textData}
      type={'cel-phone'}
      placeholder={props.placeText}
      options={{
        maskType: 'BRL',
        withDDD: true,
        dddMask: '(99) ',
      }}
      onChangeText={props.whenChangeText}
    />
  );
};

export const VrAddress = () => {
  return (
    <View style={{alignItems: 'center'}}>
      <View style={styles.vrAddress} />
    </View>
  );
};

export const Hr = () => {
  return (
    <View style={{alignItems: 'center'}}>
      <View style={styles.hr} />
    </View>
  );
};

export const FormHr = () => {
  return (
    <View style={{alignItems: 'center'}}>
      <View style={styles.formHr} />
    </View>
  );
};

export const HrAddress = () => {
  return (
    <View style={{alignItems: 'center'}}>
      <View style={styles.hrAddress} />
    </View>
  );
};

export const HrMenu = () => {
  return (
    <View style={{alignItems: 'center'}}>
      <View style={styles.hrMenu} />
    </View>
  );
};

export const ButtonBlack = props => {
  return (
    <TouchableOpacity
      style={styles.simpleShadow}
      onPress={props.clicked}
      elevation={5}>
      <View style={styles.buttonBlackEffect}>
        <Text style={styles.buttonNative}>{props.name}</Text>
      </View>
    </TouchableOpacity>
  );
};

export const ButtonOliva = props => {
  return (
    <TouchableOpacity onPress={props.clicked} elevation={5}>
      <View style={[styles.buttonRedEffect, styles.simpleShadow]} elevation={5}>
        <Text style={styles.buttonNative}>{props.name}</Text>
      </View>
    </TouchableOpacity>
  );
};

export const ButtonWhite = props => {
  return (
    <TouchableOpacity
      style={[styles.padH5, styles.buttonWhitePaddingTop]}
      onPress={props.clicked}>
      <View
        style={[styles.buttonWhiteEffect, styles.simpleShadow]}
        elevation={5}>
        <Text style={styles.buttonWhiteNative}>{props.name}</Text>
      </View>
    </TouchableOpacity>
  );
};

export const ButtonWhiteError = props => {
  return (
    <TouchableOpacity
      style={[styles.padH5, styles.buttonWhitePaddingTop]}
      onPress={props.clicked}>
      <View
        style={[styles.buttonWhiteErrorEffect, styles.simpleShadow]}
        elevation={5}>
        <Text style={styles.buttonWhiteNative}>{props.name}</Text>
      </View>
    </TouchableOpacity>
  );
};

export const OrderButtonOliva = props => {
  return (
    <TouchableOpacity onPress={props.clicked}>
      <View
        style={[styles.orderButtonOlivaEffect, styles.simpleShadow]}
        elevation={5}>
        <Text style={styles.buttonNative}>{props.name}</Text>
        <Icons
          style={[styles.arrowOrderButton, styles.padH5]}
          name={'arrow-forward'}
        />
      </View>
    </TouchableOpacity>
  );
};

export const MapButtonWhite = props => {
  return (
    <TouchableWithoutFeedback onPress={props.clicked}>
      <View
        style={[styles.mapButtonUserPosition, styles.simpleShadow]}
        elevation={5}>
        <Text style={styles.buttonNativeRed}>{props.name}</Text>
        <Icons
          style={[styles.arrowOrderButtonOliva, styles.padH5]}
          name={'arrow-forward'}
        />
      </View>
    </TouchableWithoutFeedback>
  );
};

export const ButtonOlivaCompany = props => {
  return (
    <TouchableOpacity onPress={props.clicked}>
      <View style={styles.buttonRedCompanyEffect}>
        <Text style={styles.buttonNative}>{props.name}</Text>
      </View>
    </TouchableOpacity>
  );
};

/**
 *
 * @param props {{ objectPolicy, accepted, refused, active }}
 * @returns {*}
 * @constructor
 */
export const ModalPolicyPrivacy = props => {
  return (
    <Modal visible={props.active} onRequestClose={false} animationType={'fade'}>
      <View style={styles.modalPolicyPrivacy}>
        <ScrollView contentContainerStyle={styles.scrollView}>
          {props.objectPolicy}
          <View style={{paddingVertical: 20}}>
            <ButtonOliva name={'Aceitar'} clicked={props.accepted} />
          </View>
          <View style={{paddingBottom: 20}}>
            <ButtonOliva name={'Recusar'} clicked={props.refused} />
          </View>
        </ScrollView>
      </View>
    </Modal>
  );
};

export const ActionButton = props => {
  return (
    <TouchableOpacity onPress={props.clicked}>
      <View style={[styles.pad10, styles.padH10, styles.simpleShadow]}>
        <View style={styles.actionButton} elevation={5}>
          <Icons style={styles.actionButtonContent} name={props.name} />
        </View>
      </View>
    </TouchableOpacity>
  );
};

export const AddressChoise = props => {
  return (
    <TouchableWithoutFeedback onPress={props.onPress}>
      <View>
        <HrAddress />
        <Text style={[styles.padH5, styles.pad10, styles.modalAddressText]}>
          {props.text}
        </Text>
      </View>
    </TouchableWithoutFeedback>
  );
};

export const HeaderSimplePDF = props => {
  const iconIOS = () => {
    if (isIos || !isIos) {
      return (
        <TouchableOpacity style={styles.padH5} onPress={props.toBack}>
          <View style={styles.backButtonHeader}>
            <Icons
              style={styles.menuHeaderIcon}
              name="arrow-back"
              color="white"
              size={25}
            />
          </View>
        </TouchableOpacity>
      );
    } else {
      return <View style={[styles.padH5, styles.backButtonHeader]} />;
    }
  };

  return (
    <SafeAreaView style={[styles.menuHeader, styles.pad5]}>
      <View style={{flex: wFlex}} />
      <View style={[styles.headerMenu, styles.padH5]}>
        <View>{iconIOS()}</View>
        <TouchableOpacity style={styles.padH5} onPress={props.share}>
          <View style={styles.backButtonHeader}>
            <Icons
              style={styles.menuHeaderIconShare}
              name="share"
              color="white"
              size={20}
            />
          </View>
        </TouchableOpacity>
        <View>
          <Text style={styles.menuHeaderName}>{props.name}</Text>
        </View>
        <View style={[styles.padH5, styles.ViewHeaderTutorial]}>
          {/*<Text style={{ fontSize: Porcent(1, 93), color: 'black' }}>?</Text>*/}
        </View>
      </View>
    </SafeAreaView>
  );
};
