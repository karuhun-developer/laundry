package com.karuhundeveloper.laundryhub;

import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.net.Uri;

import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

@CapacitorPlugin(name = "ExternalLink")
public class ExternalLinkPlugin extends Plugin {
    @PluginMethod
    public void open(PluginCall call) {
        String value = call.getString("url");
        if (value == null || value.isBlank()) {
            call.reject("Tautan WhatsApp kosong.", "missing_url");
            return;
        }

        Uri uri = Uri.parse(value);
        if (
            !"https".equals(uri.getScheme()) ||
            !"wa.me".equals(uri.getHost()) ||
            uri.getPort() != -1 ||
            uri.getUserInfo() != null
        ) {
            call.reject("Tautan eksternal tidak diizinkan.", "url_not_allowed");
            return;
        }

        Intent intent = new Intent(Intent.ACTION_VIEW, uri);
        try {
            getActivity().startActivity(intent);
            call.resolve();
        } catch (ActivityNotFoundException exception) {
            call.reject("Tidak ada aplikasi yang dapat membuka WhatsApp.", "handler_unavailable", exception);
        }
    }
}
