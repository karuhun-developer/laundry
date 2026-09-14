<?php

test('the Android application declares location permissions', function () {
    $manifest = file_get_contents(dirname(__DIR__, 2).'/android/app/src/main/AndroidManifest.xml');

    expect($manifest)
        ->not->toBeFalse()
        ->toContain('android.permission.ACCESS_COARSE_LOCATION')
        ->toContain('android.permission.ACCESS_FINE_LOCATION')
        ->toContain('android.hardware.location.gps')
        ->toContain('android:required="false"');
});

test('the Android application requests Bluetooth and legacy download permissions', function () {
    $projectRoot = dirname(__DIR__, 2);
    $manifest = file_get_contents($projectRoot.'/android/app/src/main/AndroidManifest.xml');
    $printerPlugin = file_get_contents(
        $projectRoot.'/android/app/src/main/java/com/karuhundeveloper/laundryhub/ThermalPrinterPlugin.java',
    );

    expect($manifest)
        ->not->toBeFalse()
        ->toContain('android.permission.BLUETOOTH_SCAN')
        ->toContain('android.permission.BLUETOOTH_CONNECT')
        ->toContain('android.permission.READ_EXTERNAL_STORAGE')
        ->toContain('android.permission.WRITE_EXTERNAL_STORAGE');

    expect($printerPlugin)
        ->not->toBeFalse()
        ->toContain('Manifest.permission.BLUETOOTH_SCAN')
        ->toContain('Manifest.permission.BLUETOOTH_CONNECT');
});

test('the Android application enumerates USB printers and requests device access', function () {
    $projectRoot = dirname(__DIR__, 2);
    $manifest = file_get_contents($projectRoot.'/android/app/src/main/AndroidManifest.xml');
    $printerPlugin = file_get_contents(
        $projectRoot.'/android/app/src/main/java/com/karuhundeveloper/laundryhub/ThermalPrinterPlugin.java',
    );

    expect($manifest)
        ->not->toBeFalse()
        ->toContain('android.hardware.usb.host');

    expect($printerPlugin)
        ->not->toBeFalse()
        ->toContain('public void listUsb(PluginCall call)')
        ->toContain('usbManager.getDeviceList().values()')
        ->toContain('usbManager.requestPermission(device, pendingIntent)');
});
