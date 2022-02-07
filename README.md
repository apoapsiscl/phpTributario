# phpTributario
**Esta libreria no esta lista para usar en produccion !!**

Este proyecto pretende implementar la logica y peticiones necesarias para emitir 
documentos tributarios electronicos en Chile para el servicio de impuestos internos (SII)

Aun esta en etapa muy temprana de desarrollo, por lo que no recomiendo utilizarla en produccion
si no sabes lo que estas haciendo.

Por ahora, esta implementado:
- Conexion a servidor de certificacion o servidor de produccion de SII
- Generacion de boletas en formato XML
- Generacion de firma electronica en los archivos XML
- Timbraje digital en las boletas
- Envio a SII de la boleta timbrada y firmada
- Generacion del archivo de Resumen de Ventas Diarias (RVD, ex-RCOF)
- Firma y envio del archivo RVD

El envio de boletas se realiza con la API nueva del SII y el envio de RVD se hace por medio de la API clasica.

## Licencia
El codigo de esta libreria esta regido bajo los terminos de la licencia de software libre MIT. Este ha sido escrito desde cero utilizando la documentacion provista por el SII (ver carpeta referencias).

Eres libre de utilizar el proyecto para fines personales o comerciales, codigo abierto o codigo cerrado, siempre y cuando la nota de copyright y la parte de los derechos se incluya en todas las copias o partes sustanciales de tu software.
