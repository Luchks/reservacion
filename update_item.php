<?php
	header('Content-Type: application/json');
	include 'db.php';

	// Para usar convertirAGoogleCalendar (utilitarios de fechas)
	include 'utilitarios/fechaCalendar.php';

	// Para usar crear/actualizar eventos en Google Calendar
	require 'google_calendar_service.php';

	$data = json_decode(file_get_contents('php://input'), true);

	if (!$data || !isset($data['id'])) {
	    echo json_encode(["success" => false, "message" => "ID de reserva faltante"]);
	    exit;
	}

	$id = (int)$data['id'];

	// ==================================================================
	// 1) CONTROL DE CONFLICTOS (CONCURRENCIA OPTIMISTA)
	// ==================================================================
	// Recibimos el updatedAt que el celular tiene guardado localmente
	$clientUpdatedAt = $data['updatedAt'] ?? '';

	$stmtCheck = $conn->prepare("SELECT updated_at, id_calendar FROM items WHERE id = ?");
	$stmtCheck->bind_param("i", $id);
	$stmtCheck->execute();
	$stmtCheck->bind_result($serverUpdatedAt, $id_calendar_actual);

	if (!$stmtCheck->fetch()) {
	    $stmtCheck->close();
	    echo json_encode(["success" => false, "message" => "El registro no existe en el servidor."]);
	    exit;
	}
	$stmtCheck->close();

	// Si el celular mandó un updatedAt y este no coincide con el del servidor...
	if (!empty($clientUpdatedAt) && $serverUpdatedAt !== $clientUpdatedAt) {
	    echo json_encode([
		"success" => false,
		"conflict" => true, // Flag importante para Kotlin
		"message" => "Conflicto: Esta reserva ha sido modificada por otro usuario.",
		"serverUpdatedAt" => $serverUpdatedAt
	    ]);
	    exit;
	}

	// ==================================================================
	// 2) ASIGNACIÓN DE DATOS DEL JSON
	// ==================================================================
	$name                = $data['name'] ?? '';
	$description         = $data['description'] ?? '';
	$codigoReserva       = $data['codigoReserva'] ?? '';
	$nombreTour          = $data['nombreTour'] ?? '';
	$tipoCliente         = $data['tipoCliente'] ?? '';
	$fecha               = $data['fecha'] ?? '';
	$horaInicio          = $data['horaInicio'] ?? '';
	$turno               = $data['turno'] ?? '';
	$hotelDireccion      = $data['hotelDireccion'] ?? '';
	$duracion            = $data['duracion'] ?? '';
	$nombrePrincipal     = $data['nombrePrincipal'] ?? '';
	// Los pasajeros adicionales vienen como lista de strings en Kotlin, los convertimos a JSON para MySQL
	$pasajeros           = json_encode($data['pasajerosAdicionales'] ?? []);
	$pasaporteID         = $data['pasaporteID'] ?? '';
	$countryCodewhatsapp = $data['countryCodewhatsapp'] ?? '';
	$whatsapp            = $data['whatsapp'] ?? '';
	$correo              = $data['correo'] ?? '';
	$habitacion          = $data['habitacion'] ?? '';
	$idioma              = $data['idioma'] ?? '';
	$pais                = $data['pais'] ?? '';
	$tipoPago            = $data['tipoPago'] ?? '';

	$precioPorPersona    = (float)($data['precioPorPersona'] ?? 0);
	$precioTotal         = (float)($data['precioTotal'] ?? 0);
	$precioComisionable  = (float)($data['precioComisionable'] ?? 0);
	$totalComision       = (float)($data['totalComision'] ?? 0);

	$agente              = $data['agente'] ?? '';
	$countryCodewaAgente = $data['countryCodewaAgente'] ?? '';
	$waAgente            = $data['waAgente'] ?? '';
	$observacion         = $data['observacion'] ?? '';
	$driver              = $data['driver'] ?? '';
	$countryCodewaDriver = $data['countryCodewaDriver'] ?? '';
	$waDriver            = $data['waDriver'] ?? '';
	$guia                = $data['guia'] ?? '';
	$countryCodewaGuia   = $data['countryCodewaGuia'] ?? '';
	$waGuia              = $data['waGuia'] ?? '';

	$id_map              = $data['id_map'] ?? '';
	$cantidadPasajero    = $data['cantidadPasajero'] ?? '';
	$tipoServicio        = $data['tipoServicio'] ?? '';
	$observacionGeneral  = $data['observacionGeneral'] ?? '';
	$estadoPago          = $data['estadoPago'] ?? '';
	$estadoReserva       = $data['estadoReserva'] ?? 'COMPLETADO';
	$comprobantePago     = $data['comprobantePago'] ?? '';
	$fechaFinal          = $data['fechaFinal'] ?? '';
	$horaFinal           = $data['horaFinal'] ?? '';

	// ==================================================================
	// 3) ACTUALIZACIÓN EN MYSQL
	// ==================================================================
	$sql = "UPDATE items SET 
		    name=?, description=?, codigoReserva=?, nombreTour=?, tipoCliente=?, 
		    fecha=?, horaInicio=?, turno=?, hotelDireccion=?, duracion=?, 
		    nombrePrincipal=?, pasajerosAdicionales=?, pasaporteID=?, countryCodewhatsapp=?, whatsapp=?, 
		    correo=?, habitacion=?, idioma=?, pais=?, tipoPago=?, 
		    precioPorPersona=?, precioTotal=?, precioComisionable=?, totalComision=?, agente=?, 
		    countryCodewaAgente=?, waAgente=?, observacion=?, driver=?, countryCodewaDriver=?, 
		    waDriver=?, guia=?, countryCodewaGuia=?, waGuia=?, id_map=?, 
		    cantidadPasajero=?, tipoServicio=?, observacionGeneral=?, estadoPago=?, estadoReserva=?, 
		    comprobantePago=?, fechaFinal=?, horaFinal=?
		WHERE id=?";

	$stmt = $conn->prepare($sql);

	// s = string, d = double, i = integer
	// 43 campos en el SET + 1 en el WHERE = 44 parámetros totales
	$stmt->bind_param(
	    "ssssssssssssssssssssddddsssssssssssssssssssi",
	    $name, $description, $codigoReserva, $nombreTour, $tipoCliente, 
	    $fecha, $horaInicio, $turno, $hotelDireccion, $duracion, 
	    $nombrePrincipal, $pasajeros, $pasaporteID, $countryCodewhatsapp, $whatsapp, 
	    $correo, $habitacion, $idioma, $pais, $tipoPago, 
	    $precioPorPersona, $precioTotal, $precioComisionable, $totalComision, $agente, 
	    $countryCodewaAgente, $waAgente, $observacion, $driver, $countryCodewaDriver, 
	    $waDriver, $guia, $countryCodewaGuia, $waGuia, $id_map, 
	    $cantidadPasajero, $tipoServicio, $observacionGeneral, $estadoPago, $estadoReserva, 
	    $comprobantePago, $fechaFinal, $horaFinal, $id
	);

	$ok = $stmt->execute();

	if ($ok) {
	    // Tras la actualización, obtenemos el NUEVO updated_at generado por MySQL
	    $stmtNewDate = $conn->prepare("SELECT updated_at FROM items WHERE id = ?");
	    $stmtNewDate->bind_param("i", $id);
	    $stmtNewDate->execute();
	    $stmtNewDate->bind_result($newUpdatedAt);
	    $stmtNewDate->fetch();
	    $stmtNewDate->close();

        // ==================================================================
        // 4) ACTUALIZACIÓN EN GOOGLE CALENDAR (Opcional según tu lógica)
        // ==================================================================
        if (!empty($id_calendar_actual)) {
            $fechaStart = convertirAGoogleCalendar($fecha, $horaInicio, 0);
            $fechaEnd   = convertirAGoogleCalendar($fechaFinal, $horaFinal, 0);

            // FIX (nuevo): esta validación NO existía antes en este archivo — se agrega
            // para que actualizarEventoCalendar() nunca reciba una fecha null/inválida.
            if ($fechaStart && $fechaEnd) {
            $summary = "[$codigoReserva] $nombreTour - $nombrePrincipal";
            $description_cal = "Tour: $nombreTour\nCliente: $nombrePrincipal\nWhatsApp: $whatsapp";

            actualizarEventoCalendar($id_calendar_actual, $summary, $description_cal, $hotelDireccion, $fechaStart, $fechaEnd, "1");
            } else {
                error_log("Google Calendar (update): fecha/hora inválida — inicio: fecha=$fecha hora=$horaInicio (válida=" . ($fechaStart ? 'sí' : 'no') . ") | fin: fecha=$fechaFinal hora=$horaFinal (válida=" . ($fechaEnd ? 'sí' : 'no') . "). No se actualizó el evento id=$id_calendar_actual.");
            }
        }



	    echo json_encode([
		"success" => true,
		"message" => "Reserva actualizada con éxito",
		"serverUpdatedAt" => $newUpdatedAt // Lo devolvemos para que Room se actualice
	    ]);
	} else {
	    echo json_encode([
		"success" => false, 
		"message" => "Error al actualizar en base de datos: " . $stmt->error
	    ]);
	}

	$stmt->close();
	$conn->close();
?>
