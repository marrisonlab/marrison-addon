# Cookie Manager — consegna del 27 ottobre 2026

## Correzioni e cause verificate
- Lo scanner interrompeva l'inventario su array, stringhe e liste URL grandi. La lettura ora riprende da un cursore, senza perdere le parti successive; gli errori effettivi riportano la sorgente precisa.
- Il campionamento browser non includeva correttamente tutte le pagine pubblicate. Ora tutti i contenuti pubblici entrano nel raggruppamento: risorse diverse producono campioni distinti, gli equivalenti vengono riuniti.
- Il redirect WooCommerce da checkout vuoto a carrello perdeva il contesto firmato della scansione e causava timeout. Ora il contesto segue esclusivamente redirect pubblici dello stesso sito registrati dal server per quel token.
- Nessun CSS, template o impostazione di aspetto del banner è stato modificato.

## Pacchetto installato
`marrison-addon-cookie-repair-20261027-r3.zip`, versione plugin 1.3.91, revisione motore 20261027.3. Installato e attivo sul sito di test Hostinger. FAZ rimane installato e disattivato. Non è stata pubblicata una release GitHub.

Il pacchetto contiene esattamente tre file diversi rispetto allo ZIP base: `class-static-scanner.php`, `class-scan-sampler.php`, `class-scan-engine.php`, tutti in `marrison-addon/includes/modules/cookie-manager/includes/`. Il manifest riporta SHA-256 e percorsi; `cookie-repair.patch` contiene il confronto con il pacchetto base. Le altre modifiche già presenti nel repository sono state preservate.

## Verifiche locali completate
- PHP: scanner statico, motore locale, sicurezza, archivio consensi e policy.
- Array di 2300 elementi, oltre 2000 nodi, stringhe oltre 2 MiB, oltre 200 URL, URL tra blocchi, ripresa dopo pressione di memoria, errore con sorgente precisa.
- 42 URL candidati, servizio distinto in una pagina successiva, oltre 30 equivalenti raggruppati; redirect firmati consentiti e rifiuto di destinazioni esterne, amministrative, non registrate o con token copiato.
- Chrome con trasporto HTTP simulato: frame senza credenziali amministrative, fasi prima/dopo, blocco dinamico, scelta selettiva e revoca.
- PHP/Chrome con HTTP locale reale: consenso firmato HttpOnly, accetta/rifiuta/personalizza/revoca/ricarica, ricevute e protezioni; policy desktop/mobile.

## Verifiche sul sito reale
La prova con pagina temporanea oltre 2 MiB e metastruttura Elementor di 2300 elementi ha completato 26/26 passaggi browser, 13/13 gruppi da 25 URL, 19 contenuti, senza errori. Il rapporto `live-scan-r3-stress.json` documenta anche i redirect checkout/carrello e l'effettivo controllo browser della pagina aggiunta.

`live-consent-results.json` contiene nove controlli reali: prima del consenso, rifiuto e ricaricamento, accettazione e ricaricamento, revoca, soli analitici e ricaricamento, revoca selettiva. Le risorse e i cookie di prova seguono le scelte: zero prima/rifiuto/revoca, entrambe le risorse dopo accettazione, soltanto analitici nella scelta selettiva. Il video YouTube resta bloccato prima del consenso e viene abilitato con marketing.

Cinque ricevute corrispondenti alle scelte sono state verificate nell'interfaccia con esito «Ricevuta e informativa integre». Evidenze: `live-receipts-ui.txt` e `live-receipts.png`. La cookie policy si renderizza senza shortcode grezzi.

Il plugin temporaneo è stato disattivato ed eliminato; la sua pagina è nel cestino e le sorgenti originarie sono state ripristinate. La scansione finale senza dati di prova ha completato 24/24 passaggi browser, 12/12 gruppi da 24 URL, 18 contenuti, 11 cookie osservati e 4 documentati dal catalogo, senza errori né cookie del probe. Evidenze: `live-scan-final.json` e `live-scan-final.png`.

## Limite di osservazione
La verifica browser locale non legge i cookie di terze parti: il rapporto distingue cookie osservati e cookie documentati dal catalogo. I risultati ottenuti riguardano questo sito di test e i casi elencati.
