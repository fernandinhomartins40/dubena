package br.inf.qti.movelapp;

import android.content.Context;
import android.content.Intent;
import android.graphics.Bitmap;
import android.graphics.Canvas;
import android.graphics.Color;
import android.graphics.Paint;
import android.graphics.Rect;
import android.os.Bundle;
//import android.support.v7.app.ActionBarActivity;

//import android.support.v7.app.AppCompatActivity;
import android.view.MenuItem;
import android.view.MotionEvent;
import android.view.View;
import android.widget.ScrollView;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

public class ReportImpressaoActivity extends AppCompatActivity {

    public Bitmap retBitmap = null;
    DrawView mDrawing;
    private String tipoImpressao;
    private String codReport;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        //setContentView(R.layout.activity_print_notafiscal);

        tipoImpressao = getIntent().getStringExtra("tipo");
        codReport = getIntent().getStringExtra("codReport");

        String result = this.LoadReport(1);

        if(tipoImpressao.equals("view")){
            if(ReportImpressaoActivity.this.retBitmap == null || !result.substring(0,1).equals("2")){
                Toast.makeText(ReportImpressaoActivity.this,  result.substring(1,result.length()), Toast.LENGTH_LONG).show();
                Intent returnIntent = new Intent();
                setResult(RESULT_OK, returnIntent);
                finish();
            }
            ReportImpressaoActivity.this.viewReport();
        } else {
            Toast.makeText(ReportImpressaoActivity.this, result.substring(1,result.length()), Toast.LENGTH_LONG).show();
            Intent returnIntent = new Intent();
            setResult(RESULT_OK, returnIntent);
            finish();
        }
    }


    @Override
    public boolean onOptionsItemSelected(MenuItem item) {
        // Handle action bar item clicks here. The action bar will
        // automatically handle clicks on the Home/Up button, so long
        // as you specify a parent activity in AndroidManifest.xml.
        int id = item.getItemId();
        if (id == R.id.action_settings) {
            return true;
        }
        return super.onOptionsItemSelected(item);
    }

    public void viewReport(){
        if(this.retBitmap != null) {
            mDrawing = new DrawView(this);
            ScrollView.LayoutParams lp = new ScrollView.LayoutParams(1000, 1000);
            setContentView(mDrawing, lp);
        }
    }

    public String LoadReport(int codReport) {
        try {
            if (codReport == 1) {
                String dataInicial = getIntent().getStringExtra("dataInicial");
                String dataFinal = getIntent().getStringExtra("dataFinal");
                ReportImpressao rep = new ReportImpressao("1", ReportImpressaoActivity.this);
                if (tipoImpressao.equals("view")) {
                    this.retBitmap = rep.gerarReport1Bmp(dataInicial, dataFinal);
                } else {
                    return rep.imprimirReport1Bmp(dataInicial, dataFinal);
                }
            }
            return "2Impressao OK";
        } catch (Exception e) {
            e.printStackTrace();
            return "0";
        }

        //return null;
    }

    private class DrawView extends View
    {
        private boolean move=false;
        private int X=0, Y=0, iX=0, iY=0;

        public DrawView(Context context) {
            super(context);
            //this.setBackgroundResource(R.color.window_background);
        }
        @Override

        public boolean onTouchEvent(final MotionEvent event)
        {
            boolean handled = false;
            int xTouch;
            int yTouch;
            int pointerId;
            int actionIndex = event.getActionIndex();

            switch (event.getActionMasked()) {
                case MotionEvent.ACTION_DOWN:
                    xTouch = (int) event.getX(0);
                    yTouch = (int) event.getY(0);

                    iX=(xTouch-X);
                    iY=(yTouch-Y);

                    invalidate();
                    handled = true;
                    move = true;
                    break;

                case MotionEvent.ACTION_POINTER_DOWN:
                    pointerId = event.getPointerId(actionIndex);

                    xTouch = (int) event.getX(actionIndex);
                    yTouch = (int) event.getY(actionIndex);

                    iX=(xTouch-X);
                    iY=(yTouch-Y);

                    invalidate();
                    handled = true;
                    move=true;
                    break;

                case MotionEvent.ACTION_MOVE:
                    final int pointerCount = event.getPointerCount();

                    for (actionIndex = 0; actionIndex < pointerCount; actionIndex++)
                    {
                        pointerId = event.getPointerId(actionIndex);

                        xTouch = (int) event.getX(actionIndex);
                        yTouch = (int) event.getY(actionIndex);

                        if (move) {
                            X = (xTouch - iX );
                            Y = (yTouch - iY);
                        }
                    }
                    invalidate();
                    handled = true;
                    break;

                case MotionEvent.ACTION_UP:
                    move=false;
                    invalidate();
                    handled = true;
                    break;

                case MotionEvent.ACTION_POINTER_UP:
                    move=false;
                    pointerId = event.getPointerId(actionIndex);
                    invalidate();
                    handled = true;
                    break;

                case MotionEvent.ACTION_CANCEL:
                    move=false;
                    handled = true;
                    break;

                default:
                    break;
            }

            return super.onTouchEvent(event) || handled;
        }

        protected void onDraw(Canvas canvas)
        {
            if (retBitmap!=null)
            {
                Paint myPaint = new Paint();
                myPaint.setColor(Color.BLACK);

                boolean resize=false;
                if (!resize){
                    canvas.drawBitmap(retBitmap, X, Y, myPaint);
                }
                else
                {
                    int ih = retBitmap.getHeight();
                    int iw = retBitmap.getWidth();
                    int mh = getHeight();
                    float fat=( ih / mh);
                    int mw = (int)((iw * mh)/ih);
                    canvas.drawBitmap(retBitmap,
                            new Rect(0,0,iw,ih),
                            new Rect(0,0,mw,mh),
                            myPaint);
                }
            }

        }
    }

}