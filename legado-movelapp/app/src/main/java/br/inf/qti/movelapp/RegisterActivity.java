package br.inf.qti.movelapp;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.content.SharedPreferences;
import android.os.Build;
import android.os.Bundle;
import android.preference.PreferenceManager;
//import android.support.v4.content.LocalBroadcastManager;
//import android.support.v7.app.AppCompatActivity;
//import android.support.v7.widget.Toolbar;
import android.util.Log;
import android.view.Menu;
import android.view.MenuItem;
import android.view.View;
import android.widget.AutoCompleteTextView;
import android.widget.EditText;
import android.widget.ImageButton;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;
import androidx.localbroadcastmanager.content.LocalBroadcastManager;

import com.google.android.gms.common.ConnectionResult;
import com.google.android.gms.common.GoogleApiAvailability;

import org.w3c.dom.Text;

public class RegisterActivity extends AppCompatActivity {

    private static final int PLAY_SERVICES_RESOLUTION_REQUEST = 9000;
    private static final String TAG = "RegisterActivity";


    private BroadcastReceiver mRegistrationBroadcastReceiver;
    private ProgressBar mRegistrationProgressBar;
    private TextView mInformationTextView;
    private AutoCompleteTextView mEmailView;
    private EditText mPasswordView;
    private EditText mServidorView;
    private EditText mClienteidView;
    private EditText mRevendaidView;
    private EditText mDescricaoView;
    private TextView mlblDescricaoView;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_register);

        mRegistrationProgressBar = (ProgressBar) findViewById(R.id.registrationProgressBar);
        mRegistrationProgressBar.setVisibility(ProgressBar.GONE);
        mRegistrationBroadcastReceiver = new BroadcastReceiver() {
            @Override
            public void onReceive(Context context, Intent intent) {

                SharedPreferences sharedPreferences =
                        PreferenceManager.getDefaultSharedPreferences(context);
                int sentToken = sharedPreferences
                        .getInt(RegistrationPreferences.SENT_TOKEN_TO_SERVER, 2);
                if (sentToken == 1) {
                    //mInformationTextView.setText(getString(R.string.gcm_send_message));
                    Intent returnIntent = new Intent();
                    RegisterActivity.this.setResult(RESULT_OK, returnIntent);
                    RegisterActivity.this.finish();
                    Toast.makeText(RegisterActivity.this, "Registro realizado com sucesso", Toast.LENGTH_LONG).show();

                } else if (sentToken == 0) {
                    mRegistrationProgressBar.setVisibility(ProgressBar.GONE);
                    mInformationTextView.setText(getString(R.string.token_error_message));
                } else {
                    mRegistrationProgressBar.setVisibility(ProgressBar.GONE);
                    mInformationTextView.setText(getString(R.string.gcm_send_message_null));
                }
            }
        };
        mInformationTextView = (TextView) findViewById(R.id.informationTextView);

        mEmailView = (AutoCompleteTextView) findViewById(R.id.emailR);
        mPasswordView = (EditText) findViewById(R.id.passwordR);
        mServidorView = (EditText) findViewById(R.id.servidorR);
        mClienteidView = (EditText) findViewById(R.id.cliente_idR);
        mRevendaidView = (EditText) findViewById(R.id.revenda_idR);
        mDescricaoView = (EditText) findViewById(R.id.descricaoR);
        mlblDescricaoView = (TextView) findViewById(R.id.lblDescricaoR);

        DataBaseHandler dbHandler = new DataBaseHandler(getApplicationContext());
        Config config = dbHandler.getConfig();
        if (config.getUsuario() != null) {
            mEmailView.setText(config.getUsuario());
            mServidorView.setText(config.getUrl());
            mClienteidView.setText(config.getCliente_id());
            mRevendaidView.setText(config.getRevenda_id());
            mDescricaoView.setVisibility(View.GONE);
            mlblDescricaoView.setVisibility(View.GONE);
        }

        ImageButton btn = (ImageButton) findViewById(R.id.btnRegistrar);
        btn.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {
                if (checkPlayServices()) {
                    mRegistrationProgressBar.setVisibility(ProgressBar.VISIBLE);
                    mInformationTextView.setText(getString(R.string.registering_message));
                    // Start IntentService to register this application with GCM.
                    Intent intent = new Intent(RegisterActivity.this, RegistrationIntentService.class);
                    intent.putExtra("email", mEmailView.getText().toString());
                    intent.putExtra("password", mPasswordView.getText().toString());
                    intent.putExtra("servidor", mServidorView.getText().toString());
                    intent.putExtra("cliente_id", mClienteidView.getText().toString());
                    intent.putExtra("revenda_id", mRevendaidView.getText().toString());
                    intent.putExtra("descricao", mDescricaoView.getText().toString());
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                        startForegroundService(intent);
                    } else {
                        startService(intent);
                    }
                    //startService(intent);
                }
            }
        });

    }

    @Override
    protected void onResume() {
        super.onResume();
        LocalBroadcastManager.getInstance(this).registerReceiver(mRegistrationBroadcastReceiver,
                new IntentFilter(RegistrationPreferences.REGISTRATION_COMPLETE));
    }

    @Override
    protected void onPause() {
        LocalBroadcastManager.getInstance(this).unregisterReceiver(mRegistrationBroadcastReceiver);
        super.onPause();
    }

    private boolean checkPlayServices() {
        GoogleApiAvailability apiAvailability = GoogleApiAvailability.getInstance();
        int resultCode = apiAvailability.isGooglePlayServicesAvailable(this);
        if (resultCode != ConnectionResult.SUCCESS) {
            if (apiAvailability.isUserResolvableError(resultCode)) {
                apiAvailability.getErrorDialog(this, resultCode, PLAY_SERVICES_RESOLUTION_REQUEST)
                        .show();
            } else {
                Log.i(TAG, "This device is not supported.");
                finish();
            }
            return false;
        }
        return true;
    }

    @Override
    public boolean onCreateOptionsMenu(Menu menu) {
        // Inflate the menu; this adds items to the action bar if it is present.
        getMenuInflater().inflate(R.menu.menu_register, menu);
        return true;
    }

    @Override
    public boolean onOptionsItemSelected(MenuItem item) {
        // Handle action bar item clicks here. The action bar will
        // automatically handle clicks on the Home/Up button, so long
        // as you specify a parent activity in AndroidManifest.xml.
        int id = item.getItemId();

        //noinspection SimplifiableIfStatement
        if (id == R.id.action_settings) {
            return true;
        }

        return super.onOptionsItemSelected(item);
    }

}
