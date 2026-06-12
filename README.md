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
*   Extensión **`mbstring`** habilitada en PHP (necesaria para el soporte multibyte UTF-8 de íconos con acentos o eñes como `CAFÉ`, `BAÑO` y `MONTAÑA`).

---

## Guía de Configuración Rápida en Windows

Si tú o tus compañeros usan Windows y no tienen PHP configurado o no saben cómo ejecutar el proyecto, sigan estos sencillos pasos:

### 1. Instalar PHP
* **Opción A (Recomendada - Manual):**
  1. Descarga el archivo ZIP de PHP (versión x64 Thread Safe o Non-Thread Safe) desde el sitio oficial: [windows.php.net/download](https://windows.php.net/download/).
  2. Crea una carpeta en tu disco local, por ejemplo: `C:\php`, y extrae allí todo el contenido del ZIP.
  3. Agrega esa carpeta a la variable de entorno `Path`:
     - Presiona la tecla **Inicio**, escribe `variables de entorno` y selecciona **Editar las variables de entorno del sistema**.
     - Haz clic en el botón **Variables de entorno...**.
     - En la sección **Variables del sistema**, selecciona la variable **Path** y haz clic en **Editar**.
     - Haz clic en **Nuevo** y escribe `C:\php`.
     - Haz clic en **Aceptar** en todas las ventanas para guardar los cambios.
  4. Abre una nueva terminal (PowerShell o Símbolo del sistema CMD) y ejecuta `php -v` para comprobar la instalación.

* **Opción B (Si ya usas XAMPP / Laragon / WampServer):**
  - Si ya tienes alguna de estas herramientas instaladas, el ejecutable de PHP ya está en tu computadora. Solo debes buscar su ubicación (ej. `C:\xampp\php\`) y agregar esa ruta a tu variable de entorno **Path** siguiendo los pasos anteriores.

### 2. Habilitar la extensión `mbstring` (¡Muy Importante!)
El parser valida caracteres multibyte (acentos y eñes). Si no habilitas esta extensión, el programa podría fallar o no reconocer caracteres especiales.
1. Abre tu carpeta de PHP (ej. `C:\php`).
2. Cambia el nombre del archivo `php.ini-development` a **`php.ini`**.
3. Abre `php.ini` con cualquier editor de texto (como el Bloc de notas o VS Code).
4. Busca la línea `;extension_dir = "ext"` (alrededor de la línea 760) y quítale el punto y coma (`;`) del inicio:
   ```ini
   extension_dir = "ext"
   ```
5. Busca la línea `;extension=mbstring` (alrededor de la línea 920) y quítale el punto y coma (`;`) del inicio:
   ```ini
   extension=mbstring
   ```
6. Guarda y cierra el archivo.

---

## Instrucciones de Compilación y Ejecución

Dado que PHP es un lenguaje interpretado, **no requiere un paso de compilación previo**. Los scripts se ejecutan directamente sobre el motor de PHP.

### 1. Ejecutar las Pruebas Unitarias
El proyecto cuenta con 17 pruebas unitarias automáticas para verificar que todo funcione correctamente (validación de UTF-8, reporte de errores con línea/columna y producciones gramaticales).

Desde la terminal del proyecto (CMD, PowerShell o Git Bash), ejecuta:
```bash
php test/test_runner.php
```
Si todo es correcto, verás una lista de tests con el mensaje `✓ PASÓ: [Nombre del test]`.

### 2. Iniciar el Servidor Web Local
Inicia el servidor integrado de PHP para poder usar la API y la UI:
```bash
php -S localhost:8000
```
*Deja esta terminal abierta.* El servidor se ejecutará en segundo plano.

### 3. Probar la Interfaz Gráfica (IDE Web)
Con el servidor corriendo, abre tu navegador web favorito (Chrome, Edge, Firefox, etc.) e ingresa a:
```text
http://localhost:8000/
```
Esta interfaz interactiva SPA te permitirá probar el parser de forma visual y amigable (especialmente útil si no estás familiarizado con llamadas a APIs):
* **Teclado Virtual de Íconos**: Haz clic en los botones para armar tu secuencia de íconos respetando la gramática.
* **Tokens en Tiempo Real**: Observa cómo se parsea la cadena y qué badges de tokens se generan.
* **Árbol Sintáctico Interactivo**: Visualiza el AST de manera gráfica y colapsable, o examina el JSON coloreado.
* **Reporte de Errores**: Si cometes un error léxico o sintáctico, la interfaz te dirá exactamente qué falló y en qué línea/columna.

---

## Consumo del Endpoint (API REST) y Pruebas Manuales

Si prefieres probar el parser enviando peticiones directas, puedes hacerlo de las siguientes maneras:

### Método A: Desde el Navegador (GET)
Accede directamente a la URL pasando tu consulta codificada en el parámetro `entrada`:
```text
http://localhost:8000/?entrada=%5BPOR_FAVOR%5D+%5BCAF%C3%89%5D+%5BY%5D+%5BWIFI%5D
```

### Método B: Vía Terminal / Consola
* **En Linux / macOS / Git Bash (usando cURL):**
  ```bash
  curl -G "http://localhost:8000/" --data-urlencode "entrada=[POR_FAVOR] [CAFÉ] [Y] [WIFI]"
  ```
  O con POST:
  ```bash
  curl -X POST http://localhost:8000/ \
       -H "Content-Type: application/json" \
       -d '{"entrada": "[IR_A] [AUTO] [HACIA] [CIUDAD]"}'
  ```

* **En Windows PowerShell (usando Invoke-RestMethod):**
  Dado que `curl` tradicional con comillas simples suele fallar en las consolas nativas de Windows, abre PowerShell y corre:
  ```powershell
  # Petición GET
  Invoke-RestMethod -Uri "http://localhost:8000/?entrada=[POR_FAVOR] [CAFÉ] [Y] [WIFI]"

  # Petición POST
  $body = @{ entrada = "[IR_A] [AUTO] [HACIA] [CIUDAD]" } | ConvertTo-Json -Compress
  Invoke-RestMethod -Uri "http://localhost:8000/" -Method Post -Body $body -ContentType "application/json; charset=utf-8"
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
