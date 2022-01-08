<?php

declare(strict_types=1);

namespace PhpCfdi\XmlCancelacion\Tests\System;

use DateTimeImmutable;
use PhpCfdi\XmlCancelacion\Capsules\Cancellation;
use PhpCfdi\XmlCancelacion\Credentials;
use PhpCfdi\XmlCancelacion\Signers\DOMSigner;
use PhpCfdi\XmlCancelacion\Tests\TestCase;
use PhpCfdi\XmlCancelacion\Definitions\Folio;

class XmlSignedUsingDOMSignerTest extends TestCase
{
    /** @var DOMSigner */
    private $domSigner;

    public function setUp(): void
    {
        parent::setUp();

        $credentials = new Credentials(
            $this->filePath('LAN7008173R5.cer.pem'),
            $this->filePath('LAN7008173R5.key.pem'),
            trim($this->fileContents('LAN7008173R5.password'))
        );

        $f = new Folio('E174F807-BEFA-4CF6-9B11-2A013B12F398', '02');
        $capsule = new Cancellation(
            'LAN7008173R5',
            [$f],
            new DateTimeImmutable('2019-04-05T16:29:17')
        );

        $document = $capsule->exportToDocument();

        $signer = new DOMSigner();
        $signer->signDocument($document, $credentials);

        $this->domSigner = $signer;
    }

    public function testCreatedValues(): void
    {
        // signature text for preset capsule *must* be the following, see not used xmlns declarations
        /** @noinspection XmlUnusedNamespaceDeclaration */
        $expectedDigestSource = '<Cancelacion xmlns="http://cancelacfd.sat.gob.mx"'
            . ' xmlns:xsd="http://www.w3.org/2001/XMLSchema"'
            . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            . ' Fecha="2019-04-05T16:29:17" RfcEmisor="LAN7008173R5">'
            . '<Folios><Folio Motivo="02" UUID="E174F807-BEFA-4CF6-9B11-2A013B12F398"></Folio></Folios>'
            . '</Cancelacion>';

        $expectedDigestValue = 'YBtGnfi2aq9RXXOWt5dtZpYOidg=';

        // signed info text for preset capsule *must* be the following, see not used xmlns declarations and C14N
        /** @noinspection XmlUnusedNamespaceDeclaration */
        $expectedSignedInfo = '<SignedInfo xmlns="http://www.w3.org/2000/09/xmldsig#"'
            . ' xmlns:xsd="http://www.w3.org/2001/XMLSchema"'
            . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315">'
            . '</CanonicalizationMethod>'
            . '<SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"></SignatureMethod>'
            . '<Reference URI="">'
            . '<Transforms>'
            . '<Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"></Transform>'
            . '</Transforms>'
            . '<DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"></DigestMethod>'
            . '<DigestValue>YBtGnfi2aq9RXXOWt5dtZpYOidg=</DigestValue>'
            . '</Reference>'
            . '</SignedInfo>';

        $expectedSignedValue = 'V+eTCjmr1aoLAwS4GdV0KI2LRb9MUYm1qT4ZA/XSFol269xNFl/U9xGWZbS/3CNDv+MmPQZ8XnOXuyd+fQOY'
            . 'Ra4VlBO14jkSfw7h8JoBsEQhvpOna2aRFiIGR35VXUmdg9BT+L94whbbMOTw584zOSbYr8ozU3oxa5CoMnjbA7OfzN+JZmWp8rQUwRT'
            . 'yQK4SEqUlcc4iTXJYxeIEqjCzQonZT9FoHW2PKoCWwBpx40KimSrPXeSRBk06/+J1ILn0HIGlMtkOVtWW87cyEhPEQzWUxsSttnz9wq'
            . 'i+YFV5nfQ6aYlFdQZaQ4H1FV66exiUXZE20nh4GJ2RI5P11JxiyA==';

        $this->assertSame($expectedDigestSource, $this->domSigner->getDigestSource());
        $this->assertSame($expectedDigestValue, $this->domSigner->getDigestValue());
        $this->assertSame($expectedSignedInfo, $this->domSigner->getSignedInfoSource());
        $this->assertSame($expectedSignedValue, $this->domSigner->getSignedInfoValue());
    }
}
