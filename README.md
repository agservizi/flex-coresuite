# Flex Coresuite

Sistema di gestione segnalazioni e opportunity.

## Installazione PWA

L'app supporta PWA per installazione su dispositivi mobili.

## Build come App Nativa con Capacitor

Per un'esperienza più nativa, usa Capacitor per generare app iOS/Android.

### Prerequisiti

- Node.js e npm
- Xcode (per iOS)
- Android Studio (per Android)

### Setup

1. Installa dipendenze:
   ```bash
   npm install
   ```

2. Sincronizza Capacitor:
   ```bash
   npm run sync
   ```

3. Aggiungi piattaforme:
   ```bash
   npm run add:ios
   npm run add:android
   ```

4. Apri in IDE:
   ```bash
   npm run open:ios
   npm run open:android
   ```

5. Build e distribuisci dall'IDE.

### Sviluppo

Per sviluppo locale:
```bash
npm start
```

Questo avvia un server locale per testare la PWA.