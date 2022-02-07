<?php

/** 
 * Clase para enviar datos a los endpoints del SII
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

class SiiConexion
{
    private const SERVIDOR_CERTIFICACION_APICERT = "https://apicert.sii.cl/recursos/v1/";
    private const SERVIDOR_CERTIFICACION_PANGAL = "https://pangal.sii.cl/recursos/v1/";
    private const SERVIDOR_CERTIFICACION_MAULLIN = "https://maullin.sii.cl/";

    private const SERVIDOR_PRODUCCION_API = "https://api.sii.cl/recursos/v1/";
    private const SERVIDOR_PRODUCCION_RAHUE = "https://rahue.sii.cl/recursos/v1/";
    private const SERVIDOR_PRODUCCION_PALENA = "https://palena.sii.cl/";

    private $_servidorSiiApi = null;
    private $_servidorSiiEnvioBoleta = null;
    private $_servidorSiiEnvioOtro = null;

    private $_tokenObtenido = null;
    private $_tokenObtenidoOld = null;

    private $_firma = null;

    
    public function __construct($servidor, $firma)
    {
        if ($servidor == 'Produccion') {
            $this->_servidorSiiApi = self::SERVIDOR_PRODUCCION_API;
            $this->_servidorSiiEnvioBoleta = self::SERVIDOR_PRODUCCION_RAHUE;
            $this->_servidorSiiEnvioOtro = self::SERVIDOR_PRODUCCION_PALENA;
        } else {
            $this->_servidorSiiApi = self::SERVIDOR_CERTIFICACION_APICERT;
            $this->_servidorSiiEnvioBoleta = self::SERVIDOR_CERTIFICACION_PANGAL;
            $this->_servidorSiiEnvioOtro = self::SERVIDOR_CERTIFICACION_MAULLIN;
        }

        $this->_firma = $firma;

        $this->_getToken();
    }

    private function _getSemilla()
    {
        $client = new GuzzleHttp\Client(['base_uri' => $this->_servidorSiiApi]);

        $res = $client->request('GET', 'boleta.electronica.semilla', ['http_errors' => false]);

        if ($res->getStatusCode() != Teapot\StatusCode::OK) {
            throw new Exception("Error obteniendo semilla, HTTP!=OK");
        }

        if ($res->getHeader('content-type')[0] != 'application/xml') {
            throw new Exception("Error obteniendo semilla, respuesta!=xml");
        }

        $response = $res->getBody()->getContents();

        $xml = new SimpleXMLElement($response);

        $estado = (string)$xml->xpath('/SII:RESPUESTA/SII:RESP_HDR/ESTADO')[0];

        if ($estado !== '00') {
            throw new Exception("Error obteniendo semilla, estado!=00");
        }

        $semilla = $xml->xpath('/SII:RESPUESTA/SII:RESP_BODY/SEMILLA')[0];

        return (string)$semilla;
    }

    private function _getToken()
    {
        // XML base para pedir el token
        $xmlstr = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xmlstr .= "<getToken></getToken>";

        // Agrega la semilla
        $gettoken = new SimpleXMLElement($xmlstr);
        $item = $gettoken->addChild('item');
        $Semilla = $item->addChild('Semilla');
        $Semilla[0] = $this->_getSemilla();

        // Firma el XML
        $xml_firmado = $this->_firma->firmaXML($gettoken, '');

        // Envia el XML al SII
        $client = new GuzzleHttp\Client(['base_uri' => $this->_servidorSiiApi]);
        $res = $client->request(
            'POST', 
            'boleta.electronica.token', [
                'http_errors' => false,
                'headers' => ['Content-Type' => 'application/xml'],
                'body' => $xml_firmado->asXML()
            ]
        );

        if ($res->getStatusCode() != Teapot\StatusCode::OK) {
            throw new Exception("Error obteniendo token, HTTP!=OK");
        }

        if ($res->getHeader('content-type')[0] != 'application/xml') {
            throw new Exception("Error obteniendo token, respuesta!=xml");
        }

        $response = $res->getBody()->getContents();

        $xml = new SimpleXMLElement($response);

        $estado = (string)$xml->xpath('/SII:RESPUESTA/SII:RESP_HDR/ESTADO')[0];
        if ($estado !== '00') {
            throw new Exception("Error obteniendo token, estado!=00");
        }

        $token = $xml->xpath('/SII:RESPUESTA/SII:RESP_BODY/TOKEN')[0];

        $this->_tokenObtenido = $token;
    }

    public function getTrackidStatus($trackid, $rutEmisor)
    {
        $client = new GuzzleHttp\Client(['base_uri' => $this->_servidorSiiApi]);

        $rut = explode('-', $rutEmisor)[0];
        $dv = explode('-', $rutEmisor)[1];

        $res = $client->request(
            'GET',
            "boleta.electronica.envio/$rut-$dv-$trackid",
            [
                'http_errors' => false,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'Mozilla/4.0 ( compatible; PROG 1.0; Windows NT)',
                    'Cookie' => 'TOKEN=' . $this->_tokenObtenido
                ],
            ]
        );

        if ($res->getStatusCode() != Teapot\StatusCode::OK) {
            throw new Exception("Error obteniendo semilla, HTTP!=OK");
        }

        if ($res->getHeader('content-type')[0] != 'application/json') {
            throw new Exception("Error obteniendo semilla, respuesta!=xml");
        }

        $response = $res->getBody()->getContents();

        $json = json_decode($response);

        return $json;
    }

    private function _getSemillaOld()
    {
        // Al necesitar SOAP ya no conviene usar Guzzle :c

        // TODO: try/catch?
        $soap = new SoapClient($this->_servidorSiiEnvioOtro.'DTEWS/CrSeed.jws?WSDL');
        $response = $soap->getSeed();
        
        $xml = new SimpleXMLElement($response);

        $estado = (string)$xml->xpath('/SII:RESPUESTA/SII:RESP_HDR/ESTADO')[0];

        if ($estado !== '00') {
            throw new Exception("Error obteniendo semillaOld, estado!=00");
        }

        $semilla = $xml->xpath('/SII:RESPUESTA/SII:RESP_BODY/SEMILLA')[0];

        return (string)$semilla;

    }

    private function _getTokenOld()
    {
        // XML base para pedir el token
        $xmlstr = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xmlstr .= "<getToken></getToken>";

        // Agrega la semilla
        $gettoken = new SimpleXMLElement($xmlstr);
        $item = $gettoken->addChild('item');
        $Semilla = $item->addChild('Semilla');
        $Semilla[0] = $this->_getSemillaOld();

        // Firma el XML
        $xml_firmado = $this->_firma->firmaXML($gettoken, '');

        // Al necesitar SOAP ya no conviene usar Guzzle :c

        // TODO: try/catch?
        $soap = new SoapClient($this->_servidorSiiEnvioOtro.'DTEWS/GetTokenFromSeed.jws?WSDL');
        $response = $soap->getToken($xml_firmado->asXML());
        
        $xml = new SimpleXMLElement($response);

        $estado = (string)$xml->xpath('/SII:RESPUESTA/SII:RESP_HDR/ESTADO')[0];

        if ($estado !== '00') {
            throw new Exception("Error obteniendo semillaOld, estado!=00");
        }

        $token = $xml->xpath('/SII:RESPUESTA/SII:RESP_BODY/TOKEN')[0];

        $this->_tokenObtenidoOld = $token;
    }

    public function enviaDocumentoOld($documentoXML, $rutEnvia, $rutEmisor)
    {
        $this->_getTokenOld();

        $client = new GuzzleHttp\Client(['base_uri' => $this->_servidorSiiEnvioOtro]);
        $res = $client->request(
            'POST', 'cgi_dte/UPL/DTEUpload', [
                'debug' => true,
                'http_errors' => false,
                'headers' => [
                    //'Accept' => 'application/json',
                    'User-Agent' => 'Mozilla/4.0 ( compatible; PROG 1.0; Windows NT)',
                    'Cookie' => 'TOKEN=' . $this->_tokenObtenidoOld
                ],
                'multipart' => [
                    [
                        'name'     => 'rutSender',
                        'contents' => explode('-', $rutEnvia)[0]
                    ],
                    [
                        'name'     => 'dvSender',
                        'contents' => explode('-', $rutEnvia)[1]
                    ],
                    [
                        'name'     => 'rutCompany',
                        'contents' => explode('-', $rutEmisor)[0]
                    ],
                    [
                        'name'     => 'dvCompany',
                        'contents' => explode('-', $rutEmisor)[1]
                    ],
                    [
                        'name'     => 'archivo',
                        'headers'  => ['Content-Type' => 'application/xml'],
                        'contents' => $documentoXML,
                        'filename' => 'file.xml'
                    ]
                ]
            ]
        );

        if ($res->getStatusCode() != Teapot\StatusCode::OK) {
            throw new Exception("Error obteniendo token, HTTP!=OK, {$res->getBody()->getContents()}");
        }

        if ($res->getHeader('content-type')[0] != 'text/html') {
            throw new Exception("Error obteniendo token, respuesta!=xml, {$res->getHeader('content-type')[0]}, {$res->getBody()->getContents()}");
        }

        $response = $res->getBody()->getContents();

        return $response;
    }

    public function enviaDocumentoBoleta($documentoXML, $rutEnvia, $rutEmisor)
    {
        $dte = $documentoXML;
        //$dte = gzencode($documentoXML, 9);
        //$dte = base64_encode($dte);

        if ($dte === false) {
            throw new Exception("Error comprimiendo archivo de dte");
        }

        $client = new GuzzleHttp\Client(['base_uri' => $this->_servidorSiiEnvioBoleta]);
        $res = $client->request(
            'POST', 'boleta.electronica.envio', [
                'debug' => true,
                'http_errors' => false,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'Mozilla/4.0 ( compatible; PROG 1.0; Windows NT)',
                    'Cookie' => 'TOKEN=' . $this->_tokenObtenido
                ],
                'multipart' => [
                    [
                        'name'     => 'rutSender',
                        'contents' => explode('-', $rutEnvia)[0]
                    ],
                    [
                        'name'     => 'dvSender',
                        'contents' => explode('-', $rutEnvia)[1]
                    ],
                    [
                        'name'     => 'rutCompany',
                        'contents' => explode('-', $rutEmisor)[0]
                    ],
                    [
                        'name'     => 'dvCompany',
                        'contents' => explode('-', $rutEmisor)[1]
                    ],
                    [
                        'name'     => 'archivo',
                        'headers'  => ['Content-Type' => 'application/xml'],
                        'contents' => $dte,
                        'filename' => 'file.xml'
                    ]
                ]
            ]
        );

        if ($res->getStatusCode() != Teapot\StatusCode::OK) {
            throw new Exception("Error obteniendo token, HTTP!=OK, {$res->getBody()->getContents()}");
        }

        if ($res->getHeader('content-type')[0] != 'application/json') {
            throw new Exception("Error obteniendo token, respuesta!=xml, {$res->getBody()->getContents()}");
        }

        $response = $res->getBody()->getContents();

        return $response;
    }

}