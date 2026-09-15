import { registerPlugin } from '@capacitor/core';

export type NativeQrisDownloadResult = {
    status: 'saved' | 'cancelled';
};

export interface NativeQrisDownloadPlugin {
    save(options: {
        readonly dataUri: string;
        readonly filename: string;
    }): Promise<NativeQrisDownloadResult>;
}

export const nativeQrisDownload = registerPlugin<NativeQrisDownloadPlugin>('QrisDownload');
