# Bronset Freeze Manifest - Implementatieronde 2026-04-03

Status: frozen  
Freeze-datum: 2026-04-03  
Scope: bronset uit sectie 2 van `design/implementatie_design.md`

## 1. Actieve bronset (frozen)
| Bestand | Rol | Status | Grootte (bytes) | SHA-256 |
|---|---|---|---:|---|
| `design/README.md` | pakketoverzicht | actief | 1076 | `63e95209f1aa96706326873b58aace3e0d9ef53c07bdb456bef01f64f3e978ac` |
| `design/trainer-bobby-design-system-v2.md` | hoofdbron interaction rules | actief | 10791 | `fe96f2cde12ec4b48718f777a00629537aff30ddbf17aa0cf59d04f1f9b5651b` |
| `design/trainer-bobby.tokens.v2.css` | primaire tokenbron | actief | 5132 | `cabe2e22f1b8a80e35bea50c9d3b53b148c4db79ec0ae2d2f309bb00bf32d698` |
| `design/trainer-bobby.tokens.v2.json` | token-spiegel voor validatie/sync | actief | 1528 | `24b6e9291b1344e2623a59dc8d3a3501d6b64cf9aea41691c4d1904a59e611e4` |
| `design/Trainer_Bobby_design_handoff.pptx` | merk en visuele richting | actief | 2946501 | `e1a0d61082f690af7834d2a2712c6d4c4f66f1af00dc7323ed37f6f1f56d3d06` |
| `design/Trainer_Bobby_component_sheet_v2.pptx` | component- en schermpatronen | actief | 398877 | `88752cfc3ae10a4e8f69a8ef6e7f9840b6bab8c890fbae7ddf36c447dae105f9` |
| `design/codex-implementation-prompt.md` | operationele prompt voor agent-workflow | optioneel | 3416 | `b58ef6b44638e171efbee6c60b5f04718b107c4a6dcbea938fb170d7fb928da9` |

## 2. Metadata-bestanden (geen implementatie-impact)
- `design/README.md:Zone.Identifier`
- `design/Trainer_Bobby_component_sheet_v2.pptx:Zone.Identifier`
- `design/Trainer_Bobby_design_handoff.pptx:Zone.Identifier`
- `design/codex-implementation-prompt.md:Zone.Identifier`
- `design/trainer-bobby-design-system-v2.md:Zone.Identifier`
- `design/trainer-bobby.tokens.v2.css:Zone.Identifier`
- `design/trainer-bobby.tokens.v2.json:Zone.Identifier`

## 3. Freeze-regels voor deze ronde
1. De actieve bronset hierboven is immutable tijdens deze implementatieronde.
2. Wijzigingen op frozen bestanden mogen alleen na expliciete freeze-break beslissing.
3. `design/implementatie_design.md` valt buiten de freeze voor checklist-voortgang en bewijslinks.
4. `/design` blijft referentie-only; runtime/build/app-imports naar `/design/*` blijven verboden.

## 4. Verificatie uitgevoerd op 2026-04-03
- Inventaris in `/design` komt overeen met de contextbronnen uit sectie 2 van het implementatieplan.
- Snelle codecheck op koppelingen buiten `/design`:
  - Command: `rg -n --hidden --glob '!design/**' '/design/' src public views scripts`
  - Resultaat: geen treffers.

## 5. Bewijs voor MUST-01
Deze file is het auditspoor voor checklist-item `MUST-01`.
