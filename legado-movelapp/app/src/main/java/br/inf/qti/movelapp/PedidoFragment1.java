package br.inf.qti.movelapp;

import android.annotation.TargetApi;
import android.app.Activity;
import android.Manifest;
import android.app.AlertDialog;
import android.content.Context;
import android.content.DialogInterface;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.graphics.Typeface;
import android.location.LocationListener;
import android.location.LocationManager;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
//import android.support.v4.app.Fragment;
//import android.support.v4.content.ContextCompat;
import android.util.Log;
import android.view.LayoutInflater;
import android.view.MenuItem;
import android.view.View;
import android.view.ViewGroup;

import android.widget.ImageButton;
import android.widget.ListView;

import android.widget.PopupMenu;
import android.widget.TextView;
import android.widget.Toast;

import androidx.core.content.ContextCompat;
import androidx.fragment.app.Fragment;

import com.google.zxing.client.android.CaptureActivity;

import java.text.ParseException;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.LinkedList;
import java.util.Locale;

public class PedidoFragment1 extends Fragment {

    ImageButton btnMudarStatus, btnAceitar, btnTransferir, btnCancelar;
    View viewG;
    Pedido pedido;
    String pedidoId, statusId;
    ListView listItens;
    TextView txtCodigoPedido, txtBairroPedido, txtClientePedido, txtComplementoPedido, txtDataPedido, txtEnderecoPedido, txtPontoReferenciaPedido,txtStatusPedido, txtValorTotalPedido, txtObservacaoPedido, txtCondicaoPedido, txtUrgentePedido, txtConvenioPedido, txtAppPedido;

    private static final int LOCATION_REQUEST=1340;
    private static final int CAMERA_REQUEST=1341;


    private static final String[] LOCATION_PERMS={
            Manifest.permission.ACCESS_FINE_LOCATION
    };

    private static final String[] CAMERAPERMS={
            Manifest.permission.CAMERA
    };

    private static final int REQUEST_ATUALIZA_PEDIDOS = 2;
    private static final int REQUEST_FINALIZA_PEDIDO = 3;
    private static final int REQUEST_CANCELA_PEDIDO = 4;
    private static final int REQUEST_TRANSFERE_PEDIDO = 5;
    private static final int REQUEST_LEITURA_GASDEBOLSO = 6;
    private static final int REQUEST_CONFERE_GASDEBOLSO = 7;
    private static final int REQUEST_GERA_PIX = 8;

    private OnItemSelectedListener listener;

    @Override
    public View onCreateView(LayoutInflater inflater, ViewGroup container,
                             Bundle savedInstanceState) {
        // TODO Auto-generated method stub

        View view = inflater.inflate(R.layout.pedido_fragment1, container, false);
        viewG = view;
        pedidoId = getArguments().getString("pedidoId");

        txtCodigoPedido = (TextView) view.findViewById(R.id.txtCodigoPedido);
        txtBairroPedido = (TextView) view.findViewById(R.id.txtBairroPedido);
        txtClientePedido = (TextView) view.findViewById(R.id.txtClientePedido);
        txtComplementoPedido = (TextView) view.findViewById(R.id.txtComplementoPedido);
        txtDataPedido = (TextView) view.findViewById(R.id.txtDataPedido);
        txtEnderecoPedido = (TextView) view.findViewById(R.id.txtEnderecoPedido);
        txtPontoReferenciaPedido = (TextView) view.findViewById(R.id.txtPontoReferenciaPedido);
        txtStatusPedido = (TextView) view.findViewById(R.id.txtStatusPedido);
        txtValorTotalPedido = (TextView) view.findViewById(R.id.txtValorTotalPedido);
        txtObservacaoPedido = (TextView) view.findViewById(R.id.txtObservacaoPedido);
        txtCondicaoPedido = (TextView) view.findViewById(R.id.txtCondicaoPedido);
        txtUrgentePedido = (TextView) view.findViewById(R.id.txtUrgentePedido);
        txtConvenioPedido = (TextView) view.findViewById(R.id.txtConvenioPedido);
        txtAppPedido = (TextView) view.findViewById(R.id.txtAppPedido);
        listItens = (ListView) view.findViewById(R.id.listItens);
        btnMudarStatus = (ImageButton) view.findViewById(R.id.btnMudarStatus);
        btnAceitar = (ImageButton) view.findViewById(R.id.btnAceitarPedido);
        btnTransferir = (ImageButton) view.findViewById(R.id.btnTransferirPedido);
        btnCancelar = (ImageButton) view.findViewById(R.id.btnCancelarPedido);
        carregarPedido(true);
        atualizarStatusPedidoMsgLida(pedidoId);
        carregarItens();
        configureBtnAceitar(viewG);
        configureBtnFinalizarStatus(viewG);
        configureBtnTransferir(viewG);
        configureBtnCancelarStatus(viewG);
        return view;
    }

    public interface OnItemSelectedListener {

    }

    @Override
    public void onAttach(Activity activity) {
        super.onAttach(activity);
        if (activity instanceof OnItemSelectedListener) {
            listener = (OnItemSelectedListener) activity;
        } else {
            throw new ClassCastException(activity.toString()
                    + " must  implements PedidosFragment1.OnItemSelectedListener");
        }
    }

    @Override
    public void onDetach() {
        super.onDetach();
        listener = null;
    }

    private void carregarPedido(boolean buscaDB){
        if(buscaDB){
            DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
            pedido = dbHandler.getPedido(String.valueOf(pedidoId));
        }
        txtCodigoPedido.setText("" + String.valueOf(pedido.getId()) + " em " + pedido.getDataPedidoTexto());
        txtBairroPedido.setText("" + pedido.getBairro());
        txtClientePedido.setText("" + pedido.getCliente());
        txtComplementoPedido.setText("" + pedido.getComplemento());
        txtEnderecoPedido.setText("" + pedido.getRua() + ", " + pedido.getNumero());
        txtPontoReferenciaPedido.setText("" + pedido.getPonto_referencia());
        txtStatusPedido.setText(  "" + pedido.getDescStatus());
        txtValorTotalPedido.setText(String.format(new Locale("pt", "BR"), "%1$,.2f", pedido.getValor_venda()));
        txtObservacaoPedido.setText(pedido.getObservacao());
        txtCondicaoPedido.setText(pedido.getCondicao());
        if(pedido.getCondicao().toLowerCase().matches(".*online.*") || pedido.getCondicao().toLowerCase().matches(".*pix.*") ){
            txtCondicaoPedido.setTypeface(null, Typeface.BOLD);
            txtCondicaoPedido.setTextColor(Color.parseColor("#800080"));
        }
        txtUrgentePedido.setText(pedido.getUrgente());
        txtConvenioPedido.setText(pedido.getConvenio());
        txtAppPedido.setText(pedido.getApp());
        if(pedido.getApp().equalsIgnoreCase("S")){
            txtCodigoPedido.setTextColor(Color.parseColor("#AB24B7"));
            txtAppPedido.setTextColor(Color.parseColor("#AB24B7"));
        } else {
            //txtCodigoPedido.setTextColor(Color.parseColor("#000000"));
            //txtAppPedido.setTextColor(Color.parseColor("#AB24B7"));
        }
    }

    private void carregarItens(){
            DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
            final LinkedList<PedidoItem> itens = dbHandler.getAllItens(String.valueOf(pedido.getId()));
            pedido.setItens(itens);
            listItens.setAdapter(new CustomListAdapter(getActivity(), itens, "itens"));
    }

    private void atualizarStatusPedidoMsgLida(String codPedido){
        DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
        pedido = dbHandler.getPedido(String.valueOf(codPedido));
        String ret = dbHandler.getSituacoesPedidosPendentes();
        if(pedido.getCodStatus()==dbHandler.getSituacaoPendente() || pedido.getCodStatus()==dbHandler.getSituacaoMensagemEnviada()){
            Intent intent = new Intent(getActivity().getApplicationContext(), PedidoStatusActivity.class);
            intent.putExtra("pedidoId", String.valueOf(codPedido));
            intent.putExtra("statusId", String.valueOf(dbHandler.getSituacaoMensagemLida()));
            intent.putExtra("pedeMotivoAtraso", "false");
            intent.putExtra("pedeCartao", "false");
            intent.putExtra("conferePix", "false");
            startActivity(intent);
        }
    }
    private void configureBtnAceitar(View view) {

        btnAceitar.setOnClickListener(new View.OnClickListener() {
            @TargetApi(Build.VERSION_CODES.HONEYCOMB)
            @Override
            public void onClick(View view) {
                DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
                if(dbHandler.isSituacaoPendente(pedido.getCodStatus())){
                    Intent intent = new Intent(getActivity().getApplicationContext(), PedidoStatusActivity.class);
                    intent.putExtra("pedidoId", String.valueOf(pedido.getId()));
                    intent.putExtra("statusId", String.valueOf(dbHandler.getSituacaoEmEntrega()));
                    intent.putExtra("pedeMotivoAtraso", "false");
                    intent.putExtra("pedeCartao", "false");
                    intent.putExtra("conferePix", "false");
                    startActivityForResult(intent, REQUEST_ATUALIZA_PEDIDOS);
                }
            }
        });
    }

    private void configureBtnTransferir(View view) {
        btnTransferir.setOnClickListener(new View.OnClickListener() {
            @TargetApi(Build.VERSION_CODES.HONEYCOMB)
            @Override
            public void onClick(View view) {
                PopupMenu popupMenu = new PopupMenu(getActivity(), btnTransferir);
                popupMenu.getMenu().add(1, 1, 1, "Transferir");
                popupMenu.getMenu().add(2, 2, 2, "Rota para Endereço");
                if(pedido.getCondicao().toLowerCase().matches(".*pix.*") ){
                    popupMenu.getMenu().add(3, 3, 3, "gerar PIX");
                }
                popupMenu.setOnMenuItemClickListener(new PopupMenu.OnMenuItemClickListener() {
                    public boolean onMenuItemClick(MenuItem item) {
                        if(item.getItemId() == 1) {
                            DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
                            if(dbHandler.isSituacaoPendente(pedido.getCodStatus())){
                                AlertDialog.Builder builder = new AlertDialog.Builder(getActivity());
                                builder.setTitle("Questão");
                                builder.setMessage("Confirma transferência para outro entregador?");

                                builder.setPositiveButton("Sim", new DialogInterface.OnClickListener() {
                                    @Override
                                    public void onClick(DialogInterface dialog, int which) {
                                        DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
                                        dialog.dismiss();
                                        Intent intent = new Intent(getActivity().getApplicationContext(), PedidoStatusActivity.class);
                                        intent.putExtra("pedidoId", String.valueOf(pedido.getId()));
                                        intent.putExtra("statusId", String.valueOf(dbHandler.getSituacaoTransferir()));
                                        intent.putExtra("pedeMotivoAtraso", "false");
                                        intent.putExtra("pedeCartao", "false");
                                        intent.putExtra("conferePix", "false");
                                        startActivityForResult(intent, REQUEST_TRANSFERE_PEDIDO);
                                    }
                                });
                                builder.setNegativeButton("Não", new DialogInterface.OnClickListener() {
                                    @Override
                                    public void onClick(DialogInterface dialog, int which) {
                                        dialog.dismiss();
                                    }
                                });
                                AlertDialog alert = builder.create();
                                alert.show();
                            }
                            return true;
                        } else if(item.getItemId() == 2) {
                            String endereco = txtEnderecoPedido.getText() + " " + txtBairroPedido.getText() + " +" + pedido.getCidade() + "+" + pedido.getUf() +  "+Brazil";
                            endereco.replace(" ","+");
                            //Uri gmmIntentUri = Uri.parse("google.navigation:q=Juvenal+Caldas,426,+Guarapuava+PR+Brazil");
                            Uri gmmIntentUri = Uri.parse("google.navigation:q=" + endereco);
                            Intent mapIntent = new Intent(Intent.ACTION_VIEW, gmmIntentUri);
                            mapIntent.setPackage("com.google.android.apps.maps");
                            startActivity(mapIntent);
                            return true;
                        } else {
                            Intent intent = new Intent(getActivity().getApplicationContext(), PedidoPixActivity.class);
                            intent.putExtra("pedidoId", String.valueOf(pedido.getId()));
                            startActivityForResult(intent, REQUEST_GERA_PIX);
                            return true;
                        }
                    }
                });
                popupMenu.show();//showing popup menu
            }
        });

    }


    private void configureBtnFinalizarStatus(View view) {

        btnMudarStatus.setOnClickListener(new View.OnClickListener() {
            @TargetApi(Build.VERSION_CODES.HONEYCOMB)
            @Override
            public void onClick(View view) {
                PopupMenu popupMenu = new PopupMenu(getActivity(), btnMudarStatus);
                DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
                final LinkedList<Situacao> situacoes = dbHandler.getSituacoesFinalizar();
                for(int i=0; i< situacoes.size(); i++){
                    Situacao sit = situacoes.get(i);
                    popupMenu.getMenu().add(sit.getId(),sit.getId(), sit.getId(), sit.toString());
                }
                popupMenu.setOnMenuItemClickListener(new PopupMenu.OnMenuItemClickListener() {
                    public boolean onMenuItemClick(MenuItem item) {
                        DataBaseHandler dbh = new DataBaseHandler(getActivity().getApplicationContext());
                        Empresa empresa = dbh.getEmpresa();
                        String pedeMotivoAtraso = "false";
                        String pedeCartao = "false";
                        if (item.getItemId() == dbh.getSituacaoValeGas() && empresa.getValidaGB() == 1) {
                            statusId = String.valueOf(item.getItemId());
                            try {
                                if (PackageManager.PERMISSION_GRANTED== ContextCompat.checkSelfPermission(getActivity().getApplicationContext(), Manifest.permission.CAMERA)) {
                                    Intent intent = new Intent(getActivity().getApplicationContext(), CaptureActivity.class);
                                    intent.setAction("com.google.zxing.client.android.SCAN");
                                    intent.putExtra("SAVE_HISTORY", false);
                                    startActivityForResult(intent, REQUEST_LEITURA_GASDEBOLSO);
                                    return true;
                                } else {
                                    requestPermissions(CAMERAPERMS, CAMERA_REQUEST);
                                    Intent intent = new Intent(getActivity().getApplicationContext(), CaptureActivity.class);
                                    intent.setAction("com.google.zxing.client.android.SCAN");
                                    intent.putExtra("SAVE_HISTORY", false);
                                    startActivityForResult(intent, REQUEST_LEITURA_GASDEBOLSO);
                                    return true;
                                }
                            } catch (SecurityException e) {
                                e.printStackTrace();
                                return false;
                            } catch (Exception e) {
                                e.printStackTrace();
                                return false;
                            }




                        } else {
                            if(dbh.getSituacao(item.getItemId()).getCartao() == 1){
                                pedeCartao = "true";
                            }
                            if (empresa.getValidaAtraso() == 1) {
                                SimpleDateFormat format = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss");

                                Date d1 = null;
                                Date d2 = new Date();
                                try {
                                    d1 = format.parse(pedido.getData_pedido());
                                } catch (ParseException e) {
                                    e.printStackTrace();
                                }
                                long diff = d2.getTime() - d1.getTime();
                                long diffMinutes = diff / (60 * 1000);
                                int tempoEntrega = empresa.getTempoEntrega();
                                if (pedido.getUrgente().equals("S")) {
                                    tempoEntrega = empresa.getTempoEntregaUrgente();
                                }
                                if (diffMinutes > tempoEntrega) {
                                    //buscar o motivo
                                    pedeMotivoAtraso = "true";
                                }
                            }
                            //get Your Current Location
                            CoordenadaResult coord = new CoordenadaResult(true, 0, 0, "");

                            if(empresa.getValidaCoordenadas()==1 || pedido.getGasdopovo()==1) {
                                try {
                                    if (PackageManager.PERMISSION_GRANTED== ContextCompat.checkSelfPermission(getActivity().getApplicationContext(), Manifest.permission.ACCESS_FINE_LOCATION)) {
                                        coord = getCurrentLocation();
                                        if(!coord.getAchou()){
                                            Toast.makeText(getActivity().getApplicationContext(), "GPS ainda não posicionado!", Toast.LENGTH_SHORT).show();
                                        }
                                    } else {
                                        requestPermissions(LOCATION_PERMS, LOCATION_REQUEST);
                                        coord = getCurrentLocation();
                                        if(!coord.getAchou()){
                                            Toast.makeText(getActivity().getApplicationContext(), "GPS ainda não posicionado!", Toast.LENGTH_SHORT).show();
                                        }
                                    }
                                } catch (SecurityException e) {
                                    e.printStackTrace();
                                } catch (Exception e) {
                                    e.printStackTrace();
                                }
                            }
                            if(coord.getAchou()) {
                                Intent intent = new Intent(getActivity().getApplicationContext(), PedidoStatusActivity.class);
                                intent.putExtra("pedidoId", String.valueOf(pedido.getId()));
                                intent.putExtra("statusId", String.valueOf(item.getItemId()));
                                intent.putExtra("longitude", String.valueOf(coord.getLongitude()));
                                intent.putExtra("latitude", String.valueOf(coord.getLatitude()));
                                intent.putExtra("pedeMotivoAtraso", pedeMotivoAtraso);
                                intent.putExtra("pedeCartao", pedeCartao);
                                intent.putExtra("conferePix", pedido.getCondicao().toLowerCase().matches(".*pix.*") && empresa.getValidaPix()==1?"true":"false");
                                startActivityForResult(intent, REQUEST_FINALIZA_PEDIDO);
                            }
                            return true;
                        }
                    }
                });
                popupMenu.show();//showing popup menu
            }
        });
    }

    private CoordenadaResult getCurrentLocation() {
        if (PackageManager.PERMISSION_GRANTED == ContextCompat.checkSelfPermission(getActivity().getApplicationContext(), Manifest.permission.ACCESS_FINE_LOCATION)) {
            LocationManager mlocManager = null;
            LocationListener mlocListener;
            mlocManager = (LocationManager) getActivity().getApplicationContext().getSystemService(Context.LOCATION_SERVICE);
            mlocListener = new MyCurrentLocationListener();
            mlocManager.requestLocationUpdates(LocationManager.GPS_PROVIDER, 0, 0, mlocListener);

            if (mlocManager.isProviderEnabled(LocationManager.GPS_PROVIDER)) {
                if (MyCurrentLocationListener.latitude != 0) {
                    CoordenadaResult res = new CoordenadaResult(true, MyCurrentLocationListener.latitude, MyCurrentLocationListener.longitude, "");
                    return res;
                } else {
                    CoordenadaResult res = new CoordenadaResult(false, 0, 0, "GPS ainda não posicionado!");
                    return res;
                }
            } else {
                CoordenadaResult res = new CoordenadaResult(false, 0, 0, "GPS ainda não posicionado!");
                return res;
            }

        } else {
            requestPermissions(LOCATION_PERMS, LOCATION_REQUEST);
            CoordenadaResult res = new CoordenadaResult(false, 0, 0, "GPS ainda não posicionado!");
            return res;
        }
    }
    private void configureBtnCancelarStatus(View view) {

        btnCancelar.setOnClickListener(new View.OnClickListener() {
            @TargetApi(Build.VERSION_CODES.HONEYCOMB)
            @Override
            public void onClick(View view) {
                PopupMenu popupMenu = new PopupMenu(getActivity(), btnCancelar);
                DataBaseHandler dbHandler = new DataBaseHandler(getActivity().getApplicationContext());
                final LinkedList<Situacao> situacoes = dbHandler.getSituacoesCancelar();
                for(int i=0; i< situacoes.size(); i++){
                    Situacao sit = situacoes.get(i);
                    popupMenu.getMenu().add(sit.getId(),sit.getId(), sit.getId(), sit.toString());
                }
                popupMenu.setOnMenuItemClickListener(new PopupMenu.OnMenuItemClickListener() {
                    public boolean onMenuItemClick(MenuItem item) {
                        final int codStatus = item.getItemId();

                        AlertDialog.Builder builder = new AlertDialog.Builder(getActivity());
                        builder.setTitle("Questão");
                        builder.setMessage("Confirma cancelamento da entrega?");

                        builder.setPositiveButton("Sim", new DialogInterface.OnClickListener() {
                            @Override
                            public void onClick(DialogInterface dialog, int which) {
                                dialog.dismiss();
                                Intent intent = new Intent(getActivity().getApplicationContext(), PedidoStatusActivity.class);
                                intent.putExtra("pedidoId", String.valueOf(pedido.getId()));
                                intent.putExtra("statusId", String.valueOf(codStatus));
                                intent.putExtra("pedeMotivoAtraso", "false");
                                intent.putExtra("pedeCartao", "false");
                                intent.putExtra("conferePix", "false");
                                startActivityForResult(intent, REQUEST_CANCELA_PEDIDO);
                            }
                        });
                        builder.setNegativeButton("Não", new DialogInterface.OnClickListener() {
                            @Override
                            public void onClick(DialogInterface dialog, int which) {
                                dialog.dismiss();
                            }
                        });
                        AlertDialog alert = builder.create();
                        alert.show();
                        return true;
                    }
                });
                popupMenu.show();//showing popup menu
            }
        });
    }
    public void onActivityResult(int requestCode, int resultCode, Intent data) {
        if (requestCode == REQUEST_ATUALIZA_PEDIDOS || requestCode == REQUEST_GERA_PIX) {
            carregarPedido(true);
        } else if (requestCode == REQUEST_FINALIZA_PEDIDO) {
            boolean erro = data.getStringExtra("ERRO").equals("true");
            if(!erro) {
                getActivity().finish();
            }
        } else if (requestCode == REQUEST_CANCELA_PEDIDO) {
            getActivity().finish();
        } else if (requestCode == REQUEST_TRANSFERE_PEDIDO) {
            getActivity().finish();
        }else if (requestCode == REQUEST_LEITURA_GASDEBOLSO) {
            if (resultCode == Activity.RESULT_OK) {
                String contents = data.getStringExtra("SCAN_RESULT");
                Intent intent = new Intent(getActivity().getApplicationContext(), GasdeBolsoVerificacaoActivity.class);
                intent.putExtra("gasdebolso", contents);
                startActivityForResult(intent, REQUEST_CONFERE_GASDEBOLSO);
            } else if (resultCode == Activity.RESULT_CANCELED) {
                // Handle cancel
                Log.d("AAA", "CANCELADO");
            }
        }else if (requestCode == REQUEST_CONFERE_GASDEBOLSO) {
            if (resultCode == Activity.RESULT_OK) {
                DataBaseHandler dbh = new DataBaseHandler(getActivity().getApplicationContext());
                Empresa empresa = dbh.getEmpresa();
                String pedeMotivoAtraso = "false";
                String pedeCartao = "false";
                if(dbh.getSituacao(pedido.codStatus).getCartao() == 1){
                    pedeCartao = "true";
                }
                if(empresa.getValidaAtraso()==1){
                    SimpleDateFormat format = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss");

                    Date d1 = null;
                    Date d2 = new Date();
                    try {
                        d1 = format.parse(pedido.getData_pedido());
                    } catch (ParseException e) {
                        e.printStackTrace();
                    }
                    long diff = d2.getTime() - d1.getTime();
                    long diffMinutes = diff / (60 * 1000);
                    int tempoEntrega = empresa.getTempoEntrega();
                    if(pedido.getUrgente().equals("S")){
                        tempoEntrega = empresa.getTempoEntregaUrgente();
                    }
                    if(diffMinutes > tempoEntrega){
                        //buscar o motivo
                        pedeMotivoAtraso = "true";
                    }
                }
                //get Your Current Location
                CoordenadaResult coord = new CoordenadaResult(true, 0, 0, "");
                if(empresa.getValidaCoordenadas()==1) {
                    try {
                        if (PackageManager.PERMISSION_GRANTED == ContextCompat.checkSelfPermission(getActivity().getApplicationContext(), Manifest.permission.ACCESS_FINE_LOCATION)) {
                            coord = getCurrentLocation();
                            if (!coord.getAchou()) {
                                Toast.makeText(getActivity().getApplicationContext(), "GPS ainda não posicionado!", Toast.LENGTH_SHORT).show();
                            }
                        } else {
                            requestPermissions(LOCATION_PERMS, LOCATION_REQUEST);
                            coord = getCurrentLocation();
                            if (!coord.getAchou()) {
                                Toast.makeText(getActivity().getApplicationContext(), "GPS ainda não posicionado!", Toast.LENGTH_SHORT).show();
                            }
                        }
                    } catch (SecurityException e) {
                        e.printStackTrace();
                    } catch (Exception e) {
                        e.printStackTrace();
                    }
                }
                if(coord.getAchou()) {
                    Intent intent = new Intent(getActivity().getApplicationContext(), PedidoStatusActivity.class);
                    intent.putExtra("pedidoId", String.valueOf(pedido.getId()));
                    intent.putExtra("statusId", String.valueOf(statusId));
                    intent.putExtra("longitude", String.valueOf(coord.getLongitude()));
                    intent.putExtra("latitude", String.valueOf(coord.getLatitude()));
                    intent.putExtra("pedeMotivoAtraso", pedeMotivoAtraso);
                    intent.putExtra("pedeCartao", pedeCartao);
                    intent.putExtra("conferePix", pedido.getCondicao().toLowerCase().matches(".*pix.*")?"true":"false");
                    startActivityForResult(intent, REQUEST_FINALIZA_PEDIDO);
                }
            } else if (resultCode == Activity.RESULT_CANCELED) {
                // Handle cancel
                Log.d("AAA", "ERRO");
            }
        }

    }

}