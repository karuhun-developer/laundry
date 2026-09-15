import { registerPlugin } from '@capacitor/core';

export type NativeAppUpdateInstallResult = {
    status: 'install_started' | 'permission_required';
};

export interface NativeAppUpdatePlugin {
    getInstalledVersion(): Promise<{ version: string }>;
    downloadAndInstall(options: {
        readonly url: string;
        readonly filename: string;
        readonly sha256: string;
    }): Promise<NativeAppUpdateInstallResult>;
}

export const nativeAppUpdate = registerPlugin<NativeAppUpdatePlugin>('AppUpdate');
