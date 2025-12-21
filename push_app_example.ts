// In app.component.ts (per Ionic/Angular)
import { Component } from '@angular/core';
import { PushNotifications } from '@capacitor/push-notifications';
import { Capacitor } from '@capacitor/core';

@Component({
  selector: 'app-root',
  templateUrl: 'app.component.html',
  styleUrls: ['app.component.scss'],
})
export class AppComponent {
  constructor() {
    this.initializePushNotifications();
  }

  async initializePushNotifications() {
    // Richiedi permessi
    const permission = await PushNotifications.requestPermissions();
    if (permission.receive === 'granted') {
      // Registra per push
      await PushNotifications.register();
    }

    // Listener per registrazione token
    PushNotifications.addListener('registration', (token) => {
      console.log('Token FCM:', token.value);
      // Invia token al server
      this.sendTokenToServer(token.value);
    });

    // Listener per errori
    PushNotifications.addListener('registrationError', (error) => {
      console.error('Errore registrazione push:', error);
    });

    // Listener per push ricevute
    PushNotifications.addListener('pushNotificationReceived', (notification) => {
      console.log('Push ricevuta:', notification);
      // Gestisci notifica (es. mostra alert)
    });
  }

  async sendTokenToServer(token: string) {
    try {
      const response = await fetch('https://flex.coresuite.it/push/subscribe.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          token: token,
          platform: Capacitor.getPlatform() // 'ios' o 'android'
        }),
      });
      const result = await response.json();
      console.log('Token inviato:', result);
    } catch (error) {
      console.error('Errore invio token:', error);
    }
  }
}