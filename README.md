Plugin wordpress per mostrare popup quando si preme su un link.

Specifiche richieste:

Realizza un plugin per wordpress che permetta di inserire all'interno di un paragrafo uno shortcode che apra un popup quando cliccato.
Il plugin deve sostituire allo shortcode un tag "a" o comunque un elemento inline che, tramite javascript applichi un overlay alla pagina 
e mostri al di sopra di esso un div, centrato nello schermo, di dimensioni minime 50vw x 50vh (su cellulari è bene che sia un fullscreen, 
su desktop di grandi dimensioni invece va bene che le dimensioni effettive non superino la metà del viewport).
In ogni pagina del sito wordpress possono esserci più shortcode che aprono popup, di media al massimo una decina.

Il caricamento del plugin avverrà tramite SFTP e non è necessario che sia pubblicato su una repository globale.

## Popup testuali (shortcode)

1. Crea un nuovo contenuto dal menu **Popup** (custom post type `purim-popup`) e compila titolo e corpo come per una pagina normale. Puoi usare anche immagini in evidenza o excerpt per tenere organizzati i contenuti.
2. Inserisci lo shortcode in qualunque punto dei tuoi contenuti:

   ```php
   [purim_popup id="123"]Apri popup[/purim_popup]
   ```

   In alternativa puoi usare lo slug del popup:

   ```php
   [purim_popup slug="nome-del-popup" text="Scopri di più"]
   ```

   - L'attributo `text` è facoltativo: se omesso viene usato il contenuto compreso tra i tag di apertura/chiusura dello shortcode; se manca anche quello, il testo predefinito è "Apri popup".
   - Il plugin accetta qualsiasi post del tipo `purim-popup` pubblicato; l'uso dello slug permette di evitare di ricordare l'ID numerico.

3. Al click viene effettuata una richiesta AJAX verso `admin-ajax.php` che restituisce titolo e contenuto del popup, renderizzati dentro il layout del popup.

Ogni popup mostra in coda al contenuto lo shortcode pronto da copiare per comodità.

## Mappe popup interattive (blocco Gutenberg)

1. Dal menu **Mappe Popup** crea un nuovo elemento:
   - scegli un titolo descrittivo (es. "Mappa sala 1");
   - seleziona l’immagine di base tramite il pulsante **Scegli immagine**;
   - fai clic sull’immagine per aggiungere punti interattivi, oppure usa il pulsante **Aggiungi punto**;
   - per ogni punto imposta la posizione (in percentuale), l’etichetta e il popup da visualizzare (tra quelli del CPT `purim-popup`).
2. Inserisci nel contenuto un blocco **Mappa popup interattiva** (categoria “Widget”) e seleziona dal pannello laterale la mappa da mostrare.
3. In front-end ogni hotspot aprirà il popup associato sfruttando lo stesso overlay già usato dallo shortcode.

Note operative:
- puoi rimuovere un punto dalla lista con il link “Rimuovi” accanto alle sue impostazioni;
- il testo alternativo dell’immagine è modificabile direttamente sotto al selettore dell’immagine;
- i nuovi popup o le nuove mappe appena creati compaiono nelle liste del blocco dopo il salvataggio/reload della pagina editor.
