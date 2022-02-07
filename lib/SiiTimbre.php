<?php

/** 
 * Clase para procesar el timbre electronico requerido por el SII
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

class SiiTimbre
{
    public $rutEmisor = null;
    public $razonEmisor = null;
    private $_caf = null;
    public $folioDesde = null;
    public $folioHasta = null;
    private $_pkeyFolios = null;

    public function __construct($archivo_caf)
    {
        $strfolios = file_get_contents($archivo_caf);
        $this->_cargaFolios($strfolios);
    }

    private function _cargaFolios($strxmlfolio)
    {
        $xmlfolio = new SimpleXMLElement($strxmlfolio);

        $this->rutEmisor = (string)$xmlfolio->CAF->DA->RE;
        $this->razonEmisor = (string)$xmlfolio->CAF->DA->RS;

        $this->_caf = $xmlfolio->CAF;

        $this->folioDesde = intval((string)$xmlfolio->CAF->DA->RNG->D);
        $this->folioHasta = intval((string)$xmlfolio->CAF->DA->RNG->H);

        $this->_pkeyFolios = (string)$xmlfolio->RSASK;
    }

    public function timbraBoletas($boletas)
    {
        $boletastimbradas = [];

        foreach ($boletas as $boleta) {
            $TED = new SimpleXMLElement("<TED version=\"1.0\"><DD><RE></RE><TD></TD><F></F><FE></FE><RR></RR><RSR></RSR><MNT></MNT><IT1></IT1><CAF></CAF><TSTED></TSTED></DD><FRMT algoritmo=\"SHA1withRSA\"></FRMT></TED>");

            $TED->DD->RE = $boleta->Encabezado->Emisor->RUTEmisor;
            $TED->DD->TD = $boleta->Encabezado->IdDoc->TipoDTE;
            $TED->DD->F = $boleta->Encabezado->IdDoc->Folio;
            $TED->DD->FE = $boleta->Encabezado->IdDoc->FchEmis;
            $TED->DD->RR = $boleta->Encabezado->Receptor->RUTRecep;
            $TED->DD->RSR = $boleta->Encabezado->Receptor->RznSocRecep;
            $TED->DD->MNT = $boleta->Encabezado->Totales->MntTotal;
            $TED->DD->IT1 = $boleta->Detalle[0]->NmbItem;
            $TED->DD->TSTED = date('Y-m-d\TH:i:s');

            // Inserta CAF
            $dom     = dom_import_simplexml($TED->DD->CAF);
            $import  = $dom->ownerDocument->importNode(
                dom_import_simplexml($this->_caf),
                true
            );
            $dom->parentNode->replaceChild($import, $dom);

            $DD = dom_import_simplexml($TED->DD)->C14N();

            $DD = preg_replace("/\>\n\s+\</", '><', $DD);
            $DD = preg_replace("/\>\n\t+\</", '><', $DD);
            $DD = preg_replace("/\>\n+\</", '><', $DD);

            if (openssl_sign($DD, $timbre, $this->_pkeyFolios, OPENSSL_ALGO_SHA1) == false) {
                throw new Exception("Error firmando certificado");
            }

            $TED->FRMT = base64_encode($timbre);

            // Inserta TED en boleta
            $dom     = dom_import_simplexml($boleta);
            $import  = $dom->ownerDocument->importNode(
                dom_import_simplexml($TED),
                true
            );
            $dom->appendChild($import);

            $boletastimbradas[] = $boleta;
        }

        return $boletastimbradas;
    }
}