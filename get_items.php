<?php
	header('Content-Type: application/json');
	include 'db.php';

	/**
	 * Utilidades de normalización
	 */
	function nstr($v) { return $v === null ? "" : (string)$v; }
	function nint($v) { return $v === null ? 0 : (int)$v; }

	/**
	 * Normaliza el estado de pago para asegurar compatibilidad con la App
	 */
	function normalizeEstadoPago($v) {
	    $s = strtoupper(trim((string)$v));
	    if ($s === "" || $s === "0") return "PENDIENTE PAGO";
	    $valid = ["PAGADO", "PENDIENTE PAGO", "CREDITO AGENCIA", "SERVICIO GRATUITO"];
	    return in_array($s, $valid, true) ? $s : "PENDIENTE PAGO";
	}

	// 1. Leer parámetros de fecha
	$mes  = intval($_GET['mes']  ?? date('n'));
	$anio = intval($_GET['anio'] ?? date('Y'));

	// Validaciones básicas de rango
	if ($mes  < 1  || $mes  > 12)    $mes  = (int)date('n');
	if ($anio < 2020 || $anio > 2100) $anio = (int)date('Y');

	// 2. Consulta SQL 
	// Incluimos explícitamente updated_at para el control de concurrencia
	$sql = "SELECT * FROM items 
		WHERE FlagActivo = 1 
		  AND (estadoReserva = 'COMPLETADO' OR estadoReserva = 'BORRADOR')
		  AND YEAR(fecha) = $anio
		  AND MONTH(fecha) = $mes
		ORDER BY fecha ASC, horaInicio ASC";

	$result = $conn->query($sql);

	if (!$result) {
	    echo json_encode(["error" => "Error en la consulta: " . $conn->error]);
	    exit;
	}

	$items = [];

	while ($row = $result->fetch_assoc()) {
	    // Manejo de JSON de pasajeros adicionales
	    $row['pasajerosAdicionales'] = json_decode($row['pasajerosAdicionales'] ?? '[]', true) ?: [];

	    // Normalización de tipos numéricos (Enteros)
	    $row['id']                 = nint($row['id']);
	    $row['precioPorPersona']   = nint($row['precioPorPersona']);
	    $row['precioTotal']        = nint($row['precioTotal']);
	    $row['precioComisionable'] = nint($row['precioComisionable']);
	    $row['totalComision']      = nint($row['totalComision']);
	    $row['FlagActivo']         = nint($row['FlagActivo']);

	    // Normalización de Strings
	    $stringFields = [
		'name','description','codigoReserva','nombreTour','tipoCliente',
		'fecha','horaInicio','turno','hotelDireccion','duracion',
		'nombrePrincipal','pasaporteID','countryCodewhatsapp','whatsapp',
		'correo','habitacion','idioma','pais','tipoPago','agente',
		'countryCodewaAgente','waAgente','observacion','driver',
		'countryCodewaDriver','waDriver','guia','countryCodewaGuia',
		'waGuia','id_calendar','id_map','cantidadPasajero','tipoServicio',
		'observacionGeneral','status','estadoPago','estadoReserva',
		'ComprobantePago','fechaFinal','horaFinal',
		'updated_at' // <--- AGREGADO: Importante para el control de conflictos
	    ];

	    foreach ($stringFields as $field) {
		if (array_key_exists($field, $row)) {
		    $row[$field] = nstr($row[$field]);
		}
	    }

	    // 3. Normalizar nombres de campos para Kotlin (CamelCase)
	    
	    // Comprobante de pago
	    if (isset($row['ComprobantePago'])) {
		$row['comprobantePago'] = $row['ComprobantePago'];
		unset($row['ComprobantePago']);
	    }

	    // updated_at -> updatedAt (Para que coincida con el data class Item de Kotlin)
	    if (isset($row['updated_at'])) {
		$row['updatedAt'] = $row['updated_at'];
		// No eliminamos updated_at por si acaso, pero exponemos el formato correcto
	    }

	    // Normalizar Estado de Pago
	    $row['estadoPago'] = normalizeEstadoPago($row['estadoPago'] ?? '');

	    $items[] = $row;
	}

	// 4. Respuesta Final
	echo json_encode($items);

	$conn->close();
?>
