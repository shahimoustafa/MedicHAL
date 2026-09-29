<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Bootstrap;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Router;
use PHPUnit\Framework\TestCase;

class MedicalDocumentApiTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $container = Bootstrap::createContainer(':memory:');
        $this->router = $container->get(Router::class);
    }

    public function testExportDocumentsStubRoute(): void
    {
        $res = $this->router->dispatch(new Request('POST', '/api/documents/export', bodyParams: [
            'patient_ids' => ['pat_123'],
            'format' => 'zip',
        ]));

        $this->assertSame(200, $res->getStatusCode(), 'Stub export should return HTTP 200');
        $data = $res->getData();
        $this->assertTrue($data['success']);
        $this->assertSame('not_implemented', $data['data']['status'], 'Should indicate not_implemented stub status');
        $this->assertArrayHasKey('patient_ids', $data['data']['requested_filters']);
    }

    public function testOcrAnalyzerTestWipRoute(): void
    {
        $res = $this->router->dispatch(new Request('POST', '/api/documents/test-ocr', bodyParams: [
            'file_name' => 'ordonnance_test.pdf',
            'content_base64' => 'T1JET05OQU5DRQ==',
        ]));

        $this->assertSame(200, $res->getStatusCode(), 'OCR test route should return HTTP 200');
        $data = $res->getData();
        $this->assertTrue($data['success']);
        $this->assertTrue($data['data']['test_mode'], 'Should indicate test_mode');
        $this->assertArrayHasKey('todo_warnings', $data['data'], 'Should contain TODO/TEST warnings');
        $this->assertSame(0.985, $data['data']['confidence']);
    }

    public function testDocumentLifecycleRealCalls(): void
    {
        // 1. Create a patient first
        $patRes = $this->router->dispatch(new Request('POST', '/api/patients', bodyParams: [
            'first_name' => 'Claire',
            'last_name' => 'Dubois',
            'email' => 'claire.dubois@test.local',
            'phone' => '0611223344',
        ]));
        $this->assertSame(201, $patRes->getStatusCode());
        $patData = $patRes->getData()['data'];
        $patientId = is_array($patData) ? $patData['id'] : $patData->getId();

        // 2. Create a medical document
        $docRes = $this->router->dispatch(new Request('POST', '/api/documents', bodyParams: [
            'patient_id' => $patientId,
            'title' => 'Compte rendu cardiologie',
            'type' => 'CONSULTATION_REPORT',
            'file_name' => 'cr_cardio.pdf',
            'mime_type' => 'application/pdf',
            'content_base64' => 'JVBERi0xLjQK...',
            'tags' => ['cardio', 'ecg'],
            'notes' => 'RAS tension normale',
        ]));

        $this->assertSame(201, $docRes->getStatusCode(), 'Document should be created with 201');
        $docData = $docRes->getData()['data'];
        $docId = $docData['id'];
        $this->assertSame('Compte rendu cardiologie', $docData['title']);
        $this->assertSame('CONSULTATION_REPORT', $docData['type']);

        // 3. Get document by ID
        $getRes = $this->router->dispatch(new Request('GET', '/api/documents/' . $docId));
        $this->assertSame(200, $getRes->getStatusCode());
        $this->assertSame($docId, $getRes->getData()['data']['id']);

        // 4. List all documents
        $listRes = $this->router->dispatch(new Request('GET', '/api/documents'));
        $this->assertSame(200, $listRes->getStatusCode());
        $listData = $listRes->getData()['data'];
        $this->assertGreaterThanOrEqual(1, $listData['total']);

        // 5. Get patient documents
        $patDocsRes = $this->router->dispatch(new Request('GET', '/api/patients/' . $patientId . '/documents'));
        $this->assertSame(200, $patDocsRes->getStatusCode());
        $patDocsData = $patDocsRes->getData()['data'];
        $this->assertSame(1, $patDocsData['count']);
        $this->assertSame($patientId, $patDocsData['patient_id']);

        // 6. Delete document
        $delRes = $this->router->dispatch(new Request('DELETE', '/api/documents/' . $docId));
        $this->assertSame(204, $delRes->getStatusCode());
    }
    public function testDocumentValidationAndEdgeCases(): void
    {
        // 1. Missing patient_id -> 422
        $resNoPatient = $this->router->dispatch(new Request('POST', '/api/documents', bodyParams: [
            'title' => 'Test',
        ]));
        $this->assertSame(422, $resNoPatient->getStatusCode());

        // 2. Non-existent patient -> 404
        $resPatient404 = $this->router->dispatch(new Request('POST', '/api/documents', bodyParams: [
            'patient_id' => 'unknown-pat-id',
            'title' => 'Test',
        ]));
        $this->assertSame(404, $resPatient404->getStatusCode());

        // 3. Create valid patient
        $patRes = $this->router->dispatch(new Request('POST', '/api/patients', bodyParams: [
            'first_name' => 'Paul',
            'last_name' => 'Verlaine',
            'email' => 'paul.verlaine@test.local',
            'phone' => '0611223344',
        ]));
        $patData = $patRes->getData()['data'];
        $patientId = is_array($patData) ? $patData['id'] : $patData->getId();

        // 4. Non-existent practitioner -> 404
        $resPract404 = $this->router->dispatch(new Request('POST', '/api/documents', bodyParams: [
            'patient_id' => $patientId,
            'practitioner_id' => 'unknown-pract-id',
            'title' => 'Test',
        ]));
        $this->assertSame(404, $resPract404->getStatusCode());

        // 5. Document not found (404)
        $doc404 = $this->router->dispatch(new Request('GET', '/api/documents/unknown-doc-id'));
        $this->assertSame(404, $doc404->getStatusCode());

        // 6. Test OCR route with empty content_base64
        $ocrRes = $this->router->dispatch(new Request('POST', '/api/documents/test-ocr', bodyParams: []));
        $this->assertSame(200, $ocrRes->getStatusCode());
        $this->assertStringContainsString('ORDONNANCE', $ocrRes->getData()['data']['extracted_text']);

        // 7. Create document without title -> falls back to 'Document Médical sans titre'
        $docNoTitleRes = $this->router->dispatch(new Request('POST', '/api/documents', bodyParams: [
            'patient_id' => $patientId,
        ]));
        $this->assertSame(201, $docNoTitleRes->getStatusCode());
        $this->assertSame('Document Médical sans titre', $docNoTitleRes->getData()['data']['title']);
        $this->assertSame('OTHER', $docNoTitleRes->getData()['data']['type']);

        // 8. List documents with pagination (limit & offset) and type filter
        $listPaged = $this->router->dispatch(new Request('GET', '/api/documents', [
            'limit' => '1',
            'offset' => '1',
            'type' => 'OTHER',
            'patient_id' => $patientId,
        ]));
        $this->assertSame(200, $listPaged->getStatusCode());
        $this->assertSame(1, $listPaged->getData()['data']['limit']);
        $this->assertSame(1, $listPaged->getData()['data']['offset']);

        // 9. Filter patient documents by type
        $patFilter = $this->router->dispatch(new Request('GET', "/api/patients/{$patientId}/documents", ['type' => 'OTHER']));
        $this->assertSame(200, $patFilter->getStatusCode());
        $this->assertGreaterThanOrEqual(1, $patFilter->getData()['data']['count']);
    }
}
