package com.karuhundeveloper.laundryhub;

import android.Manifest;
import android.annotation.SuppressLint;
import android.app.PendingIntent;
import android.bluetooth.BluetoothAdapter;
import android.bluetooth.BluetoothDevice;
import android.bluetooth.BluetoothSocket;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.hardware.usb.UsbConstants;
import android.hardware.usb.UsbDevice;
import android.hardware.usb.UsbDeviceConnection;
import android.hardware.usb.UsbEndpoint;
import android.hardware.usb.UsbInterface;
import android.hardware.usb.UsbManager;
import android.os.Build;
import android.util.Base64;

import com.getcapacitor.JSArray;
import com.getcapacitor.JSObject;
import com.getcapacitor.PermissionState;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.getcapacitor.annotation.Permission;
import com.getcapacitor.annotation.PermissionCallback;

import java.io.OutputStream;
import java.util.Arrays;
import java.util.UUID;

@CapacitorPlugin(
    name = "ThermalPrinter",
    permissions = {
        @Permission(alias = "bluetooth", strings = { Manifest.permission.BLUETOOTH_CONNECT })
    }
)
public class ThermalPrinterPlugin extends Plugin {
    private static final UUID SPP_UUID = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB");
    private static final String ACTION_USB_PERMISSION =
        "com.karuhundeveloper.laundryhub.USB_PERMISSION";
    private static final int USB_TRANSFER_TIMEOUT_MILLISECONDS = 5000;
    private static final int USB_TRANSFER_CHUNK_BYTES = 16384;

    @PluginMethod
    public void listBluetooth(PluginCall call) {
        if (needsBluetoothPermission()) {
            requestPermissionForAlias("bluetooth", call, "bluetoothPermissionCallback");
            return;
        }

        new Thread(() -> resolveBluetoothPrinters(call)).start();
    }

    @PluginMethod
    public void listUsb(PluginCall call) {
        UsbManager usbManager = getUsbManager();
        JSArray printers = new JSArray();

        if (usbManager != null) {
            for (UsbDevice device : usbManager.getDeviceList().values()) {
                JSObject printer = new JSObject();
                printer.put("id", device.getDeviceName());
                printer.put("name", usbDeviceName(device));
                printers.put(printer);
            }
        }

        JSObject result = new JSObject();
        result.put("printers", printers);
        call.resolve(result);
    }

    @PluginMethod
    public void print(PluginCall call) {
        byte[] bytes = decodePrintData(call);
        if (bytes == null) {
            call.reject("Data cetak kosong atau tidak valid.", "invalid_data");
            return;
        }

        String connection = call.getString("connection", "");
        if ("bluetooth".equals(connection)) {
            if (needsBluetoothPermission()) {
                requestPermissionForAlias("bluetooth", call, "bluetoothPermissionCallback");
                return;
            }

            new Thread(() -> printBluetooth(call, bytes)).start();
            return;
        }

        if ("usb".equals(connection)) {
            printUsb(call, bytes);
            return;
        }

        call.reject("Jenis koneksi printer tidak dikenal.", "unknown_connection");
    }

    @PermissionCallback
    private void bluetoothPermissionCallback(PluginCall call) {
        if (needsBluetoothPermission()) {
            call.reject("Izin Bluetooth ditolak.", "permission_denied");
            return;
        }

        if ("print".equals(call.getMethodName())) {
            byte[] bytes = decodePrintData(call);
            if (bytes == null) {
                call.reject("Data cetak kosong atau tidak valid.", "invalid_data");
                return;
            }

            new Thread(() -> printBluetooth(call, bytes)).start();
            return;
        }

        new Thread(() -> resolveBluetoothPrinters(call)).start();
    }

    @SuppressLint("MissingPermission")
    private void resolveBluetoothPrinters(PluginCall call) {
        BluetoothAdapter adapter = BluetoothAdapter.getDefaultAdapter();
        if (adapter == null) {
            call.reject("Perangkat ini tidak memiliki Bluetooth.", "bluetooth_unavailable");
            return;
        }

        if (!adapter.isEnabled()) {
            call.reject("Bluetooth belum aktif.", "bluetooth_disabled");
            return;
        }

        JSArray printers = new JSArray();
        for (BluetoothDevice device : adapter.getBondedDevices()) {
            JSObject printer = new JSObject();
            printer.put("id", device.getAddress());
            printer.put("name", device.getName() == null ? device.getAddress() : device.getName());
            printers.put(printer);
        }

        JSObject result = new JSObject();
        result.put("printers", printers);
        call.resolve(result);
    }

    @SuppressLint("MissingPermission")
    private void printBluetooth(PluginCall call, byte[] bytes) {
        BluetoothAdapter adapter = BluetoothAdapter.getDefaultAdapter();
        if (adapter == null || !adapter.isEnabled()) {
            call.reject("Bluetooth belum aktif.", "bluetooth_disabled");
            return;
        }

        String printerId = call.getString("id");
        if (printerId == null || printerId.isBlank()) {
            call.reject("ID printer Bluetooth kosong.", "missing_printer_id");
            return;
        }

        BluetoothSocket socket = null;
        try {
            BluetoothDevice device = adapter.getRemoteDevice(printerId);
            socket = device.createRfcommSocketToServiceRecord(SPP_UUID);
            adapter.cancelDiscovery();
            socket.connect();

            OutputStream output = socket.getOutputStream();
            output.write(bytes);
            output.flush();
            call.resolve();
        } catch (Exception exception) {
            call.reject(
                "Gagal mencetak lewat Bluetooth: " + exception.getMessage(),
                "print_failed",
                exception
            );
        } finally {
            if (socket != null) {
                try {
                    socket.close();
                } catch (Exception ignored) {
                }
            }
        }
    }

    private void printUsb(PluginCall call, byte[] bytes) {
        UsbManager usbManager = getUsbManager();
        if (usbManager == null) {
            call.reject("Perangkat ini tidak mendukung USB host.", "usb_unavailable");
            return;
        }

        UsbDevice device = findUsbDevice(usbManager, call.getString("id"));
        if (device == null) {
            call.reject("Printer USB tidak ditemukan atau sudah tercabut.", "device_not_found");
            return;
        }

        if (!usbManager.hasPermission(device)) {
            requestUsbPermission(call, usbManager, device, bytes);
            return;
        }

        new Thread(() -> sendUsbBytes(call, usbManager, device, bytes)).start();
    }

    private void requestUsbPermission(
        PluginCall call,
        UsbManager usbManager,
        UsbDevice device,
        byte[] bytes
    ) {
        int flags = Build.VERSION.SDK_INT >= Build.VERSION_CODES.S ? PendingIntent.FLAG_MUTABLE : 0;
        Intent permissionIntent = new Intent(ACTION_USB_PERMISSION)
            .setPackage(getContext().getPackageName());
        PendingIntent pendingIntent = PendingIntent.getBroadcast(
            getContext(),
            0,
            permissionIntent,
            flags
        );

        BroadcastReceiver receiver = new BroadcastReceiver() {
            @Override
            public void onReceive(Context context, Intent intent) {
                if (!ACTION_USB_PERMISSION.equals(intent.getAction())) {
                    return;
                }

                getContext().unregisterReceiver(this);
                boolean granted = intent.getBooleanExtra(UsbManager.EXTRA_PERMISSION_GRANTED, false);
                if (!granted) {
                    call.reject("Izin akses printer USB ditolak.", "permission_denied");
                    return;
                }

                new Thread(() -> sendUsbBytes(call, usbManager, device, bytes)).start();
            }
        };

        IntentFilter filter = new IntentFilter(ACTION_USB_PERMISSION);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            getContext().registerReceiver(receiver, filter, Context.RECEIVER_NOT_EXPORTED);
        } else {
            getContext().registerReceiver(receiver, filter);
        }

        usbManager.requestPermission(device, pendingIntent);
    }

    private void sendUsbBytes(
        PluginCall call,
        UsbManager usbManager,
        UsbDevice device,
        byte[] bytes
    ) {
        UsbTarget target = findUsbTarget(device);
        if (target == null) {
            call.reject("Endpoint printer USB tidak ditemukan.", "endpoint_not_found");
            return;
        }

        UsbDeviceConnection connection = null;
        try {
            connection = usbManager.openDevice(device);
            if (connection == null) {
                call.reject("Koneksi printer USB tidak dapat dibuka.", "open_failed");
                return;
            }

            if (!connection.claimInterface(target.usbInterface, true)) {
                call.reject("Interface printer USB tidak dapat digunakan.", "claim_failed");
                return;
            }

            int sentBytes = 0;
            while (sentBytes < bytes.length) {
                int chunkBytes = Math.min(USB_TRANSFER_CHUNK_BYTES, bytes.length - sentBytes);
                byte[] chunk = Arrays.copyOfRange(bytes, sentBytes, sentBytes + chunkBytes);
                int transferredBytes = connection.bulkTransfer(
                    target.endpoint,
                    chunk,
                    chunk.length,
                    USB_TRANSFER_TIMEOUT_MILLISECONDS
                );

                if (transferredBytes <= 0) {
                    call.reject("Transfer data ke printer USB gagal.", "transfer_failed");
                    return;
                }

                sentBytes += transferredBytes;
            }

            call.resolve();
        } catch (Exception exception) {
            call.reject(
                "Gagal mencetak lewat USB: " + exception.getMessage(),
                "print_failed",
                exception
            );
        } finally {
            if (connection != null) {
                connection.releaseInterface(target.usbInterface);
                connection.close();
            }
        }
    }

    private UsbTarget findUsbTarget(UsbDevice device) {
        for (int pass = 0; pass < 2; pass++) {
            for (int interfaceIndex = 0; interfaceIndex < device.getInterfaceCount(); interfaceIndex++) {
                UsbInterface usbInterface = device.getInterface(interfaceIndex);
                if (pass == 0 && usbInterface.getInterfaceClass() != UsbConstants.USB_CLASS_PRINTER) {
                    continue;
                }

                for (int endpointIndex = 0; endpointIndex < usbInterface.getEndpointCount(); endpointIndex++) {
                    UsbEndpoint endpoint = usbInterface.getEndpoint(endpointIndex);
                    if (
                        endpoint.getDirection() == UsbConstants.USB_DIR_OUT &&
                        endpoint.getType() == UsbConstants.USB_ENDPOINT_XFER_BULK
                    ) {
                        return new UsbTarget(usbInterface, endpoint);
                    }
                }
            }
        }

        return null;
    }

    private UsbDevice findUsbDevice(UsbManager usbManager, String printerId) {
        if (printerId == null || printerId.isBlank()) {
            return null;
        }

        for (UsbDevice device : usbManager.getDeviceList().values()) {
            if (device.getDeviceName().equals(printerId)) {
                return device;
            }
        }

        return null;
    }

    private UsbManager getUsbManager() {
        return (UsbManager) getContext().getSystemService(Context.USB_SERVICE);
    }

    private String usbDeviceName(UsbDevice device) {
        String productName = device.getProductName();
        if (productName != null && !productName.isBlank()) {
            return productName;
        }

        return "USB " + Integer.toHexString(device.getVendorId()) + ":" +
            Integer.toHexString(device.getProductId());
    }

    private byte[] decodePrintData(PluginCall call) {
        String encodedData = call.getString("data");
        if (encodedData == null || encodedData.isBlank()) {
            return null;
        }

        try {
            return Base64.decode(encodedData, Base64.DEFAULT);
        } catch (IllegalArgumentException exception) {
            return null;
        }
    }

    private boolean needsBluetoothPermission() {
        return Build.VERSION.SDK_INT >= Build.VERSION_CODES.S &&
            getPermissionState("bluetooth") != PermissionState.GRANTED;
    }

    private static final class UsbTarget {
        private final UsbInterface usbInterface;
        private final UsbEndpoint endpoint;

        private UsbTarget(UsbInterface usbInterface, UsbEndpoint endpoint) {
            this.usbInterface = usbInterface;
            this.endpoint = endpoint;
        }
    }
}
