import { registerPlugin } from '@capacitor/core';

export interface NativeExternalLinkPlugin {
    open(options: { readonly url: string }): Promise<void>;
}

export const nativeExternalLink = registerPlugin<NativeExternalLinkPlugin>('ExternalLink');
