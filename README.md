# Analizador Léxico-Sintáctico de Iconos
### Integrantes:
* Bys, Paz
* Cantero, Augusto
* Centurión Villamayor, Giovanni Isaías
* Lezcano, Lautaro

Este proyecto implementa un analizador léxico (Lexer) y un analizador sintáctico (Parser) descendente recursivo para un lenguaje formal icónico de asistencia al viajero, desarrollado en el marco de la materia **Teoría de la Computación (2026)**.

El sistema valida cadenas de íconos representados por corchetes (ej. `[POR_FAVOR] [CAFÉ] [Y] [WIFI]`) y las traduce en un Árbol Sintáctico Abstracto (AST) serializable en formato JSON a través de un endpoint HTTP.

---

## Requisitos

*   **PHP >= 8.0**
*   Extensión **`mbstring`** habilitada en PHP (necesaria para el soporte multibyte UTF-8 de íconos como `CAFÉ`, `BAÑO` y `MONTAÑA`).

---
## Instrucciones de Compilación y Ejecución

Dado que PHP es un lenguaje interpretado, **no requiere un paso de compilación previo**. Los scripts se ejecutan directamente sobre el motor de PHP.

### 1. Ejecutar las Pruebas Unitarias
El proyecto cuenta con 17 pruebas que cubren la validación léxica de UTF-8, el reporte de posiciones de error y las 11 producciones de la gramática sintáctica (casos válidos e inválidos).

Para ejecutar los tests, corre el siguiente comando desde la raíz del proyecto:
```bash
php test/test_runner.php
```

### 2. Iniciar el Servidor Web Local
Para probar el analizador desde un navegador, Postman o mediante peticiones `curl`, inicia el servidor integrado de PHP:
```bash
php -S localhost:8000
```

### 3. Probar la Interfaz Gráfica Interactiva (IDE Web)
Una vez que el servidor web esté en funcionamiento, puedes abrir tu navegador y acceder directamente a la raíz del servidor:
```text
http://localhost:8000/
```
Esta interfaz SPA (Single Page Application) te permitirá probar el analizador de forma mucho más amigable, ofreciéndote:
* **Teclado Virtual de Íconos**: Organizado por categorías gramaticales de la GLC para insertar rápidamente los corchetes y caracteres acentuados.
* **Secuencia de Tokens**: Visualización en tiempo real de cada badge de token y sus posiciones exactas.
* **Árbol Sintáctico (AST) Interactivo**: Un árbol colapsable dinámicamente en HTML/CSS junto con una vista de código JSON formateado y coloreado.
* **Reporte de Errores Detallado**: Visualización interactiva que resalta la fase del error (léxico o sintáctico), el mensaje y la ubicación exacta de línea/columna.

---

## Consumo del Endpoint (API REST)

Una vez que el servidor web local esté corriendo en `http://localhost:8000`, puedes realizar consultas enviando la secuencia de íconos a analizar.

### Método A: Petición GET (Vía Navegador o cURL)
Accede directamente a la URL pasando la cadena en el parámetro `entrada` (URL encoded):
```text
http://localhost:8000/?entrada=%5BPOR_FAVOR%5D+%5BCAF%C3%89%5D+%5BY%5D+%5BWIFI%5D
```
O desde la terminal con `curl`:
```bash
curl -G "http://localhost:8000/" --data-urlencode "entrada=[POR_FAVOR] [CAFÉ] [Y] [WIFI]"
```

### Método B: Petición POST (JSON Payload)
Envía un cuerpo JSON con la clave `entrada`:
```bash
curl -X POST http://localhost:8000/ \
     -H "Content-Type: application/json" \
     -d '{"entrada": "[IR_A] [AUTO] [HACIA] [CIUDAD]"}'
```

### Ejemplo de Respuesta Exitosa (200 OK)
```json
{
    "estado": "exito",
    "mensaje": "Cadena analizada correctamente.",
    "codigo_analizado": "[POR_FAVOR] [CAFÉ] [Y] [WIFI]",
    "ejemplo_defecto": false,
    "conteo_tokens": 4,
    "tokens": [...],
    "resultado_parser": {
        "nodo": "Oracion",
        "categoria": "Peticion",
        "detalle": {
            "nodo": "Peticion",
            "por_favor": true,
            "necesidad": {
                "nodo": "Necesidad",
                "servicio": {
                    "nodo": "Servicio",
                    "valor": "[CAFÉ]"
                },
                "lista_extra": {
                    "nodo": "ListaExtra",
                    "necesidad": {
                        "nodo": "Necesidad",
                        "servicio": {
                            "nodo": "Servicio",
                            "valor": "[WIFI]"
                        },
                        "lista_extra": null
                    }
                }
            }
        }
    }
}
```

### Ejemplo de Respuesta con Error Sintáctico (400 Bad Request)
```json
{
    "estado": "error",
    "fase": "sintactico",
    "mensaje": "Error Sintáctico: Se esperaba la palabra clave '[HACIA]', pero se encontró '[HOTEL]'.",
    "linea": 1,
    "columna": 15,
    "codigo_analizado": "[IR_A] [AVION] [HOTEL]"
}
```

---

## Resumen de la Gramática Soportada (EBNF/BNF)

El analizador sintáctico procesa 3 tipos principales de oraciones estructuradas bajo las siguientes reglas no ambiguas de clase LL(1):

1.  **Peticiones:** `[POR_FAVOR]` (opcional) seguido de uno o más servicios (ej. `[CAFÉ]`, `[BAÑO]`) separados por el conector `[Y]`.
2.  **Acciones con Contexto:** Una acción (ej. `[DORMIR]`, `[COMER]`) seguida de modificadores de tiempo o lugar (ej. `[AHORA]`, `[NOCHE]`, `[HOTEL]`).
3.  **Rutas de Transporte:** La secuencia fija `[IR_A]` + `<VEHICULO>` + `[HACIA]` + `<LUGAR>`.
