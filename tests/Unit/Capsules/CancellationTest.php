<?php

declare(strict_types=1);

namespace PhpCfdi\XmlCancelacion\Tests\Unit\Capsules;

use DateTimeImmutable;
use PhpCfdi\XmlCancelacion\Capsules\Cancellation;
use PhpCfdi\XmlCancelacion\Definitions\DocumentType;
use PhpCfdi\XmlCancelacion\Tests\TestCase;
use PhpCfdi\XmlCancelacion\Definitions\Folio;

class CancellationTest extends TestCase
{
    public function testConstructAndGetParameters(): void
    {
        $rfc = 'LAN7008173R5';
        $folios = [
            new Folio('12345678-1234-aaaa-1234-123456789001', '02'),
            new Folio('12345678-1234-aaaa-1234-123456789002', '02'),
        ];
        $expectedUuids = $folios;
        $date = new DateTimeImmutable('2019-01-13 14:15:16');
        $documentType = DocumentType::cfdi();
        $cancellation = new Cancellation($rfc, $folios, $date, $documentType);
        $this->assertSame($rfc, $cancellation->rfc());
        $this->assertSame($expectedUuids, $cancellation->folios());
        $this->assertSame($date, $cancellation->date());
        $this->assertSame($documentType, $cancellation->documentType());

        $this->assertTrue($cancellation->belongsToRfc($rfc));
        $this->assertFalse($cancellation->belongsToRfc('AAA010101AAA'));
    }

    public function testConstructWithoutDocumentType(): void
    {
        $rfc = 'LAN7008173R5';
        $uuids = [
            new Folio('12345678-1234-1234-1234-123456789001', '02'),
            new Folio('12345678-1234-1234-1234-123456789002', '02'),
        ];
        $date = new DateTimeImmutable('2019-01-13 14:15:16');
        $cancellation = new Cancellation($rfc, $uuids, $date);
        $this->assertEquals(DocumentType::cfdi(), $cancellation->documentType());
    }

    public function testCount(): void
    {
        $date = new DateTimeImmutable('2019-01-13 14:15:16');
        $uuids = [
            new Folio('12345678-1234-1234-1234-123456789001', '02'),
            new Folio('12345678-1234-1234-1234-123456789002', '02'),
        ];
        $rfc = 'LAN7008173R5';
        $cancellation = new Cancellation($rfc, $uuids, $date);
        $this->assertCount(2, $cancellation);
    }

    public function testExportToDocument(): void
    {
        // even when UUID using ampersand is not correct, it does not have to break our library
        $uuids = [ new Folio('11111111-2222-3333-4444-000000000001', '02'),
            new Folio('11111111-2222-3333-4444-000000000002', '02'), ];
        $dateTime = new DateTimeImmutable('2019-01-13 14:15:16');
        $cancellation = new Cancellation('LAN7008173R5', $uuids, $dateTime);
        $expectedFile = $this->filePath('cancellation-document.xml');
        $this->assertXmlStringEqualsXmlFile($expectedFile, (string) $cancellation->exportToDocument()->saveXML());
    }

    public function testExportToDocumentWithRetention(): void
    {
        $uuids = [ new Folio('11111111-2222-3333-4444-000000000001', '02'),
            new Folio('11111111-2222-3333-4444-000000000002', '02'), ];
        $dateTime = new DateTimeImmutable('2019-01-13 14:15:16');
        $cancellation = new Cancellation('LAN7008173R5', $uuids, $dateTime, DocumentType::retention());
        $expectedFile = $this->filePath('cancellation-retention-document.xml');
        $this->assertXmlStringEqualsXmlFile($expectedFile, (string) $cancellation->exportToDocument()->saveXML());
    }

    public function testCreateDocumentWithAmpersandsOnUuids(): void
    {
        // even when UUID using ampersand is not correct, it does not have to break our library
        $badUuidWithAmpersand = 'E174F807-&&&&-4CF6-9B11-2A013B12F398';
        $dateTime = new DateTimeImmutable('2019-04-05T16:29:17');
        $cancellation = new Cancellation('LAN7008173R5', [new Folio($badUuidWithAmpersand, '02')], $dateTime);
        $document = $cancellation->exportToDocument();
        $xml = $document->saveXML() ?: '';
        $this->assertStringContainsString(htmlspecialchars($badUuidWithAmpersand, ENT_XML1), $xml);
    }
}
