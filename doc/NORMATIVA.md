# Normativa de los modelos 420 y 425 del IGIC

Este documento recoge la normativa en la que se basa cada regla del plugin. La regla del proyecto es **no inventar
nada**: todo tipo, casilla, plazo o criterio que aplica el código cita aquí su fuente oficial (BOC, BOE o
documentación de la Agencia Tributaria Canaria). Lo que no se ha podido verificar figura en
[Pendiente de verificar](#pendiente-de-verificar) y el plugin no lo calcula o lo marca en pantalla.

Fecha de la última revisión: 29/09/2026.

Abreviaturas:

- **TR IGIC**: texto refundido de las normas legales de la Comunidad Autónoma de Canarias sobre el IGIC y el AIEM,
  aprobado por el Decreto Legislativo 1/2025.
- **RG**: Reglamento de gestión de los tributos derivados del REF de Canarias, aprobado por el Decreto 268/2011.
- **ATC**: Agencia Tributaria Canaria.

## Normas vigentes

| Norma | Publicación | Qué regula para los modelos 420 y 425 |
|---|---|---|
| [Ley 20/1991](https://www.boe.es/buscar/act.php?id=BOE-A-1991-14463), de 7 de junio, de modificación de los aspectos fiscales del REF de Canarias | BOE (BOE-A-1991-14463) | Ley del IGIC: devengo (art. 18), inversión del sujeto pasivo (art. 19.1.2.º), modificación de bases (art. 22), rectificación de deducciones (art. 44) |
| [Decreto Legislativo 1/2025](https://www.gobiernodecanarias.org/boc/2025/207/3598.html), de 13 de octubre, texto refundido del IGIC y el AIEM ([consolidado](https://www.gobiernodecanarias.org/libroazul/pdf/79559.pdf)) | BOC n.º 207, de 20/10/2025. En vigor desde el 21/10/2025 (disposición final única) | Tipos de gravamen (arts. 32 a 41) y regímenes especiales (arts. 42 y siguientes). Deroga la parte del IGIC de la Ley 4/2012 (disposición derogatoria única, a) |
| Resolución de 19/11/2025, [tabla de correspondencias](https://sede.gobiernodecanarias.org/boc/boc-a-2025-239-4131.pdf) entre la Ley 4/2012 y el TR IGIC | BOC n.º 239, de 02/12/2025 | Correspondencia informativa de artículos |
| [Ley 9/2025](https://www.gobiernodecanarias.org/boc/2025/256/4414.html), de 23 de diciembre, de Presupuestos Generales de la CAC para 2026 | BOC n.º 256, de 29/12/2025 | Disposición final novena: modifica el TR IGIC con efectos desde el 01/01/2026 y crea el tipo específico del 1 % |
| [Decreto 268/2011](http://www.gobiernodecanarias.org/boc/2011/159/001.html), de 4 de agosto, Reglamento de gestión ([consolidado](https://www.gobiernodecanarias.org/libroazul/pdf/65872.pdf)) | BOC n.º 159, de 12/08/2011, con sus modificaciones posteriores | Autoliquidaciones periódicas, período de liquidación, plazos y declaración-resumen anual (art. 57). Libros registro por el SII (art. 49.5) |
| [Resolución de 15/01/2020](https://www.gobiernodecanarias.org/boc/2020/016/001.html) de la ATC, que modifica los modelos 417, 418 y 420 | BOC n.º 16, de 24/01/2020 | Modelo 420 vigente publicado |
| [Resolución de 18/11/2024](https://www.gobiernodecanarias.org/boc/2024/241/4002.html) de la ATC, que modifica el modelo 425 | BOC n.º 241, de 03/12/2024 | Modelo 425 vigente, para el año 2024 y siguientes |
| [Resolución de 22/03/2024](https://www.gobiernodecanarias.org/boc/2024/064/002.html) de la ATC | BOC n.º 64, de 01/04/2024 | Obligación de presentación telemática del 420 y el 425 y sus excepciones |
| [Orden de 28/05/2015](https://www.gobiernodecanarias.org/boc/2015/107/003.html) | BOC n.º 107, de 05/06/2015 | Domiciliación bancaria y plazos de presentación con domiciliación |
| [Ley 4/2012](https://www.boe.es/buscar/act.php?id=BOE-A-2012-9282), de 25 de junio, de medidas administrativas y fiscales | BOE-A-2012-9282 | Regulaba el IGIC hasta el 20/10/2025. Su parte del IGIC está derogada por el DL 1/2025 |
| [Ley 19/1994](https://www.boe.es/buscar/act.php?id=BOE-A-1994-15794), de 6 de julio, de modificación del REF | BOE-A-1994-15794 | Exenciones de los arts. 25 y 47 (casilla 46 del 420; casillas 124 y 135 del 425) |
| [Real Decreto 2538/1994](https://www.boe.es/buscar/act.php?id=BOE-A-1994-28972), de 29 de diciembre | BOE-A-1994-28972 | Normas de desarrollo del IGIC |

### Documentación oficial de la ATC

- [Ficha del modelo 420](https://www3.gobiernodecanarias.org/tributos/atc/w/modelo-420) y
  [versiones del programa de ayuda](https://www3.gobiernodecanarias.org/tributos/atc/w/modelo-420-versiones-programa-de-ayuda).
- [Ficha del modelo 425](https://www3.gobiernodecanarias.org/tributos/atc/w/modelo-425).
- [Instrucciones del modelo 420](https://www3.gobiernodecanarias.org/tributos/atc/documents/65729/201004/Instrucciones_modelo_420.pdf/59fbc2b2-7aff-4a68-95ea-4984da563cfc?t=1742821063325):
  copia en [`Instrucciones_modelo_420.pdf`](Instrucciones_modelo_420.pdf), idéntica a la publicada (SHA-1
  `e055a814748ac226a295176652cf59944899f935`).
- [Instrucciones del modelo 425](https://www3.gobiernodecanarias.org/tributos/atc/documents/65729/204267/Instrucciones_modelo_425.pdf/cf497b3d-275e-0bb3-4ffc-67fc70b0ade0?t=1742821251705):
  copia en [`Instrucciones_modelo_425.pdf`](Instrucciones_modelo_425.pdf).
- Manual del programa de ayuda del modelo 420, versión 9.3.x de 16/02/2026: copia en
  [`Manual_Modelo_420.pdf`](Manual_Modelo_420.pdf), extraída del programa de ayuda de 2026 (`m420v930e26`)
  publicado en la ficha del modelo 420.
- [Sede electrónica de la ATC](https://sede.gobiernodecanarias.org/tributos/) y
  [trámite de presentación del 420](https://sede.gobiernodecanarias.org/sede/tramites/4015).
- [Contacto y ayuda de la ATC](https://www3.gobiernodecanarias.org/tributos/atc/contacto-y-ayuda).

## Tipos de gravamen

TR IGIC, art. 32.1, en la redacción de la Ley 9/2025 (efectos desde el 01/01/2026):

> «El tipo general en el impuesto general indirecto canario es el 7% [...] a) El tipo cero [...] b) El tipo
> específico del 1% [...] c) El tipo superreducido del 3% [...] d) El tipo reducido del 5% [...] e) El tipo
> incrementado del 9,5% [...] f) El tipo incrementado del 15% [...] g) El tipo especial del 20% [...]»

| Tipo | % | Artículo del TR IGIC | Antes en la Ley 4/2012 (tabla BOC n.º 239/2025) |
|---|---|---|---|
| Cero | 0 | art. 33 | art. 52 |
| Específico | 1 | art. 33 bis (desde el 01/01/2026) | — |
| Superreducido | 3 | art. 34 | art. 54 («reducido del 3 por ciento») |
| Reducido | 5 | art. 35 | art. 54 bis («reducido del 5 %») |
| General | 7 | art. 32.1 | art. 51 |
| Incrementado | 9,5 | arts. 39, 40 y 41 | arts. 59 a 61 |
| Incrementado | 15 | art. 36 | art. 56 |
| Especial | 20 | art. 37 | art. 57 |

- La exposición de motivos del DL 1/2025 explica el cambio de nombres: «se ha optado por denominar al tipo del 5
  por ciento como tipo reducido y al tipo del 3 por ciento como tipo superreducido».
- El tipo aplicable a cada operación es el vigente en el momento del devengo (TR IGIC, art. 32.3).
- Los tipos 6,5 % y 13,5 % que incluye FacturaScripts entre sus impuestos de IGIC no figuran en el art. 32.1
  vigente. Si aparecen en un período, el plugin los muestra con el aviso «Tipo no previsto».

## Período de liquidación y plazos

- **El 420 es trimestral.** RG art. 57.5: «El periodo de liquidación coincidirá con el trimestre natural». El
  período es mensual para las grandes empresas, los inscritos en el REDEME, el régimen de grupo de entidades y
  quienes llevan los libros registro por el SII (art. 57.5, letras a a d); todos ellos llevan los libros por el
  SII (art. 49.5) y presentan el modelo 417 (o el 418, el grupo de entidades), no el 420. La ficha del 420 dice:
  «El Modelo 420 es una autoliquidación de periodicidad trimestral».
- **Plazos** (RG art. 57.6): «durante los veinte primeros días naturales del mes siguiente al correspondiente
  periodo de liquidación trimestral, salvo la autoliquidación correspondiente al último periodo trimestral del año
  que deberá presentarse durante el mes de enero del año siguiente».

| Trimestre | Plazo | Con domiciliación (ficha ATC del 420) |
|---|---|---|
| 1T (enero–marzo) | 1–20 de abril | 1–15 de abril |
| 2T (abril–junio) | 1–20 de julio | 1–15 de julio |
| 3T (julio–septiembre) | 1–20 de octubre | 1–15 de octubre |
| 4T (octubre–diciembre) | 1–31 de enero | 1–20 de enero |

- La ficha del 420 añade: «Si la fecha de presentación del modelo coincide con un día inhábil, el plazo quedará
  ampliado hasta el siguiente día hábil». El plugin muestra el plazo general y esa advertencia, pero no calcula
  días inhábiles.
- **El 425** se presenta «conjuntamente con la autoliquidación correspondiente al último período de liquidación
  de cada año» (RG art. 57.8), es decir, en enero. No están obligados quienes llevan los libros registro por el
  SII (RG art. 57.8, en relación con el art. 49.5).
- Presentación telemática obligatoria del 420, salvo las personas físicas que solo arriendan inmuebles con un
  volumen no superior a 50.000 € (Resolución de 22/03/2024, apartado segundo.2). Las instrucciones del 420 de 2020
  todavía mencionan solo a las sociedades anónimas y limitadas.

## Imputación de las facturas al período

- El 420 declara el IGIC **devengado** en el período. Las instrucciones remiten a «la regla general de devengo
  contenida en el artículo 18 de la Ley 20/1991» (instrucciones del 420, apartado 9) y las casillas 21 y 22
  hablan de «operaciones devengadas con anterioridad al período».
- El plugin toma la **fecha de devengo** de la factura (`fechadevengo`) y, si no la tiene, su fecha. Es el mismo
  criterio que usa FacturaScripts para fechar el asiento contable de la factura, del que sale la regularización
  contable.

## Qué facturas y líneas entran en el cálculo

- Solo las líneas cuyo impuesto tiene la operación **IGIC** de FacturaScripts (`ES_03`) y no tienen causa de
  exención. Las líneas con otro impuesto (por ejemplo, IVA) o con causa de exención se muestran aparte, en
  «Líneas que no entran en el cálculo».
- Las causas de exención de FacturaScripts citan artículos de la Ley del IVA, no de la Ley 20/1991 ni de la
  Ley 19/1994. Por eso el plugin no las asigna a las casillas 46 y 47 del 420 ni a las operaciones específicas
  del 425: hay que revisarlas.
- Los suplidos no forman parte de la base imponible y no se incluyen, igual que en los totales de la factura.
- **Recargo**: no se suma al IGIC devengado ni al deducible. En el IGIC el único recargo es el del régimen
  especial de comerciantes minoristas, que grava las importaciones y cuya «liquidación y recaudación [...] se
  efectuará conjuntamente con el impuesto general indirecto canario que grave las importaciones de bienes» (TR
  IGIC, art. 70.Uno.a). No tiene casilla en el modelo 420. Si una factura tiene recargo, el plugin lo avisa.

## Casillas del modelo 420

Fuente: instrucciones oficiales del modelo 420, apartado 7 («Liquidación») y apartados 8 y 9. Estado en el
plugin: **C** calculada, **P** parcial (suma oficial con casillas que el plugin no calcula), **N** no calculada.

| Casillas | Concepto | Estado | Cálculo del plugin |
|---|---|---|---|
| 01–18 | Base, tipo y cuota de cada tipo de gravamen aplicado, incluido el tipo cero | C | Una fila por tipo de las ventas con IGIC, ordenadas de menor a mayor tipo |
| 16b–18c | Filas adicionales del programa de ayuda de 2026 | C | Solo si hay más de seis tipos; ver pendiente 1 |
| 19–20 | Inversión del sujeto pasivo (art. 19.1.2.º Ley 20/1991) | N | |
| 21–22 | Modificación de bases y rectificación de cuotas repercutidas | N | |
| 23–24 | Devoluciones del régimen de viajeros | N | |
| 25 | Total devengado = 03+06+09+12+15+18+20+22−24 | P | Suma de las cuotas de las filas 01–18 (y 18b, 18c) |
| 26–27 | Operaciones interiores, bienes y servicios corrientes | C | Base y cuota de todas las compras con IGIC; ver pendiente 4 |
| 28–33 | Bienes de inversión e importaciones | N | FacturaScripts no distingue estas compras |
| 34–35 | Rectificación de deducciones (art. 44 Ley 20/1991) | N | |
| 36 | Compensaciones del régimen de agricultura, ganadería y pesca | N | |
| 37–39 | Regularizaciones (bienes de inversión, inicio de actividad, prorrata definitiva) | N | |
| 40 | Total deducible = 27+29+31+33+35+36+37+38+39 | P | Casilla 27 |
| 41 | 25 − 40 | P | |
| 42 | Regularización art. 22.8.5.ª Ley 20/1991 | N | |
| 43 | Cuotas a compensar de períodos anteriores | N | |
| 44 | A deducir, solo en complementarias | N | |
| 45 | Resultado = 41+42−43−44 | P | Casilla 41 |
| 46–47 | Información adicional (exportaciones y exentas; no sujetas e inversión del sujeto pasivo) | N | |
| 48–51 | Régimen especial del criterio de caja | N | |

**Tipo de resultado** (instrucciones, apartados 3 a 5): a ingresar si la casilla 45 es positiva; a compensar si
es negativa o cero; en el cuarto trimestre, si es negativa, se puede optar por la devolución. «Sin actividad»
cuando no se ha devengado ni soportado cuota alguna; el plugin solo lo propone si no hay ninguna operación con
IGIC en el período.

**Deducciones** (instrucciones, apartado 7): las cuotas se consignan «después de aplicar la regla de prorrata en
los casos en que así proceda». El plugin no aplica prorrata: ver pendiente 4.

## Casillas del modelo 425

Fuente: instrucciones oficiales del modelo 425, apartados 5, 7, 8 y 9.

| Casillas | Concepto | Estado | Cálculo del plugin |
|---|---|---|---|
| 01–18 | Régimen ordinario: bases, tipos y cuotas de cada tipo vigente en el ejercicio | C | Una fila por tipo de las ventas con IGIC del año |
| 18 bis | Fila adicional citada en las instrucciones («casillas de la 01 a 18 bis») | — | Sin numerar; ver pendiente 6 |
| 19–73 | Regímenes especiales (bienes usados, arte, criterio de caja, agencias de viajes) y modificaciones de bases | N | |
| 74 | Total bases = 01+04+…+16+19+…+67+70−72 | P | Suma de las bases de las filas 01–18 |
| 75–78 | Inversión del sujeto pasivo y régimen de viajeros | N | |
| 79 | Total cuotas devengadas = 03+06+…+18+21+…+71−73+76−78 | P | Suma de las cuotas de las filas 01–18 |
| 80–81 | Deducible en operaciones interiores corrientes | C | Base y cuota de todas las compras con IGIC del año |
| 82–93 | Resto de deducciones y regularizaciones | N | |
| 94 | Total deducible = 81+83+85+87+89+90+91+92+93 | P | Casilla 81 |
| 95 | Resultado régimen general = 79 − 94 | P | |
| 96–111 | Régimen simplificado | N | |
| 112 | Regularización art. 22.8.5.ª Ley 20/1991 | N | |
| 113 | Suma de resultados = 95 + 111 | P | Casilla 95 |
| 114 | A compensar del ejercicio anterior | N | |
| 115 | Resultado de la liquidación anual = 112 + 113 − 114 | P | Casilla 113 |
| 116 | Total de ingresos de las autoliquidaciones del ejercicio | P | Suma de los resultados a ingresar de los 420 registrados en el plugin, sin los rectificados |
| 117–119 | Devoluciones del REDEME; resultado a compensar o a devolver del último período | N | La opción entre compensar y devolver la elige el declarante |
| 120 | Operaciones en régimen general, sin incluir el IGIC | P | Casilla 74; el plugin supone que todas las ventas con IGIC son del régimen general |
| 121–147 | Operaciones específicas, informativas y régimen del pequeño empresario o profesional | N | |

## Correspondencia código ↔ norma

| Código | Regla | Fuente |
|---|---|---|
| `IGICHelper::nombreTipoIGIC()`, `claveTipoIGIC()`, `esTipoVigente()` | Denominación y vigencia de cada tipo según la fecha | TR IGIC art. 32.1 (Ley 9/2025); exposición de motivos y disposición final única del DL 1/2025 |
| `IGICHelper::FECHA_TEXTO_REFUNDIDO` | 21/10/2025 | DL 1/2025, disposición final única; BOC n.º 207 de 20/10/2025 |
| `IGICHelper::FECHA_TIPO_ESPECIFICO` | 01/01/2026 | Ley 9/2025, disposición final novena |
| `IGICHelper::PERIODOS_420`, `Modelo420::setPeriodo()` | Solo trimestres naturales | RG art. 57.5; ficha ATC del 420 |
| `IGICHelper::plazoPresentacion()` | 1–20 del mes siguiente; 4T y 425 durante enero | RG arts. 57.6 y 57.8 |
| `IGICHelper::wherePeriodo()` | Imputación por fecha de devengo | Ley 20/1991 art. 18; instrucciones del 420, apartados 7 y 9 |
| `IGICHelper::analizar()`, `esLineaIGIC()` | Solo líneas de IGIC sin causa de exención | Instrucciones del 420, apartado 7 (casillas 01–18) |
| `IGICHelper::calcularTotalDevengado()`, `calcularTotalDeducible()` | Sin recargo | TR IGIC art. 70.Uno.a |
| `CasillasModelo420` | Casillas 01–18, 25–27, 40, 41 y 45; tipo de resultado | Instrucciones del 420, apartados 3 a 5 y 7 |
| `CasillasModelo420::FILAS_DEVENGADO` (16b–18c) | Filas adicionales | Manual del programa de ayuda 2026 (pendiente 1) |
| `CasillasModelo425` | Casillas 01–18, 74, 79–81, 94, 95, 113, 115, 116 y 120 | Instrucciones del 425, apartados 5, 7, 8 y 9 |
| `Modelo425` | Plazo y obligados | RG arts. 57.6, 57.8 y 49.5 |

## Pendiente de verificar

1. **Filas 16b–18c y casilla 25 del programa 2026.** El programa de ayuda del 420 de 2026 (v9.3.0) y su manual de
   16/02/2026 añaden las filas 16b–18b y 16c–18c y calculan la casilla 25 como
   «03+06+09+12+15+18+18b+18c+20+22», sin restar la 24. No se ha localizado la resolución del BOC que ampare el
   cambio: la última publicada del 420 es la de 15/01/2020. El plugin usa esas filas solo si hay más de seis tipos
   y las marca en pantalla.
2. **Régimen de viajeros (casillas 23 y 24).** Figura en las instrucciones de 2020, pero el manual de 2026 ya no
   lo incluye en la casilla 25. No se ha verificado si ha desaparecido del modelo.
3. **Fichero de presentación (`.dec`).** La ATC no publica una especificación. El formato que genera el plugin se
   revisa en una fase posterior.
4. **Bienes de inversión, importaciones y prorrata.** FacturaScripts no distingue las compras de bienes de
   inversión ni las importaciones, ni aplica la regla de prorrata. El plugin consigna todo el IGIC soportado en
   las casillas 26–27 (420) y 80–81 (425) sin prorratear; hay que revisarlo si procede.
5. **Deducción de las cuotas soportadas en el período.** El plugin imputa las compras por su fecha de devengo,
   como las ventas. No se ha verificado en la Ley 20/1991 el período en que puede ejercerse el derecho a deducir.
6. **Fila «18 bis» del 425.** Las instrucciones citan «casillas de la 01 a 18 bis» sin detallar su numeración. El
   plugin solo numera las filas 01–18 y avisa si hay más tipos.
7. **Modelo 425 de 2026** (a presentar en enero de 2027): no hay programa de ayuda publicado. No se sabe si tendrá
   filas para los tipos del 1 % y del 5 %.
8. **Denominaciones anteriores al 21/10/2025.** Para períodos anteriores al DL 1/2025 el plugin solo muestra el
   porcentaje de cada tipo, sin su denominación en la Ley 4/2012.
9. **Tipos 1 % y 5 % en FacturaScripts.** El núcleo no incluye impuestos de IGIC del 1 % ni del 5 %; hay que
   crearlos con la operación IGIC para usarlos.
10. **Domiciliación.** Los plazos con domiciliación salen de la ficha del 420; no se ha podido extraer el anexo I
    de la Orden de 28/05/2015.
