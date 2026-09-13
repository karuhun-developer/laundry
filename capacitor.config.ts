import type { CapacitorConfig } from '@capacitor/cli';

const serverUrl = process.env.CAPACITOR_SERVER_URL;

const config = {
    appId: 'com.karuhundeveloper.laundryhub',
    appName: 'LaundryHub',
    webDir: 'capacitor-web',
    ...(serverUrl
        ? {
              server: {
                  url: serverUrl,
                  cleartext: serverUrl.startsWith('http://'),
              },
          }
        : {}),
} satisfies CapacitorConfig;

export default config;
