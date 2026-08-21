package br.inf.qti.movelapp;

import android.app.IntentService;
import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.content.ContentResolver;
import android.content.Intent;
import android.content.Context;
import android.content.SharedPreferences;
import android.graphics.Color;
import android.media.AudioAttributes;
import android.media.RingtoneManager;
import android.net.Uri;
import android.os.Build;
import android.preference.PreferenceManager;
import android.provider.Settings;
//import android.support.v4.content.LocalBroadcastManager;
//import android.util.Base64;
import android.util.Log;

import androidx.core.app.NotificationCompat;
import androidx.localbroadcastmanager.content.LocalBroadcastManager;

import com.google.firebase.iid.FirebaseInstanceId;
import com.google.firebase.messaging.FirebaseMessaging;

import org.json.JSONException;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.BufferedWriter;
import java.io.IOException;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.io.OutputStreamWriter;
import java.io.UnsupportedEncodingException;
import java.net.HttpURLConnection;
import java.net.MalformedURLException;
import java.net.ProtocolException;
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
 * An {@link IntentService} subclass for handling asynchronous task requests in
 * a service on a separate handler thread.
 * <p>
 * TODO: Customize class - update intent actions, extra parameters and static
 * helper methods.
 */
public class RegistrationIntentService extends IntentService {
    private static final String TAG = "RegIntentService";
    private static final String[] TOPICS = {"global"};
    private String email;
    private String password;
    private String servidor;
    private String cliente_id;
    private String revenda_id;
    private String descricao;

    public RegistrationIntentService() {
        super(TAG);
    }

    /**
     * Starts this service to perform action Foo with the given parameters. If
     * the service is already performing a task this action will be queued.
     *
     * @see IntentService
     */


    @Override
    protected void onHandleIntent(Intent intent) {
        Log.i("FLAVIO", "ONHANDLEINTENT");
        boolean ret = false;
        SharedPreferences sharedPreferences = PreferenceManager.getDefaultSharedPreferences(this);

        if (Build.VERSION.SDK_INT >= 26) {
            AudioAttributes audioAttributes = new AudioAttributes.Builder()
                    .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                    .setUsage(AudioAttributes.USAGE_NOTIFICATION)
                    .build();

            String CHANNEL_ID = "my_channel_01";
            NotificationChannel channel = new NotificationChannel(CHANNEL_ID,
                    "my_channel_01",
                    NotificationManager.IMPORTANCE_HIGH);
            //Uri defaultSoundUri = Uri.parse("android.resource://" + getPackageName() + "/" +R.raw.pop);
            Uri defaultSoundUri = Uri.parse(ContentResolver.SCHEME_ANDROID_RESOURCE + "://" + getApplicationContext().getPackageName() + "/" + R.raw.pop);
            channel.enableLights(true);
            channel.setLightColor(Color.RED);
            channel.enableVibration(true);
            channel.setVibrationPattern(new long[]{100, 200, 300, 400, 500, 400, 300, 200, 400});
            channel.setSound(defaultSoundUri, audioAttributes);

            ((NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE)).createNotificationChannel(channel);

            Notification notification = new NotificationCompat.Builder(this, CHANNEL_ID)
                    .setContentTitle("")
                    .setContentText("").build();

            startForeground(1, notification);
        }

        try {

            email = intent.getStringExtra("email");
            password = intent.getStringExtra("password");
            servidor = intent.getStringExtra("servidor");
            cliente_id = intent.getStringExtra("cliente_id");
            revenda_id = intent.getStringExtra("revenda_id");
            descricao = intent.getStringExtra("descricao");
            if (email == null || password == null || servidor == null || cliente_id == null || revenda_id == null) {
                sharedPreferences.edit().putInt(RegistrationPreferences.SENT_TOKEN_TO_SERVER, 2).apply();
            } else {

                String token = FirebaseInstanceId.getInstance().getToken();
                ret = getOauthTokenFromServer(token);
                // Subscribe to topic channels
                subscribeTopics(token);
                subscribeTopics(token);
                if (ret)
                    sharedPreferences.edit().putInt(RegistrationPreferences.SENT_TOKEN_TO_SERVER, 1).apply();
                else
                    sharedPreferences.edit().putInt(RegistrationPreferences.SENT_TOKEN_TO_SERVER, 0).apply();
            }
        } catch (Exception e) {
            Log.d(TAG, "Failed to complete token refresh", e);
            // If an exception happens while fetching the new token or updating our registration data
            // on a third-party server, this ensures that we'll attempt the update at a later time.
            sharedPreferences.edit().putInt(RegistrationPreferences.SENT_TOKEN_TO_SERVER, 0).apply();
        }
        // Notify UI that registration has completed, so the progress indicator can be hidden.
        Intent registrationComplete = new Intent(RegistrationPreferences.REGISTRATION_COMPLETE);
        LocalBroadcastManager.getInstance(this).sendBroadcast(registrationComplete);
    }

    @Override
    public void onCreate() {
        super.onCreate();
        Log.i("FLAVIO", "ONCREATE");
   }
    private boolean getOauthTokenFromServer(String token) {
        String url;
        //servidor = "1123";
        if(servidor != "" && servidor != null){
            //email = "teste";
            //password = "1234";
            String secret = Utils.encodeSecret(password);

            if(secret == "" || secret == null){
                return false;
            }
            if (!servidor.equals("")) {
                url = servidor;
                String urlServer = url;
                url = url + "/public/oauth/token";
                // Building Parameters
                Map<String, String> params = new HashMap<String, String>();
                params.put("username", email);
                params.put("grant_type", "password");
                params.put("client_id", cliente_id);
                params.put("scope", "");
                params.put("password", password);
                params.put("client_secret", secret);
                try {
                    URL urldef = new URL(url);
                    String type = "application/x-www-form-urlencoded; charset=utf-8";
                    HttpURLConnection httpURLConnection = (HttpURLConnection)urldef.openConnection();
                    httpURLConnection.setDoOutput(true);
                    httpURLConnection.setDoInput(true);
                    httpURLConnection.setRequestMethod("POST");
                    httpURLConnection.setRequestProperty("Content-Type", type);
                    httpURLConnection.connect();
                    OutputStream os = httpURLConnection.getOutputStream();
                    BufferedWriter writer = new BufferedWriter(
                            new OutputStreamWriter(os, "UTF-8"));
                    writer.write(Utils.getQuery(params));
                    writer.flush();
                    writer.close();
                    os.close();
                    httpURLConnection.connect();
                    int responseCode=httpURLConnection.getResponseCode();
                    String response = "";
                    if (responseCode == HttpURLConnection.HTTP_OK) {
                        String line;
                        BufferedReader br=new BufferedReader(new InputStreamReader(httpURLConnection.getInputStream()));
                        while ((line=br.readLine()) != null) {
                            response+=line;
                        }
                        JSONObject json = new JSONObject(response);
                        String token_received = json.getString("access_token");
                        Config conf = new Config(1, secret, token_received, email, urlServer, cliente_id, revenda_id);
                        DataBaseHandler dbHandler = new DataBaseHandler(getApplicationContext());
                        dbHandler.atualizaConfig(conf);
                        return sendRegistrationToServer(token);
                    }
                    else {
                        String line;
                        BufferedReader br = new BufferedReader(new InputStreamReader(httpURLConnection.getErrorStream()));
                        while ((line=br.readLine()) != null) {
                            response+=line;
                        }
                        return false;
                    }
                } catch (ProtocolException e) {
                    e.printStackTrace();
                } catch (IOException e) {
                    e.printStackTrace();
                } catch (JSONException e) {
                    e.printStackTrace();
                }
            }
        }
        return false;
    }
    private boolean sendRegistrationToServer(String token) {
        DataBaseHandler dbHandler = new DataBaseHandler(getApplicationContext());
        String url;

        Config config = dbHandler.getConfig();

        if(config.getId() != 0 && config.getUrl() != null) {
            if(config.getSecret() == "" || config.getSecret() == null){
                return false;
            }
            //return false;
            if (!config.getUrl().equals("")) {
                url = config.getUrl();
                url = url + "/public/api/setAndroidRegistration";
                // Building Parameters
                Map<String, String> params = new HashMap<String, String>();
                params.put("token", token);
                params.put("id", Settings.Secure.getString(getApplicationContext().getContentResolver(), Settings.Secure.ANDROID_ID));
                params.put("url", config.getUrl());
                params.put("cliente_id", config.getCliente_id());
                params.put("revenda_id", config.getRevenda_id());
                params.put("usuario", config.getUsuario());
                params.put("descricao", descricao);
                try {
                    URL urldef = new URL(url);
                    String type = "application/x-www-form-urlencoded; charset=utf-8";
                    String auth = "Bearer " + config.getToken();
                    HttpURLConnection httpURLConnection = (HttpURLConnection)urldef.openConnection();
                    httpURLConnection.setDoOutput(true);
                    httpURLConnection.setDoInput(true);
                    httpURLConnection.setRequestMethod("POST");
                    httpURLConnection.setRequestProperty("Content-Type", type);
                    httpURLConnection.setRequestProperty ("Authorization", auth);
                    httpURLConnection.setRequestProperty("Accept", "application/json");
                    httpURLConnection.connect();
                    OutputStream os = httpURLConnection.getOutputStream();
                    BufferedWriter writer = new BufferedWriter(
                            new OutputStreamWriter(os, "UTF-8"));
                    writer.write(Utils.getQuery(params));
                    writer.flush();
                    writer.close();
                    os.close();
                    httpURLConnection.connect();
                    int responseCode=httpURLConnection.getResponseCode();
                    String response = "";
                    if (responseCode == HttpURLConnection.HTTP_OK) {
                        String line;
                        BufferedReader br=new BufferedReader(new InputStreamReader(httpURLConnection.getInputStream()));
                        while ((line=br.readLine()) != null) {
                            response+=line;
                        }
                        if(response.equals("OK")) {
                            return true;
                        } else {
                            return false;
                        }
                    }
                    else {
                        String line;
                        BufferedReader br = new BufferedReader(new InputStreamReader(httpURLConnection.getErrorStream()));
                        while ((line=br.readLine()) != null) {
                            response+=line;
                        }
                        return false;
                    }
                } catch (ProtocolException e) {
                    e.printStackTrace();
                    return false;
                } catch (IOException e) {
                    e.printStackTrace();
                    return false;
                }
            }
        }
        return false;
    }

    /**
     * Subscribe to any GCM topics of interest, as defined by the TOPICS constant.
     *
     * @param token GCM token
     * @throws IOException if unable to reach the GCM PubSub service
     */
    // [START subscribe_topics]
    private void subscribeTopics(String token) throws IOException {
        for (String topic : TOPICS) {
            FirebaseMessaging.getInstance().subscribeToTopic(topic);
        }
    }

}
