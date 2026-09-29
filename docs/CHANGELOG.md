# Changelog

Formato: [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).
FacturaScripts requiere versiones enteras o con un solo decimal, por lo que
la primera versión publicada será la `1.0` (etiqueta `1.0`).

## [Unreleased]

### Added

- Casillas oficiales de los modelos 420 y 425, con el estado de cada una (calculada, parcial o no calculada) según
  sus instrucciones de la ATC.
- Tipos de IGIC vigentes desde 2026: específico del 1 %, superreducido del 3 % y reducido del 5 % (Ley 9/2025 y
  Decreto Legislativo 1/2025, art. 32.1). Aviso para los tipos que no figuran en el art. 32.1.
- Plazo de presentación de cada trimestre y del resumen anual (Decreto 268/2011, arts. 57.6 y 57.8).
- Listado de las líneas que no entran en el cálculo: impuestos que no son IGIC y líneas con causa de exención.
- `doc/NORMATIVA.md` reescrito con las normas vigentes, sus enlaces al BOC y al BOE, la correspondencia entre el
  código y la norma y la lista de puntos pendientes de verificar.
- Instrucciones oficiales del modelo 425 y manual del programa de ayuda del 420 de 2026 en `doc/`.

### Changed

- El 4T y el 425 se presentan durante todo enero (1–31), no del 1 al 30 (Decreto 268/2011, art. 57.6).
- El 420 solo admite trimestres naturales (Decreto 268/2011, art. 57.5); ya no se pueden elegir fechas libres.
- Las facturas se imputan al período por su fecha de devengo o, si no la tienen, por su fecha.
- Solo se calculan las líneas con impuestos de IGIC sin causa de exención.
- El recargo ya no se suma al IGIC devengado ni al deducible (Decreto Legislativo 1/2025, art. 70.Uno.a).
