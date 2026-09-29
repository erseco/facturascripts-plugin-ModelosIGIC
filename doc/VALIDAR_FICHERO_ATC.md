# Validar el fichero del Modelo 420 en el programa de ayuda de la ATC

El fichero `.atc` que genera el plugin es **experimental**. Su formato sale del programa de ayuda oficial
(ver `doc/NORMATIVA.md`, «Formato del fichero») y se ha comprobado con las clases del propio programa, pero
todavía no se ha usado para presentar una declaración real. Esta guía explica cómo comprobarlo.

Las referencias entre paréntesis son apartados del manual oficial del programa de ayuda del 420
(`doc/Manual_Modelo_420.pdf`, versión de 16/02/2026).

## 1. Descargar el programa de ayuda del ejercicio

1. Entra en la [ficha del modelo 420](https://www3.gobiernodecanarias.org/tributos/atc/w/modelo-420) de la ATC y
   descarga el programa de ayuda **del mismo ejercicio que la declaración**. Cada programa solo importa las
   declaraciones de su ejercicio.
2. La versión multiplataforma es un `.zip` con `pa-mod420.jar`. Extráelo y ábrelo con doble clic; necesita
   Java instalado, según las instrucciones que acompañan al programa.

## 2. Generar el fichero en FacturaScripts

1. Abre la regularización del trimestre en **Informes > Modelo 420**.
2. Pulsa **Fichero para el programa de ayuda** y completa los datos que FacturaScripts no guarda:
   - Datos identificativos: NIF, apellidos y nombre o razón social, sigla y nombre de la vía, códigos de
     provincia y municipio, y código postal.
   - Casillas 42, 43, 44, 46 y 47, si proceden.
   - Si el resultado es negativo, si se compensa o, en el 4T, se devuelve.
   - Forma de pago e IBAN, si corresponden.
3. Descarga el fichero `NIF-número.atc`. Los datos identificativos y de pago se recuerdan para el trimestre
   siguiente.

## 3. Importarlo y revisarlo en el programa de ayuda

1. En el programa de ayuda usa la opción **Importar declaraciones** (manual, apartado 3.10) y elige el fichero
   `.atc`. El programa confirma la importación de cada fichero.
2. Abre la declaración importada con **Archivo > Abrir** (apartado 3.5).
3. Revisa el **área de errores** (apartado 3.18): la declaración es correcta si no hay errores graves (icono
   rojo). Las advertencias (triángulo amarillo) no impiden continuar.
4. Completa en el programa las casillas que el plugin no calcula: 19–24 y 28–39 (inversión del sujeto
   pasivo, modificación de bases, bienes de inversión, importaciones, regularizaciones y prorrata) y 48–51
   (régimen especial del criterio de caja).
5. Comprueba los importes con tu contabilidad o con tu asesor y presenta la declaración desde el programa de
   ayuda (apartados 3.13 y 3.14).

## 4. Informar del resultado

Si la importación falla o el programa muestra errores que no esperabas, abre una incidencia en el repositorio
con el mensaje del programa, el ejercicio y el período. No adjuntes ficheros con datos reales.

## Ficheros de ejemplo

`doc/ejemplos/` contiene ficheros del ejercicio 2026 generados por el plugin con **datos ficticios**: la empresa
«Empresa de Ejemplo Canarias, S.L.» con NIF `B00000000` y dirección inventada. Cada caso tiene el `.atc` y el XML
que contiene:

| Fichero | Caso |
|---|---|
| `modelo420-2026-ingresar` | 1T, ventas al 3 % y al 7 %, compras al 7 %; resultado a ingresar (480,00 €) con pago telemático |
| `modelo420-2026-compensar` | 2T, más IGIC deducible que devengado; resultado a compensar (140,00 €) |
| `modelo420-2026-devolver` | 4T, con una fila al tipo cero; resultado a devolver (140,00 €) con un IBAN de ejemplo |
| `modelo420-2026-sin-actividad` | 3T sin operaciones; declaración sin actividad |

Los cuatro se validaron y se importaron con las clases del programa de ayuda de 2026 mediante
`Test/atc/validar.sh 2026 doc/ejemplos/*.atc`. Ese script descarga el programa oficial y necesita Docker.
