/// <reference types="node" />

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const pluginSource = readFileSync(
    resolve(
        process.cwd(),
        'android/app/src/main/java/com/karuhundeveloper/laundryhub/ExternalLinkPlugin.java',
    ),
    'utf8',
);

describe('Android WhatsApp external-link contract', () => {
    it('launches the validated https URL directly and handles only a real missing handler', () => {
        expect(pluginSource).toContain('new Intent(Intent.ACTION_VIEW, uri)');
        expect(pluginSource).toContain('getActivity().startActivity(intent)');
        expect(pluginSource).not.toContain('resolveActivity(');
        expect(pluginSource).toContain('catch (ActivityNotFoundException exception)');
        expect(pluginSource).toContain('"handler_unavailable"');
    });

    it('does not force WhatsApp through a package or custom scheme', () => {
        expect(pluginSource).not.toContain('setPackage(');
        expect(pluginSource).not.toContain('whatsapp://');
    });
});
