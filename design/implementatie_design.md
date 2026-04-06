# Implementatieplan Design System - Trainer Bobby (1-mans)

## 1. Doel en scope
Dit plan vertaalt de designregels uit `/design` naar een uitvoerbare, sobere implementatieaanpak voor een 1-mans project met Codex.

Doel:
- 1 consistente visuele en interactionele laag over het hele platform
- zo min mogelijk ad-hoc styling en uitzonderingen
- gefaseerde uitrol zonder productiestops
- `/design` is referentie-only; platformcode mag er niet runtime/build van afhankelijk zijn

Scope:
- dashboard, trainingen, oefeningen, team/spelers, wedstrijden, account, admin, auth, rapportages

## 2. Contextbronnen in `/design`
| Bestand | Rol | Status |
|---|---|---|
| `README.md` | pakketoverzicht | actief |
| `trainer-bobby-design-system-v2.md` | hoofdbron interaction rules | actief |
| `trainer-bobby.tokens.v2.css` | primaire tokenbron (CSS variabelen + component starters) | actief |
| `trainer-bobby.tokens.v2.json` | token-spiegel voor validatie/sync | actief |
| `Trainer_Bobby_design_handoff.pptx` | merk en visuele richting | actief |
| `Trainer_Bobby_component_sheet_v2.pptx` | component- en schermpatronen | actief |
| `codex-implementation-prompt.md` | operationele prompt voor agent-workflow | optioneel |
| `*:Zone.Identifier` bestanden | metadata | geen implementatie-impact |

Harde randvoorwaarde:
- Bestanden in `/design` zijn alleen context en guidelines.
- Runtime, build- en app-imports mogen nooit naar `/design/*` verwijzen.
- De map `/design` moet na implementatie verwijderbaar zijn zonder functionele impact.

## 3. Bewust niet opgenomen (overbodig voor 1-mans)
- Geen aparte rolverdeling per sprint (design/product/dev): jij + Codex vormen 1 uitvoerteam.
- Geen aparte capaciteitsplanning met teamafhankelijkheden: we sturen op prioriteit en afgeronde checklist-items.

## 4. Prioriteringsmatrix per domein (scope-bewaking)
Regel: per domein eerst alle `Must`, daarna pas `Should`, daarna `Could`.

| Domein | Must | Should | Could |
|---|---|---|---|
| Platform foundation | tokens, fonts, basis typografie/spacing, button hierarchy | cleanup oude utility-klassen | cosmetische micro-animaties |
| Dashboard | geen create-FAB op dashboard, minder concurrerende CTA's | chip-filters compacter | extra stat-card varianten |
| Training Builder | FAB add exercise, 1 expliciete save-actie, icon-acties per blok | betere preview/duplicate flow | extra drag-and-drop polish |
| Team/Spelers | FAB add, compacte row/card acties, commit-momenten expliciet | extra filterchips/statusweergave | extra bulk-actions |
| Match Mode | expliciete hoge-impact commit buttons, compacte support-acties | verbeterde mobiele action tray | extra sneltoetsen |
| Account/Admin/Auth/Reports | zelfde primitives en tokengebruik, geen afwijkende CTA-stijlen | visuele harmonisatie details | aanvullende thematische varianten |

## 5. Technische migratieregels (hard rules)
Deze regels zijn verplicht tijdens implementatie.

1. Naming en structuur
- Component/primitives classes krijgen `tb-` prefix (bijv. `tb-button`, `tb-icon-button`, `tb-fab`).
- Token custom properties gebruiken `--tb-` prefix.
- Nieuwe ad-hoc UI classes zonder patroon zijn niet toegestaan.

2. Afhankelijkheden naar `/design` (verboden)
- Geen `@import`, `link`, `require`, `include`, script of runtime file-read naar `/design/*`.
- Geen buildstap die tokens of componentdefinities direct uit `/design` inlaadt.
- Platformbrede check blijft: buiten `/design` zijn er 0 verwijzingen naar `/design/*`.

3. Tokengebruik
- Geen nieuwe hardcoded hex/radius/spacing/typografie als token bestaat.
- Runtime tokenbron staat buiten `/design` (in platformcode).
- Ontbreekt een token, dan toevoegen in runtime tokenbron; optioneel daarna terugspiegelen in `/design` voor documentatie.

4. Inline styles beleid
- Nieuwe inline `style="..."` in views: niet toegestaan.
- Tijdelijke uitzondering alleen voor echt dynamische runtime-waarden die niet via class/data-attribute kunnen.
- Tijdelijke uitzonderingen moeten in backlog als afbouwpunt landen.

5. Action hierarchy
- Overview create/add = FAB (1 dominante create-entry), met expliciete uitzondering: Dashboard heeft geen create-FAB.
- Commit-acties (save/publish/start/confirm) = gelabelde primary button.
- Contextacties op items = icon button met `aria-label`.
- Secondary acties = outline/ghost/text, niet concurrerend met primary.

## 6. Kwaliteitsgates
### 6.1 Accessibility target
- Target: WCAG 2.2 AA op kernflows.

Vaste testscript per kernflow (Dashboard, Training Builder, Team/Spelers, Match Mode, Oefeningenlijst):
1. Alleen toetsenbord: alle interactieve elementen bereikbaar.
2. Focus zichtbaar op elke interactieve control.
3. Enter/Space activeert controls correct.
4. Icon-only knoppen hebben bruikbare `aria-label`.
5. Touch targets minimaal 44x44 px op mobiel.
6. Contrast voldoet voor tekst en primaire acties.

### 6.2 Visual regression
- Tooling: Playwright screenshot-regressie voor alleen `Must`-schermen.
- Baselines: desktop (1440x900) en mobiel (390x844).
- Acceptatie:
  - geen layout-breuk (overlap, clipping, verdwenen primaire acties)
  - pixelverschil boven ingestelde drempel = handmatige review verplicht

### 6.3 Release en rollback (lichtgewicht)
- Gefaseerde release per domein (geen big-bang).
- Rollback: `git revert` op domein-commit(s), geen brede resetacties.
- Alleen doorgaan naar volgend domein als `Must` + kwaliteitsgates groen zijn.

Commitstrategie voor toekomstige design-wijzigingen:
- 1 commit per domein of per scherm, afhankelijk van omvang.
- Domeinen: Platform foundation, Dashboard, Training Builder, Team/Spelers, Match Mode, Account/Admin/Auth/Reports, Quality/CI.
- Commitbericht bevat domein-prefix, bijv. `[design:dashboard] …` of `[design:foundation] …`.
- Gedeelde CSS-wijzigingen (style.css, tb-*.css) worden meegenomen in de domein-commit die ze motiveert.
- Cross-domein wijzigingen (layout, header, tokens) gaan in een aparte `[design:foundation]` commit.

Rollback bestaande MUST-ronde:
- Initiële implementatie (MUST-02 t/m MUST-11) is uitgerold in commit `dca0240`.
- Volledige rollback mogelijk via `git revert dca0240`.
- Granulaire per-domein rollback is niet van toepassing op deze ronde, wel op alle toekomstige wijzigingen.

### 6.4 KPI's voor voortgang en succes
Startwaarden (huidig):
- inline `style="..."` in views: 443
- inline `<style>` blokken in views: 5
- CSS-regels in hoofdstylesheets samen: 3701

Doelwaarden:
- inline `style="..."`: 443 -> <= 150 (tussenmijlpaal) -> <= 50 (einddoel)
- inline `<style>` blokken: 5 -> 0
- nieuwe UI-wijzigingen met tokens: 100%
- kernflows met geslaagde a11y testscript: 100%
- blocker visual regressions per release: 0
- codeverwijzingen naar `/design/*` buiten de designmap: 0 (blijft 0)

## 7. Uitvoeringschecklist (enige checklist)
- [x] MUST-01 Bronset in `/design` is final en frozen voor deze implementatieronde. (bewijs: `design/bronset_freeze_manifest.md`, datum: 2026-04-03)
- [x] MUST-02 Platform foundation staat: tokens geladen, fonts self-hosted, basistypografie en spacing uniform. (bewijs: `/public/css/tb-tokens.css`, `/public/css/tb-fonts.css`, `/public/css/tb-base.css`, `/public/fonts/tb-sans-regular.ttf`, `/public/fonts/tb-sans-bold.ttf`, `/src/views/layout/header.php`, datum: 2026-04-03)
- [x] MUST-03 Button hierarchy geformaliseerd in 1 gedeelde primitive-laag (`tb-button`, `tb-icon-button`, `tb-fab`, chip/segmented). (bewijs: `/public/css/tb-primitives.css`, `/src/views/layout/header.php`, `/src/views/tactics/index.php`, `/src/views/matches/live.php`, datum: 2026-04-03)
- [x] MUST-04 Technische migratieregels (sectie 5) zijn actief en worden niet geschonden bij nieuwe wijzigingen. (bewijs: `/scripts/check_must04.sh`, `/scripts/check_action_hierarchy.sh`, `/scripts/quality/must04_baseline.env`, `/scripts/quality/action_hierarchy_overviews.txt`, `/.github/workflows/regression-tests.yml`, datum: 2026-04-05)
- [x] MUST-05 Dashboard omgezet volgens matrix (geen create-FAB, rustiger CTA-hiërarchie, compacte acties). (bewijs: `/src/views/dashboard.php`, `/public/css/style.css`, datum: 2026-04-05)
- [x] MUST-06 Training Builder omgezet volgens matrix (FAB add, 1 duidelijke save, icon-acties op blokniveau). (bewijs: `/src/views/trainings/form.php`, `/public/css/style.css`, datum: 2026-04-03)
- [x] MUST-07 Team/Spelers omgezet volgens matrix (FAB add, compacte item-acties, expliciete commits). (bewijs: `/src/views/account/teams.php`, `/src/views/players/index.php`, `/src/views/players/create.php`, `/src/views/teams/create.php`, `/public/css/style.css`, datum: 2026-04-03)
- [x] MUST-08 Match Mode omgezet volgens matrix (hoge-impact commit acties expliciet, support compact). (bewijs: `/src/views/matches/live.php`, `/public/js/live-match.js`, `/public/css/style.css`, datum: 2026-04-03)
- [x] MUST-09 Account/Admin/Auth/Reports geharmoniseerd op dezelfde primitives en tokens. (bewijs: `/public/css/style.css`, `/src/views/account/index.php`, `/src/views/admin/index.php`, `/src/views/admin/users.php`, `/src/views/admin/teams.php`, `/src/views/admin/team_members.php`, `/src/views/admin/user_teams.php`, `/src/views/admin/edit_team.php`, `/src/views/admin/mail_settings.php`, `/src/views/admin/options.php`, `/src/views/admin/system.php`, `/src/views/login.php`, `/src/views/register.php`, `/src/views/matches/reports.php`, datum: 2026-04-05)
- [x] MUST-10 A11y testscript (6 checks) is uitgevoerd en geslaagd op alle kernflows. (bewijs: `/scripts/check_must10.sh`, `/scripts/quality/must10_core_flows.txt`, `/src/views/exercises/index.php`, `/public/css/tb-tokens.css`, `/.github/workflows/regression-tests.yml`, datum: 2026-04-05)
- [x] MUST-11 Visual regression baseline is vastgelegd voor must-schermen op 2 viewports. (bewijs: `/scripts/capture_must11_baseline.py`, `/scripts/prepare_must11_fixture.php`, `/scripts/check_must11.sh`, `/scripts/quality/must11_screens.txt`, `/scripts/quality/visual_baseline/*`, `/.github/workflows/regression-tests.yml`, datum: 2026-04-05)
- [x] MUST-12 Iedere domeinrelease heeft rollback-pad via kleine revertbare commits. (bewijs: rollback bestaande ronde via `git revert dca0240`, commitstrategie voor toekomstige wijzigingen vastgelegd in sectie 6.3, datum: 2026-04-05)
- [x] MUST-13 KPI-check: inline styles en inline style-blokken dalen volgens doelwaarden. (bewijs: inline `style="..."` 443 → 13, inline `<style>` 5 → 0, utility/component classes in `/public/css/style.css`, datum: 2026-04-05)
- [x] MUST-14 Verificatiecheck: buiten `/design` zijn 0 verwijzingen naar `/design/*` (map blijft verwijderbaar). (bewijs: grep + `scripts/check_must04.sh` bevestigen 0 runtime/build-refs; enige vermeldingen zijn `.gitignore`, `README.md` (docs) en het checkscript zelf, datum: 2026-04-06)
- [x] SHOULD-15 Opruimen van legacy/duplicatieve UI-klassen na afronding van alle must-items. (bewijs: 38 dode klassen verwijderd uit `style.css` (3886 → 3689 regels), 0 runtime-referenties gebroken, regressietests 14/14 groen, datum: 2026-04-06)
- [x] SHOULD-16 Extra harmonisatie van detailstaten (hover/focus/empty states) op minder kritieke pagina's. (bewijs: `:focus-visible` op links/forms/action-cards/dropdowns/multi-selects/match-items/speelwijze-items; 8 legacy klassen gemigreerd naar tb-primitives (`.alert`→`tb-alert`, `.card`→`tb-card`, `.action-card`→`tb-action-card`, `.modal`→`tb-modal`, `.dropdown`→`tb-dropdown`, `.multi-select-*`→`tb-multi-select-*`); `.tb-empty-state` component + 5 views geupgraded; `style.css` 3689→3451 regels, `tb-primitives.css` 337→605 regels; regressietests 14/14 groen, datum: 2026-04-06)
- [ ] COULD-17 Visuele polish (micro-animaties, extra cardvarianten) pas na stabiele must+should basis.

## 8. Definition of done
Dit plan is succesvol uitgevoerd als:
- alle `MUST` checklist-items afgevinkt zijn
- `SHOULD` alleen wordt opgepakt zonder regressie op de must-basis
- elke nieuwe UI-wijziging via tokens + primitives loopt
- kernflows visueel, functioneel en toegankelijk stabiel zijn
- `/design` verwijderd kan worden zonder regressie in runtime/build

---
Laatste update: 2026-04-06
