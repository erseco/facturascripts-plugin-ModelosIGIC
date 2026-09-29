<?php

/**
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

namespace FacturaScripts\Test\Plugins;

use FacturaScripts\Core\Base\ControllerPermissions;
use FacturaScripts\Core\DataSrc\Empresas;
use FacturaScripts\Core\Lib\Accounting\AccountingPlanImport;
use FacturaScripts\Core\Lib\Calculator;
use FacturaScripts\Core\Lib\MenuManager;
use FacturaScripts\Core\Response;
use FacturaScripts\Core\Template\Controller as TemplateController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\MultiRequestProtection;
use FacturaScripts\Dinamic\Model\Almacen;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Divisa;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\Impuesto;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Dinamic\Model\RegularizacionImpuesto;
use FacturaScripts\Dinamic\Model\Serie;
use FacturaScripts\Dinamic\Model\Subcuenta;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Plugins\ModelosIGIC\Lib\RegularizacionIGIC;
use FacturaScripts\Plugins\ModelosIGIC\Model\DeclaracionIGIC;
use ReflectionClass;
use ReflectionMethod;

/**
 * Datos de prueba: un ejercicio lejano con el plan contable español,
 * facturas con IGIC y un usuario administrador con sesión.
 *
 * La clase que use este trait debe llamar a cleanFixtures() en tearDown().
 */
trait ModelosIGICFixtures
{
    /** @var object[] */
    private array $fixtures = [];

    /** Año de pruebas: lejano para no mezclarse con datos reales. */
    protected static string $year = '2090';

    protected function cleanFixtures(): void
    {
        // regularizaciones y declaraciones de los períodos de prueba
        $servicio = new RegularizacionIGIC();
        $regivas = RegularizacionImpuesto::all([
            Where::gte('fechainicio', static::$year . '-01-01'),
            Where::lte('fechafin', static::$year . '-12-31'),
        ]);
        foreach ($regivas as $regiva) {
            foreach (DeclaracionIGIC::all([Where::eq('idregiva', $regiva->idregiva)]) as $declaracion) {
                $declaracion->estado = 'borrador';
                $declaracion->save();
            }
            $servicio->eliminar($regiva);
        }
        foreach (DeclaracionIGIC::all([Where::eq('codejercicio', $this->ejercicio()->codejercicio)]) as $declaracion) {
            $declaracion->delete();
        }

        foreach (array_reverse($this->fixtures) as $model) {
            $model->delete();
        }
        $this->fixtures = [];

        $_GET = $_POST = $_REQUEST = $_COOKIE = [];
    }

    /**
     * Devuelve el ejercicio de pruebas, creándolo con el plan contable si no existe.
     */
    protected function ejercicio(): Ejercicio
    {
        $ejercicio = new Ejercicio();
        $ejercicio->idempresa = Empresas::default()->idempresa;
        $this->assertTrue($ejercicio->loadFromDate(static::$year . '-01-01', false, true), 'No exercise');

        if (Subcuenta::count([Where::eq('codejercicio', $ejercicio->codejercicio)]) === 0) {
            $plan = FS_FOLDER . '/Core/Data/Codpais/ESP/defaultPlan.csv';
            $imported = (new AccountingPlanImport())->importCSV($plan, $ejercicio->codejercicio);
            $this->assertTrue($imported, 'No se pudo importar el plan contable: ' . $this->recentLog());
        }

        return $ejercicio;
    }

    /**
     * Inicia sesión como administrador mediante las cookies del núcleo.
     */
    protected function login(): User
    {
        $user = new User();
        if (false === $user->load('admin')) {
            $user->nick = 'admin';
            $user->email = 'admin@example.com';
            $user->setPassword('admin');
        }
        $user->admin = true;
        $user->enabled = true;
        $logkey = $user->newLogkey('127.0.0.1');
        $this->assertTrue($user->save());

        $_COOKIE['fsNick'] = $user->nick;
        $_COOKIE['fsLogkey'] = $logkey;

        return $user;
    }

    /**
     * Devuelve un token válido para los formularios protegidos.
     */
    protected function formToken(): string
    {
        return (new MultiRequestProtection())->newToken();
    }

    /**
     * Ejecuta un controlador y devuelve el contenido de la respuesta, sin enviarla.
     */
    protected function runController(object $controller): string
    {
        if ($controller instanceof TemplateController) {
            $method = new ReflectionMethod($controller, 'response');
            $method->setAccessible(true);
            $method->invoke($controller)->disableSend();
            $controller->run();

            return $method->invoke($controller)->getContent();
        }

        // controladores clásicos (ListController, EditController...)
        $className = (new ReflectionClass($controller))->getShortName();
        $user = $this->login();
        $response = (new Response())->disableSend();
        $controller->privateCore($response, $user, new ControllerPermissions($user, $className));
        if ($controller->getTemplate()) {
            $response->view($controller->getTemplate(), [
                'controllerName' => $className,
                'fsc' => $controller,
                'menuManager' => MenuManager::init()->selectPage($controller->getPageData()),
                'template' => $controller->getTemplate(),
            ]);
        }

        return $response->getContent();
    }

    protected function makeCliente(): Cliente
    {
        $cliente = new Cliente();
        $cliente->nombre = 'Cliente IGIC ' . substr(uniqid(), -5);
        $cliente->cifnif = 'B' . random_int(1000000, 9999999);
        $this->assertTrue($cliente->save());
        $this->fixtures[] = $cliente;

        return $cliente;
    }

    /**
     * Crea una factura de venta con una línea de la base y el tipo indicados.
     */
    protected function makeFacturaCliente(string $fecha, float $base, float $tipo = 7.0): FacturaCliente
    {
        $factura = new FacturaCliente();
        $factura->setSubject($this->makeCliente());

        return $this->saveFactura($factura, $fecha, $base, $tipo);
    }

    /**
     * Crea una factura de compra con una línea de la base y el tipo indicados.
     */
    protected function makeFacturaProveedor(string $fecha, float $base, float $tipo = 7.0): FacturaProveedor
    {
        $factura = new FacturaProveedor();
        $factura->setSubject($this->makeProveedor());

        return $this->saveFactura($factura, $fecha, $base, $tipo);
    }

    protected function makeProveedor(): Proveedor
    {
        $proveedor = new Proveedor();
        $proveedor->nombre = 'Proveedor IGIC ' . substr(uniqid(), -5);
        $proveedor->cifnif = 'B' . random_int(1000000, 9999999);
        $this->assertTrue($proveedor->save());
        $this->fixtures[] = $proveedor;

        return $proveedor;
    }

    /**
     * Crea facturas de un trimestre: 1.000 € de ventas y 400 € de compras al 7 %.
     */
    protected function makeTrimestre(string $mes = '02'): void
    {
        $this->ejercicio();
        $this->makeFacturaCliente('10-' . $mes . '-' . static::$year, 1000.0);
        $this->makeFacturaProveedor('12-' . $mes . '-' . static::$year, 400.0);
    }

    protected function recentLog(): string
    {
        $parts = [];
        foreach (Tools::log()->read('', ['critical', 'error', 'warning']) as $entry) {
            $parts[] = '[' . $entry['level'] . '] ' . $entry['message'];
        }

        return implode('; ', $parts);
    }

    private function codalmacen(): string
    {
        $codalmacen = (string) Tools::settings('default', 'codalmacen', '');
        if ('' !== $codalmacen) {
            return $codalmacen;
        }

        foreach (Almacen::all([], [], 0, 1) as $almacen) {
            return (string) $almacen->codalmacen;
        }

        $almacen = new Almacen();
        $almacen->codalmacen = 'IGIC';
        $almacen->nombre = 'Almacén IGIC';
        $almacen->idempresa = Empresas::default()->idempresa;
        $this->assertTrue($almacen->save());

        return 'IGIC';
    }

    private function impuesto(float $tipo): Impuesto
    {
        foreach (Impuesto::all([Where::eq('iva', $tipo)]) as $impuesto) {
            return $impuesto;
        }

        $impuesto = new Impuesto();
        $impuesto->codimpuesto = 'TIGIC' . (int) $tipo;
        $impuesto->descripcion = 'IGIC ' . $tipo . '%';
        $impuesto->iva = $tipo;
        $this->assertTrue($impuesto->save());
        $this->fixtures[] = $impuesto;

        return $impuesto;
    }

    private function coddivisa(): string
    {
        $divisa = new Divisa();
        if (false === $divisa->load('EUR')) {
            $divisa->coddivisa = 'EUR';
            $divisa->descripcion = 'Euro';
            $divisa->simbolo = '€';
            $divisa->tasaconv = 1;
            $divisa->tasaconvcompra = 1;
            $this->assertTrue($divisa->save());
        }

        return 'EUR';
    }

    private function codpago(): string
    {
        foreach (FormaPago::all([], ['codpago' => 'ASC'], 0, 1) as $formaPago) {
            return (string) $formaPago->codpago;
        }

        $formaPago = new FormaPago();
        $formaPago->codpago = 'CONT';
        $formaPago->descripcion = 'Contado';
        $this->assertTrue($formaPago->save());

        return 'CONT';
    }

    private function codserie(): string
    {
        foreach (Serie::all([], ['codserie' => 'ASC'], 0, 1) as $serie) {
            return (string) $serie->codserie;
        }

        $serie = new Serie();
        $serie->codserie = 'A';
        $serie->descripcion = 'Serie general';
        $this->assertTrue($serie->save());

        return 'A';
    }

    /**
     * @param FacturaCliente|FacturaProveedor $factura
     */
    private function saveFactura($factura, string $fecha, float $base, float $tipo)
    {
        $factura->fecha = $fecha;
        $factura->codalmacen = $this->codalmacen();

        // las instalaciones mínimas de CI no tienen serie, forma de pago ni divisa por defecto
        $factura->codserie = $factura->codserie ?: $this->codserie();
        $factura->codpago = $factura->codpago ?: $this->codpago();
        $factura->coddivisa = $factura->coddivisa ?: $this->coddivisa();
        $this->assertTrue($factura->save(), $this->recentLog());

        $impuesto = $this->impuesto($tipo);
        $linea = $factura->getNewLine();
        $linea->descripcion = 'Servicio de pruebas';
        $linea->cantidad = 1;
        $linea->pvpunitario = $base;
        $linea->codimpuesto = $impuesto->codimpuesto;
        $linea->iva = $impuesto->iva;
        $lineas = [$linea];
        $this->assertTrue(Calculator::calculate($factura, $lineas, true), $this->recentLog());
        $this->assertNotEmpty($factura->idasiento, 'La factura no tiene asiento: ' . $this->recentLog());
        $this->fixtures[] = $factura;

        return $factura;
    }
}
