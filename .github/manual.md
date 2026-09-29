# Manual de ModelosIGIC

ModelosIGIC añade a FacturaScripts el **Modelo 420** (autoliquidación trimestral del IGIC) y el
**Modelo 425** (declaración-resumen anual del IGIC) de la **Agencia Tributaria Canaria (ATC)**.

> La revisión de la normativa vigente está en curso. Antes de presentar una declaración,
> contrasta los importes con el programa de ayuda de la ATC o con tu asesor.

---

## Requisitos

- FacturaScripts **2025.7** o superior.
- PHP **8.1** o superior.
- Empresa con los **impuestos IGIC** y el **plan contable** configurados. El asiento de
  regularización usa las subcuentas especiales de impuestos repercutidos, soportados y de
  Hacienda Pública acreedora/deudora.

---

## Instalación

1. Descarga el plugin desde la forja o desde la página de *Releases* del repositorio.
2. En FacturaScripts ve a **Panel de Admin → Plugins**.
3. Sube el ZIP y pulsa **Activar**. Las tablas del plugin se crean automáticamente.

---

## Modelo 420 (trimestral)

1. Ve a **Informes → Modelo 420** y pulsa **Nueva regularización**.
2. Elige el **trimestre** y el **ejercicio** (las fechas son siempre las del trimestre natural) y pulsa
   **Calcular**. Verás la previsualización del asiento de regularización.
3. Pulsa **Guardar**. Se crea la regularización, el asiento contable y la declaración en estado
   *borrador* con las facturas incluidas. Al abrir la regularización verás las casillas del
   modelo, el resultado (casilla 45), el plazo de presentación y las partidas del asiento.

   Cada casilla indica si el plugin la **calcula**, si es **parcial** (una suma oficial en la que
   intervienen casillas que el plugin no calcula) o si **no la calcula**. Estas últimas (bienes de
   inversión, importaciones, prorrata, regímenes especiales, compensaciones de períodos
   anteriores...) hay que revisarlas y completarlas en el programa de ayuda de la ATC. Las líneas
   de factura que no son de IGIC o tienen una causa de exención se muestran aparte para que las
   revises. Las fuentes de cada regla están en `doc/NORMATIVA.md` del repositorio.

   El plugin no deja guardar si hay facturas del período sin asiento contable, si ya existe una
   regularización que se solapa con esas fechas o si faltan las subcuentas especiales de
   Hacienda Pública. En esos casos no se crea nada.
4. Descarga el fichero **.dec** y preséntalo en la
   [sede electrónica de la ATC](https://sede.gobiernodecanarias.org/tributos/).
5. Pulsa **Marcar como presentado** e indica el número de referencia de la ATC y la fecha de
   presentación.

### Eliminar una regularización

Mientras la declaración esté en *borrador* puedes pulsar **Eliminar**: se borran la
regularización, su asiento y la declaración. Una declaración presentada o rectificada no se
puede eliminar.

### Declaración rectificativa

Si una declaración ya presentada contiene un error, abre la regularización y pulsa **Crear
rectificativo**. La declaración original pasa a *rectificada* y se crea una nueva en *borrador*
con las mismas facturas, que puedes revisar antes de presentar.

---

## Modelo 425 (anual)

1. Ve a **Informes → Modelo 425** y elige el **ejercicio**.
2. Revisa las casillas del resumen anual: las que salen de las facturas del ejercicio, la casilla
   116 (ingresos de los modelos 420 registrados en el plugin) y las que hay que completar en el
   programa de ayuda de la ATC. El 425 se presenta junto con el 420 del cuarto trimestre.
3. Pulsa **Guardar** para registrar la declaración y **Marcar como presentado** cuando la presentes.

---

## Historial de declaraciones

En **Informes → Declaraciones IGIC** tienes todas las declaraciones guardadas con su tipo,
periodo, importes y estado. Desde la ficha de cada una puedes ver las facturas de venta y de
compra incluidas y descargar de nuevo el fichero para la ATC.

---

## Normativa

Las referencias a la normativa aplicada se recogen en `doc/NORMATIVA.md`, en el repositorio del
plugin.
