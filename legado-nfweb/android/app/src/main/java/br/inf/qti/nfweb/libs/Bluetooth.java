package br.inf.qti.nfweb.libs;

import java.io.BufferedOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.util.Set;
import java.util.UUID;

import android.bluetooth.BluetoothAdapter;
import android.bluetooth.BluetoothDevice;
import android.bluetooth.BluetoothSocket;

public class Bluetooth 
{
    public BluetoothAdapter Adapter = null;
    public BluetoothSocket Socket;
    public BluetoothDevice Device;
    public InputStream Istream;
    public BufferedOutputStream Ostream;
    private static final UUID BLUETOOTH_SPP_UUID =  UUID.fromString("00001101-0000-1000-8000-00805f9b34fb");	   
	
    
    public boolean isConnected()
    {
    	return (Socket!=null && Socket.isConnected());
    }
    
    public boolean Enable()
	{
		Adapter = BluetoothAdapter.getDefaultAdapter();
		if (Adapter != null)
		{
			if (Adapter.isEnabled()) {
				return true;
			}
			Adapter.enable();
			try {
				Thread.sleep(5000, 0);
			} catch (InterruptedException e) {
				e.printStackTrace();
			};
			Adapter = BluetoothAdapter.getDefaultAdapter();
			if (Adapter.isEnabled())
			{
				return true;
			}
		}
		return false;
	}
    
    public boolean Disable()
    {
    	if (Adapter != null)
    	{
    		if (Adapter.isEnabled()) {
    			Adapter.disable();
    		}
    		if (!Adapter.isEnabled())
    		{
    			return true;
    		}
    	}
    	return false;
    }    
	
    public Set<BluetoothDevice> GetBondedDevices()
    {
    	return Adapter.getBondedDevices();
    }    
    
    public boolean Open(String mac)
    {   
    	try
    	{
    		this.Close();    			
    		Device = Adapter.getRemoteDevice(mac);        
    		Adapter.cancelDiscovery();
    		Socket = Device.createRfcommSocketToServiceRecord(BLUETOOTH_SPP_UUID);    		
    		Socket.connect();
    		Istream = Socket.getInputStream();
    		Ostream = new BufferedOutputStream(Socket.getOutputStream());
    		return true;
    	}
    	catch (IOException e)
    	{
    		return false;
    	}
    }
    
    public boolean Close()
    {
    	try
    	{
    		if (Ostream!=null)
    			Ostream.flush();
    		if (Socket!=null)
    			Socket.close();
    		Socket = null;
    	}
    	catch (IOException e)
    	{
    		return false;
    	}
    	return true;
    }
    
    public boolean Write(byte[] buffer, int count)
    {
    	try
    	{
    		Ostream.write(buffer, 0, count);
    	}
    	catch (IOException e)
    	{
    		return false;
    	}
    	return true;
    }
    
    public boolean Read(byte[] buffer, int length)
    {
    	try
    	{
    		Istream.read(buffer, 0, length);
    	}
    	catch (IOException e)
    	{
    		return false;
    	}
    	return true;
    }    
}
