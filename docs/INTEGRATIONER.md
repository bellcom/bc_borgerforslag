# Integrationer — teknisk beslutningsnotat

## Verificerede upstream-referencer

- https://github.com/bellcom/valgsted (composer.json og composer.lock, main).
- https://github.com/OS2web/os2web_nemlogin commit 7e3c4ac223736cec3477ae5e84f036a685257ebd (1.3.0). Drupal 11 deklareret. `os2web_nemlogin.auth_provider`, `getActivePlugin()`, `isAuthenticatedPerson()`, `isAuthenticatedCompany()`, `fetchValue()`, `getSessionInitialized()`, `os2web_nemlogin.login`.
- https://github.com/OS2web/os2web_datalookup commit 843c41b7d77aac8f67995dea9747d33811439765 (3.5.0). `plugin.manager.os2web_datalookup`, `createDefaultInstanceByGroup('cpr_lookup'/'cvr_lookup')`. `ServiceplatformenCPRExtended::lookup($cpr, FALSE, FALSE)` undgår opslag af børn og test-CPR-erstatning; `CprLookupResult` giver fødselsdato, kommune, navn, beskyttelsesflag. `CompanyLookupResult` giver CVR, navn, kommunekode.
- https://github.com/OS2web/os2web_key (1.0.0-rc7 i Valgsted) og https://github.com/OS2web/os2web_audit (1.3.1).
- https://github.com/OS2Forms/os2forms (Valgsted lock: e4430d2fe1bd1773fdb4f3d1f13bb080489c52bf). Develop-branchen indeholder `modules/os2forms_digital_post`. Det afhænger af OS2Forms, webform, webform_submission_log, Advanced Queue, beskedfordeler, datalookup, key, audit. DigitalPostHelper/MeMoHelper er tæt koblet til Webform. Ingen falsk eller hjemmelavet Digital Post-adapter.

Pakker installeres via Composer fra de publicerede upstream-pakker, ikke kopier. Private kunderepos er ikke kopieret. De viste versionskrav er intervaller; kontrollér ny lock mod de verificerede API'er. Valgsted har yderligere Composer-plugins; kun nødvendige, kendte plugins er tilladt i grundprojektet. Hvis en dependency kræver yderligere plugins eller patches, gennemgå dem før aktivering. Ingen global lenient eller disabled security blocking. Ved OS2Forms-sporet kan versionskonflikter, særligt gamle Symfony-constraints og Drupal Webform-moduler, kræve en nyere Bellcom-/OS2Forms-branch. `scripts/add-os2forms.sh` er et eksplicit separat dry-run spor, ikke en bekræftet installation. Det ændrer composer.json lokalt; kør i ny branch.

## Konfiguration af NemLog-in og registeropslag

1. På et tomt testsite uden fixtures: `bash scripts/enable-integrations.sh`.
2. Konfigurér `/admin/config/system/os2web-nemlogin` med godkendt Bellcom-provider. Den inspicerede `bellcom_os2nemlogin`-provider understøtter både CPR og CVR; `fetch_once` skal være FALSE for dette flertrinsflow. Undersøg faktisk sessionoprettelse, tokenvalidering, binding, logout, sessionrotation, LoA, issuer/audience og levetid. POC-adapteren kræver en initialiseret session på højst 900 sekunder. En Drupal-konto alene giver ikke live-adgang. Kontrollér også eID-gateway og virksomhedens repræsentationsrettighed. CVR-opslag beviser ikke repræsentationsret; live CVR skal forblive slået fra, indtil providerens organisationskontekst er godkendt.
3. Konfigurér datalookup (SF1520 extended CPR og korrekt CVR-plugin), certifikater/serviceaftaler via os2web_key. Kontrollér dataminimering og at upstream-modulers egne logs ikke indeholder fortrolige personoplysninger. Kontrollér at test-CPR-erstatning og test-endpoints ikke er aktiveret i live.
4. Gennemgå og test adapteren `src/Identity/Os2Identity.php`. Den afviser ved ukendt identitet, manglende registerdata, registerfejl, uverificeret virksomhedskontekst og udløbet session. Kommunekode normaliseres til fire cifre. Navnebeskyttet privatperson får alias. Virksomheder er aldrig stemmeberettigede, heller ikke ved både CPR- og CVR-kontekst.
5. Først efter godkendt integrationstest på en ny installation uden demo kan en ansvarlig administrator beslutte at sætte de to deployment settings i `settings.local.php` (live er **ikke** produktionsgodkendt af POC):

```php
$settings['bc_borgerforslag_live_enabled'] = TRUE;
// Kun efter validering af organisationskontekst/repræsentationsret:
$settings['bc_borgerforslag_company_context_verified'] = TRUE;
```

POC har ingen dedikeret secret-key-rotation, separat signerede revisionslogs eller krypteret kontaktlager. Brug stabil, stærk Drupal hash_salt; planlæg separat nøgle i Key/Vault og rotationsstrategi før produktion. POC gemmer ikke rå CPR/CVR i egne tabeller; organisationens HMAC bruges til egne forslag. Afklar behov for CVR, Digital Post, repræsentantidentitet, opbevaring og sletning før lagring. Kontaktmail er selvangivet og skal ikke anvendes til følsomme oplysninger. Navn og forslag er offentlige efter godkendelse; administrator skal kontrollere persondata i frit tekstfelt. Afklar lovligt behandlingsgrundlag og arkivering.

## Digital Post og andre integrationer

Kør `scripts/add-os2forms.sh` i en separat branch, hvis kunden skal have Digital Post. Efter dry-run og bekræftet kompatibilitet: installér godkendt OS2Forms-version, gennemgå de eksisterende Webform-handlers og adaptere i Bellcoms aktuelle miljø, og integrér med en kø til officielle meddelelser. Kræver modtageridentitet opbevaret/indhentet sikkert under korrekt hjemmel og serviceaftale. Ingen automatisk Digital Post-afsendelse med POC. ESDH skal følge samme model. Vedligehold integrationsadaptere adskilt fra borgerforslagslogikken. Brug samme core-modul på standalone og integrerede Drupal-sites.
