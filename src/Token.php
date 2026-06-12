<?php

declare(strict_types=1);

namespace App\Parser;

use JsonSerializable;

/**
 * Clase que representa un componente léxico (Token).
 */
class Token implements JsonSerializable
{
    public string $tipo;
    public string $valor;
    public int $linea;
    public int $columna;

    public function __construct(string $tipo, string $valor, int $linea, int $columna)
    {
        $this->tipo = $tipo;
        $this->valor = $valor;
        $this->linea = $linea;
        $this->columna = $columna;
    }

    /**
     * Convierte el token a un array asociativo 
     *
     * @return array{tipo: string, valor: string, linea: int, columna: int}
     */
    public function aArreglo(): array
    {
        return [
            'tipo' => $this->tipo,
            'valor' => $this->valor,
            'linea' => $this->linea,
            'columna' => $this->columna,
        ];
    }

    /**
     * Especifica los datos que deben serializarse a JSON.
     *
     * @return array{tipo: string, valor: string, linea: int, columna: int}
     */
    public function jsonSerialize(): array
    {
        return $this->aArreglo();
    }
}
