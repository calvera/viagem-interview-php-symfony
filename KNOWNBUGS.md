# Známé chyby

Nálezy z nezávislé kontroly přepisu proti plánu a proti legacy aplikaci (27. 11. 2026). Zatím neopravené.

## Chyby

1. **Testy zapisují do provozních logů.** Testovací i dev prostředí sdílí `var/log` a kanály `owners`, `export` a `cron` v `config/packages/monolog.yaml` nemají přepis pro `when@test`. Každý běh `phpunit` proto přidá falešné záznamy do `var/log/owners.log` (např. „operator.a upravil vlastníka 28“ z `tests/Functional/OwnerEditTest.php`), `export.log` a `cron.log`, takže auditní stopa obsahuje úpravy a exporty, které se nestaly.
   - Oprava: v `when@test` směrovat tyto kanály do jiného souboru nebo do paměti.

2. **`/vlastnici/0` nezobrazí hlášku o neexistujícím vlastníkovi.** `templates/owner/not_found.html.twig:8` testuje `{% if id %}`, což je pro 0 nepravda, takže stránka ukáže jen „Zpět na výpis vlastníků“. Legacy pro `id=0` píše „Vlastník s ID 0 v evidenci není.“
   - Oprava: `{% if id is not null %}`.

3. **Validace e-mailu se liší od legacy.** `src/Form/OwnerType.php:36` používá `Assert\Email` ve výchozím režimu html5, legacy `FILTER_VALIDATE_EMAIL`.
   - Nově projde `a..b@example.com`, `.a@example.com`, `a.@example.com` (legacy je odmítne).
   - Nově neprojde `"q"@example.com` (legacy ho přijme).
   - K rozhodnutí: vrátit validaci 1:1 s legacy, nebo rozdíl přijmout a zapsat mezi odchylky v README.

4. **Časy v access logu jsou posunuté proti legacy.** `src/EventListener/AccessLogListener.php:33` zapisuje `date('Y-m-d H:i:s')` v časové zóně PHP (Europe/Prague), legacy `log_access()` používá `now()` databáze, která běží v UTC. Rozdíl je 1 h v zimě a 2 h v létě.
   - K rozhodnutí: zapisovat v UTC jako legacy, nebo v místním čase a zapsat to mezi odchylky.

5. **Jiný formát řádků v logu.** Legacy zapisuje `2026-11-27 01:37:28 operator.a exportoval …`, nová aplikace výchozí formát Monologu `[2026-11-27T01:37:28.414643+01:00] export.INFO: operator.a exportoval … [] []`. Týká se `owners.log`, `export.log` a `cron.log`.
   - Oprava: line formatter s formátem `%datetime% %message%` a datem `Y-m-d H:i:s`.

6. **Příliš velké id vrací 404.** `/vlastnici/99999999999999999999` vrátí 404, legacy ukáže stránku „Vlastník nenalezen“. README zmiňuje jen nečíselná id.

## Výkon

- **CSV export je pomalejší než legacy.** 20 000 řádků trvá 1,6–3,4 s, legacy 1,0–2,1 s (měřeno v dev prostředí). SQL je stejné; čas jde na hydrataci entit `Owner` a `Municipality` přes ORM (`OwnerRepository::exportRows()` + `toIterable()`, `em->clear()` po 1 000 řádcích).
  - Oprava: v `exportRows()` vybírat jen sloupce exportu a vracet pole místo entit.

## Prostředí

- **Kontejner se nedostane k Vite dev serveru na hostiteli** (`host.docker.internal:5173`, spojení visí, nejspíš firewall hostitele). Dev režim funguje, protože prohlížeč načítá assety přímo z `localhost:5173`. Popsáno v README.
- **Kernel bez debugu nevidí změny konfigurace** (`--no-debug`, test 404). Po změně konfigurace je potřeba ručně smazat cache. Popsáno v README.

## Drobnosti

- `src/Story/AppStory.php` je prázdný stub z Foundry recipe; mrtvý kód v `src/`, Foundry je jen dev závislost.
- `paginate()` vrací `App\Dto\ListPage` (kvůli ořezu stránky na poslední), ne Doctrine `Paginator`, jak uvádí plán. V README chybí mezi odchylkami od plánu.
- Chybí unit testy mapování query DTO (plán je požadoval); pokrývají je jen funkční testy.
- Potvrzovací dialog mazání poznámky není ověřen v prohlížeči; testy kontrolují jen atribut `data-turbo-confirm`.
- Access log se z legacy neimportuje; nový log začíná prázdný.
