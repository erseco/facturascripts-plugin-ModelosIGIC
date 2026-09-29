/*
 * This file is part of ModelosIGIC plugin for FacturaScripts.
 * Copyright (C) 2026 Ernesto Serrano <info@ernesto.es>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

import java.io.File;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Paths;
import java.util.ArrayList;
import java.util.List;
import org.grecasa.ext.codificador.Codificador;
import org.grecasa.ext.pa.mod420.logica.dto.Declaracion;
import org.grecasa.ext.pa.mod420.logica.managers.ObjectUtils;
import org.grecasa.ext.pa.mod420.logica.managers.impl.GestorDeclaracionesImpl;
import org.grecasa.ext.pa.mod420.logica.managers.impl.ModuloPA420Impl;
import org.grecasa.ext.pa.mod420.logica.xml.jaxb.DEC;
import org.grecasa.ext.pa.tributos.logica.dto.Mensaje;

/**
 * Comprueba ficheros .atc del plugin con las clases del programa de ayuda oficial del 420.
 *
 * Uso (con pa-mod420.jar en el classpath, ver Test/atc/validar.sh):
 * - java ValidarFicheroATC fichero.atc [...]: decodifica cada fichero con el decodificador
 *   oficial, lo convierte en declaración, la valida con el validador del programa y la importa
 *   con GestorDeclaracionesImpl.importarDeclaraciones() en un directorio temporal.
 * - java ValidarFicheroATC codifica fichero.xml: codifica un XML con Codificador.codifica().
 * - java ValidarFicheroATC decodifica fichero.atc: muestra el XML de un fichero.
 */
public class ValidarFicheroATC {
    public static void main(String[] args) throws Exception {
        if (args.length == 2 && "codifica".equals(args[0])) {
            String xml = new String(Files.readAllBytes(Paths.get(args[1])), StandardCharsets.ISO_8859_1);
            System.out.print(Codificador.codifica(xml));
            return;
        }
        if (args.length == 2 && "decodifica".equals(args[0])) {
            String contenido = new String(Files.readAllBytes(Paths.get(args[1])), StandardCharsets.ISO_8859_1);
            System.out.print(Codificador.decodifica(contenido));
            return;
        }

        int fallos = 0;
        for (String ruta : args) {
            File fichero = new File(ruta);
            System.out.println("== " + fichero.getName());
            File trabajo = Files.createTempDirectory("pa420").toFile();
            ModuloPA420Impl modulo = new ModuloPA420Impl(trabajo);
            GestorDeclaracionesImpl gestor = new GestorDeclaracionesImpl();
            gestor.setDirBase(trabajo);

            DEC dec = gestor.obtenerDEC(fichero);
            if (dec == null) {
                System.out.println("  ERROR: el programa no puede leer el fichero");
                fallos++;
                continue;
            }
            System.out.println("  leído: MOD=" + dec.getMOD() + " ANY=" + dec.getANY() + " PER=" + dec.getPER() + " VER=" + dec.getVER());

            Declaracion declaracion = ObjectUtils.crearDeclaracion(dec);
            boolean valida = modulo.isDeclaracionValida(declaracion);
            List<Mensaje> mensajes = modulo.getMensajes();
            for (Mensaje m : mensajes) {
                System.out.println("  " + (m.getTipo() == Mensaje.TIPO_ERROR ? "ERROR" : "AVISO") + " [" + m.getSeccion() + "/" + m.getCampo() + "] " + m.getCodigo() + " " + m.getDescripcion());
            }
            System.out.println("  validación del programa: " + (valida ? "OK" : "CON ERRORES"));
            if (!valida) {
                fallos++;
            }

            List<String> importacion = gestor.importarDeclaraciones(new File[]{fichero}, new ArrayList<>());
            for (String linea : importacion) {
                System.out.println("  importación: " + linea);
                if (!linea.endsWith("importada con \u00e9xito")) {
                    fallos++;
                }
            }
        }
        System.exit(fallos == 0 ? 0 : 1);
    }
}
