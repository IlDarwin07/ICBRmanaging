# Modificare il sito vetrina

Accedi con un utente di ruolo `admin`, poi scegli **Sito vetrina** nel menu.
Gli altri ruoli del gestionale non possono aprire o salvare questa sezione.

## Cosa si può cambiare

- Titoli, testo e pulsante di apertura; stagione.
- Racconto del club, testi e nomi delle tre tessere.
- Indirizzo, città, Google Maps, invito a visitare la sede e contatti.
- Profili Instagram, Facebook, YouTube e TikTok nel footer. Un campo vuoto
  nasconde quel social; sono accettati solo URL HTTPS della piattaforma corretta.
- Le tre fotografie, selezionandole dall’archivio locale oppure caricando JPEG,
  PNG o WebP, massimo 6 MB e 24 megapixel, minimo 640×360 px.

**Salva e aggiorna il sito** pubblica subito i contenuti. Il ripristino richiede
conferma e riporta ai testi/foto iniziali versionati nella repository.
Un controllo di revisione blocca il salvataggio se un altro admin ha modificato
il sito nel frattempo. I campi accettano testo, non codice HTML eseguibile.

## Installazione su Linux Mint / XAMPP

Non serve una nuova migrazione SQL per l’editor. Il contenuto personalizzato è
salvato in `storage/site/content.json`, fuori dalla directory pubblica, con lock
e sostituzione atomica del file. Se il file non esiste la home usa i contenuti
versionati in `config/site_defaults.php`.

Nel percorso XAMPP standard, Apache viene eseguito come `daemon`. Verifica
il tuo utente di servizio prima di assegnare questi permessi:

```bash
cd /opt/lampp/htdocs/ICBRmanaging
sudo install -d -o daemon -g daemon -m 0750 storage/site
sudo chown daemon:daemon public/assets/images
sudo chmod 0755 public/assets/images
```

Non usare permessi `777`. Se l’utente di Apache è diverso, sostituire `daemon`.
Per consentire tre immagini da 6 MB in un salvataggio, la configurazione PHP
deve consentire `upload_max_filesize=6M` o maggiore e `post_max_size=20M` o maggiore;
riavviare Apache dopo aver cambiato `php.ini`.

Eseguire il backup sia di `storage/site` sia di `public/assets/images`, oltre
al database del gestionale. Le personalizzazioni non vengono sovrascritte da
`git pull`, perché il file JSON non è tracciato. Le foto caricate dall’admin
restano sul server e vanno incluse esplicitamente in Git se si desidera
versionarle; il salvataggio dal sito non esegue push automatici.

## Riferimenti editoriali e social

Profilo usato per il tono e per il collegamento Instagram:
https://www.instagram.com/inter.club.brindisi.jz/

I nomi iniziali Fedelissimo, Sostenitore e Fuori sede riprendono la campagna
pubblicata su quel profilo. Non sono state inventate quote o promesse di biglietti.
Facebook, YouTube e TikTok restano vuoti finché il club non inserisce i propri
collegamenti effettivi. Le foto d’archivio e i crediti sono descritti in
`public/assets/images/README.md`.

Il database di produzione non viene modificato da questa funzionalità.
