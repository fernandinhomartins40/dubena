package br.inf.qti.movelapp;

import android.content.Intent;
import android.os.Bundle;
//import android.support.design.widget.FloatingActionButton;
//import android.support.design.widget.Snackbar;
//import android.support.v7.app.AppCompatActivity;
//import android.support.v7.widget.Toolbar;
import android.view.View;
import android.widget.AdapterView;
import android.widget.ListView;
import android.widget.TextView;

import androidx.appcompat.app.AppCompatActivity;

import java.util.LinkedList;

public class PedidoConsultaActivity extends AppCompatActivity {

    private static final int REQUEST_GET_PEDIDOS = 1;
    private static final int REQUEST_ATUALIZA_PEDIDOS = 2;
    private static final int REQUEST_CONSULTA_PEDIDO = 3;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_pedido_consulta);
        //Toolbar toolbar = (Toolbar) findViewById(R.id.toolbar);
        //setSupportActionBar(toolbar);
        carregarPedidosPendentes();
        configureListPedidos();
    }
    private void carregarPedidosPendentes() {
        Intent intent = new Intent(getApplicationContext(), PedidoRecepcaoActivity.class);
        startActivityForResult(intent, REQUEST_GET_PEDIDOS);
    }

    private void configureListPedidos() {
        final ListView lv1 = (ListView) findViewById(R.id.listParcelasPedidoConsulta);
        lv1.setOnItemClickListener(new AdapterView.OnItemClickListener() {
            @Override
            public void onItemClick(AdapterView<?> parent, View view, int position,
                                    long id) {
                int id1 = ((Pedido) parent.getItemAtPosition(position)).getId();
                Intent i = new Intent(getApplicationContext(), PedidoActivity.class);
                i.putExtra("pedidoId",String.valueOf(id1).toString());
                startActivityForResult(i, REQUEST_CONSULTA_PEDIDO);
            }
        });
    }

    public void onActivityResult(int requestCode, int resultCode,
                                 Intent data) {
        if (requestCode == REQUEST_GET_PEDIDOS) {

            DataBaseHandler dbHandler = new DataBaseHandler(getApplicationContext());

            final LinkedList<Pedido> pedidos = dbHandler.getAllPedidosPendentes();
            if(pedidos.size()==0){
                ((TextView) findViewById(R.id.txtSemPedidos)).setVisibility(View.VISIBLE);
            } else {
                ((TextView) findViewById(R.id.txtSemPedidos)).setVisibility(View.GONE);
            }
            final ListView lv1 = (ListView) findViewById(R.id.listParcelasPedidoConsulta);
            lv1.setAdapter(new CustomListAdapter(PedidoConsultaActivity.this, pedidos, "pedidos"));
            for(int i=0;i<pedidos.size();i++) {
                if(pedidos.get(i).getCodStatus() == dbHandler.getSituacaoPendente()) {
                    Intent intent = new Intent(getApplicationContext(), PedidoStatusActivity.class);
                    intent.putExtra("pedidoId", String.valueOf(pedidos.get(i).getId()).toString());
                    intent.putExtra("statusId", String.valueOf(dbHandler.getSituacaoMensagemEnviada()).toString());
                    intent.putExtra("pedeMotivoAtraso", "false");
                    intent.putExtra("pedeCartao", "false");
                    intent.putExtra("conferePix", "false");
                    startActivityForResult(intent, REQUEST_ATUALIZA_PEDIDOS);
                }
            }
        } else if(requestCode == REQUEST_ATUALIZA_PEDIDOS){

        }else if(requestCode == REQUEST_CONSULTA_PEDIDO){
            carregarPedidosPendentes();
        }

    }
}
