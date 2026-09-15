package com.karuhundeveloper.laundryhub;

import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.content.pm.PackageInfo;
import android.net.Uri;
import android.os.Build;
import android.provider.Settings;

import androidx.core.content.FileProvider;

import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.getcapacitor.util.InternalUtils;

import java.io.BufferedInputStream;
import java.io.BufferedOutputStream;
import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.security.MessageDigest;
import java.util.Locale;
import java.util.regex.Pattern;

@CapacitorPlugin(name = "AppUpdate")
public class AppUpdatePlugin extends Plugin {
    private static final long APK_SIZE_LIMIT_BYTES = 104_857_600L;
    private static final int BUFFER_SIZE_BYTES = 16_384;
    private static final Pattern APK_FILENAME_PATTERN = Pattern.compile(
        "^laundryhub-v[0-9]+\\.[0-9]+\\.[0-9]+\\.apk$"
    );
    private static final Pattern SHA256_PATTERN = Pattern.compile("^[a-fA-F0-9]{64}$");

    @PluginMethod
    public void getInstalledVersion(PluginCall call) {
        try {
            PackageInfo packageInfo = InternalUtils.getPackageInfo(
                getContext().getPackageManager(),
                getContext().getPackageName()
            );
            String version = packageInfo.versionName;
            if (version == null || version.isBlank()) {
                call.reject("Versi aplikasi tidak tersedia.", "version_unavailable");
                return;
            }

            JSObject result = new JSObject();
            result.put("version", version);
            call.resolve(result);
        } catch (Exception exception) {
            call.reject("Versi aplikasi tidak dapat dibaca.", "version_unavailable", exception);
        }
    }

    @PluginMethod
    public void downloadAndInstall(PluginCall call) {
        String downloadUrl = call.getString("url");
        String filename = call.getString("filename");
        String sha256 = call.getString("sha256");

        if (!isAllowedDownloadUrl(downloadUrl)) {
            call.reject("Alamat unduhan pembaruan tidak diizinkan.", "url_not_allowed");
            return;
        }

        if (filename == null || !APK_FILENAME_PATTERN.matcher(filename).matches()) {
            call.reject("Nama berkas pembaruan tidak valid.", "invalid_filename");
            return;
        }

        if (sha256 == null || !SHA256_PATTERN.matcher(sha256).matches()) {
            call.reject("Checksum pembaruan tidak valid.", "invalid_checksum");
            return;
        }

        if (requiresInstallPermission()) {
            requestInstallPermission(call);
            return;
        }

        String normalizedChecksum = sha256.toLowerCase(Locale.ROOT);
        new Thread(() -> downloadAndLaunchInstaller(call, downloadUrl, filename, normalizedChecksum)).start();
    }

    private boolean isAllowedDownloadUrl(String value) {
        if (value == null || value.isBlank()) {
            return false;
        }

        String serverUrl = getBridge().getServerUrl();
        if (serverUrl == null || serverUrl.isBlank()) {
            return false;
        }

        Uri downloadUri = Uri.parse(value);
        Uri serverUri = Uri.parse(serverUrl);

        return "https".equalsIgnoreCase(downloadUri.getScheme()) &&
            downloadUri.getUserInfo() == null &&
            downloadUri.getHost() != null &&
            serverUri.getHost() != null &&
            downloadUri.getHost().equalsIgnoreCase(serverUri.getHost()) &&
            effectivePort(downloadUri) == effectivePort(serverUri) &&
            downloadUri.getScheme().equalsIgnoreCase(serverUri.getScheme());
    }

    private int effectivePort(Uri uri) {
        if (uri.getPort() != -1) {
            return uri.getPort();
        }

        return "https".equalsIgnoreCase(uri.getScheme()) ? 443 : 80;
    }

    private boolean requiresInstallPermission() {
        return Build.VERSION.SDK_INT >= Build.VERSION_CODES.O &&
            !getContext().getPackageManager().canRequestPackageInstalls();
    }

    private void requestInstallPermission(PluginCall call) {
        Intent intent = new Intent(
            Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES,
            Uri.parse("package:" + getContext().getPackageName())
        );

        try {
            getActivity().startActivity(intent);
            JSObject result = new JSObject();
            result.put("status", "permission_required");
            call.resolve(result);
        } catch (ActivityNotFoundException exception) {
            call.reject("Pengaturan izin pemasangan tidak dapat dibuka.", "permission_settings_unavailable", exception);
        }
    }

    private void downloadAndLaunchInstaller(
        PluginCall call,
        String downloadUrl,
        String filename,
        String expectedChecksum
    ) {
        File updateDirectory = new File(getContext().getCacheDir(), "updates");
        File apkFile = new File(updateDirectory, filename);
        File partialFile = new File(updateDirectory, filename + ".part");

        try {
            if (!updateDirectory.exists() && !updateDirectory.mkdirs()) {
                throw new IllegalStateException("Direktori pembaruan tidak dapat dibuat.");
            }

            if (isVerifiedApk(apkFile, expectedChecksum)) {
                launchInstaller(call, apkFile);
                return;
            }

            if (apkFile.exists() && !apkFile.delete()) {
                throw new IllegalStateException("Berkas pembaruan lama tidak dapat diganti.");
            }
            if (partialFile.exists() && !partialFile.delete()) {
                throw new IllegalStateException("Unduhan pembaruan lama tidak dapat dibersihkan.");
            }

            download(downloadUrl, partialFile);
            if (!isVerifiedApk(partialFile, expectedChecksum)) {
                partialFile.delete();
                call.reject("Checksum APK tidak cocok. Unduhan dibatalkan.", "checksum_mismatch");
                return;
            }

            if (!partialFile.renameTo(apkFile)) {
                partialFile.delete();
                throw new IllegalStateException("Berkas pembaruan tidak dapat disiapkan.");
            }

            launchInstaller(call, apkFile);
        } catch (Exception exception) {
            partialFile.delete();
            call.reject("Pembaruan gagal diunduh: " + exception.getMessage(), "download_failed", exception);
        }
    }

    private void download(String downloadUrl, File destination) throws Exception {
        HttpURLConnection connection = (HttpURLConnection) new URL(downloadUrl).openConnection();
        connection.setConnectTimeout(10_000);
        connection.setReadTimeout(60_000);
        connection.setInstanceFollowRedirects(false);
        connection.setRequestProperty("Accept", "application/vnd.android.package-archive");

        try {
            int statusCode = connection.getResponseCode();
            if (statusCode != HttpURLConnection.HTTP_OK) {
                throw new IllegalStateException("Server pembaruan merespons HTTP " + statusCode + ".");
            }

            long contentLength = connection.getContentLengthLong();
            if (contentLength > APK_SIZE_LIMIT_BYTES) {
                throw new IllegalStateException("Ukuran APK melebihi batas 100 MB.");
            }

            try (
                InputStream input = new BufferedInputStream(connection.getInputStream());
                BufferedOutputStream output = new BufferedOutputStream(new FileOutputStream(destination))
            ) {
                byte[] buffer = new byte[BUFFER_SIZE_BYTES];
                long downloadedBytes = 0;
                int readBytes;

                while ((readBytes = input.read(buffer)) != -1) {
                    downloadedBytes += readBytes;
                    if (downloadedBytes > APK_SIZE_LIMIT_BYTES) {
                        throw new IllegalStateException("Ukuran APK melebihi batas 100 MB.");
                    }

                    output.write(buffer, 0, readBytes);
                }
            }
        } finally {
            connection.disconnect();
        }
    }

    private boolean isVerifiedApk(File file, String expectedChecksum) throws Exception {
        if (!file.isFile() || file.length() <= 0 || file.length() > APK_SIZE_LIMIT_BYTES) {
            return false;
        }

        MessageDigest digest = MessageDigest.getInstance("SHA-256");
        try (InputStream input = new BufferedInputStream(new FileInputStream(file))) {
            byte[] buffer = new byte[BUFFER_SIZE_BYTES];
            int readBytes;
            while ((readBytes = input.read(buffer)) != -1) {
                digest.update(buffer, 0, readBytes);
            }
        }

        return expectedChecksum.equals(toHex(digest.digest()));
    }

    private String toHex(byte[] bytes) {
        StringBuilder value = new StringBuilder(bytes.length * 2);
        for (byte current : bytes) {
            value.append(String.format(Locale.ROOT, "%02x", current & 0xff));
        }

        return value.toString();
    }

    private void launchInstaller(PluginCall call, File apkFile) {
        getActivity().runOnUiThread(() -> {
            try {
                Uri apkUri = FileProvider.getUriForFile(
                    getContext(),
                    getContext().getPackageName() + ".fileprovider",
                    apkFile
                );
                Intent intent = new Intent(Intent.ACTION_VIEW)
                    .setDataAndType(apkUri, "application/vnd.android.package-archive")
                    .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);

                getActivity().startActivity(intent);
                JSObject result = new JSObject();
                result.put("status", "install_started");
                call.resolve(result);
            } catch (ActivityNotFoundException exception) {
                call.reject("Installer Android tidak tersedia.", "installer_unavailable", exception);
            } catch (Exception exception) {
                call.reject("Berkas pembaruan tidak dapat dibuka.", "installer_failed", exception);
            }
        });
    }
}
