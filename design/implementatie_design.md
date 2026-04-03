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
| Dashboard | 1 duidelijke create-FAB, minder concurrerende CTA's | chip-filters compacter | extra stat-card varianten |
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
- Overview create/add = FAB (1 dominante create-entry).
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
- Commitstrategie: kleine, terugdraai-bare commits per scherm/feature.
- Rollback: `git revert` op laatste domein-commit(s), geen brede resetacties.
- Alleen doorgaan naar volgend domein als `Must` + kwaliteitsgates groen zijn.

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
- [ ] MUST-02 Platform foundation staat: tokens geladen, fonts self-hosted, basistypografie en spacing uniform.
- [ ] MUST-03 Button hierarchy geformaliseerd in 1 gedeelde primitive-laag (`tb-button`, `tb-icon-button`, `tb-fab`, chip/segmented).
- [ ] MUST-04 Technische migratieregels (sectie 5) zijn actief en worden niet geschonden bij nieuwe wijzigingen.
- [ ] MUST-05 Dashboard omgezet volgens matrix (FAB create, rustiger CTA-hiërarchie, compacte acties).
- [ ] MUST-06 Training Builder omgezet volgens matrix (FAB add, 1 duidelijke save, icon-acties op blokniveau).
- [ ] MUST-07 Team/Spelers omgezet volgens matrix (FAB add, compacte item-acties, expliciete commits).
- [ ] MUST-08 Match Mode omgezet volgens matrix (hoge-impact commit acties expliciet, support compact).
- [ ] MUST-09 Account/Admin/Auth/Reports geharmoniseerd op dezelfde primitives en tokens.
- [ ] MUST-10 A11y testscript (6 checks) is uitgevoerd en geslaagd op alle kernflows.
- [ ] MUST-11 Visual regression baseline is vastgelegd voor must-schermen op 2 viewports.
- [ ] MUST-12 Iedere domeinrelease heeft rollback-pad via kleine revertbare commits.
- [ ] MUST-13 KPI-check: inline styles en inline style-blokken dalen volgens doelwaarden.
- [ ] MUST-14 Verificatiecheck: buiten `/design` zijn 0 verwijzingen naar `/design/*` (map blijft verwijderbaar).
- [ ] SHOULD-15 Opruimen van legacy/duplicatieve UI-klassen na afronding van alle must-items.
- [ ] SHOULD-16 Extra harmonisatie van detailstaten (hover/focus/empty states) op minder kritieke pagina's.
- [ ] COULD-17 Visuele polish (micro-animaties, extra cardvarianten) pas na stabiele must+should basis.

## 8. Definition of done
Dit plan is succesvol uitgevoerd als:
- alle `MUST` checklist-items afgevinkt zijn
- `SHOULD` alleen wordt opgepakt zonder regressie op de must-basis
- elke nieuwe UI-wijziging via tokens + primitives loopt
- kernflows visueel, functioneel en toegankelijk stabiel zijn
- `/design` verwijderd kan worden zonder regressie in runtime/build

---
Laatste update: 2026-04-03
