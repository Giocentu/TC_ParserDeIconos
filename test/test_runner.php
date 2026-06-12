<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Token.php';
require_once __DIR__ . '/../src/LexerException.php';
require_once __DIR__ . '/../src/ParserException.php';
require_once __DIR__ . '/../src/Lexer.php';
require_once __DIR__ . '/../src/Parser.php';

use App\Parser\Lexer;
use App\Parser\Parser;
use App\Parser\Token;
use App\Parser\LexerException;
use App\Parser\ParserException;

function test(string $nombre, callable $prueba): void {
    try {
        $prueba();
        echo "✓ PASÓ: $nombre\n";
    } catch (Throwable $e) {
        echo "✗ FALLÓ: $nombre\n";
        echo "  Detalle: " . $e->getMessage() . "\n";
        echo "  En: " . $e->getFile() . " línea " . $e->getLine() . "\n\n";
    }
}

// 1. Caso Exitoso
test("Caso Exitoso - Tokenización básica", function () {
    $entrada = "[POR_FAVOR] [DORMIR] [AHORA]";
    $lexer = new Lexer($entrada);
    $tokens = $lexer->tokenizacion();
    
    if (count($tokens) !== 3) {
        throw new Exception("Se esperaban 3 tokens, se obtuvieron " . count($tokens));
    }
    
    if ($tokens[0]->tipo !== 'POR_FAVOR' || $tokens[0]->valor !== '[POR_FAVOR]') {
        throw new Exception("Primer token incorrecto: " . json_encode($tokens[0]));
    }
    
    if ($tokens[1]->tipo !== 'DORMIR' || $tokens[1]->valor !== '[DORMIR]') {
        throw new Exception("Segundo token incorrecto");
    }

    if ($tokens[2]->tipo !== 'AHORA' || $tokens[2]->valor !== '[AHORA]') {
        throw new Exception("Tercer token incorrecto");
    }

    // Probar atributos en español y método aArreglo()
    $tokenArreglo = $tokens[0]->aArreglo();
    if (!isset($tokenArreglo['tipo'], $tokenArreglo['valor'], $tokenArreglo['linea'], $tokenArreglo['columna'])) {
        throw new Exception("Las claves del array retornado por aArreglo() no están en español: " . json_encode($tokenArreglo));
    }
});

// 2. Caso Multibyte (UTF-8 con acentos y eñes)
test("Caso Multibyte - Soporta acentos y eñes sin desfasar columnas", function () {
    $entrada = "[CAFÉ] \n  [BAÑO]   [MONTAÑA]";
    $lexer = new Lexer($entrada);
    $tokens = $lexer->tokenizacion();
    
    if (count($tokens) !== 3) {
        throw new Exception("Se esperaban 3 tokens");
    }
    
    // El primer token [CAFÉ] está en la línea 1, columna 1
    if ($tokens[0]->tipo !== 'CAFÉ' || $tokens[0]->linea !== 1 || $tokens[0]->columna !== 1) {
        throw new Exception("Fallo en primer token: " . json_encode($tokens[0]));
    }

    // El segundo token [BAÑO] está en la línea 2, columna 3 (ya que hay 2 espacios de sangrado)
    if ($tokens[1]->tipo !== 'BAÑO' || $tokens[1]->linea !== 2 || $tokens[1]->columna !== 3) {
        throw new Exception("Fallo en segundo token: " . json_encode($tokens[1]));
    }

    // El tercer token [MONTAÑA] está en la línea 2, columna 12 (el token [BAÑO] ocupa 6 caracteres, más 3 espacios = columna 12)
    if ($tokens[2]->tipo !== 'MONTAÑA' || $tokens[2]->linea !== 2 || $tokens[2]->columna !== 12) {
        throw new Exception("Fallo en tercer token: " . json_encode($tokens[2]));
    }
});

// 3. Caso Carácter Inválido fuera de corchetes
test("Caso Error - Carácter inválido fuera de corchetes", function () {
    $entrada = "[CAFÉ] x [BAÑO]";
    $lexer = new Lexer($entrada);
    
    try {
        $lexer->tokenizacion();
        throw new Exception("Se esperaba que fallara por carácter inválido");
    } catch (LexerException $e) {
        if ($e->obtenerLinea() !== 1 || $e->obtenerColumna() !== 8) {
            throw new Exception("Ubicación del error incorrecta: Línea " . $e->obtenerLinea() . ", Columna " . $e->obtenerColumna());
        }
        if (strpos($e->getMessage(), "Carácter inválido") === false) {
            throw new Exception("Mensaje de error incorrecto: " . $e->getMessage());
        }
    }
});

// 4. Caso Corchete Mal Cerrado
test("Caso Error - Corchete mal cerrado", function () {
    $entrada = "[POR_FAVOR] [DORMIR [AHORA]";
    $lexer = new Lexer($entrada);
    
    try {
        $lexer->tokenizacion();
        throw new Exception("Se esperaba que fallara por corchete mal cerrado");
    } catch (LexerException $e) {
        if ($e->obtenerLinea() !== 1 || $e->obtenerColumna() !== 13) {
            throw new Exception("Ubicación del error incorrecta (debería ser el inicio del corchete mal cerrado, col 13): Línea " . $e->obtenerLinea() . ", Columna " . $e->obtenerColumna());
        }
    }
});

// 5. Caso Terminal No Reconocido
test("Caso Error - Ícono no reconocido", function () {
    $entrada = "[HOTEL] [PROGRAMAR]";
    $lexer = new Lexer($entrada);
    
    try {
        $lexer->tokenizacion();
        throw new Exception("Se esperaba que fallara por ícono no reconocido");
    } catch (LexerException $e) {
        if ($e->obtenerLinea() !== 1 || $e->obtenerColumna() !== 9) {
            throw new Exception("Ubicación del error incorrecta (debería ser col 9): Línea " . $e->obtenerLinea() . ", Columna " . $e->obtenerColumna());
        }
        if (strpos($e->getMessage(), "Ícono no reconocido") === false) {
            throw new Exception("Mensaje de error incorrecto: " . $e->getMessage());
        }
    }
});

// 6. Casos Sintácticos Válidos (8 casos requeridos)

test("Parser - Caso Válido 1: Petición simple", function () {
    $entrada = "[CAFÉ]";
    $lexer = new Lexer($entrada);
    $tokens = $lexer->tokenizacion();
    $parser = new Parser($tokens);
    $ast = $parser->analizar();

    if ($ast['nodo'] !== 'Oracion' || $ast['categoria'] !== 'Peticion') {
        throw new Exception("AST incorrecto: " . json_encode($ast));
    }
});

test("Parser - Caso Válido 2: Petición formal", function () {
    $entrada = "[POR_FAVOR] [WIFI]";
    $lexer = new Lexer($entrada);
    $tokens = $lexer->tokenizacion();
    $parser = new Parser($tokens);
    $ast = $parser->analizar();

    if ($ast['detalle']['por_favor'] !== true) {
        throw new Exception("Se esperaba que 'por_favor' fuera true");
    }
});

test("Parser - Caso Válido 3: Petición múltiple con conector Y", function () {
    $entrada = "[BAÑO] [Y] [TELEFONO]";
    $lexer = new Lexer($entrada);
    $tokens = $lexer->tokenizacion();
    $parser = new Parser($tokens);
    $ast = $parser->analizar();

    if ($ast['detalle']['necesidad']['lista_extra'] === null) {
        throw new Exception("Se esperaba que tuviera lista_extra");
    }
});

test("Parser - Caso Válido 4: Petición formal múltiple", function () {
    $entrada = "[POR_FAVOR] [CAFÉ] [Y] [RESTAURANTE] [Y] [WIFI]";
    $lexer = new Lexer($entrada);
    $tokens = $lexer->tokenizacion();
    $parser = new Parser($tokens);
    $ast = $parser->analizar();

    if ($ast['detalle']['por_favor'] !== true) {
        throw new Exception("Se esperaba por_favor = true");
    }
});

test("Parser - Caso Válido 5: Acción con tiempo", function () {
    $entrada = "[DORMIR] [AHORA]";
    $lexer = new Lexer($entrada);
    $tokens = $lexer->tokenizacion();
    $parser = new Parser($tokens);
    $ast = $parser->analizar();

    if ($ast['categoria'] !== 'AccionConContexto') {
        throw new Exception("Se esperaba categoria AccionConContexto");
    }
});

test("Parser - Caso Válido 6: Acción con lugar", function () {
    $entrada = "[COMER] [HOTEL]";
    $lexer = new Lexer($entrada);
    $tokens = $lexer->tokenizacion();
    $parser = new Parser($tokens);
    $ast = $parser->analizar();

    if ($ast['detalle']['modificador']['lugar']['valor'] !== '[HOTEL]') {
        throw new Exception("Se esperaba lugar [HOTEL]");
    }
});

test("Parser - Caso Válido 7: Acción con tiempo y lugar", function () {
    $entrada = "[REPARAR] [NOCHE] [MONTAÑA]";
    $lexer = new Lexer($entrada);
    $tokens = $lexer->tokenizacion();
    $parser = new Parser($tokens);
    $ast = $parser->analizar();

    $mod = $ast['detalle']['modificador'];
    if ($mod['tiempo']['valor'] !== '[NOCHE]' || $mod['lugar']['valor'] !== '[MONTAÑA]') {
        throw new Exception("Modificadores incorrectos");
    }
});

test("Parser - Caso Válido 8: Ruta de transporte", function () {
    $entrada = "[IR_A] [AVION] [HACIA] [CIUDAD]";
    $lexer = new Lexer($entrada);
    $tokens = $lexer->tokenizacion();
    $parser = new Parser($tokens);
    $ast = $parser->analizar();

    if ($ast['categoria'] !== 'RutaTransporte') {
        throw new Exception("Se esperaba categoria RutaTransporte");
    }
});

// 7. Casos Sintácticos Inválidos (Errores sintácticos de control)

test("Parser - Caso Inválido 1: Entrada vacía", function () {
    $tokens = [];
    $parser = new Parser($tokens);
    try {
        $parser->analizar();
        throw new Exception("Se esperaba ParserException por entrada vacía");
    } catch (ParserException $e) {
        // Correcto
    }
});

test("Parser - Caso Inválido 2: Petición mal formada (conector Y incompleto)", function () {
    $entrada = "[POR_FAVOR] [WIFI] [Y]";
    $lexer = new Lexer($entrada);
    $tokens = $lexer->tokenizacion();
    $parser = new Parser($tokens);
    try {
        $parser->analizar();
        throw new Exception("Se esperaba ParserException por conector Y sin necesidad");
    } catch (ParserException $e) {
        // Correcto
    }
});

test("Parser - Caso Inválido 3: Ruta de transporte incompleta", function () {
    $entrada = "[IR_A] [AVION]";
    $lexer = new Lexer($entrada);
    $tokens = $lexer->tokenizacion();
    $parser = new Parser($tokens);
    try {
        $parser->analizar();
        throw new Exception("Se esperaba ParserException por falta de [HACIA] y [LUGAR]");
    } catch (ParserException $e) {
        if (strpos($e->getMessage(), "HACIA") === false) {
            throw new Exception("Mensaje de error incorrecto: " . $e->getMessage());
        }
    }
});

test("Parser - Caso Inválido 4: Oración con categorías mezcladas inválidas", function () {
    $entrada = "[DORMIR] [CAFÉ]";
    $lexer = new Lexer($entrada);
    $tokens = $lexer->tokenizacion();
    $parser = new Parser($tokens);
    try {
        $parser->analizar();
        throw new Exception("Se esperaba ParserException");
    } catch (ParserException $e) {
        // Correcto: [CAFÉ] no es un modificador de tiempo o lugar válido para la acción [DORMIR]
    }
});

