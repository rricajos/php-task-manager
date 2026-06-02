<?php

declare(strict_types=1);

namespace MiniProject;

/**
 * Interfaz para exportadores de tareas - Patrón Strategy.
 * Interface for task exporters - Strategy Pattern.
 *
 * Define el contrato que deben cumplir todos los exportadores.
 * Cada implementación representa una estrategia de exportación diferente.
 *
 * Defines the contract that all exporters must fulfill.
 * Each implementation represents a different export strategy.
 *
 * Patrón / Pattern: Strategy
 */
interface ExporterInterface
{
    /**
     * Exporta una lista de tareas al formato específico (archivo).
     * Exports a list of tasks to the specific format (file).
     *
     * @param Task[] $tasks Lista de tareas a exportar / List of tasks to export
     * @return string Ruta del archivo generado / Path of the generated file
     */
    public function export(array $tasks): string;

    /**
     * Exporta una lista de tareas y retorna el contenido como string.
     * Exports a list of tasks and returns the content as a string.
     *
     * Útil para streaming directo en la API sin escribir a disco.
     * Useful for direct streaming in the API without writing to disk.
     *
     * @param Task[] $tasks Lista de tareas a exportar / List of tasks to export
     * @return string Contenido exportado como string / Exported content as string
     */
    public function exportToString(array $tasks): string;

    /**
     * Devuelve el nombre descriptivo del formato de exportación.
     * Returns the descriptive name of the export format.
     *
     * @return string Nombre del formato (ej: 'JSON', 'CSV') / Format name (e.g.: 'JSON', 'CSV')
     */
    public function getFormatName(): string;
}

/**
 * Exportador de tareas a formato JSON.
 * Task exporter to JSON format.
 *
 * Implementa la estrategia de exportación JSON con formato legible
 * (pretty print) y soporte Unicode para caracteres en español.
 *
 * Implements the JSON export strategy with readable format
 * (pretty print) and Unicode support for Spanish characters.
 *
 * Patrón / Pattern: Strategy (implementación concreta / concrete implementation)
 * Características PHP 8 / PHP 8 Features: constructor promotion, readonly
 */
class JsonExporter implements ExporterInterface
{
    /**
     * @param string $outputDir Directorio donde se guardarán los archivos exportados /
     *                          Directory where exported files will be saved
     */
    public function __construct(
        private readonly string $outputDir,
    ) {
    }

    /**
     * Exporta las tareas a un archivo JSON con timestamp en el nombre.
     * Exports tasks to a JSON file with a timestamp in the name.
     *
     * El archivo se genera con formato legible (JSON_PRETTY_PRINT) y
     * sin escapar caracteres Unicode (JSON_UNESCAPED_UNICODE) para
     * preservar tildes y caracteres especiales del español.
     *
     * The file is generated with readable format (JSON_PRETTY_PRINT) and
     * without escaping Unicode characters (JSON_UNESCAPED_UNICODE) to
     * preserve accents and special Spanish characters.
     *
     * @param Task[] $tasks Lista de tareas a exportar / List of tasks to export
     * @return string Ruta absoluta del archivo JSON generado / Absolute path of the generated JSON file
     * @throws AppException Si no se puede escribir el archivo / If the file cannot be written
     */
    public function export(array $tasks): string
    {
        // Ensure that the output directory exists
        $this->ensureDirectory();

        // Convert tasks to arrays for JSON serialization
        $data = [
            'exported_at' => date('Y-m-d H:i:s'),
            'total_tasks' => count($tasks),
            'tasks' => array_map(
                callback: fn (Task $t): array => $t->toArray(),
                array: $tasks,
            ),
        ];

        // Generate filename with timestamp
        $filename = sprintf('tasks_%s.json', date('Y-m-d_His'));
        $filepath = $this->outputDir . '/' . $filename;

        // Encode to JSON with readable format and Unicode support
        $json = json_encode(
            value: $data,
            flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        $result = file_put_contents($filepath, $json);

        if ($result === false) {
            throw new AppException(
                message: "Could not write the file: {$filepath}",
                code: AppException::ERROR_FILESYSTEM,
            );
        }

        return $filepath;
    }

    /**
     * Exporta las tareas a una cadena JSON sin escribir a disco.
     * Exports tasks to a JSON string without writing to disk.
     *
     * @param Task[] $tasks Lista de tareas a exportar / List of tasks to export
     * @return string Contenido JSON como string / JSON content as string
     */
    public function exportToString(array $tasks): string
    {
        $data = [
            'exported_at' => date('Y-m-d H:i:s'),
            'total_tasks' => count($tasks),
            'tasks' => array_map(
                callback: fn (Task $t): array => $t->toArray(),
                array: $tasks,
            ),
        ];

        return json_encode(
            value: $data,
            flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Devuelve el nombre del formato de exportación.
     * Returns the name of the export format.
     *
     * @return string 'JSON'
     */
    public function getFormatName(): string
    {
        return 'JSON';
    }

    /**
     * Asegura que el directorio de salida exista, creándolo si es necesario.
     * Ensures the output directory exists, creating it if necessary.
     *
     * @throws AppException Si no se puede crear el directorio / If the directory cannot be created
     */
    private function ensureDirectory(): void
    {
        if (!is_dir($this->outputDir)) {
            if (!mkdir($this->outputDir, 0755, true)) {
                throw new AppException(
                    message: "Could not create directory: {$this->outputDir}",
                    code: AppException::ERROR_FILESYSTEM,
                );
            }
        }
    }
}

/**
 * Exportador de tareas a formato CSV.
 * Task exporter to CSV format.
 *
 * Implementa la estrategia de exportación CSV compatible con
 * hojas de cálculo (Excel, LibreOffice Calc, etc).
 *
 * Implements the CSV export strategy compatible with
 * spreadsheets (Excel, LibreOffice Calc, etc).
 *
 * Patrón / Pattern: Strategy (implementación concreta / concrete implementation)
 * Características PHP 8 / PHP 8 Features: constructor promotion, readonly
 */
class CsvExporter implements ExporterInterface
{
    /**
     * @param string $outputDir Directorio donde se guardarán los archivos exportados /
     *                          Directory where exported files will be saved
     */
    public function __construct(
        private readonly string $outputDir,
    ) {
    }

    /**
     * Exporta las tareas a un archivo CSV con encabezados.
     * Exports tasks to a CSV file with headers.
     *
     * Incluye BOM UTF-8 para compatibilidad con Excel y usa
     * las funciones nativas de PHP para generar CSV válido.
     *
     * Includes UTF-8 BOM for Excel compatibility and uses
     * native PHP functions to generate valid CSV.
     *
     * @param Task[] $tasks Lista de tareas a exportar / List of tasks to export
     * @return string Ruta absoluta del archivo CSV generado / Absolute path of the generated CSV file
     * @throws AppException Si no se puede escribir el archivo / If the file cannot be written
     */
    public function export(array $tasks): string
    {
        // Ensure that the output directory exists
        $this->ensureDirectory();

        // Generate filename with timestamp
        $filename = sprintf('tasks_%s.csv', date('Y-m-d_His'));
        $filepath = $this->outputDir . '/' . $filename;

        // Open file for writing
        $handle = fopen($filepath, 'w');

        if ($handle === false) {
            throw new AppException(
                message: "Could not create the file: {$filepath}",
                code: AppException::ERROR_FILESYSTEM,
            );
        }

        // Write UTF-8 BOM for Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");

        // Write headers
        fputcsv($handle, [
            'ID',
            'Title',
            'Description',
            'Priority',
            'Status',
            'Created At',
            'Completed At',
        ]);

        // Write each task as a CSV row
        foreach ($tasks as $task) {
            fputcsv($handle, [
                $task->id,
                $task->title,
                $task->description,
                $task->priority->value,
                $task->status->value,
                $task->createdAt,
                $task->completedAt ?? '',
            ]);
        }

        fclose($handle);

        return $filepath;
    }

    /**
     * Exporta las tareas a una cadena CSV sin escribir a disco.
     * Exports tasks to a CSV string without writing to disk.
     *
     * Usa php://temp como stream en memoria para generar el CSV.
     * Uses php://temp as an in-memory stream to generate the CSV.
     *
     * @param Task[] $tasks Lista de tareas a exportar / List of tasks to export
     * @return string Contenido CSV como string / CSV content as string
     */
    public function exportToString(array $tasks): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new AppException(
                message: 'Could not open temporary stream for CSV',
                code: AppException::ERROR_FILESYSTEM,
            );
        }

        // Write UTF-8 BOM for Excel compatibility
        fwrite($handle, "\xEF\xBB\xBF");

        // Write headers
        fputcsv($handle, [
            'ID',
            'Title',
            'Description',
            'Priority',
            'Status',
            'Created At',
            'Completed At',
        ]);

        // Write each task as a CSV row
        foreach ($tasks as $task) {
            fputcsv($handle, [
                $task->id,
                $task->title,
                $task->description,
                $task->priority->value,
                $task->status->value,
                $task->createdAt,
                $task->completedAt ?? '',
            ]);
        }

        // Read all content from the stream
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content !== false ? $content : '';
    }

    /**
     * Devuelve el nombre del formato de exportación.
     * Returns the name of the export format.
     *
     * @return string 'CSV'
     */
    public function getFormatName(): string
    {
        return 'CSV';
    }

    /**
     * Asegura que el directorio de salida exista, creándolo si es necesario.
     * Ensures the output directory exists, creating it if necessary.
     *
     * @throws AppException Si no se puede crear el directorio / If the directory cannot be created
     */
    private function ensureDirectory(): void
    {
        if (!is_dir($this->outputDir)) {
            if (!mkdir($this->outputDir, 0755, true)) {
                throw new AppException(
                    message: "Could not create directory: {$this->outputDir}",
                    code: AppException::ERROR_FILESYSTEM,
                );
            }
        }
    }
}

/**
 * Servicio de exportación que gestiona las estrategias disponibles.
 * Export service that manages the available strategies.
 *
 * Actúa como contexto del patrón Strategy, permitiendo seleccionar
 * dinámicamente el exportador a utilizar.
 *
 * Acts as the Strategy pattern context, allowing dynamic selection
 * of the exporter to use.
 *
 * Patrón / Pattern: Strategy (contexto / context)
 * Características PHP 8 / PHP 8 Features: constructor promotion, readonly, match, named arguments
 */
class ExportService
{
    /** @var array<string, ExporterInterface> Exportadores registrados por nombre / Exporters registered by name */
    private array $exporters = [];

    /**
     * Inicializa el servicio con el directorio de salida por defecto
     * y registra los exportadores disponibles.
     *
     * Initializes the service with the default output directory
     * and registers the available exporters.
     *
     * @param string $outputDir Directorio base para archivos exportados / Base directory for exported files
     */
    public function __construct(
        private readonly string $outputDir,
    ) {
        // Register available exporters
        $this->registerExporter('json', new JsonExporter(outputDir: $this->outputDir));
        $this->registerExporter('csv', new CsvExporter(outputDir: $this->outputDir));
    }

    /**
     * Registra un nuevo exportador con un nombre clave.
     * Registers a new exporter with a key name.
     *
     * @param string $name Nombre identificador del exportador / Exporter identifier name
     * @param ExporterInterface $exporter Instancia del exportador / Exporter instance
     */
    public function registerExporter(string $name, ExporterInterface $exporter): void
    {
        $this->exporters[strtolower($name)] = $exporter;
    }

    /**
     * Exporta tareas usando el exportador especificado.
     * Exports tasks using the specified exporter.
     *
     * @param Task[] $tasks Lista de tareas a exportar / List of tasks to export
     * @param string $format Formato deseado: 'json' o 'csv' / Desired format: 'json' or 'csv'
     * @return string Ruta del archivo generado / Path of the generated file
     * @throws ValidationException Si el formato no está registrado / If the format is not registered
     */
    public function export(array $tasks, string $format = 'json'): string
    {
        $format = strtolower(trim($format));

        if (!isset($this->exporters[$format])) {
            $available = implode(', ', array_keys($this->exporters));

            throw new ValidationException(
                message: "Export format '{$format}' not available. Available formats: {$available}",
                code: ValidationException::ERROR_INVALID_FORMAT,
                field: 'format',
            );
        }

        $exporter = $this->exporters[$format];

        return $exporter->export($tasks);
    }

    /**
     * Exporta tareas como string (sin escribir a archivo) usando el exportador especificado.
     * Exports tasks as a string (without writing to file) using the specified exporter.
     *
     * Útil para streaming directo en la API REST.
     * Useful for direct streaming in the REST API.
     *
     * @param Task[] $tasks Lista de tareas a exportar / List of tasks to export
     * @param string $format Formato deseado: 'json' o 'csv' / Desired format: 'json' or 'csv'
     * @return string Contenido exportado como string / Exported content as string
     * @throws ValidationException Si el formato no está registrado / If the format is not registered
     */
    public function exportAsString(array $tasks, string $format = 'json'): string
    {
        $format = strtolower(trim($format));

        if (!isset($this->exporters[$format])) {
            $available = implode(', ', array_keys($this->exporters));

            throw new ValidationException(
                message: "Export format '{$format}' not available. Available formats: {$available}",
                code: ValidationException::ERROR_INVALID_FORMAT,
                field: 'format',
            );
        }

        $exporter = $this->exporters[$format];

        return $exporter->exportToString($tasks);
    }

    /**
     * Devuelve los nombres de los formatos de exportación disponibles.
     * Returns the names of the available export formats.
     *
     * @return string[] Lista de formatos registrados / List of registered formats
     */
    public function availableFormats(): array
    {
        return array_keys($this->exporters);
    }
}
