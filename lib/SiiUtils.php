<?php

/** 
 * Clase con algunas funciones de utilidad que usan otras clases del proyecto
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

class SiiUtils
{
    public static function arrayToXML($array, $xml = null)
    {
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                if (is_int($key)) {
                    if ($key == 0) {
                        $node = $xml;
                    } else {
                        $parent = $xml->xpath("..")[0];
                        $node = $parent->addChild($xml->getName());
                    }
                } else {
                    $node = $xml->addChild($key);
                }
                self::arrayToXML($value, $node);
            }
        } else {
            $xml[0] = $array;
        }
    }

    static function XMLPrettify($xml)
    {
        $dom = new DOMDocument("1.0");
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        return $dom->saveXML();
    }
}