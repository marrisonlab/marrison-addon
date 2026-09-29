# Marrison Addon — Codex Instructions

## Project

Marrison Addon è un plugin WordPress modulare per Elementor,
WooCommerce, JetEngine e funzionalità frontend/backend indipendenti.

Ogni modulo può essere abilitato o disabilitato individualmente.
I moduli disabilitati non devono essere caricati o inizializzati.

Prima di lavorare consulta README.md per identificare il modulo interessato.

## Regola fondamentale

Preserva sempre le funzionalità esistenti.

Non modificare moduli non coinvolti nel task.
Non effettuare refactoring generali se non esplicitamente richiesti.
Non rinominare classi, funzioni, hook, option, meta key o slug esistenti
senza una necessità funzionale.

Preferisci sempre la modifica minima necessaria.

## Strategia di esplorazione

NON esplorare automaticamente l'intero repository.

Prima identifica il modulo coinvolto.

Se il task richiede ricerca estesa nel codebase, usa `explorer`
per individuare:
- file pertinenti;
- classi e funzioni;
- hook WordPress;
- hook WooCommerce;
- integrazioni Elementor;
- integrazioni JetEngine;
- dipendenze;
- asset JS/CSS collegati.

Usa `explorer` soltanto quando la ricerca richiede più file o
ricostruzione del flusso.

Per ricerche semplici e localizzate procedi direttamente.

L'output di `explorer` deve essere sintetico.
Non ripetere l'intera esplorazione con il modello principale
se le informazioni ricevute sono sufficienti.

## Implementazione

Il modello principale è responsabile delle modifiche al codice.

Prima di modificare:
1. identifica il modulo;
2. identifica i file strettamente necessari;
3. verifica il comportamento esistente;
4. valuta le dipendenze;
5. implementa la modifica minima.

Dopo la modifica:
1. controlla errori PHP/JS evidenti;
2. verifica regressioni nel modulo interessato;
3. controlla eventuali integrazioni coinvolte;
4. non eseguire analisi dell'intero plugin se non necessaria.

## WordPress

Usa API, hook e convenzioni WordPress esistenti.

Quando pertinente verifica:
- sanitizzazione input;
- escaping output;
- nonce;
- capability;
- AJAX/REST;
- compatibilità PHP >= 7.4.

## WooCommerce

Quando il modulo coinvolge WooCommerce:
- usa API e hook WooCommerce;
- considera prodotti semplici, variabili e variazioni quando pertinente;
- evita query inutilmente pesanti;
- considera HPOS quando il task riguarda ordini.

## Elementor

Quando il modulo coinvolge Elementor:
- preserva editor e preview;
- evita di caricare asset dove non necessari;
- usa le API Elementor esistenti;
- verifica separatamente frontend/editor quando pertinente.

## JetEngine

Quando il modulo coinvolge JetEngine:
- preserva compatibilità con Listing e Query Builder;
- considera cache e contesto dinamico quando pertinenti;
- non introdurre dipendenze JetEngine nei moduli che non la richiedono.

## Efficienza

Ottimizza l'utilizzo del contesto.

Non leggere file non pertinenti.
Non rileggere file invariati senza motivo.
Non produrre lunghi resoconti intermedi.
Non duplicare ricerche già completate.
Non avviare subagent multipli quando ne basta uno.

Alla fine restituisci soltanto:
- cosa è stato modificato;
- file modificati;
- test/verifiche eseguiti;
- eventuali problemi rimasti.