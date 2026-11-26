# BC Borgerforslag 0.2.0 POC

Selvstændigt Composer-projekt: Drupal 11.3+, PHP 8.4, MariaDB, Drush 13, Gin. Ingen kode kopieret fra ITK eller OS2webs borgerforslagsmoduler; disse og Cabana er kun funktionel inspiration. Forslag til projektlicens er GPL-2.0-or-later; Bellcom skal godkende licens før offentlig distribution.

## Status og afgrænsning

POC, ikke produktion. Modulet indeholder offentlig oversigt, oprettelse som person/organisation, forhåndsvisning, private egne forslag, administration, politisk behandling og udfald, statushistorik, CSV-oversigt, mailkø og demodata. Kun privatpersoner kan afgive stemme, håndhævet også i stemmeservicen. Unik primærnøgle pr. forslag og forslagsspecifik HMAC af identiteten. CPR gemmes ikke i POC-tabellerne. Ejeridentitet gemmes som separat HMAC, kontaktmail som privat felt. Begge er personoplysninger. Offentliggørelse af navne skal afklares med kommunen; navnebeskyttede personer vises med alias. Ingen persondata i demodata.

Tre egne tabeller (forslag, stemmer, statushistorik). Content Entities, Drupal Content Moderation, fulde revisioner, medstillere, rettelsesanmodning, data-retention, GDPR-indsigt/sletning, ESDH, Digital Post-afsendelse og fuld sikkerheds-/WCAG-test udestår. Stemmer lukker ved stemmemål eller frist. Politisk afvisning er adskilt fra administrativ afvisning. Notifikationer er generiske og deaktiveret som standard; ingen leveringsgaranti eller genforsøg pr. modtager i POC. Cron kræves. Ved live-implementering skal mailafsender, absolute URL via korrekt Drush URI, transport og databehandling verificeres.

## Fresh install (Debian, separat testdatabase)

Forudsætninger: PHP 8.4 CLI/FPM med mysql, SOAP, XML/DOM, curl, gd, mbstring, intl, zip, Composer 2, Git, MariaDB. Opret en **ny tom** database og en separat databasebruger. Webserverens document root skal være `web/`. Brug en dedikeret deployment-bruger, ikke root til Composer. Webserveradgang til settings.php og public files skal konfigureres efter lokal praksis. Installer-scriptet bruger strict mode i en separat scriptproces, ikke i din shell.

```bash
 read -rsp 'Database-URL (mysql://user:password@127.0.0.1/db): ' BC_DB_URL; printf '\n'
 read -rsp 'Admin-adgangskode: ' BC_ADMIN_PASSWORD; printf '\n'
 BC_DB_URL="$BC_DB_URL" BC_ADMIN_PASSWORD="$BC_ADMIN_PASSWORD" BC_SITE_URI=https://dev-borgerforslag01.bellcom.dk bash scripts/install.sh --fresh --demo
 unset BC_DB_URL BC_ADMIN_PASSWORD
```

URL-encode specialtegn i DB-bruger og adgangskode. Scriptet afviser eksisterende settings.php eller en ikke-tom database. Kør kun på isoleret testmiljø, og begræns adgang. DB-URL og admin-password gives til Drush som argumenter under installation; brug en isoleret administrator-/deployment-konto, da processens argumenter kan være synlige for andre privilegerede processer. Demo-konti får tilfældige passwords, som vises én gang i terminalen. Ingen standardpasswords i repo. Kør uden `--demo` for tom, ikke-deltagende installation (live-login deaktiveret).

Ingen composer.lock er medleveret, da Composer og netværksadgang ikke findes i byggecontaineren. Første installation løser versioner med `composer update`; commit den genererede lockfil efter succes og gennemgang af `composer audit`. efterfølgende bruger scriptet `composer install`. Versioner er baseret på Valgsteds lock, men kombinationen er ikke resolver-testet. Ingen lenient/ignore-platform-reqs i grundprojektet.

## Adresser

| Rute | Funktion |
|---|---|
| `/` og `/borgerforslag` | Offentlig forside efter installationsscript |
| `/borgerforslag/opret` | Privatperson eller virksomhed |
| `/borgerforslag/mine` | Egne forslag |
| `/admin/content/bc-borgerforslag` | Godkendelse, politisk status, historik |
| `/admin/content/bc-borgerforslag/export` | CSV med aggregerede oplysninger |
| `/admin/config/content/bc-borgerforslag` | Regler og notifikationer |

Demo-login: `/user/login`. `demo_borger1`, `demo_borger2`, `demo_virksomhed`. Virksomheden kan oprette forslag, men kan ikke stemme. Et fiktivt virksomhedsforslag er med i fixtures. Demo må aldrig genbruges som produktionsdatabase. Flaget `bc_borgerforslag.demo_used` spærrer live-adapteren permanent for denne database.

## Test

```bash
 vendor/bin/drush status && vendor/bin/drush pm:list --status=enabled --filter=bc_borgerforslag && php web/modules/custom/bc_borgerforslag/tests/eligibility-smoke.php
```

På ny demo: `vendor/bin/drush php:script "$PWD/scripts/smoke-drupal.php"` tester demo-fixtures og virksomhedens afvisning. `vendor/bin/drush php:script "$PWD/scripts/smoke-person.php"` opretter et ekstra testforslag og tester stemme og dobbeltstemme. Kør førstnævnte før sidstnævnte. CI er konfigureret til Composer-resolution og MariaDB/Drupal-smoke; **ikke afviklet her**. Test i browser: afviste forslag er ikke offentlige, virksomhedslogin kan ikke stemme, privatperson kan støtte virksomhedsforslag én gang, samme forslag kan ikke stemmes på igen, statusændringer, direkte POST/CSRF, cache og session, cron og tidsfrister. Test også samtidige stemmer og fejlede opslag i integrationstest.

## Integrationer

Se [docs/INTEGRATIONER.md](docs/INTEGRATIONER.md). Koden genbruger de eksisterende NemLog-in- og datalookup-services. De installeres med Composer, men aktiveres ikke automatisk. Live kræver særskilt deploymentkonfiguration, sikkerhedstest, godkendt identitetsprovider og registeraftaler. Test med rigtige persondata må først ske efter godkendelse. Digital Post fra OS2Forms er et separat integrationsspor, ikke færdig adapter.

## Git

Importér den medleverede Git-bundle eller ZIP og commit. Remote er ikke oprettet fra denne session. Foreslået privat remote: `bellcom/bc_borgerforslag`. GitHub Actions workflow medfølger. Ingen credentials, data eller vendor-kode i repo.
