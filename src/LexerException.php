<?php

declare(strict_types=1);

namespace App\Parser;

use Exception;

/**
 * Excepción personalizada para los errores del Analizador Léxico (Lexer).
 */
class LexerException extends Exception
{
    private int $linea;
    private int $columna;

    public function __construct(string $mensaje, int $linea, int $columna)
    {
        $this->linea = $linea;
        $this->columna = $columna;
        
        $mensajeFormateado = sprintf(
            "%s en la línea %d, columna %d.",
            rtrim($mensaje, '.'),
            $linea,
            $columna
        );

        parent::__construct($mensajeFormateado);
    }

    public function obtenerLinea(): int
    {
        return $this->linea;
    }

    public function obtenerColumna(): int
    {
        return $this->columna;
    }
}
