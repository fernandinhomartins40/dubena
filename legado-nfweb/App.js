global.Symbol = require('core-js/es6/symbol');
require('core-js/fn/symbol/iterator');
require('core-js/fn/map');
require('core-js/fn/set');
require('core-js/fn/array/find');

import 'babel-polyfill';

import React, {Component} from 'react';
import {Provider} from 'react-redux';
import {Platform, StyleSheet, Text, View} from 'react-native';
import {createAppContainer, createSwitchNavigator} from 'react-navigation';
import {createStackNavigator} from 'react-navigation-stack';

import store from './store';

import LoginPage from './src/pages/Login';
import HomePage from './src/pages/Home';
import SmsPage from './src/pages/Sms';
import VeiculoPage from './src/pages/Veiculo';
import PedidoPage from './src/pages/Pedido';
import ClientePage from './src/pages/Cliente';
import ClienteBuscaPage from './src/pages/ClienteBusca';
import VerPDFPage from './src/pages/VerPDF';
import PedidoConsultaPage from './src/pages/PedidoConsulta';
import ReportPedidosPage from './src/pages/ReportPedidos';
import ReportPedidosListPage from './src/pages/ReportPedidosList';
import RedirectMiddleware from './src/pages/RedirectMiddleware';
import ErrorPage from './src/helper/Error';
import {MenuProvider} from 'react-native-popup-menu';

const MainNavigation = createStackNavigator(
  {
    Home: HomePage,
    Veiculo: VeiculoPage,
    Pedido: PedidoPage,
    Cliente: ClientePage,
    ClienteBusca: ClienteBuscaPage,
    VerPDF: VerPDFPage,
    PedidoConsulta: PedidoConsultaPage,
    ReportPedidos: ReportPedidosPage,
    ReportPedidosList: ReportPedidosListPage,
  },
  {
    initialRouteName: 'Home',
    headerMode: 'none',
  },
);

const AuthNavigation = createStackNavigator(
  {
    Login: LoginPage,
    Sms: {
      name: 'Sms',
      screen: SmsPage,
      navigationOptions: {
        gesturesEnabled: false,
      },
    },
    Error: ErrorPage,
  },
  {
    initialRouteName: 'Login',
    headerMode: 'none',
  },
);

const DefaultNavigation = createSwitchNavigator(
  {
    AuthLoading: RedirectMiddleware,
    MainApp: MainNavigation,
    AuthApp: AuthNavigation,
  },
  {
    initialRouteName: 'AuthLoading',
    headerMode: 'none',
  },
);

let Navigation = createAppContainer(DefaultNavigation);

// Render the app container component with the provider around it
export default class App extends React.Component {
  render() {
    return (
      <MenuProvider>
        <Provider store={store}>
          <Navigation />
        </Provider>
      </MenuProvider>
    );
  }
}

