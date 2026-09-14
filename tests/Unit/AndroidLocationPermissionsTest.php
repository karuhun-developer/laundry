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
