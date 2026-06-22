<?php
	/**
	 * Procesa la lista de pasajeros adicionales.
	 * Soporta tanto strings JSON como arrays de PHP.
	 */
	function formatearListaPasajeros($pasajerosData) {
	    $lista = is_string($pasajerosData) ? json_decode($pasajerosData, true) : $pasajerosData;
	    $texto = "";

	    if (is_array($lista) && !empty($lista)) {
		foreach ($lista as $p) {
		    // Maneja si es un objeto con clave 'nombre' o solo un string
		    $nombre = is_array($p) ? ($p['nombre'] ?? 'Sin nombre') : $p;
		    $texto .= "* " . mb_strtoupper(trim($nombre)) . "<br>";
		}
		return $texto;
	    }
	    
	    return "Ninguno";
	}

	/**
	 * Genera el cuerpo HTML para la descripción del evento en Google Calendar.
	 */
	function generarDescripcionHTML($d) {
	    $textoPasajeros = formatearListaPasajeros($d['pasajerosAdicionales'] ?? '[]');

	    return "<b>🆔 Código: " . ($d['codigoReserva'] ?? '') . "</b> <br>" .
		   "<br>" .
		   "<b>=======================</b><br>" .
		   "<b>📅 DATOS DE RESERVA</b><br>" .
		   "<b>🗺️ Servicio:</b> " . ($d['nombreTour'] ?? '') . "<br>" .
		   "<b>🛠️ Tipo de servicio:</b> " . ($d['tipoServicio'] ?? '') . "<br>" .
		   "<b>👥 Tipo de cliente:</b> " . ($d['tipoCliente'] ?? '') . "<br>" .
		   "<b>🏢 Contacto o Agente:</b> " . ($d['agente'] ?? '') . "<br>" .
		   "<b>📞 Teléfono contacto:</b> " . ($d['waAgente'] ?? '') . "<br>" .
		   "<b>📆 Fecha:</b> " . ($d['fecha'] ?? '') . "<br>" .
		   "<b>⏰ Hora de Inicio:</b> " . ($d['horaInicio'] ?? '') . "<br>" .
		   "<b>🏁 Fecha final:</b> " . ($d['fechaFinal'] ?? '') . "<br>" .
		   "<b>⌛ Hora de final:</b> " . ($d['horaFinal'] ?? '') . "<br>" .
		   "<br>" .
		   "<b>=======================</b><br>" .
		   "<b>👤 DATOS DEL PASAJERO</b><br>" .
		   "<b>🔢 Cantidad pasajeros:</b> " . ($d['cantidadPasajero'] ?? '') . "<br>" .
		   "<b>🥇 Pasajero principal:</b> " . ($d['nombrePrincipal'] ?? '') . "<br>" .
		   "<b>📱 WhatsApp:</b> <a href='https://wa.me/" . ($d['whatsapp'] ?? '') . "'>📲 " . ($d['countryCodewhatsapp'] ?? '') . ($d['whatsapp'] ?? '') . "</a><br>" .
		   "<b>📧 Correo:</b> " . ($d['correo'] ?? '') . "<br>" .
		   "<b>🌎 País:</b> " . ($d['pais'] ?? '') . "<br>" .
		   "<b>🗣️ Idioma:</b> " . ($d['idioma'] ?? '') . "<br>" .
		   "<b>👫 Pasajeros adicionales:</b><br>" . $textoPasajeros . "<br>" .
		   "<br>" .
		   "<b>=======================</b><br>" .
		   "<b>💰 PAGOS</b><br>" .
		   "<b>💳 Tipo de pago:</b> " . ($d['tipoPago'] ?? '') . "<br>" .
		   "<b>💵 Precio por persona:</b> " . ($d['precioPorPersona'] ?? '') . "<br>" .
		   "<b>💎 Precio total:</b> " . ($d['precioTotal'] ?? '') . "<br>" .
		   "<b>✅ Estado de pago:</b> " . ($d['estadoPago'] ?? '') . "<br>" .
		   "<b>📄 Comprobante:</b> " . ($d['comprobantePago'] ?? '') . "<br>" .
		   "<br>" .
		   "<b>=======================</b><br>" .
		   "<b>📝 OBSERVACIONES</b><br>" .
		   "<b>🔒 Interna:</b> " . ($d['observacion'] ?? '') . "<br>" .
		   "<b>📢 General:</b> " . ($d['observacionGeneral'] ?? '') . "<br>" .
		   "<br>" .
		   "<b>=======================</b><br>" .
		   "<b>🚐 PERSONAL ASIGNADO</b><br>" .
		   "<b>👨‍✈️ Driver:</b> " . ($d['driver'] ?? '') . "<br>" .
		   "<b>📞 Teléfono driver:</b> " . ($d['waDriver'] ?? '') . "<br>" .
		   "<b>🚩 Guía:</b> " . ($d['guia'] ?? '') . "<br>" .
		   "<b>📞 Teléfono guía:</b> " . ($d['waGuia'] ?? '') . "<br>";
	}
