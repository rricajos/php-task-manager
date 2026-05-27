<?php

declare(strict_types=1);

namespace MiniProject;

/**
 * Enumeracion para la prioridad de una tarea.
 *
 * Usa backed enums de PHP 8.1 con valores string que coinciden
 * con los valores almacenados en la base de datos SQLite.
 */
enum Priority: string
{
    case Alta = 'alta';
    case Media = 'media';
    case Baja = 'baja';

    /**
     * Devuelve una representacion con color ANSI para la terminal.
     *
     * @return string Texto coloreado con codigos ANSI
     */
    public function colorizado(): string
    {
        return match ($this) {
            self::Alta  => "\033[31m{$this->value}\033[0m",   // Rojo
            self::Media => "\033[33m{$this->value}\033[0m",   // Amarillo
            self::Baja  => "\033[32m{$this->value}\033[0m",   // Verde
        };
    }

    /**
     * Devuelve el emoji representativo de cada prioridad.
     *
     * @return string Simbolo indicador de prioridad
     */
    public function indicador(): string
    {
        return match ($this) {
            self::Alta  => '!!!',
            self::Media => '!! ',
            self::Baja  => '!  ',
        };
    }
}

/**
 * Enumeracion para el estado de una tarea.
 *
 * Representa los dos estados posibles: pendiente o completada.
 */
enum Status: string
{
    case Pendiente = 'pendiente';
    case Completada = 'completada';

    /**
     * Devuelve una representacion con color ANSI para la terminal.
     *
     * @return string Texto coloreado con codigos ANSI
     */
    public function colorizado(): string
    {
        return match ($this) {
            self::Pendiente  => "\033[33m{$this->value}\033[0m",   // Amarillo
            self::Completada => "\033[32m{$this->value}\033[0m",   // Verde
        };
    }

    /**
     * Devuelve un icono de casilla para la terminal.
     *
     * @return string Casilla marcada o vacia
     */
    public function casilla(): string
    {
        return match ($this) {
            self::Pendiente  => '[ ]',
            self::Completada => '[x]',
        };
    }
}

/**
 * Entidad que representa una tarea del gestor.
 *
 * Usa propiedades readonly de PHP 8.1 para inmutabilidad,
 * constructor promotion para concision, y un metodo de
 * fabrica estatico para crear instancias desde filas de BD.
 *
 * Caracteristicas PHP 8: readonly, constructor promotion, named arguments,
 * enums, union types, metodo de fabrica estatico
 */
class Task
{
    /**
     * Crea una nueva instancia de Task.
     *
     * @param int|null $id Identificador unico (null para tareas nuevas)
     * @param string $titulo Titulo de la tarea
     * @param string $descripcion Descripcion detallada
     * @param Priority $prioridad Nivel de prioridad
     * @param Status $estado Estado actual de la tarea
     * @param string $fechaCreacion Fecha y hora de creacion
     * @param string|null $fechaCompletada Fecha y hora de completado (null si pendiente)
     * @param string|null $fechaVencimiento Fecha limite de la tarea (null si no tiene)
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $titulo,
        public readonly string $descripcion,
        public readonly Priority $prioridad,
        public readonly Status $estado = Status::Pendiente,
        public readonly string $fechaCreacion = '',
        public readonly ?string $fechaCompletada = null,
        public readonly ?string $fechaVencimiento = null,
    ) {}

    /**
     * Metodo de fabrica estatico: crea un Task desde una fila de la base de datos.
     *
     * Convierte los valores string de la BD a los enums correspondientes
     * usando named arguments para mayor claridad.
     *
     * @param array<string, mixed> $row Fila asociativa de la base de datos
     * @return self Nueva instancia de Task
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            titulo: $row['titulo'],
            descripcion: $row['descripcion'] ?? '',
            prioridad: Priority::from($row['prioridad']),
            estado: Status::from($row['estado']),
            fechaCreacion: $row['fecha_creacion'] ?? '',
            fechaCompletada: $row['fecha_completada'],
            fechaVencimiento: $row['fecha_vencimiento'] ?? null,
        );
    }

    /**
     * Convierte la tarea a un array asociativo (util para exportacion).
     *
     * @return array<string, mixed> Representacion en array de la tarea
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'prioridad' => $this->prioridad->value,
            'estado' => $this->estado->value,
            'fecha_creacion' => $this->fechaCreacion,
            'fecha_completada' => $this->fechaCompletada,
            'fecha_vencimiento' => $this->fechaVencimiento,
        ];
    }

    /**
     * Genera una representacion formateada de la tarea para la terminal.
     *
     * Incluye un indicador de vencimiento si la tarea esta vencida.
     *
     * @return string Linea formateada con colores ANSI
     */
    public function formatoLinea(): string
    {
        $casilla = $this->estado->casilla();
        $indicador = $this->prioridad->indicador();
        $prioridad = $this->prioridad->colorizado();

        // Indicador de tarea vencida
        $vencida = '';
        if (
            $this->fechaVencimiento !== null
            && $this->estado === Status::Pendiente
            && $this->fechaVencimiento < date('Y-m-d')
        ) {
            $vencida = " \033[31m[VENCIDA]\033[0m";
        }

        return sprintf(
            "  \033[36m#%-4d\033[0m %s %s %-30s [%s]%s",
            $this->id,
            $casilla,
            $indicador,
            mb_substr($this->titulo, 0, 30),
            $prioridad,
            $vencida,
        );
    }

    /**
     * Genera una representacion detallada de la tarea.
     *
     * @return string Bloque de texto con todos los detalles
     */
    public function formatoDetalle(): string
    {
        $lineas = [];
        $lineas[] = "\033[1;36m--- Detalle de Tarea #{$this->id} ---\033[0m";
        $lineas[] = "  Titulo:      \033[1m{$this->titulo}\033[0m";
        $lineas[] = "  Descripcion: {$this->descripcion}";
        $lineas[] = "  Prioridad:   {$this->prioridad->colorizado()}";
        $lineas[] = "  Estado:      {$this->estado->colorizado()}";
        $lineas[] = "  Creada:      {$this->fechaCreacion}";

        if ($this->fechaCompletada !== null) {
            $lineas[] = "  Completada:  {$this->fechaCompletada}";
        }

        if ($this->fechaVencimiento !== null) {
            $vencimientoTexto = $this->fechaVencimiento;
            if ($this->estado === Status::Pendiente && $this->fechaVencimiento < date('Y-m-d')) {
                $vencimientoTexto = "\033[31m{$this->fechaVencimiento} (VENCIDA)\033[0m";
            }
            $lineas[] = "  Vencimiento: {$vencimientoTexto}";
        }

        $lineas[] = "\033[1;36m" . str_repeat('-', 35) . "\033[0m";

        return implode(PHP_EOL, $lineas);
    }
}
