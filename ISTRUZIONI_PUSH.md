# Istruzioni Complete per Configurare Notifiche Push Capacitor

## Log per Debug
- **Backend**: Controlla `uploads/debug_push_log.txt` per log push.
- **App**: Console del browser/dispositivo per log Capacitor.
- **Server**: error_log di PHP per errori FCM/VAPID.

## 1. Installa il Plugin
```bash
npm install @capacitor/push-notifications
npx cap sync
```

## 2. Configurazione google-services.json (Android)
- Vai su https://console.firebase.google.com/project/flex-coresuite/settings/general
- Scarica google-services.json
- Mettilo in android/app/google-services.json

## 3. Permessi iOS
Aggiungi in ios/App/App/Info.plist:
```xml
<key>UIBackgroundModes</key>
<array>
    <string>remote-notification</string>
</array>
<key>NSCameraUsageDescription</key>
<string>Per scattare foto dei documenti</string>
<key>NSPhotoLibraryUsageDescription</key>
<string>Per accedere alle foto</string>
```

## 4. Codice App (src/app/app.component.ts)
Sostituisci il contenuto con:

```typescript
import { Component } from '@angular/core';
import { PushNotifications } from '@capacitor/push-notifications';
import { Capacitor } from '@capacitor/core';
import { AlertController } from '@ionic/angular'; // Se usi Ionic

@Component({
  selector: 'app-root',
  templateUrl: 'app.component.html',
  styleUrls: ['app.component.scss'],
})
export class AppComponent {
  constructor(private alertController: AlertController) {
    this.initializePushNotifications();
  }

  async initializePushNotifications() {
    // Richiedi permessi
    const permission = await PushNotifications.requestPermissions();
    if (permission.receive === 'granted') {
      console.log('Permessi concessi');
      await PushNotifications.register();
    } else {
      console.log('Permessi negati');
    }

    // Token registrato
    PushNotifications.addListener('registration', (token) => {
      console.log('Token FCM:', token.value);
      this.sendTokenToServer(token.value);
    });

    // Errore registrazione
    PushNotifications.addListener('registrationError', (error) => {
      console.error('Errore registrazione:', error);
    });

    // Push ricevuta
    PushNotifications.addListener('pushNotificationReceived', async (notification) => {
      console.log('Push ricevuta:', notification);
      // Mostra alert
      const alert = await this.alertController.create({
        header: notification.title,
        message: notification.body,
        buttons: ['OK']
      });
      await alert.present();
    });
  }

  async sendTokenToServer(token: string) {
    try {
      const response = await fetch('https://flex.coresuite.it/push/subscribe.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': 'dummy' // Se necessario
        },
        body: JSON.stringify({
          token: token,
          platform: Capacitor.getPlatform()
        }),
      });
      const result = await response.json();
      console.log('Risposta server:', result);
    } catch (error) {
      console.error('Errore invio token:', error);
    }
  }
}
```

## 5. Build e Test
```bash
npx cap build android
npx cap run android
# O per iOS
npx cap build ios
npx cap run ios
```

## 6. Verifica
- Apri app, controlla console per "Token FCM:".
- Vai su DB produzione, tabella push_subscriptions: dovrebbe avere token.
- Crea opportunity come segnalatore → Push arriva!

Se problemi, controlla log app e server (error_log di PHP per backend, console per app).