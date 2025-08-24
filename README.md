Plugin wordpress per mostrare popup quando si preme su un link.

Specifiche richieste:

Realizza un plugin per wordpress che permetta di inserire all'interno di un paragrafo uno shortcode che apra un popup quando cliccato.
Il contenuto dei popup deve essere caricato dinamicamente da una pagina appartenente a una specifica tassonomia denominata "Popup" (il nome univoco della tassonomia conviene che sia meno generico tipo "purim-popup" per evitare collisioni con altri plugin).
Il plugin deve sostituire allo shortcode un tag "a" o comunque un elemento inline che, tramite javascript applichi un overlay alla pagina 
e mostri al di sopra di esso un div, centrato nello schermo, di dimensioni minime 50vw x 50vh (su cellulari è bene che sia un fullscreen, 
su desktop di grandi dimensioni invece va bene che le dimensioni effettive non superino la metà del viewport).
In ogni pagina del sito wordpress possono esserci più shortcode che aprono popup, di media al massimo una decina.

Il caricamento del plugin avverrà tramite SFTP e non è necessario che sia pubblicato su una repository globale.

Opzionale: il plugin deve mostrare all'interno di ciascuna pagina della tassonomia "purim-popup" lo shortcode da utilizzare per mostrarla come popup
Opzionale: il plugin deve integrarsi con l'editor a blocchi e prevedere un pulsante, simile a quelli per aggiungere un link, che permetta di inserire invece un popup selezionando una pagina dalla tassonomia.
Opzionale: i link creati devono avere una classe specifica assegnata, in modo da poter permettere la personalizzazione dell'aspetto