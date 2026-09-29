# ModelosIGIC para FacturaScripts

[![codecov](https://codecov.io/gh/erseco/facturascripts-plugin-ModelosIGIC/branch/main/graph/badge.svg)](https://codecov.io/gh/erseco/facturascripts-plugin-ModelosIGIC)

<a href="https://erseco.github.io/facturascripts-playground/?blueprint=https%3A%2F%2Fraw.githubusercontent.com%2Ferseco%2Ffacturascripts-plugin-ModelosIGIC%2Frefs%2Fheads%2Fmain%2Fblueprint.json">
  <img src="https://raw.githubusercontent.com/erseco/facturascripts-playground/main/ogimage.png" alt="Prueba ModelosIGIC en tu navegador" width="220">
</a><br>
<small><a href="https://erseco.github.io/facturascripts-playground/?blueprint=https%3A%2F%2Fraw.githubusercontent.com%2Ferseco%2Ffacturascripts-plugin-ModelosIGIC%2Frefs%2Fheads%2Fmain%2Fblueprint.json">Pruébalo en tu navegador</a></small>

**Modelo 420** (autoliquidación trimestral del IGIC) y **Modelo 425** (declaración-resumen anual
del IGIC) de la **Agencia Tributaria Canaria (ATC)** para FacturaScripts. Calcula el IGIC
devengado y deducible a partir de las facturas, genera el asiento de regularización y el fichero
para la presentación telemática.

<p align="center">
  <img src=".github/screenshot.png" alt="Regularización del Modelo 420 con el desglose del IGIC y el asiento generado" width="700">
</p>

## Origen

Este plugin es un **fork y evolución** de
[FacturaScripts/modelos_420_425_canarias](https://github.com/FacturaScripts/modelos_420_425_canarias),
el plugin original para FacturaScripts 2017 de Carlos García Gómez (NeoRazorX) y Francesc Pineda,
que dejó de ser compatible a partir de FacturaScripts 2018.

Se ha reescrito por completo para FacturaScripts moderno (2025.7 o superior), manteniendo la
licencia AGPL-3.0 y los créditos de los autores originales.

## Características

- **Modelo 420** (trimestral): cálculo del IGIC devengado (ventas) y deducible (compras) por
  tipo impositivo para el trimestre elegido.
- **Asiento de regularización**: previsualización y creación del asiento contable del
  trimestre.
- **Modelo 425** (anual): resumen del IGIC devengado y deducible del ejercicio.
- **Historial de declaraciones** con su estado (borrador, presentado, rectificado), número de
  referencia de la ATC, fecha de presentación y facturas incluidas.
- **Declaraciones rectificativas** a partir de una declaración ya presentada.
- **Fichero para la ATC**: descarga del fichero `.dec` para la presentación telemática en la
  sede electrónica.

## Uso

### Modelo 420

1. Ve a **Informes > Modelo 420**.
2. Pulsa **Nueva regularización**, elige el trimestre (o ajusta las fechas) y pulsa
   **Calcular** para ver la previsualización del asiento.
3. Pulsa **Guardar** para crear la regularización, el asiento contable y la declaración en
   borrador. Todo se guarda en una sola transacción: si algo falla no queda nada a medias.
4. Descarga el fichero `.dec` y preséntalo en la
   [sede electrónica de la ATC](https://sede.gobiernodecanarias.org/tributos/).
5. Marca la declaración como **presentada** indicando el número de referencia de la ATC.

### Modelo 425

1. Ve a **Informes > Modelo 425**.
2. Elige el ejercicio para ver el resumen anual del IGIC devengado y deducible.
3. Pulsa **Guardar** para registrar la declaración y, una vez presentada, márcala como
   **presentada**.

Las declaraciones guardadas se consultan en **Informes > Declaraciones IGIC**.

## Normativa

Las referencias a la normativa aplicada están en [`doc/NORMATIVA.md`](doc/NORMATIVA.md).
**La revisión de la normativa vigente está en curso**: antes de presentar una declaración,
comprueba los importes con el programa de ayuda de la ATC o con tu asesor.

## Instalación

1. Descarga el ZIP desde [Releases](../../releases/latest).
2. Ve a **Panel de Admin > Plugins** en FacturaScripts.
3. Sube el archivo ZIP y activa el plugin.

## Requisitos

- FacturaScripts **2025.7** o superior.
- PHP **8.1** o superior.
- Empresa con impuestos IGIC y plan contable configurados.

## Desarrollo

- `make upd` — arranca los contenedores Docker (FacturaScripts en <http://localhost:8081>)
- `make lint` — comprueba el estilo de código
- `make format` — corrige automáticamente el estilo
- `make test` — ejecuta los tests
- `make package VERSION=1.0` — genera el ZIP de distribución

## Créditos

- **Carlos García Gómez** (NeoRazorX) y **Francesc Pineda** — plugin original
  [modelos_420_425_canarias](https://github.com/FacturaScripts/modelos_420_425_canarias).
- **Ernesto Serrano** — reescritura para FacturaScripts moderno.

## Licencia

AGPL-3.0. Ver [LICENSE](LICENSE) para más detalles.
