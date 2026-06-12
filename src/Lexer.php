<?php

declare(strict_types=1);

namespace App\Parser;

/**
 * Analizador Léxico (Lexer) para el lenguaje formal de íconos.
 */
class Lexer
{
    /**
     * Lista de terminales válidos permitidos por la Gramática (GLC).
     */
    public const TERMINALES = [
        'POR_FAVOR', 'Y', 'CAFÉ', 'RESTAURANTE', 'WIFI', 'BAÑO', 'TELEFONO',
        'DORMIR', 'COMER', 'COMPRAR', 'REPARAR', 'AHORA', 'NOCHE', 'SOL',
        'HOTEL', 'CIUDAD', 'MONTAÑA', 'IR_A', 'HACIA', 'MOTO', 'AUTO',
        'TREN', 'AVION', 'BICI'
    ];

    private string $entrada;

    /**
     * Constructor del Lexer.
     *
     * @param string $entrada Cadena de texto a tokenizar.
     */
    public function __construct(string $entrada)
    {
        $this->entrada = $entrada;
    }

    /**
     * Recorre la cadena de entrada y devuelve un array de objetos Token.
     *
     * @return Token[]
     * @throws LexerException Si encuentra un carácter inválido o corchete mal cerrado.
     */
    public function tokenizacion(): array
    {
        $tokens = [];
        
        // Dividir la entrada en caracteres UTF-8 de forma segura
        $caracteres = preg_split('//u', $this->entrada, -1, PREG_SPLIT_NO_EMPTY);
        if ($caracteres === false) {
            $caracteres = [];
        }
        
        $total = count($caracteres);
        $linea = 1;
        $columna = 1;

        for ($i = 0; $i < $total; $i++) {
            $char = $caracteres[$i];

            // 1. Ignorar saltos de línea e incrementar contadores
            if ($char === "\n") {
                $linea++;
                $columna = 1;
                continue;
            }

            if ($char === "\r") {
                // Si es un retorno de carro de Windows (\r\n), avanzamos un índice para omitir el \r
                if ($i + 1 < $total && $caracteres[$i + 1] === "\n") {
                    $i++;
                }
                $linea++;
                $columna = 1;
                continue;
            }

            // 2. Ignorar espacios en blanco y tabulaciones
            if ($char === ' ' || $char === "\t") {
                $columna++;
                continue;
            }

            // 3. Procesar un ícono encerrado entre corchetes [...]
            if ($char === '[') {
                $inicioLinea = $linea;
                $inicioColumna = $columna;
                $acumulado = '';
                
                // Avanzar la columna por el corchete de apertura '['
                $columna++;
                
                $cerrado = false;
                $j = $i + 1;
                
                while ($j < $total) {
                    $c = $caracteres[$j];
                    
                    if ($c === ']') {
                        $cerrado = true;
                        $i = $j; // Avanzamos el puntero del bucle principal
                        $columna++; // Sumamos la columna por el corchete de cierre ']'
                        break;
                    }
                    
                    // Manejar saltos de línea dentro de los corchetes si existieran
                    if ($c === "\n") {
                        $linea++;
                        $columna = 1;
                    } elseif ($c === "\r") {
                        if ($j + 1 < $total && $caracteres[$j + 1] === "\n") {
                            $j++;
                        }
                        $linea++;
                        $columna = 1;
                    } else {
                        $columna++;
                    }
                    
                    $acumulado .= $c;
                    $j++;
                }
                
                if (!$cerrado) {
                    throw new LexerException("Corchete mal cerrado. Se esperaba ']'", $inicioLinea, $inicioColumna);
                }
                
                // Normalizar a mayúsculas con soporte multibyte para comparar con los terminales
                $valorNormalizado = mb_strtoupper($acumulado, 'UTF-8');
                
                if (!in_array($valorNormalizado, self::TERMINALES, true)) {
                    throw new LexerException("Ícono no reconocido o inválido: [{$acumulado}]", $inicioLinea, $inicioColumna);
                }
                
                // Creamos el Token. Su tipo será el nombre del ícono en mayúsculas, y su valor la representación literal completa.
                $tokens[] = new Token($valorNormalizado, "[{$valorNormalizado}]", $inicioLinea, $inicioColumna);
                continue;
            }

            // 4. Si encontramos cualquier otro carácter, es un error léxico
            throw new LexerException("Carácter inválido encontrado fuera de los corchetes: '{$char}'", $linea, $columna);
        }

        return $tokens;
    }
}
