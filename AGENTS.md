# AGENTS.md — Instructions for coding agents

This is the authoritative instruction file for any coding agent working in this repository.
Other agent files (`CLAUDE.md`, `GEMINI.md`, `.github/copilot-instructions.md`) should defer
to this one.

## Project overview

**ModelosIGIC** is a FacturaScripts plugin for the Canary Islands indirect tax (IGIC, Impuesto
General Indirecto Canario). It computes and records the **Modelo 420** (quarterly IGIC
self-assessment) and the **Modelo 425** (annual IGIC summary) of the Agencia Tributaria Canaria
(ATC), creates the accounting regularisation entry and generates the file used for electronic
filing.

It is a fork and evolution of
[FacturaScripts/modelos_420_425_canarias](https://github.com/FacturaScripts/modelos_420_425_canarias)
(FacturaScripts 2017), rewritten for modern FacturaScripts. Keep the original authors' copyright
lines in file headers.

Compatibility: **FacturaScripts 2025.7+**, **PHP 8.1+**, **PSR-12**. License: **AGPL-3.0**
(inherited from upstream — do not relicense).

## Golden rules

- **Never invent tax rules.** Every tax fact (rates, boxes/casillas, deadlines, file layout)
  must come from an official source (BOC, BOE, ATC) and be referenced in
  `doc/NORMATIVA.md`. If a rule cannot be verified, leave it marked as pending instead of
  guessing.
- **Reuse FacturaScripts APIs** — accounting entries (`Asiento`, `Partida`), tax
  regularisation (`RegularizacionImpuesto`), invoices and taxes from the core.
- **Never modify core/vendor files.** This is a plugin.
- Prefer **small, maintainable changes**. Avoid overengineering and unnecessary dependencies.
- **Translations**: every user-facing string goes through `trans()` / `Tools::lang()->trans()`
  with keys defined in `Translation/es_ES.json`.
- Keep the **GitHub Actions** workflows consistent with the author's other plugin repositories
  (ScheduledMail, ServiceRenewals, AiScan…).
- Pull requests must target **`erseco/facturascripts-plugin-ModelosIGIC`**, never the upstream
  `FacturaScripts/modelos_420_425_canarias` (this repository is a GitHub fork).

## Architecture

| Path | Purpose |
|---|---|
| `Controller/Modelo420.php` + `View/Modelo420.html.twig` | Quarterly self-assessment: compute, save regularisation, mark as filed, create corrective declaration, download filing file |
| `Controller/Modelo425.php` + `View/Modelo425.html.twig` | Annual summary for a fiscal year |
| `Controller/ListDeclaracionIGIC.php` / `EditDeclaracionIGIC.php` | History of saved declarations and the invoices included in each one |
| `Model/DeclaracionIGIC.php` + `Table/declaraciones_igic.xml` | Saved declaration (type 420/425, period, totals, status) |
| `Model/DeclaracionIGICFactura.php` + `Table/declaraciones_igic_facturas.xml` | Invoices included in a declaration |
| `Lib/IGICHelper.php` | IGIC calculation helpers |
| `Lib/ATCFileGenerator.php` | Experimental `.atc` file of the Modelo 420 for the ATC help program (format taken from the official program; see `doc/NORMATIVA.md`) |
| `Lib/ListasATC.php` | Official code lists of the help program (street types, Canary municipalities) |
| `doc/` | Official reference documents and `NORMATIVA.md` (excluded from releases) |

## Validation workflow

Run these (Docker required, `make upd` starts the container automatically):

```bash
make format   # PHP CS Fixer – auto-fix style
make lint     # PHPCS – must be clean
make test     # PHPUnit – all tests must pass
```

Unit tests live in `Test/main/`; `.dec` fixtures in `Test/fixtures/`. CI runs the tests on
PHP 8.1–8.5 and uploads coverage to Codecov.
