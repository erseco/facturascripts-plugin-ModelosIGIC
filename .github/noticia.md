# ModelosIGIC 1.0: modelos 420 y 425 del IGIC para FacturaScripts

Publicamos la primera versión de **ModelosIGIC**, un plugin gratuito que añade a FacturaScripts los
modelos del IGIC de la **Agencia Tributaria Canaria (ATC)**:

- **Modelo 420**: autoliquidación trimestral del IGIC.
- **Modelo 425**: declaración-resumen anual del IGIC.

Hasta ahora no había ningún plugin compatible con las versiones actuales de FacturaScripts para
estos modelos: el plugin original `modelos_420_425_canarias` era para FacturaScripts 2017.
ModelosIGIC es un fork y evolución de aquel plugin, reescrito para FacturaScripts 2025.7 o superior.

## Qué hace

- **Calcula las casillas** del IGIC devengado (ventas) y deducible (compras) a partir de las
  facturas registradas, agrupadas por tipo impositivo, y muestra el resultado a ingresar o a
  compensar del trimestre.
- **Indica el alcance de cada casilla**: si el plugin la calcula entera, en parte o si hay que
  completarla en el programa de ayuda de la ATC. No estima lo que no puede saber (prorrata, bienes
  de inversión, regímenes especiales, compensaciones de periodos anteriores...).
- **Crea la regularización y su asiento contable**, en una única operación: si algo falla, no se
  guarda nada.
- **Registra cada declaración** con su estado (borrador, presentada o rectificada), el número de
  referencia de la ATC, la fecha de presentación y las facturas incluidas, y permite crear
  declaraciones rectificativas.
- **Genera el fichero para el programa de ayuda de la ATC** del Modelo 420 (función
  **experimental**, ejercicios 2025 y 2026): se importa en el programa de ayuda oficial, que lo
  valida, y desde allí se presenta la declaración.

## Normativa vigente y con fuentes

El plugin aplica la normativa en vigor en 2026 y cada regla cita su fuente oficial:

- Tipos del IGIC según el art. 32.1 del **Decreto Legislativo 1/2025** (BOC n.º 207, de
  20/10/2025), con la redacción de la **Ley 9/2025** (BOC n.º 256, de 29/12/2025): 0 %, 1 %
  específico (desde el 1 de enero de 2026), 3 % superreducido, 5 % reducido, 7 % general,
  9,5 % y 15 % incrementados y 20 % especial.
- Periodos trimestrales y plazos de presentación según el art. 57 del **Decreto 268/2011**.
- Casillas según las instrucciones oficiales de los modelos 420 y 425 de la ATC.

Todas las referencias, y los puntos que aún están pendientes de verificar, están en
[NORMATIVA.md](https://github.com/erseco/facturascripts-plugin-ModelosIGIC/blob/main/doc/NORMATIVA.md).

## Sobre el fichero experimental

El formato del fichero se ha obtenido del programa de ayuda oficial de la ATC (versiones 9.2.0 y
9.3.0) y se ha comprobado con su propio validador, pero todavía no se ha usado en una presentación
real. Por eso se marca como experimental: revisa siempre la declaración en el programa de ayuda
antes de presentarla. La guía paso a paso está en
[VALIDAR_FICHERO_ATC.md](https://github.com/erseco/facturascripts-plugin-ModelosIGIC/blob/main/doc/VALIDAR_FICHERO_ATC.md).

## Requisitos

- FacturaScripts 2025.7 o superior y PHP 8.1 o superior.
- Empresa con los impuestos IGIC y el plan contable configurados.

El código es libre (AGPL-3.0) y está en
[GitHub](https://github.com/erseco/facturascripts-plugin-ModelosIGIC). Si encuentras un error, un
importe que no cuadra con el programa de ayuda de la ATC o un cambio normativo que falte, abre una
[issue](https://github.com/erseco/facturascripts-plugin-ModelosIGIC/issues); las contribuciones
mediante pull request también son bienvenidas.
