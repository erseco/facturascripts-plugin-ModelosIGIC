#!/bin/sh
# Valida ficheros .atc del plugin con el programa de ayuda OFICIAL del Modelo 420 de la ATC.
#
# Descarga el programa de ayuda multiplataforma del ejercicio indicado desde la web de la ATC,
# compila Test/atc/ValidarFicheroATC.java contra pa-mod420.jar y, con Java 8 en Docker,
# decodifica, valida e importa cada fichero con las clases del programa.
#
# Uso: Test/atc/validar.sh 2026 doc/ejemplos/*.atc
#      Test/atc/validar.sh 2025 fichero.atc
#
# Requiere Docker y curl. El programa de ayuda no se guarda en el repositorio.
set -eu

EJERCICIO="${1:?Indica el ejercicio: 2025 o 2026}"
shift
case "$EJERCICIO" in
    2025) PROGRAMA="m420v920e25-zip-1" ;;
    2026) PROGRAMA="m420v930e26-zip-1" ;;
    *) echo "No hay programa de ayuda verificado para el ejercicio $EJERCICIO" >&2; exit 2 ;;
esac

DIR_SCRIPT="$(cd "$(dirname "$0")" && pwd)"
TRABAJO="${TMPDIR:-/tmp}/modelosigic-atc-$EJERCICIO"
mkdir -p "$TRABAJO/ficheros"
rm -f "$TRABAJO/ficheros/"*.atc

if [ ! -f "$TRABAJO/pa-mod420.jar" ]; then
    curl -fsSL -o "$TRABAJO/programa.zip" \
        "https://www3.gobiernodecanarias.org/tributos/atc/documents/d/agencia-tributaria-canaria/$PROGRAMA"
    unzip -o -q "$TRABAJO/programa.zip" pa-mod420.jar -d "$TRABAJO"
fi

cp "$DIR_SCRIPT/ValidarFicheroATC.java" "$TRABAJO/"
for fichero in "$@"; do
    cp "$fichero" "$TRABAJO/ficheros/"
done

docker run --rm -v "$TRABAJO:/w" -w /w eclipse-temurin:8-jdk sh -c \
    'javac -proc:none -cp pa-mod420.jar -d . ValidarFicheroATC.java || exit 2
     java -cp pa-mod420.jar:. ValidarFicheroATC ficheros/*.atc > salida.txt 2>&1
     resultado=$?
     grep -v -E "^[[:space:]]+at |DEBUG| INFO " salida.txt
     exit $resultado'
