import React from 'react';
import {RefreshControl, ScrollView, Text, TouchableOpacity, View} from 'react-native';
import {styles} from '../assets/css/style';
import {HeaderSimple} from '../components/Views';
import {connect} from "react-redux";
import {mapDispatchToProps, mapStateToProps} from "../reducers/Functions";
import BaseComponent from "../components/BaseComponent";
import {getOrders} from "../providers/HttpRequests";
import renderIf from 'render-if';
import {IconsMCI} from '../assets/Icons';


/**
 * @param props {{navigation: {navigate: function}}}
 * @param address
 */
class OrdersPage extends BaseComponent {

    constructor(props) {
        super( props );

        this.state = {
            orders: [],
        };
    }

    componentDidMount(): void {
       this.willFocusSubscription = this.props.navigation.addListener(
        'willFocus',
        () => {
            this._onRefresh();
        }
      );
    }
    componentWillUnmount() {
        this.willFocusSubscription.remove();
    }

    _onRefresh = () => {
        this.props.changeRefresh(true);
        this.getDataOrders();
    };
    refreshOff = () => this.props.changeRefresh(false);


    getDataOrders = () => {
        getOrders( this.props.userId ).then((result) => {
            if ( typeof result.status === "string" && result.status === "OK" ) {
                this.setState({
                    orders: result.data
                });
                this.refreshOff();
            } else {
                this.showErrorAlert(result);
                this.props.navigation.navigate('Home');
            }
        }).catch((e) => {
            //console.log(e);
            this.props.navigation.navigate('Home');
        })
    };

    readOrder = (data) => {
        this.props.changeOrder(data);
        this.props.navigation.navigate('Order');
    };

    listOrders = () => {
        let orders = this.state.orders;
        return (
            /**
             * @param data {{reseller, produtos}}
             */
            orders.map((data, i) => {
               return (
                   <View key={ i } style={[ styles.padH10, styles.pad5 ]}>
                        <TouchableOpacity
                                    onPress={async () => {
                                        this.readOrder(data)
                                    }}
                                >
                            <View style={[ styles.forms, styles.simpleShadow, { flexDirection: 'row' } ]} elevation={ 5 }>
                            <View style={ [ styles.pad5, {flex:92 }] }>
                                <Text style={ styles.textOrderTitle } >{ data.id + " - " + data.cliente }</Text>
                                <Text style={ styles.textOrder } >{ data.endereco }</Text>
                                { this.listProducts(data.items) }
                            </View>
                            <View style={ [ styles.iconOrderView, {flex:8} ] }> 
                                <IconsMCI style={ data.status_atraso===0?styles.iconOrderNormal:(data.status_atraso===1?styles.iconOrderWarning:styles.iconOrderAlert) } name={ 'circle' }/>
                                <IconsMCI style={ data.status===2?styles.iconOrderAccepted:(data.status===1?styles.iconOrderRead:styles.iconOrderReceived) } name={ data.status===2?'check-box-outline':(data.status===1?'check-all':'check-all') }/>
                            </View>
                        </View>
                        </TouchableOpacity>                       
                   </View>
                )
            })
        )
    };
    listProducts = (items) => {
        return (
            /**
             * @param data {{reseller, produtos}}
             */
            items.map((data, i) => {
            return (
                <View key={ i } style={[ styles.padH10 ]}>
                            <View style={ [ styles.flexDirRow, {flex:1}] }>
                                <Text style={[ styles.textOrder, { flex:7} ]} >{ data.quantidade }</Text>
                                <Text style={[ styles.textOrder, { flex:62} ]} >{ data.produto }</Text>
                                <Text style={[ styles.textOrderNumber, {flex: 31} ]} >{ data.valor }</Text>
                            </View>
                </View>
                )
            })
        )
    };

    render() {
        return (
            <View style={ styles.containerAddress }>
                <HeaderSimple name={ 'Pedidos Pendentes' } toBack={() => {this.props.navigation.goBack()}}/>
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
                        flexDirection: "column",
                        alignItems: "center",
                    }}>
                    <View style={[ styles.containerAddress, styles.pad5 ]}>
                        { this.listOrders() }
                        {renderIf(!this.props.refreshing && this.state.orders.length === 0)(
                            <Text style={[styles.textConfirmOrder, styles.padH15]}>Não existem pedidos pendentes
                            </Text>
                        )}
                    </View>
                </ScrollView>
            </View>
        )
    }
}

export default connect(mapStateToProps, mapDispatchToProps)(OrdersPage);