<?php

declare(strict_types=1);

namespace PhpCfdi\XmlCancelacion\Capsules;

use Countable;
use DateTimeImmutable;
use DOMDocument;
use PhpCfdi\XmlCancelacion\Definitions\DocumentType;
use PhpCfdi\XmlCancelacion\Definitions\Folio;
use PhpCfdi\XmlCancelacion\Internal\XmlHelperFunctions;
use PhpCfdi\XmlCancelacion\Exceptions\XmlCancelacionLogicException;

class Cancellation implements Countable, CapsuleInterface
{
    use XmlHelperFunctions;

    private const UUID_EXISTS = true;

    /** @var string */
    private $rfc;

    /** @var DateTimeImmutable */
    private $date;

    /** @var array<string, bool> This is a B-Tree array, values are stored in keys */
    private $folios;

    /** @var DocumentType */
    private $documentType;

    /**
     * DTO for cancellation request, it supports CFDI and Retention
     *
     * @param string $rfc
     * @param Folio[] $folios
     * @param DateTimeImmutable $date
     * @param DocumentType|null $type Uses CFDI if non provided
     */
    public function __construct(string $rfc, array $folios, DateTimeImmutable $date, DocumentType $type = null)
    {
        $this->rfc = $rfc;
        $this->date = $date;
        $this->folios = [];
        $this->documentType = $type ?? DocumentType::cfdi();
        foreach ($folios as $folio) {
            if (! ($folio instanceof Folio)) {
                throw new XmlCancelacionLogicException('$folio no es una instancia de ' . Folio::class);
            }
            $this->folios[$folio->uuid()] = $folio;
        }
    }

    public function rfc(): string
    {
        return $this->rfc;
    }

    public function date(): DateTimeImmutable
    {
        return $this->date;
    }

    public function documentType(): DocumentType
    {
        return $this->documentType;
    }

    /**
     * The list of UUIDS
     * @return string[]
     */
    public function folios(): array
    {
        return array_values($this->folios);
    }

    public function count(): int
    {
        return count($this->folios);
    }

    public function exportToDocument(): DOMDocument
    {
        $document = (new BaseDocumentBuilder())->createBaseDocument('Cancelacion', $this->documentType->value());

        $cancelacion = $this->xmlDocumentElement($document);
        $cancelacion->setAttribute('RfcEmisor', $this->rfc());
        $cancelacion->setAttribute('Fecha', $this->date()->format('Y-m-d\TH:i:s'));
        $folios = $cancelacion->appendChild($document->createElement('Folios'));
        foreach ($this->folios() as $f) {
            $folio = $folios->appendChild($document->createElement('Folio'));
            $folio->setAttribute('UUID', $f->uuid());
            $folio->setAttribute('Motivo', $f->motivo());
            if ('01' == $f->motivo()) {
                $folio->setAttribute('UUID', $f->sustitucion());
            }
        }
        return $document;
    }

    public function belongsToRfc(string $rfc): bool
    {
        return ($rfc === $this->rfc());
    }
}
