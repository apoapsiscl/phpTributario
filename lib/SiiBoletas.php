<?php

/** 
 * Metodos para emitir boletas electronicas al SII en Chile
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

class SiiBoletas
{
    private $_conexion = null;
    private $_firma = null;
    private $_timbre = null;

    public function __construct($archivo_cert, $password_cert, $archivo_caf)
    {
        $this->_firma = new SiiFirma($archivo_cert, $password_cert);
        $this->_conexion = new SiiConexion('Certificacion', $this->_firma);
        $this->_timbre = new SiiTimbre($archivo_caf);
    }

    public function enviaBoletas($StrXmlEnvia)
    {
        $response = $this->_conexion->enviaDocumentoBoleta($StrXmlEnvia, $this->_firma->rutEnvia, $this->_timbre->rutEmisor);

        $json = json_decode($response);

        return $json;
    }

    public function preparaSobreEnvio($boletasXML)
    {
        $EnvioBOLETA = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\n<EnvioBOLETA xmlns=\"http://www.sii.cl/SiiDte\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.sii.cl/SiiDte EnvioBOLETA_v11.xsd\" version=\"1.0\">\n</EnvioBOLETA>");
        $SetDTE = new SimpleXMLElement("<SetDTE ID=\"SetDoc\"></SetDTE>");
        $Caratula = new SimpleXMLElement("<Caratula version=\"1.0\"><RutEmisor/><RutEnvia/><RutReceptor/><FchResol/><NroResol/><TmstFirmaEnv/><SubTotDTE><TpoDTE/><NroDTE/></SubTotDTE></Caratula>");

        $Caratula->RutEmisor = $this->_timbre->rutEmisor;
        $Caratula->RutEnvia = $this->_firma->rutEnvia;
        $Caratula->RutReceptor = '60803000-K';
        $Caratula->FchResol = '2022-02-02';
        $Caratula->NroResol = '0';
        $Caratula->TmstFirmaEnv = date('Y-m-d\TH:i:s');
        $Caratula->SubTotDTE->TpoDTE = 39;
        $Caratula->SubTotDTE->NroDTE = count($boletasXML);

        // Inserta Caratula en SetDTE
        $dom     = dom_import_simplexml($SetDTE);
        $import  = $dom->ownerDocument->importNode(
            dom_import_simplexml($Caratula),
            true
        );
        $dom->appendChild($import);

        // Inserta DTEs en SetDTE
        foreach ($boletasXML as $DTE) {
            $dom     = dom_import_simplexml($SetDTE);
            $import  = $dom->ownerDocument->importNode(
                dom_import_simplexml($DTE),
                true
            );
            $dom->appendChild($import);
        }

        // Inserta SetDTE en EnvioBOLETA
        $dom     = dom_import_simplexml($EnvioBOLETA);
        $import  = $dom->ownerDocument->importNode(
            dom_import_simplexml($SetDTE),
            true
        );
        $dom->appendChild($import);

        $EnvioBOLETA_firmado = $this->_firma->firmaXML($EnvioBOLETA, '#SetDoc', 'SetDTE');

        $EnvioBOLETA_firmado_str = str_replace("xmlns:xmlns=", "xmlns=", $EnvioBOLETA_firmado->asXML());

        return $EnvioBOLETA_firmado_str;
    }

    public function getEstadoEnvio($trackid)
    {
        $status = $this->_conexion->getTrackidStatus($trackid, $this->_timbre->rutEmisor);

        return $status;
    }

    public function generaBoleta($data, $tipoDte, $folio, $fechaEmision, $tasaIVA, $rutReceptor = '66666666-6', $razonReceptor = "RUT GENERICO")
    {
        $dte = [
            'Encabezado' => [
                'IdDoc' => [
                    'TipoDTE' => $tipoDte, // 39 Boleta afecta - 41 Boleta no afecta
                    'Folio' => $folio,
                    'FchEmis' => $fechaEmision, // '2022-02-02'
                    'IndServicio' => 3 // 3 Boletas de venta y servicios 
                ],
                'Emisor' => [
                    'RUTEmisor' => $this->_timbre->rutEmisor
                ],
                'Receptor' => [
                    'RUTRecep' => $rutReceptor,
                    'RznSocRecep' => $razonReceptor
                ],
                'Totales' => [
                    'MntNeto' => 0,
                    'MntExe' => 0,
                    'IVA' => 0,
                    'MntTotal' => 0
                ]
            ],
            'Detalle' => [],
            'Referencia' => [],
        ];

        $items = $data['Items'];
        $referencias = $data['Referencias'];

        $totalNeto = 0;
        $totalExento = 0;
        $totalIVA = 0;
        $totalTotal = 0;

        $linea = 1;

        // Arma array de Detalle
        foreach ($items as $item) {
            $precio = $item['Precio'];
            $cantidad = $item['Cantidad'];
            $unidadMedida = $item['UnidadDeMedida'] ?? null;
            $exento = $item['Exento'] ?? null;
            $total = $precio * $cantidad;

            $itemSii = [
                'NroLinDet' => $linea++,
                'IndExe' => $exento,
                'NmbItem' => $item['Nombre'],
                'QtyItem' => $cantidad,
                'UnmdItem' => $unidadMedida,
                'PrcItem' => $precio,
                'MontoItem' => $total
            ];

            if ($exento === null) {
                unset($itemSii['IndExe']);
            }

            if ($unidadMedida === null) {
                unset($itemSii['UnmdItem']);
            }

            $dte['Detalle'][] = $itemSii;

            $totalTotal += $total;

            if ($exento !== null) {
                $totalExento += $total;
                continue;
            }

            // Se calcula neto y luego IVA
            // No al reves, para evitar problemas de redondeo

            $montoNeto = round($total / (1 + ($tasaIVA/100)));
            $montoIVA = $total - $montoNeto;

            $totalNeto += $montoNeto;
            $totalIVA += $montoIVA;
        }

        $dte['Encabezado']['Totales']['MntNeto'] = $totalNeto;
        $dte['Encabezado']['Totales']['MntExe'] = $totalExento;
        $dte['Encabezado']['Totales']['IVA'] = $totalIVA;
        $dte['Encabezado']['Totales']['MntTotal'] = $totalTotal;

        if ($totalExento === 0) {
            unset($dte['Encabezado']['Totales']['MntExe']);
        }

        $linea = 1;

        // Arma array de Referencia
        foreach ($referencias as $referencia) {
            $referenciaSii = [
                'NroLinRef' => $linea++,
                'CodRef' => $referencia['Codigo'],
                'RazonRef' => $referencia['Razon'],
            ];

            $dte['Referencia'][] = $referenciaSii;
        }

        if ($referencias === null) {
            unset($dte['Referencia']);
        }

        return $dte;
    }

    public function generaXmlBoletas($data)
    {
        $boletas = [];

        // TODO: Check input format $data

        foreach ($data as $item) {
            $Documento = new SimpleXMLElement("<Documento></Documento>");
            $Documento->addAttribute('ID', 'APO_F' . $item['Encabezado']['IdDoc']['Folio'] . 'T' . $item['Encabezado']['IdDoc']['TipoDTE']);

            SiiUtils::arrayToXML($item, $Documento);

            $boletas[] = $Documento;
        }

        return $boletas;
    }

    public function timbraBoletas($boletas)
    {
        // No es necesario un foreach ya que timbraBoletas de la clase SiiTimbre recibe un array
        $boletastimbradas = $this->_timbre->timbraBoletas($boletas);

        return $boletastimbradas;
    }

    public function firmaBoletas($boletas)
    {
        $boletasfirmadas = [];

        foreach ($boletas as $boleta) {
            $TmstFirma = $boleta->addChild('TmstFirma');
            $TmstFirma[0] = date('Y-m-d\TH:i:s');

            $DTE = new SimpleXMLElement("<DTE version=\"1.0\"></DTE>");

            // Inserta TED en boleta
            $dom     = dom_import_simplexml($DTE);
            $import  = $dom->ownerDocument->importNode(
                dom_import_simplexml($boleta),
                true
            );
            $dom->appendChild($import);

            $ID = 'ID';
            $ID = (string)$DTE->Documento->attributes()->$ID;

            $firmada = $this->_firma->firmaXML($DTE, "#$ID", 'Documento');

            $boletasfirmadas[] = $firmada;
        }

        return $boletasfirmadas;
    }
}