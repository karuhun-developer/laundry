import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const projectFile = (path: string): string => readFileSync(resolve(process.cwd(), path), 'utf8');
const pluginSource = projectFile(
    'android/app/src/main/java/com/karuhundeveloper/laundryhub/AppUpdatePlugin.java',
);
const mainActivitySource = projectFile(
    'android/app/src/main/java/com/karuhundeveloper/laundryhub/MainActivity.java',
);
const manifestSource = projectFile('android/app/src/main/AndroidManifest.xml');
const filePathsSource = projectFile('android/app/src/main/res/xml/file_paths.xml');

describe('Android app updater source contract', () => {
    it('registers the updater and declares installation access', () => {
        expect(mainActivitySource).toContain('registerPlugin(AppUpdatePlugin.class)');
        expect(manifestSource).toContain('android.permission.REQUEST_INSTALL_PACKAGES');
        expect(filePathsSource).toContain('<cache-path name="app_updates" path="updates/" />');
    });

    it('accepts only same-origin HTTPS downloads with a release APK filename', () => {
        expect(pluginSource).toContain('"https".equalsIgnoreCase(downloadUri.getScheme())');
        expect(pluginSource).toContain(
            'downloadUri.getHost().equalsIgnoreCase(serverUri.getHost())',
        );
        expect(pluginSource).toContain('effectivePort(downloadUri) == effectivePort(serverUri)');
        expect(pluginSource).toContain('APK_FILENAME_PATTERN.matcher(filename).matches()');
    });

    it('downloads atomically and verifies size and SHA-256 before installation', () => {
        expect(pluginSource).toContain('filename + ".part"');
        expect(pluginSource).toContain('APK_SIZE_LIMIT_BYTES = 104_857_600L');
        expect(pluginSource).toContain('MessageDigest.getInstance("SHA-256")');
        expect(pluginSource).toContain('if (!isVerifiedApk(partialFile, expectedChecksum))');
        expect(pluginSource).toContain('partialFile.renameTo(apkFile)');
    });

    it('handles unknown-source permission and opens the verified APK with FileProvider', () => {
        expect(pluginSource).toContain('canRequestPackageInstalls()');
        expect(pluginSource).toContain('Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES');
        expect(pluginSource).toContain('FileProvider.getUriForFile');
        expect(pluginSource).toContain('Intent.FLAG_GRANT_READ_URI_PERMISSION');
    });
});
