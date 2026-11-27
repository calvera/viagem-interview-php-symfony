# Evidence vlastníků (Symfony)

Přepis legacy aplikace `../legacy-app` (ruční PHP, v2.8.1) do Symfony 8.1 a PHP 8.5. Twig šablony jsou stylované Tailwindem 4 přes Vite. Aplikace eviduje vlastníky pozemků, parcely a obce a funkčně odpovídá legacy 1:1. Má přehled, výpisy s filtry, detail vlastníka s poznámkami, úpravu vlastníka, CSV export, našeptávač obcí a noční report.

## Stack

- PHP 8.5 (FrankenPHP), Symfony 8.1, Doctrine ORM 3.8 / DBAL 4, MariaDB 10.11 (`utf8mb4_czech_ci`, striktní `sql_mode`)
- Twig + Tailwind CSS 4, Vite (`pentatrion/vite-bundle`), Turbo a Stimulus (`stimulus-autocomplete`) jako npm balíčky, bez vlastního JS
- PHPUnit 13 + Foundry + DAMA, PHPStan 2 (level 8), PHP CS Fixer (`@Symfony`, bez Yoda podmínek)
- Přihlášení není, uživatel je pevný z `APP_USER` (výchozí `operator.a`)

## Spuštění

Požadavky: Docker s Compose, Node.js (na hostiteli, jen pro assety). Composer, konzole, testy a PHPStan se spouštějí **v kontejneru `php`**, ne hostitelským PHP.

```bash
docker compose up -d --build                         # php na :8090, MariaDB na :33062
docker compose exec php composer install
npm install
npm run build                                        # assety do public/build
docker compose exec php bin/console doctrine:migrations:migrate -n
docker compose exec php bin/console app:import-legacy --no-debug   # viz Import
```

Aplikace běží na http://localhost:8090.

Pro vývoj spusťte `npm run dev` (Vite s HMR na :5173). Prohlížeč pak bere assety přímo z `localhost:5173`.

`VITE_DEV_SERVER` (`http://host.docker.internal:5173`) slouží jen proxy bundle `pentatrion_vite_build_proxy`. Z kontejneru se k Vite na hostiteli v tomto prostředí nedá připojit (spojení visí, nejspíš kvůli firewallu hostitele). Na samotné stránky to vliv nemá.

### Konfigurace (`.env`)

| Proměnná | Význam |
|---|---|
| `DATABASE_URL` | nová DB `evidence` (v testech `evidence_test`) |
| `LEGACY_DATABASE_URL` | stará DB `legacy_crm` (kontejner `legacy-app-db-1`, port 33061), jen pro import |
| `APP_USER` | přihlášený uživatel (hlavička, poznámky, logy, export) |
| `REPORT_PING_URL`, `REPORT_UPLOAD_URL` | reportovací služba nočního reportu |
| `VITE_DEV_SERVER` | adresa Vite dev serveru pro proxy |

### Testy a kontrola kódu

```bash
docker compose exec php vendor/bin/phpunit
docker compose exec php vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec php vendor/bin/php-cs-fixer fix --dry-run --diff
```

Testy běží nad `evidence_test`. DAMA každý test vrací rollbackem, Foundry vytváří data. Test importu potřebuje DB `legacy_test`, kterou vytvoří `docker/db/init.sql`.

Kontejnery s `debug: false` (`--no-debug`, test 404 stránky) Symfony po změně konfigurace samo nepřestaví. Po změně služeb nebo konfigurace je potřeba smazat cache: `bin/console cache:clear --no-debug`, případně `rm -rf var/cache/test`.

### Cron

```
15 2 * * * cd /app && bin/console app:nightly-report --no-debug
```

## Import ze staré databáze

```bash
docker compose exec php bin/console app:import-legacy --no-debug           # do prázdné DB
docker compose exec php bin/console app:import-legacy --purge --no-debug   # smaže cílová data a importuje znovu
```

- Tabulky se importují v pořadí `municipalities → owners → parcels → ownerships → owner_notes → report_runs`.
  - Čte se po dávkách 5 000 řádků podle `id > :last`, s transakcí na dávku.
  - Zapisuje se vícehodnotovými INSERTy po 1 000 řádcích a id zůstávají zachovaná.
  - Během importu jsou vypnuté kontroly FK.
- Konverze probíhají v SELECTu. Nulová data (`0000-00-00`) se mění na `NULL` a prázdné řetězce u volitelných polí také na `NULL`.
- Před importem se kontrolují neplatné řádky (`municipality_id = 0`, neznámý typ vlastníka). Pokud nějaký existuje, příkaz skončí chybou a nic nezapíše.
- Na konci příkaz spustí `ANALYZE TABLE` (včetně histogramů pro `owners`) a vypíše počty řádků ve staré a nové DB.
- **`--no-debug` je nutné.** V debug režimu Doctrine profiler drží všechny dotazy v paměti a import spadne na `memory_limit`.
- `access_log` se neimportuje, nová evidence přístupů začíná prázdná.

Ověřený běh (`--purge --no-debug`, cca 56 s):

| Tabulka | Stará DB | Nová DB |
|---|---:|---:|
| municipalities | 6 250 | 6 250 |
| owners | 500 000 | 500 000 |
| parcels | 800 000 | 800 000 |
| ownerships | 1 200 000 | 1 200 000 |
| owner_notes | 60 000 | 60 000 |
| report_runs | 1 | 1 |

## Struktura

| Kde | Co |
|---|---|
| `src/Entity`, `src/Enum` | doménový model a formátovací metody (`Owner::displayName()`, `detailName()`, `isOverdue()`, `Ownership::shareLabel()`, `shareArea()`, `OwnerType::label()`) |
| `src/Value/Area.php` | výměra „8 069 m²“ / „2,89 ha“ se zaokrouhlováním jako legacy `format_area()` |
| `src/Dto` | query DTO výpisů (`#[MapQueryString]`, legacy názvy GET parametrů), `ListPage` nad Doctrine `Paginator` |
| `src/Repository` | dotazy výpisů, filtry, řazení a dávková data pro stránku (bez N+1) |
| `src/Controller` | přehled, vlastníci (výpis, detail, úprava, poznámky), export, parcely, obce, našeptávač |
| `src/Form` | formulář úpravy vlastníka (`OwnerType` + `OwnerEditData`) |
| `src/Export` | CSV export (`OwnerExport`, `OwnerCsv`) |
| `src/Report`, `src/Command` | noční report, import ze staré DB |
| `src/Doctrine` | DBAL middleware, které počítá SQL dotazy pro patičku |
| `src/EventListener` | access log na `kernel.terminate` |
| `templates/list/*` | sdílené části výpisů: filtry, řazení, info, stránkování |
| `templates/_macros.html.twig` | jediné makro `date_or()` |

Routy: `/`, `/vlastnici`, `/vlastnici/{id}`, `/vlastnici/{id}/uprava`, `/vlastnici/{id}/poznamky` (POST), `/vlastnici/{id}/poznamky/{noteId}/smazat` (POST), `/vlastnici/export`, `/parcely`, `/obce`, `/api/obce`.

Logy jsou v `var/log/owners.log` (úpravy vlastníků), `export.log` a `cron.log`. Reporty se ukládají do `var/reports/nightly_YYYY-MM-DD.txt`.

## Rozhodnutí

- **Bez EasyAdminu.** Aplikace má vlastní kontrolery, Twig a Tailwind. Výpisy nemají vlastní „Section“ vrstvu. Parametry mapují DTO přes `#[MapQueryString]`, dotazy jsou v repozitářích a stránkování dělá Doctrine `Paginator`.
- **Nové čisté schéma** s jednou migrací, FK `RESTRICT`, `NULL` místo nulových dat, striktním `sql_mode` a českou kolací. Data převádí jednorázově `app:import-legacy`.
- **Otevřené otázky legacy (OPENQUESTIONS.md):**
  - Q1: odkaz „vlastníci“ u obce vede na obec vlastníka.
  - Q2: dnešní termín oslovení **není** po splatnosti (`duedate < dnes`) ve výpisu i v reportu.
  - Q4: validace podle legacy pravidel, navíc `Length` podle délek sloupců.
  - Q5: vlastníci se řadí podle `příjmení, jméno, id` s indexem.
  - Q6: FK `RESTRICT`, bez unique na `(owner_id, parcel_id)`.
- **Jednoznačné řazení:** všude se jako poslední klíč řadí podle `id`.
- **Bez vlastní Twig extension.** Formátování dělají metody entit, hodnotový objekt `Area`, vestavěné filtry a jedno makro.
- **Bez vlastního JS:**
  - potvrzení mazání poznámky řeší Turbo `data-turbo-confirm`;
  - našeptávač řeší `stimulus-autocomplete`;
  - formuláře s chybou vrací 422, úspěch vrací 303.
- **Počet SQL dotazů** počítá DBAL middleware nad spojením `default`. Počítadlo se nuluje na začátku každého hlavního požadavku a zobrazuje se v patičce.
- **Nové API Doctrine/Foundry:**
  - řazení v QueryBuilderu používá `SortDirection` (stringy jsou v ORM 3.8 deprecated);
  - zapnuté jsou `use_schema_editor_api`, `transactional: false` (MariaDB DDL commituje implicitně) a `enable_auto_refresh_with_lazy_objects`.

## Odchylky od legacy

Vědomé odchylky v chování:

1. **Termín oslovení „dnes“** se nepočítá jako po splatnosti (Q2). Je to jediná odchylka ve funkci.
2. **Našeptávač** (`/api/obce`) vrací HTML fragment `<li role="option">…</li>` pro stimulus-autocomplete, ne JSON. Výsledky (15 položek, prefix, min. 2 znaky) jsou stejné. Ověřeno na 13 dotazech, shoda.
3. **Pořadí parcel se stejným klíčem na detailu vlastníka.**
   - Legacy řadí `obec, k. ú., číslo parcely` bez tie-breakeru a MariaDB vrací shody v nedefinovaném pořadí. Nejčastěji je to opačné pořadí, u trojic jiné.
   - Nová verze řadí shody podle `ownerships.id`.
   - V datech je 191 681 dvojic a 100 001 trojic takových shod. Detail je proto u mnoha vlastníků ve stejné skupině seřazen jinak, obsah je stejný.
4. **Úprava vlastníka:**
   - pole formuláře se jmenují Symfony způsobem (`owner[lastName]` místo `last_name`);
   - neplatný CSRF token zobrazí hlášku „Platnost formuláře vypršela, odešlete ho prosím znovu.“ hned ve formuláři (422) a zachová vyplněné hodnoty, místo přesměrování;
   - navíc jsou hlášky `Length` („Hodnota smí mít nejvýše N znaků.“);
   - titulek stránky po chybě validace ukazuje uložené jméno, ne odeslané;
   - hláška „Uložení se nezdařilo.“ není, chyba DB se projeví výjimkou.
5. **Mazání poznámky** je POST na `/vlastnici/{id}/poznamky/{noteId}/smazat` místo POST s `note_id` na adresu detailu. Mazání přes GET dál nejde (405).
6. **Flash zprávy** se zobrazují v layoutu nad obsahem stránky, ne až pod nadpisem.
7. **Neplatné hodnoty v URL:**
   - nečíselné `/vlastnici/abc` vrací 404 z routingu;
   - POST na neexistujícího vlastníka vrací 404;
   - GET na neexistujícího vlastníka vrací jako legacy stránku „Vlastník nenalezen“ se stavem 200.
8. **Typ vlastníka „Neurčeno“** neexistuje. Enum povoluje jen F/P a import jiné typy odmítne (v datech žádné nejsou).
9. **Text u chybějícího nočního reportu** odkazuje na `bin/console app:nightly-report` místo `cron/nightly_report.php`.
10. **Access log:**
    - zapisuje se až po odeslání odpovědi (`kernel.terminate`), proto insert není v počtu SQL dotazů v patičce (legacy ho tam započítává);
    - požadavky profileru v dev (`/_…`) se nezapisují.
11. **Noční report** načítá 10 nejčastějších obcí jedním dotazem s JOINem, ne 10 dotazy. Text reportu je stejný, ověřeno na importovaných datech. Liší se jen datum, doba výpočtu a hostname.
12. **Export** používá vždy výchozí řazení, stejně jako legacy (export tam nemá řaditelné sloupce). Parametr `sort` se ignoruje.

Odchylky v implementaci oproti plánu:

- Plán počítal s Twig makrem `area`. Místo něj je hodnotový objekt `App\Value\Area`, protože přesné zaokrouhlení legacy (`area_value()`, předzaokrouhlení na 15 platných číslic jako PHP 7.4) vestavěné filtry neumí. `Ownership::shareArea()` proto vrací `?Area`, ne `?float`.
- `f_municipality_id` se nevaliduje constraintem `Regex`, ale normalizuje v konstruktoru DTO. Neplatná hodnota se tak ignoruje jako v legacy, místo aby vrátila 404.
- Paginator počítá `COUNT(*)` místo `COUNT(DISTINCT id)` (hint `CountWalker::HINT_DISTINCT = false`). Výpisy joinují jen ManyToOne, takže výsledek je stejný, a DISTINCT byl na 800k parcelách 3× pomalejší.
- Výpis parcel joinuje obec přes `LEFT JOIN` jako legacy. S `INNER JOIN` optimalizátor začínal od obcí a výpis trval 2,7 s místo 0,15 s.
- Hledání vlastníka podle jména řadí `o.id + 0` jako legacy, aby MariaDB nečetla celý index jmen (1,3 s → 0,42 s).
- Access log je `AccessLogListener` (`#[AsEventListener]`), ne subscriber.
- Navíc je index `idx_parcels_land_type` (filtr druhu pozemku). Doctrine ponechává i vlastní FK index na `owners.municipality_id`.
- Verze aplikace v patičce je `3.0.0`.

## Výkon

Počet SQL dotazů na stránku a doby odezvy. Měřeno na importovaných datech, dev prostředí, teplá cache, `curl`. Legacy do počtu započítává i insert do access logu.

| Stránka | Nová: dotazy | Nová: čas | Legacy: dotazy | Legacy: čas |
|---|---:|---:|---:|---:|
| `/` přehled | 3 | 0,39 s | 7 | 0,42 s |
| `/vlastnici` | 4 | 0,12 s | 5 | 0,13 s |
| `/vlastnici?f_municipality_id=1` | 5 | 0,04 s | 7 | 0,03 s |
| `/vlastnici?f_municipality=Lhota` | 4 | 0,16 s | 6 | 0,54 s |
| `/vlastnici?search=01256748` (IČO) | 4 | 0,05 s | 5 | 0,47 s |
| `/vlastnici?search=Svobodová` | 4 | 0,45 s | 5 | 0,40 s |
| `/vlastnici?page=5000` | 4 | 1,34 s | 5 | 1,54 s |
| `/vlastnici/1` | 3 | 0,03 s | 4 | 0,01 s |
| `/parcely` | 4 | 0,15 s | 5 | 0,83 s |
| `/parcely?f_municipality_id=1` | 4 | 0,05 s | 6 | 0,77 s |
| `/obce` | 5 | 0,04 s | 5 | 0,03 s |

Žádný výpis nemá víc než 6 dotazů. Funkční testy to hlídají (`queryCount()` z patičky).

EXPLAIN výpisu vlastníků (500 000 řádků):

- výchozí count: `index` scan přes FK index `municipality_id`, `Using index`;
- výchozí select: index `idx_owners_name`, 50 řádků, bez filesortu;
- filtr `f_municipality_id`:
  - count: `ref` na `IDX_427292FAAE6F181C`, 240 řádků;
  - select: `ref` na `idx_owners_municipality`, `Using index`;
- filtr `f_municipality` (LIKE): scan indexu obcí (6 271) a `ref` na `idx_owners_municipality`;
- IČO: `ref` na `idx_owners_ico`, 1 řádek;
- `f_type` + `f_dnc`: čte `idx_owners_name`, cca 20 000 řádků;
- řazení podle `created_at`: full scan s filesortem. Legacy se chová stejně, index na `created_at` nemá ani legacy.

Export 20 000 vlastníků trvá 1,6–3,4 s (legacy 1,0–2,1 s). Rozdíl je v hydrataci entit, EntityManager se čistí po 1 000 řádcích. Výstup je bajt po bajtu shodný s legacy, ověřeno na 10 kombinacích filtrů.
