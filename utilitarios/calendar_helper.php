<?php
	/**
	 * Determina el ID de color de Google Calendar según el tipo de servicio.
	 */
	function obtenerColorEvento($tipoServicio) {
	    switch ($tipoServicio) {
		case "Privado":
		    return "5";  // Amarillo/Plátano
		case "Grupal":
		    return "10"; // Verde/Albahaca
		case "Anulado":
		    return "11"; // Rojo/Tomate
		default:
		    return "5";  // Color por defecto
	    }
	}

	/**
	 * Genera el título (Summary) del evento para el calendario.
	 */
	function generarSummaryEvento($d) {
	    $tour = $d['nombreTour'] ?? 'Tour';
	    $cant = $d['cantidadPasajero'] ?? '1';
	    $tipo = $d['tipoServicio'] ?? '';
	    $agente = $d['agente'] ?? 'Directo';
	    
	    return "$tour X$cant ($tipo) - 👤 $agente";
	}

	/**
	 * Obtiene la ubicación formateada.
	 */
	function obtenerUbicacionEvento($hotelDireccion) {
	    if (empty($hotelDireccion)) return "📍 Dirección no especificada";
	    return "📍 " . $hotelDireccion;
	}
