package br.inf.qti.movelapp;

import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.content.ContentResolver;
import android.content.Context;
import android.content.Intent;
import android.graphics.Color;
import android.graphics.drawable.Drawable;
import android.media.AudioAttributes;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.provider.Settings;
//import android.support.design.widget.FloatingActionButton;
//import android.support.design.widget.Snackbar;
import android.view.View;
//import android.support.design.widget.NavigationView;
//import android.support.v4.view.GravityCompat;
//import android.support.v4.widget.DrawerLayout;
//import android.support.v7.app.ActionBarDrawerToggle;
//import android.support.v7.app.AppCompatActivity;
//import android.support.v7.widget.Toolbar;
import android.view.Menu;
import android.view.MenuItem;
import android.widget.Button;
import android.widget.ImageButton;
import android.widget.TextView;

import androidx.annotation.RequiresApi;
import androidx.appcompat.app.ActionBarDrawerToggle;
import androidx.appcompat.app.AppCompatActivity;
import androidx.appcompat.widget.Toolbar;
import androidx.core.view.GravityCompat;
import androidx.drawerlayout.widget.DrawerLayout;

import com.google.android.material.navigation.NavigationView;

public class Main2Activity extends AppCompatActivity
        implements NavigationView.OnNavigationItemSelectedListener {

    private String android_id;
    //private SharedPreferences prefs = null;
    private static final int REQUEST_IMPORT_NEW = 1;
    private static final int REQUEST_IMPORT = 2;
    private static final int REQUEST_LOGIN = 3;
    private static final int REQUEST_REGISTER_NEW = 4;
    private static final int REQUEST_VEICULO = 5;
    private boolean notificacao = false;

    @RequiresApi(api = Build.VERSION_CODES.LOLLIPOP)
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        CreateNotificationChannel();
        setContentView(R.layout.activity_main2);
        Toolbar toolbar = (Toolbar) findViewById(R.id.toolbar);
        setSupportActionBar(toolbar);
        /*
        if (getSupportActionBar() != null) {
            getSupportActionBar().setDisplayShowTitleEnabled(false); // hide built-in Title

            // Setting background using a drawable
            Drawable toolbarBackground = getResources().getDrawable(R.drawable.logo);
            getSupportActionBar().setBackgroundDrawable(toolbarBackground);
        }
        */
        DrawerLayout drawer = (DrawerLayout) findViewById(R.id.drawer_layout);
        ActionBarDrawerToggle toggle = new ActionBarDrawerToggle(
               this, drawer, toolbar, R.string.navigation_drawer_open, R.string.navigation_drawer_close);
        drawer.setDrawerListener(toggle);
        toggle.syncState();

        NavigationView navigationView = (NavigationView) findViewById(R.id.nav_view);
        navigationView.setNavigationItemSelectedListener(this);


        android_id = Settings.Secure.getString(getApplicationContext().getContentResolver(), Settings.Secure.ANDROID_ID);
        DataBaseHandler dbHandler = new DataBaseHandler(getApplicationContext());
        Config config = dbHandler.getConfig();
        Empresa empresa = dbHandler.getEmpresa();
        if(config.getToken()==null || config.getToken().equals("")){
            startActivityForResult(new Intent(getApplicationContext(), RegisterActivity.class), REQUEST_REGISTER_NEW);
        } else {
            try {
                notificacao = getIntent().getStringExtra("notification").equals("notification");
            } catch (Exception e) {
                notificacao = false;
                e.printStackTrace();
            }

            if(dbHandler.getEmpresa().getId()==0 || dbHandler.getUsersCount()==0){
                startActivityForResult(new Intent(getApplicationContext(), CadastroImportActivity.class), REQUEST_IMPORT_NEW);
            } else {
                startActivityForResult(new Intent(getApplicationContext(), LoginActivity.class), REQUEST_LOGIN);
            }
        }
        ((TextView) findViewById(R.id.txtVersionNew)).setText("V" + BuildConfig.VERSION_NAME);
        View headerView = navigationView.getHeaderView(0);
        ((TextView) headerView.findViewById(R.id.txtRevendaNav)).setText(empresa.getRazaoSocial());
        ((TextView) headerView.findViewById(R.id.txtUsuarioNav)).setText(config.getUsuario());
        configureBtnMeLigue();
        configureBtnPedidoConsulta();
        configureBtnPedidoReport();
        configureBtnVeiculo(true);
    }

    protected void onActivityResult(int requestCode, int resultCode,
                                    Intent data) {
        if (requestCode == REQUEST_IMPORT_NEW) {
            if (resultCode == RESULT_OK) {
                // A contact was picked.  Here we will just display it
                // to the user.
                startActivityForResult(new Intent(getApplicationContext(), LoginActivity.class), REQUEST_LOGIN);
            } else {
                finish();
            }
        } else if (requestCode == REQUEST_IMPORT) {
            if (resultCode == RESULT_OK) {
                configureBtnVeiculo(false);
            } else {

            }
        } else if (requestCode == REQUEST_LOGIN) {
            if(resultCode != RESULT_OK){
                finish();
            } else {
                if (notificacao) {
                    startActivity(new Intent(getApplicationContext(), PedidoConsultaActivity.class));
                }
            }
        } else if (requestCode == REQUEST_REGISTER_NEW) {
            if (resultCode != RESULT_OK) {
                finish();
            } else {
                startActivityForResult(new Intent(getApplicationContext(), CadastroImportActivity.class), REQUEST_IMPORT_NEW);
            }
        } else if (requestCode == REQUEST_VEICULO) {
            configureBtnVeiculo(false);
        }
    }

    @Override
    public void onBackPressed() {
        DrawerLayout drawer = (DrawerLayout) findViewById(R.id.drawer_layout);
        if (drawer.isDrawerOpen(GravityCompat.START)) {
            drawer.closeDrawer(GravityCompat.START);
        } else {
            super.onBackPressed();
        }
    }

    @Override
    public boolean onCreateOptionsMenu(Menu menu) {
        // Inflate the menu; this adds items to the action bar if it is present.
        getMenuInflater().inflate(R.menu.main2, menu);
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

    @SuppressWarnings("StatementWithEmptyBody")
    @Override
    public boolean onNavigationItemSelected(MenuItem item) {
        // Handle navigation view item clicks here.
        int id = item.getItemId();

        if (id == R.id.nav_registrar) {
            startActivity(new Intent(getApplicationContext(), RegisterActivity.class));
        } else if (id == R.id.nav_importar) {
            startActivityForResult(new Intent(getApplicationContext(), CadastroImportActivity.class), REQUEST_IMPORT);
        } else if (id == R.id.nav_contato) {
            startActivity(new Intent(getApplicationContext(), MeLigueActivity.class));
        } else if (id == R.id.nav_consultar) {
            startActivity(new Intent(getApplicationContext(), PedidoConsultaActivity.class));
        } else if (id == R.id.nav_historico) {
            startActivity(new Intent(getApplicationContext(), PedidoReportActivity.class));
        } else if (id == R.id.nav_veiculo) {
            startActivityForResult(new Intent(getApplicationContext(), VeiculoActivity.class), REQUEST_VEICULO);
        }

        DrawerLayout drawer = (DrawerLayout) findViewById(R.id.drawer_layout);
        drawer.closeDrawer(GravityCompat.START);
        return true;
    }
    private void configureBtnMeLigue(){
        ImageButton btn = (ImageButton) findViewById(R.id.btnMeligueNew);
        btn.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {
                startActivity(new Intent(getApplicationContext(), MeLigueActivity.class));
                //Intent intencao = new Intent(getApplicationContext(), NotaFiscalImpressaoActivity.class);
                //startActivity(intencao);
            }
        });
    }

    private void configureBtnPedidoConsulta(){
        ImageButton btn = (ImageButton) findViewById(R.id.btnPedidoConsultaNew);
        btn.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {
                startActivity(new Intent(getApplicationContext(), PedidoConsultaActivity.class));
            }
        });
    }
    private void configureBtnPedidoReport(){
        ImageButton btn = (ImageButton) findViewById(R.id.btnPedidoRelatorioNew);
        btn.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {
                startActivity(new Intent(getApplicationContext(), PedidoReportActivity.class));
            }
        });
    }
    private void configureBtnVeiculo(boolean setListener){
        Button btn = (Button) findViewById(R.id.btnVeiculoPlaca);
        DataBaseHandler dbHandler = new DataBaseHandler(this);
        Veiculo veiculo = dbHandler.getVeiculoAtivo();
        String descricao = veiculo.placa + " " + veiculo.descricao;
        if(descricao.trim().equals("")){
            descricao = "Alterar Veículo";
        }
        btn.setText(descricao);
        if(setListener) {
            btn.setOnClickListener(new View.OnClickListener() {
                @Override
                public void onClick(View view) {
                    startActivityForResult(new Intent(getApplicationContext(), VeiculoActivity.class), REQUEST_VEICULO);
                }
            });
        }
    }

    private void CreateNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            AudioAttributes audioAttributes = new AudioAttributes.Builder()
                    .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION )
                    .setUsage(AudioAttributes.USAGE_NOTIFICATION )
                    .build() ;

            NotificationManager notificationManager =
                    (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);

            if (notificationManager.getNotificationChannel("my_channel_01") == null) {
                NotificationChannel channel = new NotificationChannel("my_channel_01",
                        "my_channel_01",
                        NotificationManager.IMPORTANCE_HIGH);
                Uri defaultSoundUri = Uri.parse(ContentResolver.SCHEME_ANDROID_RESOURCE + "://" + getApplicationContext().getPackageName() + "/" + R.raw.pop);
                channel.enableLights(true);
                channel.setLightColor(Color.RED);
                channel.enableVibration(true);
                channel.setVibrationPattern(new long[]{100, 200, 300, 400, 500, 400, 300, 200, 400});
                channel.setSound(defaultSoundUri, audioAttributes);

                notificationManager.createNotificationChannel(channel);
            }
        }
    }
}
