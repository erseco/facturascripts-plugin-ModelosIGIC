# Fixtures del fichero para el programa de ayuda del Modelo 420

| Fichero | Contenido |
|---------|-----------|
| `codificador-oficial.xml` | XML de ejemplo del Modelo 420 (caso «ingresar» de `Test/main/EjemplosFicheroATC.php`, datos ficticios). |
| `codificador-oficial.atc` | El mismo XML codificado **por el programa de ayuda oficial** (`org.grecasa.ext.codificador.Codificador.codifica()` de `pa-mod420.jar` v9.3.0, 2026), con `Test/atc/ValidarFicheroATC.java codifica`. |

`ATCFicheroOficialTest` comprueba que `ATCFileGenerator::codificar()` produce exactamente los
mismos bytes que el codificador oficial y que decodifica el fichero oficial.

Los ficheros `.dec` que había antes los generaba el propio plugin con un formato inventado y no
demostraban nada; se han eliminado. Los ejemplos para el usuario están en `doc/ejemplos/`.
