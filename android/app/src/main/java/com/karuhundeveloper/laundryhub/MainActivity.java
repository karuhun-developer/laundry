package com.karuhundeveloper.laundryhub;

import android.os.Bundle;

import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(AppUpdatePlugin.class);
        registerPlugin(ExternalLinkPlugin.class);
        registerPlugin(QrisDownloadPlugin.class);
        registerPlugin(ThermalPrinterPlugin.class);
        super.onCreate(savedInstanceState);
    }
}
