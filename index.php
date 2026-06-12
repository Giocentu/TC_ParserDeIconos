<?php

declare(strict_types=1);

// Habilitar la visualización de errores internos si es necesario (para desarrollo, aunque los capturaremos todos)
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Cargar de forma directa los archivos del parser
require_once __DIR__ . '/src/Token.php';
require_once __DIR__ . '/src/LexerException.php';
require_once __DIR__ . '/src/ParserException.php';
require_once __DIR__ . '/src/Lexer.php';
require_once __DIR__ . '/src/Parser.php';

use App\Parser\Lexer;
use App\Parser\Parser;
use App\Parser\LexerException;
use App\Parser\ParserException;

// Configuración de cabeceras de CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Obtener el método de petición de forma segura (con fallback para CLI)
$metodoPeticion = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Manejar petición preflight de CORS (OPTIONS)
if ($metodoPeticion === 'OPTIONS') {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(200);
    exit;
}

// Si es una petición GET de navegación (acepta HTML) y no se pasa una entrada, servimos la interfaz de usuario
if ($metodoPeticion === 'GET' && !isset($_GET['entrada'])) {
    $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (str_contains($acceptHeader, 'text/html')) {
        header('Content-Type: text/html; charset=UTF-8');
        if (file_exists(__DIR__ . '/gui.html')) {
            readfile(__DIR__ . '/gui.html');
        } else {
            http_response_code(404);
            echo "<h1>Error 404: Interfaz de usuario no encontrada (gui.html)</h1>";
        }
        exit;
    }
}

// Por defecto, todas las respuestas de la API serán JSON
header('Content-Type: application/json; charset=UTF-8');

// 1. Obtener la cadena de entrada (entrada/código a analizar)
$entrada = '';

if ($metodoPeticion === 'POST') {
    // Intentar leer desde el cuerpo de la petición (JSON)
    $inputJson = file_get_contents('php://input');
    if ($inputJson) {
        $datos = json_decode($inputJson, true);
        if (is_array($datos) && isset($datos['entrada'])) {
            $entrada = (string)$datos['entrada'];
        }
    }
} else {
    // Si es GET, intentar leer del parámetro de consulta 'entrada'
    if (isset($_GET['entrada'])) {
        $entrada = (string)$_GET['entrada'];
    }
}

// Si no se proporcionó ninguna entrada, usar un ejemplo por defecto para facilitar pruebas rápidas
$usandoEjemploDefecto = false;
if ($entrada === '') {
    $entrada = "[POR_FAVOR] [CAFÉ] [Y] [WIFI]";
    $usandoEjemploDefecto = true;
}

try {
    // 2. Instanciar el Lexer y tokenizar la entrada
    $lexer = new Lexer($entrada);
    $tokens = $lexer->tokenizacion();

    // 3. Instanciar el Parser y realizar el análisis sintáctico inicial
    $parser = new Parser($tokens);
    $resultadoParser = $parser->analizar();

    // 4. Responder con éxito y los tokens estructurados
    http_response_code(200);
    echo json_encode([
        'estado' => 'exito',
        'mensaje' => 'Cadena analizada correctamente.',
        'codigo_analizado' => $entrada,
        'ejemplo_defecto' => $usandoEjemploDefecto,
        'conteo_tokens' => count($tokens),
        // json_encode llamará automáticamente al método jsonSerialize() de cada objeto Token
        'tokens' => $tokens,
        'resultado_parser' => $resultadoParser
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (LexerException $e) {
    // Captura de errores del analizador léxico (corchetes mal cerrados, caracteres inválidos)
    http_response_code(400);
    echo json_encode([
        'estado' => 'error',
        'fase' => 'lexico',
        'mensaje' => $e->getMessage(),
        'linea' => $e->obtenerLinea(),
        'columna' => $e->obtenerColumna(),
        'codigo_analizado' => $entrada
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (ParserException $e) {
    // Captura de errores del analizador sintáctico (si los hubiera)
    http_response_code(400);
    echo json_encode([
        'estado' => 'error',
        'fase' => 'sintactico',
        'mensaje' => $e->getMessage(),
        'linea' => $e->obtenerLinea(),
        'columna' => $e->obtenerColumna(),
        'codigo_analizado' => $entrada
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    // Cualquier otro error del sistema o de ejecución inesperado
    http_response_code(500);
    echo json_encode([
        'estado' => 'error',
        'fase' => 'sistema',
        'mensaje' => 'Error interno del servidor: ' . $e->getMessage(),
        'codigo_analizado' => $entrada
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
