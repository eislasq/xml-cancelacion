<?php

declare(strict_types=1);

namespace PhpCfdi\XmlCancelacion\Definitions;

use PhpCfdi\XmlCancelacion\Exceptions\XmlCancelacionLogicException;

class Folio
{
    /** @var string */
    private $uuid;

    /** @var string */
    private $motivo;

    /** @var string */
    private $sustitucion;

    public function __construct(
        string $uuid,
        string $motivo,
        string $sustitucion = null
    ) {
        $this->uuid = strtoupper($uuid);
        $this->motivo = $motivo;
        if ('01' == $motivo && empty($sustitucion)) {
            $m = 'Al indicar el motivo de cancelación 01 debe indicar tambien un UUID de sustitución';
            throw new XmlCancelacionLogicException($m);
        }
        $this->sustitucion = $sustitucion;
    }

    public function uuid(): string
    {
        return $this->uuid;
    }

    public function motivo(): string
    {
        return $this->motivo;
    }

    public function sustitucion(): string
    {
        return $this->sustitucion;
    }
}
