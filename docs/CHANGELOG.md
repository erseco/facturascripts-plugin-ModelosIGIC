# Changelog

Formato: [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).
FacturaScripts requiere versiones enteras o con un solo decimal, por lo que
la primera versión publicada es la `1.0` (etiqueta `1.0`).

## [Unreleased]

## [1.2] - 2026-10-03

### Security

- Las acciones que escriben datos (guardar, actualizar, eliminar, marcar como presentado, crear rectificativo y
  descargar el fichero) comprueban el permiso de modificación del usuario en los modelos 420 y 425; la vista ya no
  muestra sus botones a quien no lo tiene.
- El Modelo 420 solo abre las regularizaciones de la empresa activa.
- El IBAN ya no se guarda en la configuración, que no va cifrada; al actualizar se borra el que hubiera guardado.
- Los textos de las confirmaciones se escapan para JavaScript.

### Fixed

- No se puede eliminar una regularización si alguna de sus declaraciones se ha presentado, aunque la última sea una
  rectificativa en borrador.
- Las facturas de cada declaración guardan solo la base y la cuota de IGIC; las que no tienen IGIC quedan como no
  incluidas.
- La ficha de la declaración solo modifica el número de referencia y la fecha de presentación (antes se podía
  escribir cualquier estado y fallaba al guardar las declaraciones que no son rectificativas).
- La fecha de presentación debe ser válida y posterior al final del período (Decreto 268/2011, art. 57.6).
- Las facturas del período se leen una sola vez por carga de la página.
- `doc/NORMATIVA.md` y `doc/VALIDAR_FICHERO_ATC.md`, citados en el código, se incluyen en el paquete.
- El total de los modelos 420 trimestrales del 425 ya no suma las declaraciones rectificadas (#13).

### Added

- Aviso cuando el resultado de las subcuentas de IGIC no coincide con el de las facturas (asientos manuales).
- Comparativa histórica por ejercicios en el Modelo 425 y por trimestres en el Modelo 420 (#12).
- Modelo 425 en borrador: botones para actualizar los datos y para eliminarlo (#13).
- El listado del Modelo 420 y los trimestrales del 425 indican los modelos rectificativos (#13).

### Changed

- En el Modelo 425 el estado y los modelos 420 trimestrales se muestran antes que las casillas (#13).

## [1.1] - 2026-09-29

### Added

- Resultado de cada regularización en el listado del Modelo 420 y botón para actualizar las regularizaciones en
  borrador (#7).

## [1.0] - 2026-09-29

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

- Fichero del Modelo 420 para importar en el programa de ayuda de la ATC (**experimental**), con el formato
  del programa oficial (v9.3.0 para 2026 y v9.2.0 para 2025): XML según su esquema, importes sin punto decimal,
  compresión zlib y codificación UU idénticas a las del programa. Formulario para los datos que FacturaScripts no
  guarda (dirección fiscal, casillas 42–44, 46 y 47, compensar o devolver, forma de pago e IBAN), que se
  recuerdan por empresa.
- `doc/VALIDAR_FICHERO_ATC.md`, ficheros de ejemplo en `doc/ejemplos/` y `Test/atc/validar.sh`, que valida e
  importa ficheros con las clases del programa de ayuda oficial.

### Changed

- El 4T y el 425 se presentan durante todo enero (1–31), no del 1 al 30 (Decreto 268/2011, art. 57.6).
- El 420 solo admite trimestres naturales (Decreto 268/2011, art. 57.5); ya no se pueden elegir fechas libres.
- Las facturas se imputan al período por su fecha de devengo o, si no la tienen, por su fecha.
- Solo se calculan las líneas con impuestos de IGIC sin causa de exención.
- El recargo ya no se suma al IGIC devengado ni al deducible (Decreto Legislativo 1/2025, art. 70.Uno.a).
- El fichero `.dec` con formato propio se sustituye por el fichero `.atc` del programa de ayuda. El Modelo 425
  ya no genera fichero.

### Removed

- Los ficheros `.dec` de `Test/fixtures`, generados por el propio plugin, que no demostraban la compatibilidad
  con la ATC.
