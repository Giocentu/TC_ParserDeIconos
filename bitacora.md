# Bitácora de Aprendizaje - Trabajo Integrador TC 2026

En este documento se registran las principales dificultades técnicas y de diseño conceptual encontradas durante la construcción del analizador léxico (Lexer) y el analizador sintáctico (Parser) del sistema icónico de asistencia al viajero.

---

## 1. Dificultades en el Analizador Léxico (Lexer)

### A. Soporte Multibyte (UTF-8) en PHP
*   **Problema:** PHP maneja las cadenas nativas como secuencias de bytes y no de caracteres. Al utilizar palabras del español con tildes o eñes (por ejemplo, `[CAFÉ]`, `[BAÑO]`, `[MONTAÑA]`), funciones tradicionales como `strlen()` o accesos por índice directo `$cadena[$i]` leen caracteres individuales multibyte como si fuesen dos caracteres distintos. Esto rompía el reconocimiento de tokens y desfasaba el contador de columnas de error.
*   **Solución:** Se utilizó `preg_split('//u', $entrada, -1, PREG_SPLIT_NO_EMPTY)` para segmentar la cadena de entrada en un array de caracteres UTF-8 de forma segura. Asimismo, se empleó `mb_strtoupper($acumulado, 'UTF-8')` para normalizar las cadenas multibyte antes de compararlas con el conjunto de terminales.

### B. Cálculo Preciso de Columnas en Errores Léxicos
*   **Problema:** Cuando el Lexer encuentra un corchete de apertura `[` pero no encuentra el de cierre `]`, o si el contenido dentro del corchete no es un terminal válido, el error debe reportarse en el carácter inicial del token problemático. Llevar la cuenta exacta de la columna al avanzar múltiples índices requirió guardar de forma temporal la columna y línea de inicio (`$inicioLinea`, `$inicioColumna`) antes de entrar al bucle de acumulación interna.
*   **Solución:** Se implementó una lógica de puntero doble (`$i` para el bucle principal y `$j` para la exploración de contenido dentro de los corchetes), preservando la posición exacta de inicio del token para alimentar a la excepción `LexerException`.

---

## 2. Dificultades en el Analizador Sintáctico (Parser)

### A. Diseño de la Gramática no Ambigua y LL(1)
*   **Problema:** La gramática original del proyecto en formato BNF requería recursividad para permitir listas arbitrarias de necesidades y modificadores. Si se hubiese usado recursión por la izquierda (por ejemplo, `<NECESIDAD> ::= <NECESIDAD> "[Y]" <SERVICIO>`), un parser descendente recursivo caería en un bucle infinito por desbordamiento de pila.
*   **Solución:** La gramática se estructuró con recursión por la derecha en la producción `<LISTA_EXTRA> ::= "[Y]" <NECESIDAD> | ε`. Esto permitió que el parser sea de clase **LL(1)** y pueda ser implementado mediante funciones recursivas que deciden su camino de análisis consultando un único token de preanálisis.

### B. Representación del Árbol Sintáctico Abstracto (AST)
*   **Problema:** El parser sintáctico no debe limitarse a validar si una cadena es gramaticalmente correcta; también debe estructurar los datos analizados para su posterior procesamiento o serialización. Representar una jerarquía anidada compleja en PHP sin sobrecargar el diseño fue un desafío.
*   **Solución:** Se diseñó cada producción recursiva para retornar un array asociativo estructurado con la clave `'nodo'` para identificar la categoría gramatical (por ejemplo: `Peticion`, `RutaTransporte`, `Servicio`) y las propiedades asociadas a ese nodo, facilitando su posterior serialización a JSON nativo en el controlador web.

## 3. Dificultades de Implementación 

### A. Representación y Control de la Producción Vacía (ε)
*   **Problema:** Modelar la regla de producción vacía `<LISTA_EXTRA> ::= "[Y]" <NECESIDAD> | ε` requiere que el parser decida no hacer nada y continuar con éxito. En código procedural, esto significa que la función `listaExtra()` debe retornar un valor que represente "nada". Inicialmente, esto nos causó problemas de diseño al intentar definir si debíamos retornar un array vacío, un token especial de tipo Épsilon o un valor nulo.
*   **Solución:** Decidimos utilizar `null` como representación de $\epsilon$. Sin embargo, esto introdujo el desafío de manejar chequeos defensivos en las funciones que consumen el AST (como `necesidad()`) para evitar errores fatales de tipo *Cannot access offset on value of type null* al procesar o serializar los nodos hijos.

### C. Confusión Conceptual entre Lookahead (Preanálisis) y Consumo de Tokens
*   **Problema:** Al principio del desarrollo del parser descendente recursivo, confundíamos con frecuencia la acción de "inspeccionar" el token actual para tomar una decisión en la gramática con la acción de "consumir" dicho token. Esto provocaba que avanzáramos el puntero del parser antes de tiempo (por ejemplo, al verificar si un modificador era de tiempo o de lugar), perdiendo la referencia sintáctica y causando errores falsos positivos en oraciones válidas.
*   **Solución:** Tuvimos que separar estrictamente las responsabilidades creando los métodos helper `coincide()` (que realiza el lookahead sin mutar el puntero) y `consumir()` (que sí avanza el puntero). Entender esta distinción en el código fue fundamental para mapear los conceptos matemáticos de los conjuntos FIRST y la predicción sintáctica aprendidos en la cátedra.
