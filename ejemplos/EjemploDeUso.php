<?php

/** 
 * Ejemplos de uso para la libreria phpTributario
 * 
 * PHP version 7
 * 
 * @author    Carlos Pizarro <kr105@kr105.com>
 * @copyright 2022 Apoapsis SpA
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

require dirname(dirname(__DIR__)) . '/vendor/autoload.php';

require 'SiiBoletas.php';
require 'SiiConexion.php';
require 'SiiFirma.php';
require 'SiiTimbre.php';
require 'SiiReporteVentas.php';
require 'SiiUtils.php';

// Certificado de firma digital, password del certificado y archivo xml de folios (CAF)
$bol = new SiiBoletas('certificado.pfx', 'password', 'FoliosSII1234567890.xml');

// Array para almacenar las boletas que vayamos generando
$boletas = [];

// Datos de la boleta
$data = [
    'Items' =>
    [
        [
            'Nombre' => 'Cambio de aceite',
            'Cantidad' => 1,
            'Precio' => 19900,
        ],
        [
            'Nombre' => 'Alineacion y balanceo',
            'Cantidad' => 1,
            'Precio' => 9900,
        ],
    ],
    'Referencias' =>
    [
        [
            'Codigo' => 'SET',
            'Razon' => 'CASO-1'
        ]
    ]
];

// Genera la boleta, sin timbrar y sin firmar, solo el XML
$boleta = $bol->generaBoleta($data, 39, 6, date('Y-m-d'), 19);
$boletas[] = $boleta;

$data = [
    'Items' =>
    [
        [
            'Nombre' => 'Papel de regalo',
            'Cantidad' => 17,
            'Precio' => 120,
        ],
    ],
    'Referencias' =>
    [
        [
            'Codigo' => 'SET',
            'Razon' => 'CASO-2'
        ]
    ]
];
$boleta = $bol->generaBoleta($data, 39, 6, date('Y-m-d'), 19);
$boletas[] = $boleta;

$data = [
    'Items' =>
    [
        [
            'Nombre' => 'item afecto 1',
            'Cantidad' => 8,
            'Precio' => 1590,
        ],
        [
            'Nombre' => 'item exento 2',
            'Cantidad' => 2,
            'Precio' => 1000,
            'Exento' => 1
        ],
    ],
    'Referencias' =>
    [
        [
            'Codigo' => 'SET',
            'Razon' => 'CASO-4'
        ]
    ]
];
$boleta = $bol->generaBoleta($data, 39, 6, date('Y-m-d'), 19);
$boletas[] = $boleta;

// Timbra, firma y envia las boletas
$resultado = $bol->enviaBoletas($boletas);

// Obtiene estado del envio anterior
$resultado = $bol->getEstadoEnvio($resultado->trackid);

// ---------------------------------------------------------------

// Datos para reporte diario de ventas
$data = [
    'FechaResolucion' => '2022-02-02',
    'NumeroResolucion' => 0,
    'FechaInicio' => '2022-02-06',
    'FechaFinal' => '2022-02-06',
    'SecuenciaEnvio' => 1,
    'Resumen' => [
        [
            'TipoDocumento' => 39,
            'MontoNeto' => 43831,
            'MontoIva' => 8329,
            'TasaIVA' => 19,
            'MontoExento' => 2000,
            'MontoTotal' => 54160,
            'FoliosEmitidos' => 5,
            'FoliosAnulados' => 0,
            'FoliosUtilizados' => 5,
            'RangoUtilizados' => [
                [
                    'Inicial' => 1,
                    'Final' => 5,
                ]
            ],
        ],
    ],
];

$rv = new SiiReporteVentas('certificado.pfx', 'password', 'FoliosSII1234567890.xml');

// Firma y envia el RCV
$resultado = $rv->generaConsumoFolio($data);