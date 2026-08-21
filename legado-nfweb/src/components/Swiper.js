import React from 'react'
import { View, Text } from 'react-native';
import Swiper from 'react-native-swiper';
import { styles } from '../assets/css/style';

export const SwipeCompany = props => {
    return (
        <Swiper style={ styles.containerSwiper }
                containerStyle={ styles.swiper }
                showsButtons={ false }
                autoplay={ true }
                dot={<View style={{ backgroundColor:'rgba(0,0,0,.2)', width: 8, height: 8,borderRadius: 4, marginLeft: 3, marginRight: 3, marginTop: 3, marginBottom: 3, }} />}
                activeDot={
                    <View style={{ backgroundColor: '#830000', width: 8, height: 8, borderRadius: 4, marginLeft: 3, marginRight: 3, marginTop: 3, marginBottom: 3, }} />
                }
                autoplayTimeout={ 5 }>
            { props.content }
        </Swiper>
    )
};

export const SwipeProducts = props => {
    return (
        <Swiper style={ styles.containerSwiper }
                containerStyle={ styles.productSwiper }
                showsButtons={ true }
                // showPagination={ false }
                // loop={ false }
                autoplay={ false }
                dot={<View style={{ backgroundColor:'rgba(0,0,0,0)', width: 8, height: 8, borderRadius: 4, marginLeft: 3, marginRight: 3, marginTop: 8, marginBottom: 3, }} />}
                activeDot={
                    <View style={{ backgroundColor: 'rgba(131,0,0,0)', width: 8, height: 8, borderRadius: 4, marginLeft: 3, marginRight: 3, marginTop: 8, marginBottom: 3, }} />
                }
                nextButton={
                    <Text style={styles.buttonText} elevation={ 5 }>›</Text>
                }
                prevButton={
                    <Text style={styles.buttonText} elevation={ 5 }>‹</Text>
                }
            >
            { props.content }
        </Swiper>
    )
};
