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
import {HeaderSimplePDF, HrAddress} from '../components/Views';
import {connect} from 'react-redux';
import {
  getUserState,
  mapDispatchToProps,
  mapStateToProps,
} from '../reducers/Functions';
import BaseComponent from '../components/BaseComponent';
import {IconsMCI} from '../assets/Icons';
import {storeData} from '../helper/AsyncStore';
import {
  visualizarDanfe,
  visualizarBoleto,
  visualizarDuplicata,
} from '../providers/HttpRequests';
//import Pdf from 'react-native-pdf';
import Share from 'react-native-share';
//import PdfRendererView from 'react-native-pdf-renderer';
import PDFView from 'react-native-view-pdf';
/**
 * @param props {{navigation: {navigate: function}}}
 * @param address
 */
class VerPDFPage extends BaseComponent {
  constructor(props) {
    super(props);
    this.state = {
      source: {
        uri: '',
      },
      base64: '',
    };
  }
  componentDidMount(): void {
    this.willFocusSubscription = this.props.navigation.addListener(
      'willFocus',
      () => {
        if (this.props.Pedido.tipopdf == 'NF') {
          this.visualizarDanfe();
        } else if (this.props.Pedido.tipopdf == 'Boleto') {
          this.visualizarBoleto();
        } else {
          this.visualizarDuplicata();
        }
      },
    );
  }

  visualizarDanfe = () => {
    visualizarDanfe(this.props.Pedido.nfce_id)
      .then(result => {
        this.setState({
          source: {uri: 'data:application/pdf;base64,' + result.data},
          base64: result.data,
        });
      })
      .catch(e => {
        this.props.navigation.navigate('Home');
      });
  };

  visualizarBoleto = () => {
    visualizarBoleto(this.props.Pedido.id, 1)
      .then(result => {
        this.setState({
          source: {uri: 'data:application/pdf;base64,' + result.data},
          base64: result.data,
        });
      })
      .catch(e => {
        this.props.navigation.navigate('Home');
      });
  };

  visualizarDuplicata = () => {
    visualizarDuplicata(this.props.Pedido.id, 1)
      .then(result => {
        this.setState({
          source: {uri: 'data:application/pdf;base64,' + result.data},
          base64: result.data,
        });
      })
      .catch(e => {
        this.props.navigation.navigate('Home');
      });
  };

  shareFile = async (url) => {
    await Share.open({ url: url }).catch(err => console.log(err));
  }

  share = () => {
    if(this.state.source != null){
      const url = this.state.source.uri;
      this.shareFile(url);
    }
  };


  render() {
    return (
      <View style={styles.containerMenu}>
        <HeaderSimplePDF
          name={'Visualizar Documento'}
          toBack={() => {
            this.props.navigation.goBack();
          }}
          share={() => {
            this.share();
          }}
        />

        <View style={[styles.containerAddress, styles.pad5]}>
          <PDFView
            fadeInDuration={250.0}
            style={styles.pdf}
            resource={this.state.base64}
            resourceType={"base64"}
            onLoad={() => console.log(`PDF rendered`)}
            onError={(error) => console.log('Cannot render PDF', error)}
          />
          {/*
          <PdfRendererView
            source={this.state.source}

            distanceBetweenPages={16}
            maxZoom={5}
            style={styles.pdf}
          />
          */}
        </View>
      </View>
    );
  }
}

export default connect(
  mapStateToProps,
  mapDispatchToProps,
)(VerPDFPage);
