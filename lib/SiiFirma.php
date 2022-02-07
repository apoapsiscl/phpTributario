<?php

/** 
 * Clase para procesar la firma digital requerida por el SII
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

class SiiFirma
{
    public $rutEnvia = null;
    private $_certificado = null;
    private $_pkeyCert = null;

    public function __construct($archivo_cert, $password_cert)
    {
        // Lee archivo de certificado
        $pkcs12 = file_get_contents($archivo_cert);

        if ($pkcs12 === false) {
            throw new Exception("Error leyendo certificado");
        }

        $certs = null;

        // Desencripta el certificado con la password
        if (openssl_pkcs12_read($pkcs12, $certs, $password_cert) === false) {
            throw new Exception("Error abriendo certificado");
        }

        // Obtiene informacion del certificado
        // OpenSSL no soporta la extension subjectAltName por lo que se usa phpseclib
        $x509 = new \phpseclib3\File\X509();
        $cert = $x509->loadX509($certs['cert']);

        foreach ($cert['tbsCertificate']['extensions'] as $ext) {
            if ($ext['extnId'] == 'id-ce-subjectAltName') {
                $this->rutEnvia = $ext['extnValue'][0]['otherName']['value']['ia5String'];
                break;
            }
        }

        $this->_certificado = $certs['cert'];
        $this->_pkeyCert = $certs['pkey'];
    }

    public function firmaXML($gettoken, $referencia, $tag = null)
    {
        // Guarda el xml antes de agregar los nodos de la firma
        $dom = dom_import_simplexml($gettoken);

        if ($tag === null) {
            $xml_a_firmar = $dom->ownerDocument->documentElement->C14N();
        } else {
            $xml_a_firmar = $dom->getElementsByTagName($tag)->item(0)->C14N();
        }

        // Agrega los nodos necesarios para procesar la firma
        //
        // https://www4c.sii.cl/bolcoreinternetui/api/
        // https://www.w3.org/TR/xmldsig-core/#sec-o-Simple

        $Signature = $gettoken->addChild('Signature');
        $Signature->addAttribute('xmlns', 'http://www.w3.org/2000/09/xmldsig#');

        $SignedInfo = $Signature->addChild('SignedInfo');

        if ($tag === "SetDTE" || $tag === 'DocumentoConsumoFolios') {
            $SignedInfo->addAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance', 'http://www.w3.org/2000/09/xmldsig#');
        } else if ($tag === "Documento") {
            $SignedInfo->addAttribute('xmlns', 'http://www.w3.org/2000/09/xmldsig#');
        } else {
            $SignedInfo->addAttribute('xmlns', 'http://www.w3.org/2000/09/xmldsig#');
        }

        $CanonicalizationMethod = $SignedInfo->addChild('CanonicalizationMethod');
        $CanonicalizationMethod->addAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');

        $SignatureMethod = $SignedInfo->addChild('SignatureMethod');
        $SignatureMethod->addAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');

        $Reference = $SignedInfo->addChild('Reference');
        $Reference->addAttribute('URI', $referencia);

        $Transforms = $Reference->addChild('Transforms');

        $Transform = $Transforms->addChild('Transform');
        $Transform->addAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');

        $DigestMethod = $Reference->addChild('DigestMethod');
        $DigestMethod->addAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');

        $DigestValue = $Reference->addChild('DigestValue');

        $SignatureValue = $Signature->addChild('SignatureValue');

        $KeyInfo = $Signature->addChild('KeyInfo');

        $KeyValue = $KeyInfo->addChild('KeyValue');

        $RSAKeyValue = $KeyValue->addChild('RSAKeyValue');
        $Modulus = $RSAKeyValue->addChild('Modulus');
        $Exponent = $RSAKeyValue->addChild('Exponent');

        $X509Data = $KeyInfo->addChild('X509Data');
        $X509Certificate = $X509Data->addChild('X509Certificate');

        if ($tag == "SetDTE") {
            $xml_a_firmar = str_replace('<SignedInfo xmlns="http://www.w3.org/2000/09/xmldsig#">', '<SignedInfo>', $xml_a_firmar);
        }

        // Genera el digest con el xml original y lo guarda en el xml
        $DigestValue[0] = base64_encode(sha1($xml_a_firmar, true));

        $dom = dom_import_simplexml($SignedInfo);
        $porfirmar = $dom->ownerDocument->saveHTML($dom);

        $porfirmar = str_replace("xmlns:xmlns=", "xmlns=", $porfirmar);

        $signature = null;

        // Firma el XML original + nodos de signature
        if (openssl_sign($porfirmar, $signature, $this->_pkeyCert, OPENSSL_ALGO_SHA1) == false) {
            throw new Exception("Error firmando certificado");
        }

        $signature = base64_encode($signature);
        //$signature = wordwrap($signature, 64, "\n", true);

        // Algunos datos del certificado necesarios para enviar en el xml
        $details = openssl_pkey_get_details(openssl_pkey_get_private($this->_pkeyCert));
        $modulus = wordwrap(base64_encode($details['rsa']['n']), 64, "\n", true);
        $exponent = wordwrap(base64_encode($details['rsa']['e']), 64, "\n", true);

        // Establece los valores finales en el XML
        $SignatureValue[0] = $signature;
        $Modulus[0] = $modulus;
        $Exponent[0] = $exponent;

        if ($referencia != '') {
            $certificado_limpio = trim(str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----'], '', $this->_certificado));
            $X509Certificate[0] = $certificado_limpio;
        } else {
            $X509Certificate[0] = $this->_certificado;
        }

        // Listo XML firmadito
        return $gettoken;
    }
}