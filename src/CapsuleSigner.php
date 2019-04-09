<?php

declare(strict_types=1);

namespace PhpCfdi\XmlCancelacion;

use DOMDocument;

class CapsuleSigner
{
    /** @var array[] */
    private $extraNamespaces;

    public function __construct(array $extraNamespaces = null)
    {
        if (null === $extraNamespaces) {
            $extraNamespaces = $this->defaultExtraNamespaces();
        }
        $this->extraNamespaces = $extraNamespaces;
    }

    public function extraNamespaces(): array
    {
        return $this->extraNamespaces;
    }

    public function defaultExtraNamespaces(): array
    {
        return [
            'xsd' => 'http://www.w3.org/2001/XMLSchema',
            'xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
        ];
    }

    public function sign(Capsule $capsule, Credentials $signObjects): string
    {
        $document = $this->createDocument($capsule);
        $signatureCreator = new DOMSigner($document);
        $signatureCreator->sign($signObjects);
        return $document->saveXML();
    }

    public function createDocument(Capsule $capsule): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = false;
        $document->formatOutput = false;

        // elemento principal
        $satns = 'http://cancelacfd.sat.gob.mx';
        $cancelacion = $document->createElementNS($satns, 'Cancelacion');
        foreach ($this->extraNamespaces() as $prefix => $uri) {
            $cancelacion->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:' . $prefix, $uri);
        }
        $document->appendChild($cancelacion);

        // registro de atributo RfcEmisor
        $cancelacion->setAttribute('RfcEmisor', $capsule->rfc()); // en el anexo 20 es opcional!
        $cancelacion->setAttribute('Fecha', $capsule->date()->format('Y-m-d\TH:i:s'));

        // creación del nodo folios
        $folios = $cancelacion->appendChild($document->createElementNS($satns, 'Folios'));

        // creación del UUID
        foreach ($capsule->uuids() as $uuid) {
            $folios->appendChild($document->createElementNS($satns, 'UUID', $uuid));
        }

        return $document;
    }
}
