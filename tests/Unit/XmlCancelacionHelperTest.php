<?php

declare(strict_types=1);

namespace PhpCfdi\XmlCancelacion\Tests\Unit;

use DateTimeImmutable;
use PhpCfdi\XmlCancelacion\Capsules\Cancellation;
use PhpCfdi\XmlCancelacion\Capsules\CapsuleInterface;
use PhpCfdi\XmlCancelacion\Credentials;
use PhpCfdi\XmlCancelacion\Exceptions\HelperDoesNotHaveCredentials;
use PhpCfdi\XmlCancelacion\Signers\DOMSigner;
use PhpCfdi\XmlCancelacion\Signers\SignerInterface;
use PhpCfdi\XmlCancelacion\Tests\TestCase;
use PhpCfdi\XmlCancelacion\XmlCancelacionHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PhpCfdi\XmlCancelacion\Definitions\Folio;

/** @covers \PhpCfdi\XmlCancelacion\XmlCancelacionHelper */
class XmlCancelacionHelperTest extends TestCase
{
    /** @return Credentials&MockObject */
    private function createFakeCredentials()
    {
        return $this->createMock(Credentials::class);
    }

    /** @return  SignerInterface&MockObject $fakeSigner */
    private function createFakeSigner()
    {
        return $this->createMock(SignerInterface::class);
    }

    private function createRealCredentials(): Credentials
    {
        $cerFile = $this->filePath('LAN7008173R5.cer.pem');
        $keyFile = $this->filePath('LAN7008173R5.key.pem');
        $passPhrase = trim($this->fileContents('LAN7008173R5.password'));
        return new Credentials($cerFile, $keyFile, $passPhrase);
    }

    public function testConstructWithValues(): void
    {
        $credentials = $this->createFakeCredentials();
        $signer = $this->createFakeSigner();

        $helper = new XmlCancelacionHelper($credentials, $signer);
        $this->assertSame($credentials, $helper->getCredentials());
        $this->assertSame($signer, $helper->getSigner());
    }

    public function testSignCapsuleInvokesSignerMethod(): void
    {
        $fakeSign = 'fake-sign';
        $capsule = $this->createMock(CapsuleInterface::class);
        $credentials = $this->createFakeCredentials();

        $signer = $this->createFakeSigner();
        $signer->expects($this->once())->method('signCapsule')
            ->with($capsule, $credentials)
            ->willReturn($fakeSign);

        $helper = new XmlCancelacionHelper($credentials, $signer);
        $this->assertSame($fakeSign, $helper->signCapsule($capsule));
    }

    public function testCredentialChanges(): void
    {
        $fakeCredentials = $this->createFakeCredentials();

        $helper = new XmlCancelacionHelper();
        $this->assertFalse($helper->hasCredentials());

        $this->assertSame($helper, $helper->setCredentials($fakeCredentials));
        $this->assertTrue($helper->hasCredentials());

        $cerFile = $this->filePath('LAN7008173R5.cer.pem');
        $keyFile = $this->filePath('LAN7008173R5.key.pem');
        $passPhrase = trim($this->fileContents('LAN7008173R5.password'));
        $this->assertSame($helper, $helper->setNewCredentials($cerFile, $keyFile, $passPhrase));
        $this->assertTrue($helper->hasCredentials());
        $this->assertSame('LAN7008173R5', $helper->getCredentials()->rfc());
    }

    public function testSignerChanges(): void
    {
        $fakeSigner = $this->createFakeSigner();
        $helper = new XmlCancelacionHelper();
        $this->assertInstanceOf(
            DOMSigner::class,
            $helper->getSigner(),
            'Default signer must be a DOMSigner instance'
        );
        $this->assertSame($helper, $helper->setSigner($fakeSigner));
        $this->assertSame($fakeSigner, $helper->getSigner());
    }

    public function testSignCancellationCallsSignCapsule(): void
    {
        $dateTime = new DateTimeImmutable();
        $uuid = new Folio('11111111-2222-3333-4444-000000000001', '02');

        $credentials = $this->createRealCredentials();
        $expectedCapsule = new Cancellation('LAN7008173R5', [$uuid], $dateTime);

        $helper = new XmlCancelacionHelperSpy($credentials);
        $helper->signCancellation($uuid, $dateTime);

        /** @var Cancellation $cancellation */
        $cancellation = $helper->getLastSignedCapsule();
        $this->assertEquals($expectedCapsule, $cancellation);
    }

    public function testMakeUuids(): void
    {
        $credentials = $this->createRealCredentials();
        $rfc = $credentials->rfc();
        $uuids = [new Folio('11111111-2222-3333-4444-000000000001', '02'),
            new Folio('11111111-2222-3333-4444-000000000002', '02'), ];
        $helper = new XmlCancelacionHelperSpy();

        $now = new DateTimeImmutable();
        $result = $helper->setCredentials($credentials)->signCancellationUuids($uuids);
        $this->assertStringContainsString('<Signature xmlns="http://www.w3.org/2000/09/xmldsig#">', $result);

        /** @var Cancellation $spyCapsule */
        $spyCapsule = $helper->getLastSignedCapsule();
        $this->assertInstanceOf(Cancellation::class, $spyCapsule);
        $spyDate = $spyCapsule->date();
        $this->assertSame($rfc, $spyCapsule->rfc());
        $this->assertSame($uuids, $spyCapsule->folios());
        $this->assertTrue($spyDate > $now->modify('-1 second') && $spyDate < $now->modify('+1 second'));
    }

    public function testGetCredentialsWithoutSettingBefore(): void
    {
        $helper = new XmlCancelacionHelper();
        $this->expectException(HelperDoesNotHaveCredentials::class);
        $helper->getCredentials();
    }

    public function testCanConstructWithCredentials(): void
    {
        $credentials = $this->createFakeCredentials();
        $helper = new XmlCancelacionHelper($credentials);
        $this->assertSame($credentials, $helper->getCredentials());
    }

    public function testSignCancellationCreatesCorrectCancellationParatemers(): void
    {
        $dateTime = new DateTimeImmutable('2020-01-13 14:15:16');
        $folio = new Folio('11111111-2222-3333-4444-000000000001', '02');
        $credentials = $this->createRealCredentials();

        $helper = new XmlCancelacionHelperSpy($credentials);
        $helper->signCancellation($folio, $dateTime);

        $cancellation = $helper->getLastCancellation();
        $this->assertSame($credentials->rfc(), $cancellation->rfc());
        $this->assertSame([$folio], $cancellation->folios());
        $this->assertSame($dateTime, $cancellation->date());
        $this->assertTrue($cancellation->documentType()->isCfdi());
    }

    public function testSignCancellationUuidsCreatesCorrectCancellationParatemers(): void
    {
        $dateTime = new DateTimeImmutable('2020-01-13 14:15:16');
        $uuids = [new Folio('11111111-2222-3333-4444-000000000001', '02'),
            new Folio('11111111-2222-3333-4444-000000000002', '02'), ];
        $credentials = $this->createRealCredentials();

        $helper = new XmlCancelacionHelperSpy($credentials);
        $helper->signCancellationUuids($uuids, $dateTime);

        $cancellation = $helper->getLastCancellation();
        $this->assertSame($credentials->rfc(), $cancellation->rfc());
        $this->assertSame($uuids, $cancellation->folios());
        $this->assertSame($dateTime, $cancellation->date());
        $this->assertTrue($cancellation->documentType()->isCfdi());
    }

    public function testSignRetentionCancellationCreatesCorrectCancellationParatemers(): void
    {
        $dateTime = new DateTimeImmutable('2020-01-13 14:15:16');
        $uuid = new Folio('11111111-2222-3333-4444-000000000001', '02');
        $credentials = $this->createRealCredentials();

        $helper = new XmlCancelacionHelperSpy($credentials);
        $helper->signRetentionCancellation($uuid, $dateTime);

        $cancellation = $helper->getLastCancellation();
        $this->assertSame($credentials->rfc(), $cancellation->rfc());
        $this->assertSame([$uuid], $cancellation->folios());
        $this->assertSame($dateTime, $cancellation->date());
        $this->assertTrue($cancellation->documentType()->isRetention());
    }

    public function testSignRetentionCancellationUuidsCreatesCorrectCancellationParatemers(): void
    {
        $dateTime = new DateTimeImmutable('2020-01-13 14:15:16');
        $uuids = [new Folio('11111111-2222-3333-4444-000000000001', '02'),
            new Folio('11111111-2222-3333-4444-000000000002', '02'), ];
        $credentials = $this->createRealCredentials();

        $helper = new XmlCancelacionHelperSpy($credentials);
        $helper->signRetentionCancellationUuids($uuids, $dateTime);

        $cancellation = $helper->getLastCancellation();
        $this->assertSame($credentials->rfc(), $cancellation->rfc());
        $this->assertSame($uuids, $cancellation->folios());
        $this->assertSame($dateTime, $cancellation->date());
        $this->assertTrue($cancellation->documentType()->isRetention());
    }
}
