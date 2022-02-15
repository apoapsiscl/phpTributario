<?php

/** 
 * Metodos para emitir reporte de ventas diario (RVD, ex RCOF) al SII en Chile
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

class SiiReporteVentas
{
    private $_conexion = null;
    private $_firma = null;
    private $_timbre = null;

    public function __construct($archivo_cert, $password_cert, $archivo_caf, $servidor)
    {
        $this->_firma = new SiiFirma($archivo_cert, $password_cert);
        $this->_conexion = new SiiConexion($servidor, $this->_firma);
        $this->_timbre = new SiiTimbre($archivo_caf);
    }

    public function generaConsumoFolio($data)
    {
        $ConsumoFolios = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\n<ConsumoFolios xmlns=\"http://www.sii.cl/SiiDte\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.sii.cl/SiiDte ConsumoFolio_v10.xsd\" version=\"1.0\">\n</ConsumoFolios>");

        // 39
        $arrayConsumoFolios = [
            'DocumentoConsumoFolios' => [
                'Caratula' => [
                    'RutEmisor' => $this->_timbre->rutEmisor,
                    'RutEnvia' => $this->_firma->rutEnvia,
                    'FchResol' => $data['FechaResolucion'],
                    'NroResol' => $data['NumeroResolucion'],
                    'FchInicio' => $data['FechaInicio'],
                    'FchFinal' => $data['FechaFinal'],
                    'SecEnvio' => $data['SecuenciaEnvio'],
                    'TmstFirmaEnv' => date('Y-m-d\TH:i:s')
                ],
                'Resumen' => [],
            ],
        ];

        foreach ($data['Resumen'] as $resumen) {
            $siiResumen = [
                'TipoDocumento' => $resumen['TipoDocumento'],
                'MntNeto' => $resumen['MontoNeto'],
                'MntIva' => $resumen['MontoIva'],
                'TasaIVA' => $resumen['TasaIVA'],
                'MntExento' => $resumen['MontoExento'],
                'MntTotal' => $resumen['MontoTotal'],
                'FoliosEmitidos' => $resumen['FoliosEmitidos'],
                'FoliosAnulados' => $resumen['FoliosAnulados'],
                'FoliosUtilizados' => $resumen['FoliosUtilizados'],
                'RangoUtilizados' => [],
            ];

            foreach ($resumen['RangoUtilizados'] as $rangoUtilizado) {
                $rango = [
                    'Inicial' => $rangoUtilizado['Inicial'],
                    'Final' => $rangoUtilizado['Final'],
                ];

                $siiResumen['RangoUtilizados'][] = $rango;
            }

            if (count($siiResumen['RangoUtilizados']) == 0 ) {
                unset($siiResumen['RangoUtilizados']);
            }

            $arrayConsumoFolios['DocumentoConsumoFolios']['Resumen'][] = $siiResumen;
        }

        SiiUtils::arrayToXML($arrayConsumoFolios, $ConsumoFolios);

        $id = 'RCOF_' . str_replace('-', '', $this->_timbre->rutEmisor) . '_' . date('Ymd') . '_' . $data['SecuenciaEnvio'];

        $ConsumoFolios->DocumentoConsumoFolios->addAttribute('ID', $id);
        $ConsumoFolios->DocumentoConsumoFolios->Caratula->addAttribute('version', '1.0');

        $ConsumoFoliosFirmado = $this->_firma->firmaXML($ConsumoFolios, "#$id", 'DocumentoConsumoFolios');
        
        $ConsumoFoliosFirmadoStr = str_replace("xmlns:xmlns=", "xmlns=", $ConsumoFoliosFirmado->asXML());

        return $ConsumoFoliosFirmadoStr;
    }

    public function enviaConsumoFolio($documentoXML)
    {
        $response = $this->_conexion->enviaDocumentoOld($documentoXML, $this->_firma->rutEnvia, $this->_timbre->rutEmisor, true);

        return $response;
    }

}