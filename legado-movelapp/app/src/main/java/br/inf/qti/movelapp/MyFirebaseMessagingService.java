package br.inf.qti.movelapp;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.ContentResolver;
import android.content.Context;
import android.content.Intent;
import android.graphics.BitmapFactory;
import android.graphics.Color;
import android.media.AudioAttributes;
import android.media.RingtoneManager;
import android.net.Uri;
import android.util.Log;
//import android.os.IBinder;
//import android.support.v4.app.NotificationCompat;
//import android.util.Log;

import androidx.core.app.NotificationCompat;

import com.google.firebase.messaging.FirebaseMessagingService;
import com.google.firebase.messaging.RemoteMessage;

import java.util.Map;

public class MyFirebaseMessagingService extends FirebaseMessagingService {
    public MyFirebaseMessagingService() {
        Log.i("FLAVIO", "MyFirebaseMessagingService");
    }

    private static final String TAG = "MyFirebaseMessagingService";

    /**
     * Called when message is received.
     *
     * @ param from SenderID of the sender.
     * @ param data Data bundle containing message data as key/value pairs.
     *             For Set of keys use data.keySet().
     */
    // [START receive_message]
    @Override
    public void onMessageReceived(RemoteMessage message){
        Log.i("FLAVIO", "MSG RECEIVED");
        String from = message.getFrom();
        if (message.getData().size() > 0) {
            Map<String, String> data = message.getData();
            String msg = data.get("message");
            sendNotification(msg);
        }
        //else if(message.getNotification().getBody().length() > 0){
        //    sendNotification(message.getNotification().getBody());
        //}
    }
    /**
     * Create and show a simple notification containing the received GCM message.
     *
     * @param message GCM message received.
     */
    private void sendNotification(String message) {
        Log.i("FLAVIO", "SEND NOTIFICATION");
        Intent intent = new Intent(this, Main2Activity.class);
        //intent.putExtra("notification", "notification");
        //intent.addFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP);
        intent.setFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP);
        PendingIntent pendingIntent = PendingIntent.getActivity(this, RegistrationPreferences.REQUEST_NOTIFICATION, intent,
                PendingIntent.FLAG_ONE_SHOT);

        //Uri defaultSoundUri= RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE);
        //Uri defaultSoundUri = Uri.parse("android.resource://" + getPackageName() + "/" +R.raw.pop);
        Uri defaultSoundUri = Uri.parse(ContentResolver.SCHEME_ANDROID_RESOURCE + "://"+ getApplicationContext().getPackageName() + "/" + R.raw.pop);

        NotificationCompat.Builder notificationBuilder = new NotificationCompat.Builder(this, "my_channel_01")
                .setWhen(System.currentTimeMillis())
                .setVibrate(new long[]{0, 500, 1000})
                .setDefaults(Notification.DEFAULT_LIGHTS )
                .setSound(Uri.parse(ContentResolver.SCHEME_ANDROID_RESOURCE+ "://" +getApplicationContext().getPackageName()+"/"+R.raw.pop))

                .setSmallIcon(R.drawable.ic_notification)
                .setContentTitle("Revendas Nacional Gás")
                .setContentText(message)
                .setAutoCancel(true)
                .setContentIntent(pendingIntent);



        NotificationManager notificationManager =
                (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        if (android.os.Build.VERSION. SDK_INT >= android.os.Build.VERSION_CODES. O ) {
            AudioAttributes audioAttributes = new AudioAttributes.Builder()
                    .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION )
                    .setUsage(AudioAttributes.USAGE_NOTIFICATION )
                    .build() ;
            int importance = NotificationManager.IMPORTANCE_HIGH ;
            NotificationChannel notificationChannel = new NotificationChannel( "my_channel_01" , "my_channel_01" , importance) ;
            notificationChannel.enableLights( true ) ;
            notificationChannel.setLightColor(Color.RED ) ;
            notificationChannel.enableVibration( true ) ;
            notificationChannel.setVibrationPattern( new long []{ 100 , 200 , 300 , 400 , 500 , 400 , 300 , 200 , 400 }) ;
            notificationChannel.setSound(defaultSoundUri , audioAttributes) ;
            notificationBuilder.setChannelId( "my_channel_01" ) ;

            assert notificationBuilder != null;
            notificationManager.createNotificationChannel(notificationChannel) ;
        }
        notificationManager.notify(0 /* ID of notification */, notificationBuilder.build());
    }

}
