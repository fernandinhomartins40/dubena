package br.inf.qti.movelapp;

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
    public static String encodeSecret(String password)  {
        String result = null;
        try {
            String key = "secret";
            Mac sha256_HMAC = Mac.getInstance("HmacSHA256");
            SecretKeySpec secret_key = new SecretKeySpec(key.getBytes("UTF-8"), "HmacSHA256");
            sha256_HMAC.init(secret_key);
            result = Base64.encodeToString(sha256_HMAC.doFinal(password.getBytes("UTF-8")), Base64.DEFAULT);
            result = result.replace("\n", "").replace("\r", "");
            return result;

        } catch (NoSuchAlgorithmException e) {
            e.printStackTrace();
            return "";
        } catch (InvalidKeyException e) {
            e.printStackTrace();
        } catch (UnsupportedEncodingException e) {
            e.printStackTrace();
        }
        result = result.replace("\n", "").replace("\r", "");
        return result;
    }
    public static String getQuery(Map<String, String> params) throws UnsupportedEncodingException
    {
        StringBuilder result = new StringBuilder();
        boolean first = true;
        Iterator myVeryOwnIterator = params.keySet().iterator();
        while(myVeryOwnIterator.hasNext()) {
            String key=(String)myVeryOwnIterator.next();
            String value=(String)params.get(key);
            if (first)
                first = false;
            else
                result.append("&");
            result.append(URLEncoder.encode(key, "UTF-8"));
            result.append("=");
            result.append(URLEncoder.encode(value, "UTF-8"));
        }
        return result.toString();
    }
    public static boolean verificaConexao(Context context) {
        boolean conectado;
        ConnectivityManager conectivtyManager = (ConnectivityManager) context.getSystemService(Context.CONNECTIVITY_SERVICE);
        if (conectivtyManager.getActiveNetworkInfo() != null
                && conectivtyManager.getActiveNetworkInfo().isAvailable()
                && conectivtyManager.getActiveNetworkInfo().isConnected()) {
            conectado = true;
        } else {
            conectado = false;
        }
        return conectado;
    }
    public static DataResult getDataFromServer(String urldest, Context context, Config config, Map<String, String> customParams) {
        DataResult res = new DataResult(false, "");
        try {

            if(config.getId() != 0 && config.getUrl() != null) {
                if (config.getSecret() == "" || config.getSecret() == null) {
                    res.setStatus(false);
                    res.setMsg("Configuração inicial incorreta. Por favor, registre o aplicativo novamente.");
                    return res;
                }
                if (!config.getUrl().equals("")) {
                    String url = config.getUrl() + "/public/api/" + urldest;
                    if(urldest == "flavio"){
                        url = "http://192.168.0.107/ctrl2/public/api/nfeConsultaTeste";
                    }
                    // Building Parameters
                    Map<String, String> params = new HashMap<String, String>();
                    params.put("token", config.getToken());
                    params.put("androidid", Settings.Secure.getString(context.getContentResolver(), Settings.Secure.ANDROID_ID));
                    params.put("revenda_id", config.getRevenda_id());
                    for (Map.Entry<String, String> entry : customParams.entrySet())
                    {
                        params.put(entry.getKey(), entry.getValue());
                    }

                    URL urldef = new URL(url);
                    String type = "application/x-www-form-urlencoded; charset=utf-8";
                    String auth = "Bearer " + config.getToken();
                    HttpURLConnection httpURLConnection = (HttpURLConnection) urldef.openConnection();
                    httpURLConnection.setDoOutput(true);
                    httpURLConnection.setDoInput(true);
                    httpURLConnection.setRequestMethod("POST");
                    if(urldest=="flavio"){
                        httpURLConnection.setRequestMethod("GET");
                    }
                    httpURLConnection.setRequestProperty("Content-Type", type);
                    if(urldest != "flavio") {
                        httpURLConnection.setRequestProperty("Authorization", auth);
                    }
                    httpURLConnection.setRequestProperty("Accept", "application/json");
                    httpURLConnection.connect();
                    OutputStream os = httpURLConnection.getOutputStream();
                    BufferedWriter writer = new BufferedWriter(
                            new OutputStreamWriter(os, "UTF-8"));
                    writer.write(Utils.getQuery(params));
                    writer.flush();
                    writer.close();
                    os.close();
                    try {
                        httpURLConnection.connect();
                    } catch (IOException e) {
                        e.printStackTrace();
                    } catch(Exception e1){
                        e1.printStackTrace();
                    }
                    int responseCode = httpURLConnection.getResponseCode();
                    String response = "";
                    if (responseCode == HttpURLConnection.HTTP_OK) {
                        String line;
                        BufferedReader br = new BufferedReader(new InputStreamReader(httpURLConnection.getInputStream()));
                        while ((line = br.readLine()) != null) {
                            response += line;
                        }
                        res.setStatus(true);
                        res.setMsg(response);
                        return res;
                    } else {
                        String line;
                        BufferedReader br = new BufferedReader(new InputStreamReader(httpURLConnection.getErrorStream()));
                        while ((line = br.readLine()) != null) {
                            response += line;
                        }
                        res.setStatus(false);
                        //res.msg = response;
                        res.setMsg("Erro ao buscar dados: " + String.valueOf(httpURLConnection.getResponseCode()) + " " + httpURLConnection.getResponseMessage());
                        return res;
                    }

                } else {
                    res.setStatus(false);
                    res.setMsg("Configuração inicial incorreta. Por favor, registre o aplicativo novamente.");
                }
            } else {
                res.setStatus(false);
                res.setMsg("Configuração inicial incorreta. Por favor, registre o aplicativo novamente.");
                return res;
            }
        } catch (IOException e) {
            e.printStackTrace();
            res = new DataResult(false, e.getMessage());
            return res;
        } catch(Exception e1) {
            e1.printStackTrace();
            res = new DataResult(false, e1.getMessage());
            return res;
        }

        return res;
    }
    public static DataResult getDataFromServerNF(String urldest, Context context, Config config, Map<String, String> customParams) {
        DataResult res = new DataResult(false, "");
        try {
            //params.put("nfce_id", "2913");
            //params.put("colaborador_id", "345");

            if(config.getId() != 0 && config.getUrl() != null) {
                if (!config.getUrl().equals("")) {
                    String url = "http://192.168.0.107/ctrl2/public/nfeConsultaTeste?nfce_id=2913&colaborador_id=345";
                    // Building Parameters
                    //Map<String, String> params = new HashMap<String, String>();
                    //for (Map.Entry<String, String> entry : customParams.entrySet())
                    //{
                    //    params.put(entry.getKey(), entry.getValue());
                    //}

                    URL urldef = new URL(url);
                    String type = "application/x-www-form-urlencoded; charset=utf-8";
                    HttpURLConnection httpURLConnection = (HttpURLConnection) urldef.openConnection();
                    httpURLConnection.setDoOutput(true);
                    httpURLConnection.setDoInput(true);
                    httpURLConnection.setRequestMethod("GET");
                    //httpURLConnection.setRequestProperty("Content-Type", type);
                    httpURLConnection.setRequestProperty("Accept", "application/json");
                    //httpURLConnection.connect();
                    //OutputStream os = httpURLConnection.getOutputStream();
                    //BufferedWriter writer = new BufferedWriter(
                    //        new OutputStreamWriter(os, "UTF-8"));
                    //writer.write(Utils.getQuery(params));
                    //writer.flush();
                    //writer.close();
                    //os.close();
                    httpURLConnection.connect();
                    int responseCode = httpURLConnection.getResponseCode();
                    String response = "";
                    if (responseCode == HttpURLConnection.HTTP_OK) {
                        String line;
                        BufferedReader br = new BufferedReader(new InputStreamReader(httpURLConnection.getInputStream()));
                        while ((line = br.readLine()) != null) {
                            response += line;
                        }
                        res.setStatus(true);
                        res.setMsg(response);
                        return res;
                    } else {
                        String line;
                        BufferedReader br = new BufferedReader(new InputStreamReader(httpURLConnection.getErrorStream()));
                        while ((line = br.readLine()) != null) {
                            response += line;
                        }
                        res.setStatus(false);
                        //res.msg = response;
                        res.setMsg("Erro ao buscar dados: " + String.valueOf(httpURLConnection.getResponseCode()) + " " + httpURLConnection.getResponseMessage());
                        return res;
                    }

                } else {
                    res.setStatus(false);
                    res.setMsg("Configuração inicial incorreta. Por favor, registre o aplicativo novamente.");
                }
            } else {
                res.setStatus(false);
                res.setMsg("Configuração inicial incorreta. Por favor, registre o aplicativo novamente.");
                return res;
            }
        } catch (IOException e) {
            e.printStackTrace();
            res = new DataResult(false, e.getMessage());
            return res;
        } catch(Exception e1) {
            e1.printStackTrace();
            res = new DataResult(false, e1.getMessage());
            return res;
        }

        return res;
    }

}
