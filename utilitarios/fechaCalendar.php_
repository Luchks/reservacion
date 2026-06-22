<?php
function convertirAGoogleCalendar($fecha, $hora, $agregarHoras = 0) {
    try {
        $fecha = trim($fecha);
        $hora = trim($hora);
        
        // Intentamos leer el formato "02:30 PM" que manda tu Kotlin
        $dt = DateTime::createFromFormat('Y-m-d h:i A', "$fecha $hora", new DateTimeZone('America/Lima'));

        if (!$dt) {
            // Intento de respaldo si viene en 24h
            $dt = DateTime::createFromFormat('Y-m-d H:i', "$fecha $hora", new DateTimeZone('America/Lima'));
        }

        if (!$dt) return null;

        if ($agregarHoras !== 0) {
            $dt->modify("+{$agregarHoras} hours");
        }

        return $dt->format(DateTime::RFC3339);
    } catch (Exception $e) {
        return null;
    }
}
