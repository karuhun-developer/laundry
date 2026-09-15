package com.karuhundeveloper.laundryhub;

import android.app.Activity;
import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.net.Uri;
import android.util.Base64;

import androidx.activity.result.ActivityResult;

import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.ActivityCallback;
import com.getcapacitor.annotation.CapacitorPlugin;

import java.io.OutputStream;
import java.util.regex.Pattern;

@CapacitorPlugin(name = "QrisDownload")
public class QrisDownloadPlugin extends Plugin {
    private static final String SVG_DATA_URI_PREFIX = "data:image/svg+xml;base64,";
    private static final int SVG_SIZE_LIMIT_BYTES = 2_097_152;
    private static final Pattern SVG_FILENAME_PATTERN = Pattern.compile(
        "^[A-Za-z0-9][A-Za-z0-9._-]{0,119}\\.svg$"
    );
    private static final Pattern BASE64_PATTERN = Pattern.compile(
        "^[A-Za-z0-9+/]+={0,2}$"
    );

    @PluginMethod
    public void save(PluginCall call) {
        String dataUri = call.getString("dataUri");
        String filename = call.getString("filename");

        if (!isValidDataUri(dataUri)) {
            call.reject("Data QRIS tidak valid.", "invalid_data");
            return;
        }

        if (filename == null || !SVG_FILENAME_PATTERN.matcher(filename).matches()) {
            call.reject("Nama berkas QRIS tidak valid.", "invalid_filename");
            return;
        }

        Intent intent = new Intent(Intent.ACTION_CREATE_DOCUMENT)
            .addCategory(Intent.CATEGORY_OPENABLE)
            .setType("image/svg+xml")
            .putExtra(Intent.EXTRA_TITLE, filename);

        try {
            startActivityForResult(call, intent, "saveDocumentResult");
        } catch (ActivityNotFoundException exception) {
            call.reject("Penyimpanan dokumen Android tidak tersedia.", "document_picker_unavailable", exception);
        }
    }

    @ActivityCallback
    private void saveDocumentResult(PluginCall call, ActivityResult activityResult) {
        if (call == null) {
            return;
        }

        if (activityResult.getResultCode() == Activity.RESULT_CANCELED) {
            JSObject result = new JSObject();
            result.put("status", "cancelled");
            call.resolve(result);
            return;
        }

        Intent resultIntent = activityResult.getData();
        Uri destination = resultIntent == null ? null : resultIntent.getData();
        if (activityResult.getResultCode() != Activity.RESULT_OK || destination == null) {
            call.reject("Lokasi penyimpanan QRIS tidak tersedia.", "destination_unavailable");
            return;
        }

        try {
            byte[] svg = decodeSvg(call.getString("dataUri"));
            try (OutputStream output = getContext().getContentResolver().openOutputStream(destination, "w")) {
                if (output == null) {
                    throw new IllegalStateException("Tujuan penyimpanan tidak dapat dibuka.");
                }

                output.write(svg);
                output.flush();
            }

            JSObject result = new JSObject();
            result.put("status", "saved");
            call.resolve(result);
        } catch (Exception exception) {
            call.reject("QRIS gagal disimpan.", "save_failed", exception);
        }
    }

    private boolean isValidDataUri(String dataUri) {
        if (dataUri == null || !dataUri.startsWith(SVG_DATA_URI_PREFIX)) {
            return false;
        }

        String encodedSvg = dataUri.substring(SVG_DATA_URI_PREFIX.length());

        return !encodedSvg.isEmpty() &&
            encodedSvg.length() <= ((SVG_SIZE_LIMIT_BYTES + 2) / 3) * 4 &&
            encodedSvg.length() % 4 == 0 &&
            BASE64_PATTERN.matcher(encodedSvg).matches();
    }

    private byte[] decodeSvg(String dataUri) {
        if (!isValidDataUri(dataUri)) {
            throw new IllegalArgumentException("Data QRIS tidak valid.");
        }

        String encodedSvg = dataUri.substring(SVG_DATA_URI_PREFIX.length());
        byte[] svg = Base64.decode(encodedSvg, Base64.DEFAULT);
        if (svg.length == 0 || svg.length > SVG_SIZE_LIMIT_BYTES) {
            throw new IllegalArgumentException("Ukuran QRIS tidak valid.");
        }

        return svg;
    }
}
