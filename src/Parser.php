<?php

declare(strict_types=1);

namespace App\Parser;

/**
 * Clase base para un Analizador Sintáctico (Parser) Descendente Recursivo.
 */
class Parser
{
    /**
     * @var Token[] Array de tokens generados por el Lexer.
     */
    protected array $tokens;

    /**
     * Puntero a la posición actual del token que se está analizando.
     */
    protected int $posicion = 0;

    /**
     * Constructor del Parser.
     *
     * @param Token[] $tokens
     */
    public function __construct(array $tokens)
    {
        $this->tokens = $tokens;
    }

    /**
     * Obtiene el token en la posición actual.
     *
     * @return Token|null Retorna el Token actual, o null si se llegó al final de la entrada.
     */
    protected function obtenerTokenActual(): ?Token
    {
        return $this->tokens[$this->posicion] ?? null;
    }

    /**
     * Retorna si el token actual coincide con el tipo especificado sin consumirlo.
     *
     * @param string $tipo Esperado del token.
     * @return bool True si coincide, false en caso contrario.
     */
    protected function coincide(string $tipo): bool
    {
        $token = $this->obtenerTokenActual();
        if ($token === null) {
            return false;
        }
        return $token->tipo === $tipo;
    }

    /**
     * Verifica si se han procesado todos los tokens de la entrada.
     *
     * @return bool
     */
    protected function esFin(): bool
    {
        return $this->posicion >= count($this->tokens);
    }

    /**
     * Consume el token actual si coincide con el tipo esperado y avanza la posición.
     * Si no coincide, lanza una excepción de análisis.
     *
     * @param string $tipoEsperado El tipo de token que se espera recibir.
     * @return Token El token consumido.
     * @throws ParserException Si el tipo no coincide o se llegó al final de la entrada de forma inesperada.
     */
    protected function consumir(string $tipoEsperado): Token
    {
        $tokenActual = $this->obtenerTokenActual();

        if ($tokenActual === null) {
            // Error al final de la entrada, reportar con la línea y columna del último token si existe
            $ultimoToken = count($this->tokens) > 0 ? $this->tokens[count($this->tokens) - 1] : null;
            $linea = $ultimoToken ? $ultimoToken->linea : 1;
            $columna = $ultimoToken ? $ultimoToken->columna : 1;
            
            throw new ParserException(
                "Error Sintáctico: Se esperaba el token [{$tipoEsperado}], pero se llegó al final de la entrada.",
                $linea,
                $columna
            );
        }

        if ($tokenActual->tipo !== $tipoEsperado) {
            throw new ParserException(
                "Error Sintáctico: Se esperaba el token [{$tipoEsperado}], pero se encontró [{$tokenActual->tipo}].",
                $tokenActual->linea,
                $tokenActual->columna
            );
        }

        // Avanzar el puntero
        $this->posicion++;
        return $tokenActual;
    }

    /**
     * Punto de entrada principal para iniciar el análisis sintáctico.
     * Implementa un Parser Descendente Recursivo para la gramática LL(1).
     *
     * @return array Estructura de árbol sintáctico abstracto (AST).
     * @throws ParserException Si ocurre un error sintáctico durante el análisis.
     */
    public function analizar(): array
    {
        if ($this->esFin()) {
            throw new ParserException(
                "Error Sintáctico: La entrada está vacía, no se encontraron tokens para analizar.",
                1,
                1
            );
        }

        $arbolSintactico = $this->oracion();
        
        // Si después de ejecutar la regla raíz quedan tokens sin consumir, es un error sintáctico.
        if (!$this->esFin()) {
            $tokenSobrante = $this->obtenerTokenActual();
            throw new ParserException(
                "Error Sintáctico: Tokens adicionales inesperados después de la expresión principal: '" . ($tokenSobrante ? $tokenSobrante->valor : 'EOF') . "'.",
                $tokenSobrante ? $tokenSobrante->linea : 1,
                $tokenSobrante ? $tokenSobrante->columna : 1
            );
        }
        
        return $arbolSintactico;
    }

    /**
     * Regla: <ORACION> ::= <PETICION> | <ACCION_CON_CONTEXTO> | <RUTA_TRANSPORTE>
     */
    private function oracion(): array
    {
        $token = $this->obtenerTokenActual();

        if ($token === null) {
            throw new ParserException("Error Sintáctico: Oración incompleta al final de la entrada.", 1, 1);
        }

        // Determinar qué regla inicial procesar (Lookahead de 1 token)
        if ($token->tipo === 'POR_FAVOR' || $this->esServicio($token)) {
            return [
                'nodo' => 'Oracion',
                'categoria' => 'Peticion',
                'detalle' => $this->peticion()
            ];
        }

        if ($this->esAccion($token)) {
            return [
                'nodo' => 'Oracion',
                'categoria' => 'AccionConContexto',
                'detalle' => $this->accionConContexto()
            ];
        }

        if ($token->tipo === 'IR_A') {
            return [
                'nodo' => 'Oracion',
                'categoria' => 'RutaTransporte',
                'detalle' => $this->rutaTransporte()
            ];
        }

        throw new ParserException(
            "Error Sintáctico: Símbolo inicial inválido para comenzar una oración: '{$token->valor}'. Debe ser una petición, acción o ruta de transporte.",
            $token->linea,
            $token->columna
        );
    }

    /**
     * Regla: <PETICION> ::= "[POR_FAVOR]" <NECESIDAD> | <NECESIDAD>
     */
    private function peticion(): array
    {
        $porFavor = false;
        if ($this->coincide('POR_FAVOR')) {
            $this->consumir('POR_FAVOR');
            $porFavor = true;
        }

        $necesidad = $this->necesidad();

        return [
            'nodo' => 'Peticion',
            'por_favor' => $porFavor,
            'necesidad' => $necesidad
        ];
    }

    /**
     * Regla: <NECESIDAD> ::= <SERVICIO> <LISTA_EXTRA>
     */
    private function necesidad(): array
    {
        $servicio = $this->servicio();
        $listaExtra = $this->listaExtra();

        return [
            'nodo' => 'Necesidad',
            'servicio' => $servicio,
            'lista_extra' => $listaExtra
        ];
    }

    /**
     * Regla: <LISTA_EXTRA> ::= "[Y]" <NECESIDAD> | ε
     */
    private function listaExtra(): ?array
    {
        if ($this->coincide('Y')) {
            $this->consumir('Y');
            $necesidad = $this->necesidad();
            return [
                'nodo' => 'ListaExtra',
                'necesidad' => $necesidad
            ];
        }

        return null; // Representa epsilon (cadena vacía)
    }

    /**
     * Regla: <SERVICIO> ::= "[CAFÉ]" | "[RESTAURANTE]" | "[WIFI]" | "[BAÑO]" | "[TELEFONO]"
     */
    private function servicio(): array
    {
        $token = $this->obtenerTokenActual();

        if ($this->esServicio($token)) {
            $this->consumir($token->tipo);
            return [
                'nodo' => 'Servicio',
                'valor' => $token->valor
            ];
        }

        $mensaje = $token 
            ? "Error Sintáctico: Se esperaba un ícono de servicio, pero se encontró '{$token->valor}'."
            : "Error Sintáctico: Se esperaba un ícono de servicio al final de la entrada.";

        throw new ParserException(
            $mensaje,
            $token ? $token->linea : 1,
            $token ? $token->columna : 1
        );
    }

    /**
     * Regla: <ACCION_CON_CONTEXTO> ::= <ACCION> <MODIFICADOR>
     */
    private function accionConContexto(): array
    {
        $accion = $this->accion();
        $modificador = $this->modificador();

        return [
            'nodo' => 'AccionConContexto',
            'accion' => $accion,
            'modificador' => $modificador
        ];
    }

    /**
     * Regla: <ACCION> ::= "[DORMIR]" | "[COMER]" | "[COMPRAR]" | "[REPARAR]"
     */
    private function accion(): array
    {
        $token = $this->obtenerTokenActual();

        if ($this->esAccion($token)) {
            $this->consumir($token->tipo);
            return [
                'nodo' => 'Accion',
                'valor' => $token->valor
            ];
        }

        $mensaje = $token 
            ? "Error Sintáctico: Se esperaba un ícono de acción, pero se encontró '{$token->valor}'."
            : "Error Sintáctico: Se esperaba un ícono de acción al final de la entrada.";

        throw new ParserException(
            $mensaje,
            $token ? $token->linea : 1,
            $token ? $token->columna : 1
        );
    }

    /**
     * Regla: <MODIFICADOR> ::= <TIEMPO> | <LUGAR> | <TIEMPO> <LUGAR>
     */
    private function modificador(): array
    {
        $token = $this->obtenerTokenActual();
        $tiempo = null;
        $lugar = null;

        if ($this->esTiempo($token)) {
            $tiempo = $this->tiempo();
            
            // Inspeccionar si el siguiente token es un lugar (opcional: <TIEMPO> <LUGAR>)
            $siguiente = $this->obtenerTokenActual();
            if ($this->esLugar($siguiente)) {
                $lugar = $this->lugar();
            }
        } elseif ($this->esLugar($token)) {
            $lugar = $this->lugar();
        } else {
            $mensaje = $token 
                ? "Error Sintáctico: Se esperaba un modificador de tiempo o lugar, pero se encontró '{$token->valor}'."
                : "Error Sintáctico: Se esperaba un modificador de tiempo o lugar al final de la entrada.";

            throw new ParserException(
                $mensaje,
                $token ? $token->linea : 1,
                $token ? $token->columna : 1
            );
        }

        return [
            'nodo' => 'Modificador',
            'tiempo' => $tiempo,
            'lugar' => $lugar
        ];
    }

    /**
     * Regla: <TIEMPO> ::= "[AHORA]" | "[NOCHE]" | "[SOL]"
     */
    private function tiempo(): array
    {
        $token = $this->obtenerTokenActual();

        if ($this->esTiempo($token)) {
            $this->consumir($token->tipo);
            return [
                'nodo' => 'Tiempo',
                'valor' => $token->valor
            ];
        }

        $mensaje = $token 
            ? "Error Sintáctico: Se esperaba un ícono de tiempo, pero se encontró '{$token->valor}'."
            : "Error Sintáctico: Se esperaba un ícono de tiempo al final de la entrada.";

        throw new ParserException(
            $mensaje,
            $token ? $token->linea : 1,
            $token ? $token->columna : 1
        );
    }

    /**
     * Regla: <LUGAR> ::= "[HOTEL]" | "[CIUDAD]" | "[MONTAÑA]"
     */
    private function lugar(): array
    {
        $token = $this->obtenerTokenActual();

        if ($this->esLugar($token)) {
            $this->consumir($token->tipo);
            return [
                'nodo' => 'Lugar',
                'valor' => $token->valor
            ];
        }

        $mensaje = $token 
            ? "Error Sintáctico: Se esperaba un ícono de lugar, pero se encontró '{$token->valor}'."
            : "Error Sintáctico: Se esperaba un ícono de lugar al final de la entrada.";

        throw new ParserException(
            $mensaje,
            $token ? $token->linea : 1,
            $token ? $token->columna : 1
        );
    }

    /**
     * Regla: <RUTA_TRANSPORTE> ::= "[IR_A]" <VEHICULO> "[HACIA]" <LUGAR>
     */
    private function rutaTransporte(): array
    {
        $this->consumir('IR_A');
        $vehiculo = $this->vehiculo();
        
        $tokenSiguiente = $this->obtenerTokenActual();
        if ($tokenSiguiente === null || $tokenSiguiente->tipo !== 'HACIA') {
            $mensaje = $tokenSiguiente 
                ? "Error Sintáctico: Se esperaba la palabra clave '[HACIA]', pero se encontró '{$tokenSiguiente->valor}'."
                : "Error Sintáctico: Se esperaba la palabra clave '[HACIA]' al final de la entrada.";

            throw new ParserException(
                $mensaje,
                $tokenSiguiente ? $tokenSiguiente->linea : 1,
                $tokenSiguiente ? $tokenSiguiente->columna : 1
            );
        }
        $this->consumir('HACIA');
        
        $lugar = $this->lugar();

        return [
            'nodo' => 'RutaTransporte',
            'vehiculo' => $vehiculo,
            'lugar' => $lugar
        ];
    }

    /**
     * Regla: <VEHICULO> ::= "[MOTO]" | "[AUTO]" | "[TREN]" | "[AVION]" | "[BICI]"
     */
    private function vehiculo(): array
    {
        $token = $this->obtenerTokenActual();

        if ($this->esVehiculo($token)) {
            $this->consumir($token->tipo);
            return [
                'nodo' => 'Vehiculo',
                'valor' => $token->valor
            ];
        }

        $mensaje = $token 
            ? "Error Sintáctico: Se esperaba un ícono de vehículo, pero se encontró '{$token->valor}'."
            : "Error Sintáctico: Se esperaba un ícono de vehículo al final de la entrada.";

        throw new ParserException(
            $mensaje,
            $token ? $token->linea : 1,
            $token ? $token->columna : 1
        );
    }

    // --- Métodos de Ayuda Léxica y Clasificación ---

    private function esServicio(?Token $token): bool
    {
        return $token !== null && in_array($token->tipo, ['CAFÉ', 'RESTAURANTE', 'WIFI', 'BAÑO', 'TELEFONO'], true);
    }

    private function esAccion(?Token $token): bool
    {
        return $token !== null && in_array($token->tipo, ['DORMIR', 'COMER', 'COMPRAR', 'REPARAR'], true);
    }

    private function esTiempo(?Token $token): bool
    {
        return $token !== null && in_array($token->tipo, ['AHORA', 'NOCHE', 'SOL'], true);
    }

    private function esLugar(?Token $token): bool
    {
        return $token !== null && in_array($token->tipo, ['HOTEL', 'CIUDAD', 'MONTAÑA'], true);
    }

    private function esVehiculo(?Token $token): bool
    {
        return $token !== null && in_array($token->tipo, ['MOTO', 'AUTO', 'TREN', 'AVION', 'BICI'], true);
    }
}

