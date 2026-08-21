package br.inf.qti.movelapp;

import android.app.AlertDialog;
import android.content.Context;
import android.content.DialogInterface;
import android.content.Intent;
import android.os.Bundle;
//import android.support.v4.app.Fragment;
//import android.support.v4.app.FragmentActivity;
//import android.support.v4.app.FragmentManager;
//import android.support.v4.app.FragmentTransaction;
import android.view.LayoutInflater;
import android.view.Menu;
import android.view.MotionEvent;
import android.view.View;
import android.view.inputmethod.InputMethodManager;
import android.widget.ImageView;
import android.widget.TabHost;
import android.widget.TextView;
import android.widget.Toast;

import androidx.fragment.app.Fragment;
import androidx.fragment.app.FragmentActivity;
import androidx.fragment.app.FragmentManager;
import androidx.fragment.app.FragmentTransaction;

public class PedidoActivity extends FragmentActivity implements PedidoFragment1.OnItemSelectedListener {

    /* Tab identifiers */
    static String TAB_A = "Pedido";
    //static String TAB_B = "Produtos";
    //static String TAB_C = "Concluir";
    //static String TAB_D = "Trocas";

    TabHost mTabHost;

    PedidoFragment1 pedidoFragment1;
    String pedidoId;


    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_pedido);
        Bundle extras = getIntent().getExtras();

        Intent intent = getIntent();
        pedidoId = intent.getStringExtra("pedidoId");

        pedidoFragment1 = new PedidoFragment1();
        pedidoFragment1.setArguments(extras);

        mTabHost = (TabHost)findViewById(android.R.id.tabhost);
        mTabHost.setOnTabChangedListener(listener);
        mTabHost.setup();

        initializeTab();
    }
    @Override
    public boolean onCreateOptionsMenu(Menu menu) {
        // Inflate the menu; this adds items to the action bar if it is present.
        getMenuInflater().inflate(R.menu.activity_main_tab, menu);
        return true;
    }
    public void initializeTab() {

        TabHost.TabSpec spec    =   mTabHost.newTabSpec(TAB_A);
        mTabHost.setCurrentTab(-4);
        spec.setContent(new TabHost.TabContentFactory() {
            public View createTabContent(String tag) {
                return findViewById(android.R.id.tabcontent);
            }
        });
        spec.setIndicator(createTabView(TAB_A, R.drawable.tab_icon1));
        mTabHost.addTab(spec);
    }
    TabHost.OnTabChangeListener listener    =   new TabHost.OnTabChangeListener() {
        public void onTabChanged(String tabId) {
            /*Set current tab..*/
            if(tabId.equals(TAB_A)){
                pushFragments(tabId, pedidoFragment1);
            }
        }
    };

    /*
     * adds the fragment to the FrameLayout
     */
    public void pushFragments(String tag, Fragment fragment){

        FragmentManager manager         =   getSupportFragmentManager();
        FragmentTransaction ft            =   manager.beginTransaction();

        ft.replace(android.R.id.tabcontent, fragment);
        ft.commit();
    }

    /*
     * returns the tab view i.e. the tab icon and text
     */
    private View createTabView(final String text, final int id) {
        View view = LayoutInflater.from(this).inflate(R.layout.tabs_icon, null);
        ((TextView) view.findViewById(R.id.tab_text)).setText(text);
        return view;
    }

    @Override
    public boolean onTouchEvent(MotionEvent event) {
        InputMethodManager imm = (InputMethodManager)getSystemService(Context.
                INPUT_METHOD_SERVICE);
        imm.hideSoftInputFromWindow(getCurrentFocus().getWindowToken(), 0);
        return true;
    }

}
