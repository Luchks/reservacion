<?php
function convertirAGoogleCalendar($fecha, $hora, $agregarHoras = 0) {
    try {
        $fecha = trim((string)$fecha);
        $hora = trim((string)$hora);

        // FIX: blindaje — una cadena vacía debe tratarse igual que un valor ausente.
        // Antes, "" llegaba hasta createFromFormat() y producía resultados engañosos.
        if ($fecha === '' || $hora === '') {
            return null;
        }

        // Intentamos leer el formato "02:30 PM" que manda tu Kotlin
        $dt = DateTime::createFromFormat('Y-m-d h:i A', "$fecha $hora", new DateTimeZone('America/Lima'));

        if (!$dt) {
            // Intento de respaldo si viene en 24h
            $dt = DateTime::createFromFormat('Y-m-d H:i', "$fecha $hora", new DateTimeZone('America/Lima'));
        }

        if (!$dt) return null;

        if ($agregarHoras > 0) {
            $dt->modify("+$agregarHoras hours");
        }

        return $dt->format(DateTime::RFC3339);
    } catch (Exception $e) {
        error_log("Error en convertirAGoogleCalendar: " . $e->getMessage());
        return null;
    }
}
