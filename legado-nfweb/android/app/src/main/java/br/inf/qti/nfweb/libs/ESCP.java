package br.inf.qti.nfweb.libs;

import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.io.OutputStream;

import android.graphics.Bitmap;
import android.graphics.Bitmap.Config;
import android.graphics.Color;

public class ESCP 
{	
	  	private static class ESCPCommands {
    		public static final byte[] INIT = {27, 64};
	  		public static byte[] GS_DOWNLOAD = {0x1D, 0x2A};
	  		public static byte[] GS_PRINT_NORMAL = {0x1D, 0x2F, 0x00};
	  		public static byte[] FW_DENSITY = {0x1D, 0x45}; //adicionar no fim mais o byte do valor da densidade
	  	}
  		
	  	public static byte[]  ImageToEsc(Bitmap im, int bytes)
	  	{
	  		return ImageToEsc(im, bytes, null);
	  	}
	  	
	  	public static byte[]  ImageToEsc(Bitmap im, int bytes, Integer density)
	  	{
	  		ByteArrayOutputStream out = new ByteArrayOutputStream();
	  		ImageToEsc(im, out, bytes, density);
	  		return out.toByteArray();
	  	}
	  	
	    public static boolean ImageToEsc(Bitmap im, OutputStream out, int bytes, Integer density)
	    {	    	
	    	try 
	    	{	    	
				out.write(ESCPCommands.INIT);	
		        
				if (density!=null)
				{
					byte[] byteDensity = {(byte)(int)density};
					out.write(ESCPCommands.FW_DENSITY);
					out.write(byteDensity);
				}					
				
		        int wid = im.getWidth();
		        int hei = im.getHeight();
		        byte[] blen = new byte[2];
		        
		        int [] dots = new int[wid * hei];
		        im.getPixels(dots, 0, wid, 0, 0, wid, hei);
		        
	        	blen[0] = (byte) (wid / 8);
	        	blen[1] = (byte) bytes;	
	        	
		        int offset = 0;
		        while (offset < hei) 
		        {	        	
		        	out.write(ESCPCommands.GS_DOWNLOAD);	        	
		            out.write(blen);

		            for (int x = 0; x < wid; ++x) 
		            {
		            	for (int k = 0; k < bytes; ++k) 
		            	{ 
		                    byte slice = 0;
		 
		                    for (int b = 0; b < 8; ++b) 
		                    {
		                        int y = (((offset / 8) + k) * 8) + b;
		 
		                        int i = (y * wid) + x;
		 
		                        boolean v = false;
		                        if (i < dots.length) {
		                            v = (dots[i]==Color.BLACK);
		                        }
		 
		                        slice |= (byte) ((v ? 1 : 0) << (7 - b));
		                    }
		 
		                    out.write(slice);
		                }
		            }		            
		            offset += (bytes * 8);		            
		            out.write(ESCPCommands.GS_PRINT_NORMAL);
		        }
		        out.flush();
			} 
	    	catch (IOException e) 
	    	{
	    		return false;
			}
	    	return true;
	    }    	  	  
	    
	private static Boolean isBitSet(byte b, int bit)
	{
	    return (b & (1 << bit)) != 0;
	}	
	
    public static Bitmap EscToImage(byte[] arr, int width, int height)
    {		
			Bitmap im = Bitmap.createBitmap(width, height, Config.RGB_565);
     
        	int x=0, y=0, p=0;
        	byte p1=0, p2=0;
        	
    	    while (p<arr.length)
    	    {    	    
    	    	switch(arr[p])
    	    	{
    	    		case 0x1B: //27
	    	    		{
	    	    			p++;
		    	    		switch(arr[p])
		    	    		{
		    	    		case 0x40: //60
		    	    				x=0;
		    	    			break;
		    	    		}
	    	    		}
	    	    		break;
    	    		case 0x1D: //29 
	    	    		{
	    	    			p++;
		    	    		switch(arr[p])
		    	    		{
		    	    		case 0x2F: //47
		    	    			{
		    	    				x=0;
		    	    				p++;
		    	    			}
		    	    			break;
		    	    		case 0x2A: //42
		    	    			{
		    	    				p++; p1 = arr[p];
		    	    				p++; p2 = arr[p];
		    	    				int sz=((p1 * p2)*8);
		    	    				int it=0;		
		    	    				int by = y;
		    	    				x =0;
		    	    				while(it < sz)
		    	    				{
		    	    					y = by;
		    	    					for(int byt=0; byt<(int)p2; byt++)
		    	    					{		    	
		    	    						p++;
			    	    					for(int bit=7; bit>=0; bit--)
			    	    					{
				    	    						if (isBitSet(arr[p], bit))
				    	    							im.setPixel(x, y, Color.BLACK);
				    	    						else 
				    	    							im.setPixel(x, y, Color.WHITE);
				    	    						y++;   						
			    	    					}
			    	    					it++;
		    	    					}
		    	    					x++;
		    	    				}
		    	    				it=0;
		    	    			}
		    	    			break;
		    	    		}    	    			
	    	    		}
	    	    		break;
    	    	}
    	    	p++;
    	    }
    	    return im;	    
    }		
}
