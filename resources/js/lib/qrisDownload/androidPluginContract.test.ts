import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const projectFile = (path: string): string => readFileSync(resolve(process.cwd(), path), 'utf8');
const pluginSource = projectFile(
    'android/app/src/main/java/com/karuhundeveloper/laundryhub/QrisDownloadPlugin.java',
);
const mainActivitySource = projectFile(
    'android/app/src/main/java/com/karuhundeveloper/laundryhub/MainActivity.java',
);

describe('Android QRIS download source contract', () => {
    it('registers the native QRIS document saver', () => {
        expect(mainActivitySource).toContain('registerPlugin(QrisDownloadPlugin.class)');
        expect(pluginSource).toContain('@CapacitorPlugin(name = "QrisDownload")');
    });

    it('uses the Android document picker without storage permissions', () => {
        expect(pluginSource).toContain('Intent.ACTION_CREATE_DOCUMENT');
        expect(pluginSource).toContain('.setType("image/svg+xml")');
        expect(pluginSource).toContain('@ActivityCallback');
        expect(pluginSource).toContain('getContentResolver().openOutputStream(destination, "w")');
    });

    it('accepts only bounded base64 SVG data and safe SVG filenames', () => {
        expect(pluginSource).toContain('SVG_DATA_URI_PREFIX');
        expect(pluginSource).toContain('SVG_SIZE_LIMIT_BYTES = 2_097_152');
        expect(pluginSource).toContain('SVG_FILENAME_PATTERN.matcher(filename).matches()');
        expect(pluginSource).toContain('BASE64_PATTERN.matcher(encodedSvg).matches()');
    });

    it('reports document-picker cancellation without a false save failure', () => {
        expect(pluginSource).toContain('Activity.RESULT_CANCELED');
        expect(pluginSource).toContain('result.put("status", "cancelled")');
    });
});
