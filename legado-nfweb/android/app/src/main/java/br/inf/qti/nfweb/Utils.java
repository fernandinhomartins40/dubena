package br.inf.qti.nfweb;

import android.content.Context;
import android.net.ConnectivityManager;
import android.provider.Settings;
import android.util.Base64;

import java.io.BufferedReader;
import java.io.BufferedWriter;
import java.io.IOException;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.io.OutputStreamWriter;
import java.io.UnsupportedEncodingException;
import java.math.BigDecimal;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLEncoder;
import java.security.InvalidKeyException;
import java.security.NoSuchAlgorithmException;
import java.util.HashMap;
import java.util.Iterator;
import java.util.Map;

import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;

import com.facebook.react.bridge.ReadableArray;
import com.facebook.react.bridge.ReadableMap;
import com.facebook.react.bridge.ReadableType;
import com.facebook.react.bridge.ReadableMapKeySetIterator;
import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;


/**
 * Created by flavio on 20/09/2014.
 */
public class Utils {

    public static double round(double value, int places) {
        if (places < 0)
            throw new IllegalArgumentException();

        BigDecimal bd = new BigDecimal(value);
        bd = bd.setScale(places, BigDecimal.ROUND_HALF_UP);
        return bd.doubleValue();
    }
    public static double roundEven(double value, int places) {
        if (places < 0)
            throw new IllegalArgumentException();

        BigDecimal bd = new BigDecimal(value);
        bd = bd.setScale(places, BigDecimal.ROUND_HALF_EVEN);
        return bd.doubleValue();
    }

    public static double roundFloor(double value, int places) {
        if (places < 0)
            throw new IllegalArgumentException();

        BigDecimal bd = new BigDecimal(value);
        bd = bd.setScale(places, BigDecimal.ROUND_FLOOR);
        return bd.doubleValue();
    }

    public static double roundZero(double value, int places) {
        if (places < 0)
            throw new IllegalArgumentException();

        BigDecimal bd = new BigDecimal(value);
        bd = bd.setScale(places, BigDecimal.ROUND_HALF_DOWN);
        return bd.doubleValue();
    }

    public static double roundUpZero(double value, int places) {
        if (places < 0)
            throw new IllegalArgumentException();

        BigDecimal bd = new BigDecimal(value);
        bd = bd.setScale(places, BigDecimal.ROUND_HALF_UP);
        return bd.doubleValue();
    }


    public static String formatCNPJCPF(String texto){
        try {
            String ret;
            if(texto.length() == 14){
                ret = texto.substring(0,2) + "." + texto.substring(2,5) + "." + texto.substring(5,8) + "/" + texto.substring(8,12) + "-" + texto.substring(12,14);
            }
            else if(texto.length() == 11)
            {
                ret = texto.substring(0,3) + "." + texto.substring(3,6) + "." + texto.substring(6,9) + "-" + texto.substring(9,10);
            }
            else ret = texto;
            return ret;
        } catch (Exception e) {
            e.printStackTrace();
            return texto;
        }
    }
    public static String formatCEP(String texto){
        try {
            String ret;
            if(texto.length() == 8){
                ret = texto.substring(0,5) + "-" + texto.substring(5,8);
            }
            else ret = texto;
            return ret;
        } catch (Exception e) {
            e.printStackTrace();
            return texto;
        }
    }
    public static String formatFone(String texto){
        try {
            String ret;
            ret = "(" + texto.substring(0,2) + ")" + texto.substring(2,texto.length());
            return ret;
        } catch (Exception e) {
            e.printStackTrace();
            return texto;
        }
    }
    public static JSONObject convertMapToJson(ReadableMap readableMap) throws JSONException {
        JSONObject object = new JSONObject();
        ReadableMapKeySetIterator iterator = readableMap.keySetIterator();
        
        while (iterator.hasNextKey()) {
            String key = iterator.nextKey();
            switch (readableMap.getType(key)) {
                case Null:
                    object.put(key, JSONObject.NULL);
                    break;
                case Boolean:
                    object.put(key, readableMap.getBoolean(key));
                    break;
                case Number:
                    object.put(key, readableMap.getDouble(key));
                    break;
                case String:
                    object.put(key, readableMap.getString(key));
                    break;
                case Map:
                    object.put(key, convertMapToJson(readableMap.getMap(key)));
                    break;
                case Array:
                    object.put(key, convertArrayToJson(readableMap.getArray(key)));
                    break;
            }
        }
        return object;
    }
    public static JSONArray convertArrayToJson(ReadableArray readableArray) throws JSONException {
        JSONArray array = new JSONArray();
        for (int i = 0; i < readableArray.size(); i++) {
            switch (readableArray.getType(i)) {
                case Null:
                    break;
                case Boolean:
                    array.put(readableArray.getBoolean(i));
                    break;
                case Number:
                    array.put(readableArray.getDouble(i));
                    break;
                case String:
                    array.put(readableArray.getString(i));
                    break;
                case Map:
                    array.put(convertMapToJson(readableArray.getMap(i)));
                    break;
                case Array:
                    array.put(convertArrayToJson(readableArray.getArray(i)));
                    break;
            }
        }
        return array;
    }
}
