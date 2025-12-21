# Capacitor Features Implementation

Questo documento descrive le funzionalità avanzate implementate per l'app Capacitor di Flex Coresuite.

## Funzionalità Implementate

### 🔄 Cache Management
- **Svuotamento automatico**: La cache viene svuotata automaticamente all'avvio dell'app
- **Service Worker avanzato**: Gestione intelligente della cache con versioning
- **Fallback offline**: Supporto per funzionamento offline limitato

### 📷 Fotocamera
- **Integrazione nativa**: Accesso alla fotocamera del dispositivo
- **Modifica immagini**: Possibilità di editing delle foto catturate
- **Salvataggio galleria**: Opzione per salvare automaticamente nella galleria

### 📍 Geolocalizzazione
- **Posizione corrente**: Ottenimento posizione GPS precisa
- **Watch position**: Monitoraggio continuo della posizione
- **Gestione permessi**: Richiesta automatica dei permessi di localizzazione

### 🔗 Condivisione
- **Condividi contenuto**: Condivisione URL, testo e titoli tramite app native
- **Fallback clipboard**: Copia negli appunti se condivisione non disponibile
- **Integrazione social**: Supporto per tutte le app di condivisione installate

### 🔗 Deep Linking
- **Gestione URL**: Supporto per deep links che aprono pagine specifiche
- **Stato app**: Monitoraggio dello stato attivo/inattivo dell'app
- **Back button**: Gestione del pulsante indietro nativo

### 🎨 UI/UX Nativa
- **Splash Screen**: Schermata di avvio personalizzata con logo
- **Status Bar**: Personalizzazione della barra di stato
- **Permessi**: Gestione granulare dei permessi richiesti

## Utilizzo nelle Pagine

### Pulsanti nell'Header
Quando l'app è eseguita su dispositivo nativo, nell'header appaiono pulsanti aggiuntivi:

- **📷 Fotocamera**: Apre la fotocamera per scattare foto
- **📍 Geolocalizzazione**: Ottiene e mostra la posizione corrente
- **🔗 Condividi**: Condivide il link dell'app corrente

### API JavaScript Disponibili

```javascript
// Ottieni posizione corrente
const position = await window.getCurrentPosition();
// Risultato: { latitude, longitude, accuracy }

// Monitora posizione
const watchId = await window.watchPosition((position) => {
  console.log('Nuova posizione:', position);
});

// Ferma monitoraggio
window.clearPositionWatch(watchId);

// Condividi contenuto
await window.shareContent({
  title: 'Flex Coresuite',
  text: 'Scopri la nostra app',
  url: 'https://flex.coresuite.it'
});

// Info app
const appInfo = await window.getAppInfo();
const appState = await window.getAppState();
```

## Configurazione

### capacitor.config.json
Il file di configurazione include:
- Impostazioni splash screen
- Configurazione status bar
- Permessi fotocamera e geolocalizzazione
- Impostazioni specifiche per iOS e Android

### Plugin Installati
- `@capacitor/camera`: Fotocamera e galleria
- `@capacitor/geolocation`: Geolocalizzazione GPS
- `@capacitor/share`: Condivisione nativa
- `@capacitor/browser`: Browser integrato
- `@capacitor/splash-screen`: Splash screen
- `@capacitor/status-bar`: Status bar
- `@capacitor/app`: Gestione app
- `@capacitor/preferences`: Memorizzazione preferenze
- `@capacitor/filesystem`: File system
- `@capacitor/device`: Info dispositivo

## Testing

Per testare le funzionalità:

1. **Build e sync**: `npm run sync`
2. **Apri su dispositivo**: `npm run open:ios` o `npm run open:android`
3. **Test pulsanti**: Usa i pulsanti nell'header quando su app nativa
4. **Console**: Monitora i log nella console per debug

## Note di Sviluppo

- Le funzionalità sono automaticamente disabilitate quando l'app è eseguita nel browser web
- Tutti i permessi vengono richiesti automaticamente all'avvio
- Gestione errori integrata con toast notifications
- Compatibilità con Capacitor 5+ (con fallback per versioni precedenti)

## Estensioni Future

Questa implementazione fornisce una base solida per aggiungere:
- Biometria (impronta/faccia)
- Notifiche push avanzate
- Sincronizzazione dati in background
- Integrazione con contatti del dispositivo
- Supporto NFC/QR code
- Background fetch per aggiornamenti
- Modalità offline completa</content>
<parameter name="filePath">/Users/carminecavaliere/Desktop/flex coresuite/CAPACITOR_FEATURES.md