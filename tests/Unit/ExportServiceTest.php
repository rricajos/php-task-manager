<?php

declare(strict_types=1);

namespace Tests\Unit;

use MiniProject\CsvExporter;
use MiniProject\ExporterInterface;
use MiniProject\ExportService;
use MiniProject\JsonExporter;
use MiniProject\Priority;
use MiniProject\Status;
use MiniProject\Task;
use MiniProject\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para el servicio de exportación y las estrategias.
 * Unit tests for the export service and export strategies.
 *
 * @covers \MiniProject\JsonExporter
 * @covers \MiniProject\CsvExporter
 * @covers \MiniProject\ExportService
 */
class ExportServiceTest extends TestCase
{
    private string $tempDir;

    /** @var Task[] */
    private array $sampleTasks;

    protected function setUp(): void
    {
        // Create a unique temporary directory for each test
        $this->tempDir = sys_get_temp_dir() . '/mini_project_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        // Prepare sample tasks for the tests
        $this->sampleTasks = [
            new Task(
                id: 1,
                title: 'Comprar leche',
                description: 'En el supermercado',
                priority: Priority::High,
                status: Status::Pending,
                createdAt: '2026-05-01 10:00:00',
                completedAt: null,
            ),
            new Task(
                id: 2,
                title: 'Estudiar PHP',
                description: 'Repasar patrones',
                priority: Priority::Medium,
                status: Status::Completed,
                createdAt: '2026-05-01 08:00:00',
                completedAt: '2026-05-01 12:00:00',
            ),
            new Task(
                id: 3,
                title: 'Hacer ejercicio',
                description: '',
                priority: Priority::Low,
                status: Status::Pending,
                createdAt: '2026-05-02 07:00:00',
                completedAt: null,
            ),
        ];
    }

    protected function tearDown(): void
    {
        // Clean up files generated in the temporary directory
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            rmdir($this->tempDir);
        }
    }

    // ---------------------------------------------------------------
    //  Tests de JsonExporter
    // ---------------------------------------------------------------

    public function testJsonExporterProducesValidJson(): void
    {
        $exporter = new JsonExporter(outputDir: $this->tempDir);

        $filepath = $exporter->export($this->sampleTasks);

        // Verify the file was created
        $this->assertFileExists($filepath);

        // Read and decode the JSON
        $content = file_get_contents($filepath);
        $this->assertNotFalse($content);

        $data = json_decode($content, true);
        $this->assertNotNull($data, 'The generated JSON must be valid');
        $this->assertIsArray($data);
    }

    public function testJsonExporterContainsCorrectStructure(): void
    {
        $exporter = new JsonExporter(outputDir: $this->tempDir);

        $filepath = $exporter->export($this->sampleTasks);
        $data = json_decode(file_get_contents($filepath), true);

        // Verify top-level keys
        $this->assertArrayHasKey('exported_at', $data);
        $this->assertArrayHasKey('total_tasks', $data);
        $this->assertArrayHasKey('tasks', $data);

        // Verify task count
        $this->assertSame(3, $data['total_tasks']);
        $this->assertCount(3, $data['tasks']);
    }

    public function testJsonExporterContainsTaskDetails(): void
    {
        $exporter = new JsonExporter(outputDir: $this->tempDir);

        $filepath = $exporter->export($this->sampleTasks);
        $data = json_decode(file_get_contents($filepath), true);

        $firstTask = $data['tasks'][0];

        $this->assertSame(1, $firstTask['id']);
        $this->assertSame('Comprar leche', $firstTask['title']);
        $this->assertSame('En el supermercado', $firstTask['description']);
        $this->assertSame('high', $firstTask['priority']);
        $this->assertSame('pending', $firstTask['status']);
    }

    public function testJsonExporterWithEmptyList(): void
    {
        $exporter = new JsonExporter(outputDir: $this->tempDir);

        $filepath = $exporter->export([]);
        $data = json_decode(file_get_contents($filepath), true);

        $this->assertSame(0, $data['total_tasks']);
        $this->assertCount(0, $data['tasks']);
    }

    public function testJsonExporterGeneratesFileWithJsonExtension(): void
    {
        $exporter = new JsonExporter(outputDir: $this->tempDir);

        $filepath = $exporter->export($this->sampleTasks);

        $this->assertStringEndsWith('.json', $filepath);
    }

    public function testJsonExporterGetFormatName(): void
    {
        $exporter = new JsonExporter(outputDir: $this->tempDir);

        $this->assertSame('JSON', $exporter->getFormatName());
    }

    public function testJsonExporterImplementsInterface(): void
    {
        $exporter = new JsonExporter(outputDir: $this->tempDir);

        $this->assertInstanceOf(ExporterInterface::class, $exporter);
    }

    // ---------------------------------------------------------------
    //  Tests de CsvExporter
    // ---------------------------------------------------------------

    public function testCsvExporterProducesValidCsvWithHeaders(): void
    {
        $exporter = new CsvExporter(outputDir: $this->tempDir);

        $filepath = $exporter->export($this->sampleTasks);

        $this->assertFileExists($filepath);

        // Read the CSV content
        $content = file_get_contents($filepath);
        $this->assertNotFalse($content);

        // Remove UTF-8 BOM if present to analyze the content
        $contentWithoutBom = ltrim($content, "\xEF\xBB\xBF");
        $lines = explode("\n", trim($contentWithoutBom));

        // The first line must be the headers
        $headers = str_getcsv($lines[0]);
        $this->assertContains('ID', $headers);
        $this->assertContains('Title', $headers);
        $this->assertContains('Description', $headers);
        $this->assertContains('Priority', $headers);
        $this->assertContains('Status', $headers);
        $this->assertContains('Created At', $headers);
        $this->assertContains('Completed At', $headers);
    }

    public function testCsvExporterContainsDataRows(): void
    {
        $exporter = new CsvExporter(outputDir: $this->tempDir);

        $filepath = $exporter->export($this->sampleTasks);

        $content = file_get_contents($filepath);
        $contentWithoutBom = ltrim($content, "\xEF\xBB\xBF");
        $lines = explode("\n", trim($contentWithoutBom));

        // 1 header line + 3 data lines
        $this->assertCount(4, $lines);

        // Verify first data row
        $firstRow = str_getcsv($lines[1]);
        $this->assertSame('1', $firstRow[0]); // ID
        $this->assertSame('Comprar leche', $firstRow[1]); // Title
    }

    public function testCsvExporterWithEmptyList(): void
    {
        $exporter = new CsvExporter(outputDir: $this->tempDir);

        $filepath = $exporter->export([]);

        $content = file_get_contents($filepath);
        $contentWithoutBom = ltrim($content, "\xEF\xBB\xBF");
        $lines = explode("\n", trim($contentWithoutBom));

        // Only headers, no data
        $this->assertCount(1, $lines);
    }

    public function testCsvExporterGeneratesFileWithCsvExtension(): void
    {
        $exporter = new CsvExporter(outputDir: $this->tempDir);

        $filepath = $exporter->export($this->sampleTasks);

        $this->assertStringEndsWith('.csv', $filepath);
    }

    public function testCsvExporterGetFormatName(): void
    {
        $exporter = new CsvExporter(outputDir: $this->tempDir);

        $this->assertSame('CSV', $exporter->getFormatName());
    }

    public function testCsvExporterImplementsInterface(): void
    {
        $exporter = new CsvExporter(outputDir: $this->tempDir);

        $this->assertInstanceOf(ExporterInterface::class, $exporter);
    }

    public function testCsvExporterIncludesUtf8Bom(): void
    {
        $exporter = new CsvExporter(outputDir: $this->tempDir);

        $filepath = $exporter->export($this->sampleTasks);

        $rawContent = file_get_contents($filepath);

        // Verify the first 3 bytes are the UTF-8 BOM
        $this->assertSame("\xEF\xBB\xBF", substr($rawContent, 0, 3));
    }

    // ---------------------------------------------------------------
    //  Tests de ExportService (contexto del patron Strategy)
    // ---------------------------------------------------------------

    public function testExportServiceRegistersDefaultExporters(): void
    {
        $service = new ExportService(outputDir: $this->tempDir);

        $formats = $service->availableFormats();

        $this->assertContains('json', $formats);
        $this->assertContains('csv', $formats);
    }

    public function testExportServiceRegistersAndRetrievesCustomExporter(): void
    {
        $service = new ExportService(outputDir: $this->tempDir);

        // Create a custom exporter with an anonymous class
        $customExporter = new class () implements ExporterInterface {
            public function export(array $tasks): string
            {
                return '/tmp/custom_export.txt';
            }

            public function exportToString(array $tasks): string
            {
                return 'custom export string';
            }

            public function getFormatName(): string
            {
                return 'Custom';
            }
        };

        $service->registerExporter('custom', $customExporter);

        $formats = $service->availableFormats();
        $this->assertContains('custom', $formats);
    }

    public function testExportServiceExportsInJsonFormat(): void
    {
        $service = new ExportService(outputDir: $this->tempDir);

        $filepath = $service->export($this->sampleTasks, 'json');

        $this->assertFileExists($filepath);
        $this->assertStringEndsWith('.json', $filepath);

        // Verify the JSON is valid
        $data = json_decode(file_get_contents($filepath), true);
        $this->assertNotNull($data);
    }

    public function testExportServiceExportsInCsvFormat(): void
    {
        $service = new ExportService(outputDir: $this->tempDir);

        $filepath = $service->export($this->sampleTasks, 'csv');

        $this->assertFileExists($filepath);
        $this->assertStringEndsWith('.csv', $filepath);
    }

    public function testExportServiceExportCreatesFile(): void
    {
        $subDir = $this->tempDir . '/exportaciones';
        $service = new ExportService(outputDir: $subDir);

        $filepath = $service->export($this->sampleTasks, 'json');

        $this->assertFileExists($filepath);
        $this->assertDirectoryExists($subDir);

        // Clean up subdirectory
        if (is_file($filepath)) {
            unlink($filepath);
        }
        if (is_dir($subDir)) {
            rmdir($subDir);
        }
    }

    public function testExportServiceRejectsUnregisteredFormat(): void
    {
        $service = new ExportService(outputDir: $this->tempDir);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('not available');

        $service->export($this->sampleTasks, 'xml');
    }

    public function testExportServiceFormatIsCaseInsensitive(): void
    {
        $service = new ExportService(outputDir: $this->tempDir);

        // The format is converted to lowercase internally
        $filepath = $service->export($this->sampleTasks, 'JSON');

        $this->assertFileExists($filepath);
    }

    public function testExportServiceAvailableFormatsReturnsArray(): void
    {
        $service = new ExportService(outputDir: $this->tempDir);

        $formats = $service->availableFormats();

        $this->assertIsArray($formats);
        $this->assertCount(2, $formats);
    }

    // ---------------------------------------------------------------
    //  Tests de exportToString (JsonExporter)
    // ---------------------------------------------------------------

    public function testJsonExporterExportToStringReturnsValidJson(): void
    {
        $exporter = new JsonExporter(outputDir: $this->tempDir);

        $content = $exporter->exportToString($this->sampleTasks);

        $data = json_decode($content, true);
        $this->assertNotNull($data, 'The JSON string must be valid');
        $this->assertArrayHasKey('exported_at', $data);
        $this->assertArrayHasKey('total_tasks', $data);
        $this->assertArrayHasKey('tasks', $data);
        $this->assertSame(3, $data['total_tasks']);
        $this->assertCount(3, $data['tasks']);
    }

    public function testJsonExporterExportToStringWithEmptyList(): void
    {
        $exporter = new JsonExporter(outputDir: $this->tempDir);

        $content = $exporter->exportToString([]);

        $data = json_decode($content, true);
        $this->assertSame(0, $data['total_tasks']);
        $this->assertCount(0, $data['tasks']);
    }

    public function testJsonExporterExportToStringDoesNotCreateFile(): void
    {
        $subDir = $this->tempDir . '/no_debe_existir';
        $exporter = new JsonExporter(outputDir: $subDir);

        $exporter->exportToString($this->sampleTasks);

        // exportToString must not create the directory
        $this->assertDirectoryDoesNotExist($subDir);
    }

    // ---------------------------------------------------------------
    //  Tests de exportToString (CsvExporter)
    // ---------------------------------------------------------------

    public function testCsvExporterExportToStringContainsHeaders(): void
    {
        $exporter = new CsvExporter(outputDir: $this->tempDir);

        $content = $exporter->exportToString($this->sampleTasks);

        // Remove BOM if present
        $contentWithoutBom = ltrim($content, "\xEF\xBB\xBF");

        $this->assertStringContainsString('ID', $contentWithoutBom);
        $this->assertStringContainsString('Title', $contentWithoutBom);
        $this->assertStringContainsString('Description', $contentWithoutBom);
        $this->assertStringContainsString('Priority', $contentWithoutBom);
        $this->assertStringContainsString('Status', $contentWithoutBom);
    }

    public function testCsvExporterExportToStringContainsDataRows(): void
    {
        $exporter = new CsvExporter(outputDir: $this->tempDir);

        $content = $exporter->exportToString($this->sampleTasks);
        $contentWithoutBom = ltrim($content, "\xEF\xBB\xBF");
        $lines = explode("\n", trim($contentWithoutBom));

        // 1 header + 3 data rows
        $this->assertCount(4, $lines);

        // Verify the first row contains task data
        $this->assertStringContainsString('Comprar leche', $contentWithoutBom);
    }

    public function testCsvExporterExportToStringWithEmptyList(): void
    {
        $exporter = new CsvExporter(outputDir: $this->tempDir);

        $content = $exporter->exportToString([]);
        $contentWithoutBom = ltrim($content, "\xEF\xBB\xBF");
        $lines = explode("\n", trim($contentWithoutBom));

        // Only headers
        $this->assertCount(1, $lines);
    }

    public function testCsvExporterExportToStringIncludesBom(): void
    {
        $exporter = new CsvExporter(outputDir: $this->tempDir);

        $content = $exporter->exportToString($this->sampleTasks);

        $this->assertSame("\xEF\xBB\xBF", substr($content, 0, 3));
    }

    // ---------------------------------------------------------------
    //  Tests de ExportService::exportAsString
    // ---------------------------------------------------------------

    public function testExportServiceExportAsStringJson(): void
    {
        $service = new ExportService(outputDir: $this->tempDir);

        $content = $service->exportAsString($this->sampleTasks, 'json');

        $data = json_decode($content, true);
        $this->assertNotNull($data);
        $this->assertSame(3, $data['total_tasks']);
    }

    public function testExportServiceExportAsStringCsv(): void
    {
        $service = new ExportService(outputDir: $this->tempDir);

        $content = $service->exportAsString($this->sampleTasks, 'csv');

        $contentWithoutBom = ltrim($content, "\xEF\xBB\xBF");
        $this->assertStringContainsString('ID', $contentWithoutBom);
        $this->assertStringContainsString('Comprar leche', $contentWithoutBom);
    }

    public function testExportServiceExportAsStringInvalidFormat(): void
    {
        $service = new ExportService(outputDir: $this->tempDir);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('not available');

        $service->exportAsString($this->sampleTasks, 'xml');
    }

    public function testExportServiceExportAsStringCaseInsensitive(): void
    {
        $service = new ExportService(outputDir: $this->tempDir);

        $content = $service->exportAsString($this->sampleTasks, 'JSON');

        $data = json_decode($content, true);
        $this->assertNotNull($data);
    }
}
