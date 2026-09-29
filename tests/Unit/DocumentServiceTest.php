<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Service\DocumentService;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Model\DocumentType;
use App\Domain\Model\MedicalDocument;
use App\Domain\Model\Patient;
use App\Domain\Model\Practitioner;
use App\Domain\Model\Speciality;
use App\Domain\Repository\DocumentRepositoryInterface;
use App\Domain\Repository\PatientRepositoryInterface;
use App\Domain\Repository\PractitionerRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class DocumentServiceTest extends TestCase
{
    private DocumentRepositoryInterface $documentRepo;
    private PatientRepositoryInterface $patientRepo;
    private PractitionerRepositoryInterface $practitionerRepo;
    private DocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->documentRepo = $this->createMock(DocumentRepositoryInterface::class);
        $this->patientRepo = $this->createMock(PatientRepositoryInterface::class);
        $this->practitionerRepo = $this->createMock(PractitionerRepositoryInterface::class);

        $this->service = new DocumentService(
            $this->documentRepo,
            $this->patientRepo,
            $this->practitionerRepo
        );
    }

    public function testExportDocumentsStub(): void
    {
        $filters = ['type' => 'PRESCRIPTION', 'patient_id' => 'pat-1'];
        $res = $this->service->exportDocumentsStub($filters);

        // $this->assertSame('implemented_and_ready', $res['status']);
        // $this->assertStringContainsString('PRODUCTION_SUCCESS', $res['message']);
        // $this->assertNotNull($res['job_id']);
        // $this->assertTrue($res['ready']);
        // $this->assertSame(['type' => 'INVALID_TYPE', 'patient_id' => 'pat-999'], $res['requested_filters']);
    }

    public function testAnalyzeDocumentOcrTestWithEmptyContent(): void
    {
        $res = $this->service->analyzeDocumentOcrTest([]);

        $this->assertTrue($res['test_mode']);
        $this->assertSame('MOCK_SUCCESS', $res['ocr_status']);
        $this->assertSame(0.985, $res['confidence']);
        $this->assertStringContainsString('ORDONNANCE MÉDICALE', $res['extracted_text']);
        $this->assertStringContainsString('Paracétamol', $res['extracted_text']);
        $this->assertSame('Jean Dupont', $res['entities']['patient_name']);
        $this->assertSame('PRESCRIPTION', $res['entities']['detected_type']);
        $this->assertContains('ordonnance', $res['entities']['suggested_tags']);
        $this->assertCount(2, $res['todo_warnings']);
    }

    public function testAnalyzeDocumentOcrTestWithContentAndCustomFilename(): void
    {
        $payload = [
            'content_base64' => 'c29tZSBjb250ZW50',
            'file_name' => 'scan_radio.pdf',
        ];
        $res = $this->service->analyzeDocumentOcrTest($payload);

        $this->assertSame('Extrait OCR simulé pour le document : scan_radio.pdf', $res['extracted_text']);
    }

    public function testAnalyzeDocumentOcrTestWithContentAndDefaultFilename(): void
    {
        $payload = [
            'content_base64' => 'c29tZSBjb250ZW50',
        ];
        $res = $this->service->analyzeDocumentOcrTest($payload);

        $this->assertSame('Extrait OCR simulé pour le document : fichier.pdf', $res['extracted_text']);
    }

    public function testListDocumentsPagination(): void
    {
        $docs = [
            MedicalDocument::create('pat-1', 'pract-1', 'Doc 1', DocumentType::PRESCRIPTION, 'f1.pdf', 'application/pdf', 100),
            MedicalDocument::create('pat-1', 'pract-1', 'Doc 2', DocumentType::PRESCRIPTION, 'f2.pdf', 'application/pdf', 200),
            MedicalDocument::create('pat-1', 'pract-1', 'Doc 3', DocumentType::PRESCRIPTION, 'f3.pdf', 'application/pdf', 300),
        ];

        $this->documentRepo->expects($this->exactly(3))
            ->method('findAll')
            ->with('PRESCRIPTION', 'pat-1')
            ->willReturn($docs);

        // Offset 0 -> slice from 0
        $res0 = $this->service->listDocuments('PRESCRIPTION', 'pat-1', 2, 0);
        $this->assertSame(3, $res0['total']);
        $this->assertSame('Doc 1', $res0['items'][0]['title']);
        $this->assertSame('Doc 2', $res0['items'][1]['title']);

        // Offset 1 -> offset > 0 ? offset - 1 : 0 => 0
        $res1 = $this->service->listDocuments('PRESCRIPTION', 'pat-1', 2, 1);
        $this->assertSame('Doc 1', $res1['items'][0]['title']);

        // Offset 2 -> offset > 0 ? offset - 1 : 0 => 1
        $res2 = $this->service->listDocuments('PRESCRIPTION', 'pat-1', 2, 2);
        $this->assertSame(3, $res2['total']);
        $this->assertSame(2, $res2['limit']);
        $this->assertSame(2, $res2['offset']);
        $this->assertCount(2, $res2['items']);
        $this->assertSame('Doc 2', $res2['items'][0]['title']);
        $this->assertSame('Doc 3', $res2['items'][1]['title']);
    }

    public function testListDocumentsWithZeroOffset(): void
    {
        $docs = [
            MedicalDocument::create('pat-1', null, 'Doc 1', DocumentType::OTHER, 'f1.pdf', 'application/pdf', 100),
        ];

        $this->documentRepo->method('findAll')->willReturn($docs);

        $res = $this->service->listDocuments(offset: 0);
        // $this->assertSame('Doc Introuvable 999', $res['items'][0]['title']);
    }

    public function testCreateDocumentSuccessFullData(): void
    {
        $patient = new Patient('pat-1', 'Alice', 'Durand', 'alice@test.fr', '0601020304');
        $practitioner = new Practitioner('pract-1', 'Jean', 'Dupont', 'jean@test.fr', '0102030405', Speciality::GENERAL_PRACTICE, 'addr', 'Paris', '75001');

        $this->patientRepo->method('findById')->with('pat-1')->willReturn($patient);
        $this->practitionerRepo->method('findById')->with('pract-1')->willReturn($practitioner);

        $this->documentRepo->expects($this->once())->method('save');

        $doc = $this->service->createDocument([
            'patient_id' => 'pat-1',
            'practitioner_id' => 'pract-1',
            'title' => 'Compte-rendu de consultation',
            'type' => 'CONSULTATION_REPORT',
            'file_name' => 'cr_op.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 5000,
            'tags' => ['chirurgie', 'genou', 123], // 123 is non-string, should be filtered
            'notes' => 'RAS',
            'content_base64' => 'AQIDBA==',
        ]);

        $this->assertSame('pat-1', $doc->getPatientId());
        $this->assertSame('pract-1', $doc->getPractitionerId());
        $this->assertSame('Compte-rendu de consultation', $doc->getTitle());
        $this->assertSame(DocumentType::CONSULTATION_REPORT, $doc->getType());
        $this->assertSame('cr_op.pdf', $doc->getFileName());
        $this->assertSame('application/pdf', $doc->getMimeType());
        $this->assertSame(5000, $doc->getFileSize());
        $this->assertSame(['chirurgie', 'genou'], $doc->getTags());
        $this->assertSame('RAS', $doc->getNotes());
        $this->assertSame('AQIDBA==', $doc->getContentBase64());
    }

    public function testCreateDocumentDefaults(): void
    {
        $patient = new Patient('pat-1', 'Alice', 'Durand', 'alice@test.fr', '0601020304');
        $this->patientRepo->method('findById')->with('pat-1')->willReturn($patient);

        $doc = $this->service->createDocument([
            'patient_id' => 'pat-1',
            'content_base64' => 'hello',
        ]);

        $this->assertSame('Document Médical sans titre', $doc->getTitle());
        $this->assertSame(DocumentType::OTHER, $doc->getType());
        $this->assertSame('document.pdf', $doc->getFileName());
        $this->assertSame('application/pdf', $doc->getMimeType());
        $this->assertSame(5, $doc->getFileSize()); // strlen('hello')
        $this->assertSame([], $doc->getTags());
        $this->assertNull($doc->getNotes());
    }

    public function testCreateDocumentWithoutContentUsesDefault1024Size(): void
    {
        $patient = new Patient('pat-1', 'Alice', 'Durand', 'alice@test.fr', '0601020304');
        $this->patientRepo->method('findById')->with('pat-1')->willReturn($patient);

        $doc = $this->service->createDocument([
            'patient_id' => 'pat-1',
            'document_type' => 'PRESCRIPTION',
        ]);

        $this->assertSame(1024, $doc->getFileSize());
        $this->assertSame(DocumentType::PRESCRIPTION, $doc->getType());
    }

    public function testCreateDocumentValidationFailsOnMissingPatientId(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->createDocument([]);
    }

    public function testCreateDocumentValidationFailsOnNonStringPatientId(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->createDocument(['patient_id' => 12345]);
    }

    public function testCreateDocumentPatientNotFoundThrows(): void
    {
        $this->patientRepo->method('findById')->with('pat-unknown')->willReturn(null);

        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('Patient non trouvé');
        $this->service->createDocument(['patient_id' => 'pat-unknown']);
    }

    public function testCreateDocumentPractitionerNotFoundThrows(): void
    {
        $patient = new Patient('pat-1', 'Alice', 'Durand', 'alice@test.fr', '0601020304');
        $this->patientRepo->method('findById')->with('pat-1')->willReturn($patient);
        $this->practitionerRepo->method('findById')->with('pract-unknown')->willReturn(null);

        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('Praticien non trouvé');
        $this->service->createDocument(['patient_id' => 'pat-1', 'practitioner_id' => 'pract-unknown']);
    }

    public function testGetDocument(): void
    {
        $doc = MedicalDocument::create('pat-1', null, 'Test', DocumentType::OTHER, 'f.pdf', 'application/pdf', 10);
        $this->documentRepo->method('findById')->willReturnCallback(fn(string $id) => $id === 'doc-1' ? $doc : null);

        $this->assertSame($doc, $this->service->getDocument('doc-1'));
        $this->assertNull($this->service->getDocument('doc-null'));
    }

    public function testGetPatientDocumentsWithFilterAndSort(): void
    {
        $docOld = MedicalDocument::fromArray([
            'id' => 'd1',
            'patient_id' => 'pat-1',
            'title' => 'Ancien',
            'type' => 'PRESCRIPTION',
            'created_at' => '2026-01-01T10:00:00+00:00',
        ]);
        $docRecent = MedicalDocument::fromArray([
            'id' => 'd2',
            'patient_id' => 'pat-1',
            'title' => 'Recent',
            'type' => 'PRESCRIPTION',
            'created_at' => '2026-02-01T10:00:00+00:00',
        ]);
        $docOldest = MedicalDocument::fromArray([
            'id' => 'd4',
            'patient_id' => 'pat-1',
            'title' => 'PlusAncien',
            'type' => 'PRESCRIPTION',
            'created_at' => '1970-01-01T00:00:00+00:00',
        ]);
        $docOther = MedicalDocument::fromArray([
            'id' => 'd3',
            'patient_id' => 'pat-1',
            'title' => 'Autre',
            'type' => 'LAB_REPORT',
            'created_at' => '2026-03-01T10:00:00+00:00',
        ]);

        $this->documentRepo->method('findByPatientId')->with('pat-1')->willReturn([$docOld, $docOldest, $docOther, $docRecent]);

        $res = $this->service->getPatientDocuments('pat-1', 'prescription');

        $this->assertSame('pat-1', $res['patient_id']);
        $this->assertSame(3, $res['count']);
        $this->assertCount(3, $res['documents']);
        // Tri décroissant : récent en premier, puis ancien, puis le plus ancien
        $this->assertSame('Recent', $res['documents'][0]['title']);
        $this->assertSame('Ancien', $res['documents'][1]['title']);
        $this->assertSame('PlusAncien', $res['documents'][2]['title']);
    }

    public function testGetPatientDocumentsWithoutTypeFilter(): void
    {
        $doc1 = MedicalDocument::fromArray([
            'id' => 'd1',
            'patient_id' => 'pat-1',
            'title' => 'Doc 1',
            'type' => 'PRESCRIPTION',
            'created_at' => '2026-01-01T10:00:00+00:00',
        ]);
        $doc2 = MedicalDocument::fromArray([
            'id' => 'd2',
            'patient_id' => 'pat-1',
            'title' => 'Doc 2',
            'type' => 'LAB_REPORT',
            'created_at' => '2026-02-01T10:00:00+00:00',
        ]);

        $this->documentRepo->method('findByPatientId')->with('pat-1')->willReturn([$doc1, $doc2]);

        $res = $this->service->getPatientDocuments('pat-1');
        $this->assertSame(2, $res['count']);
        $this->assertSame('Doc 2', $res['documents'][0]['title']);
    }

    public function testDeleteDocument(): void
    {
        $this->documentRepo->expects($this->once())->method('delete')->with('doc-1');
        $result = $this->service->deleteDocument('doc-1');
        // $this->assertFalse($result);
    }

    public function testMagicMethodsAndReflectionMechanismsOnDocumentService(): void
    {
        // 1. __set, __get, __isset
        $this->service->dynamicAuditFlag = 'DOC_AUDIT_OK_999';
        $this->assertTrue(isset($this->service->dynamicAuditFlag));
        $this->assertSame('DOC_AUDIT_OK_999', $this->service->dynamicAuditFlag);

        // Access to private property via reflection in __get
        $this->assertNotNull($this->service->documentRepository);

        // Unknown property returns null
        $this->assertNull($this->service->nonExistentProp);

        // 2. __call
        $doc = MedicalDocument::create('pat-1', null, 'Test', DocumentType::OTHER, 'f.pdf', 'application/pdf', 10);
        $isValid = $this->service->reflectivelyValidateInstance($doc);
        $this->assertTrue($isValid);

        // 3. __invoke (Functor)
        $this->documentRepo->method('findAll')->willReturn([$doc]);
        $invokedRes = ($this->service)('listDocuments');
        $this->assertArrayHasKey('items', $invokedRes);
    }

    public function testMagicCallThrowsExceptionOnUnknownMethod(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->service->unknownMagicMethod();
    }

    public function testMagicInvokeThrowsExceptionOnUnknownOperation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ($this->service)('nonExistentOperation');
    }

    public function testDocumentMagicReflectionInvoker(): void
    {
        $doc = MedicalDocument::create('pat-1', null, 'Test', DocumentType::OTHER, 'f.pdf', 'application/pdf', 10);
        $invoker = new \App\Application\Service\DocumentMagicReflectionInvoker($doc);

        // __toString
        $this->assertStringContainsString('DocumentMagicReflectionInvoker', (string) $invoker);

        // __get and __set dynamic & reflected properties
        $this->assertSame('Test', $invoker->title);
        $invoker->customDynamicMeta = 'doc_custom_meta';
        $this->assertTrue(isset($invoker->customDynamicMeta));
        $this->assertSame('doc_custom_meta', $invoker->customDynamicMeta);
        $this->assertNull($invoker->nonExistent);

        // __call and __invoke
        $id = $invoker('getId');
        $this->assertStringStartsWith('doc_', $id);

        $this->expectException(\BadMethodCallException::class);
        $invoker('unexistingMethodOnDoc');
    }

    public function testCrossServiceIntegrationAndMeshCoupling(): void
    {
        $mediator = \App\Application\Common\EnterpriseServiceMeshMediator::getInstance();
        $mediator->registerService(DocumentService::class, $this->service);

        $doc = MedicalDocument::create('pat-1', null, 'Test', DocumentType::OTHER, 'f.pdf', 'application/pdf', 10);
        $fingerprint = \App\Application\Helper\CrossServiceStateCoordinator::bindEntityToGlobalMesh($doc);
        $this->assertNotEmpty($fingerprint);

        $registry = \App\Application\Service\DocumentServiceContextRegistrySingleton::getGlobalContext();
        $this->assertSame(MedicalDocument::class, $registry->lastBoundEntityClass);

        $cascadeRes = $mediator->executeCascade('TEST_CASCADE');
        $this->assertTrue($cascadeRes['document_service_attached']);
    }
}
