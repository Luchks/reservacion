<?php
    // create_item.php - REVISIÓN TOTAL
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/php_error.log'); 
    error_reporting(E_ALL);
    ini_set('display_errors', 0); 

    header('Content-Type: application/json');

    include 'db.php'; 
    require 'google_calendar_service.php';
    include 'utilitarios/fechaCalendar.php'; 

    $input_json = file_get_contents('php://input');
    $data = json_decode($input_json, true);

    if (!$data) {
        echo json_encode(["success" => false, "message" => "Cuerpo vacío"]);
        exit;
    }

    // 1. MAPEADO (45 variables exactas)
    $nombreTour = $data['nombreTour'] ?? '';
    $name = !empty($nombreTour) ? $nombreTour : ($data['name'] ?? 'Tour');
    $description = $data['description'] ?? '';
    $codigoReserva = $data['codigoReserva'] ?? '';
    $tipoCliente = $data['tipoCliente'] ?? '';
    $fecha = $data['fecha'] ?? date('Y-m-d');
    $horaInicio = $data['horaInicio'] ?? '00:00 AM';
    $turno = $data['turno'] ?? '';
    $hotelDireccion = $data['hotelDireccion'] ?? '';
    $duracion = $data['duracion'] ?? '';
    $nombrePrincipal = $data['nombrePrincipal'] ?? '';
    $pasajerosAdicionales = is_array($data['pasajerosAdicionales'] ?? null) ? json_encode($data['pasajerosAdicionales']) : ($data['pasajerosAdicionales'] ?? '[]');
    $pasaporteID = $data['pasaporteID'] ?? '';
    $countryCodewhatsapp = $data['countryCodewhatsapp'] ?? '';
    $whatsapp = $data['whatsapp'] ?? '';
    $correo = $data['correo'] ?? '';
    $habitacion = $data['habitacion'] ?? '';
    $idioma = $data['idioma'] ?? '';
    $pais = $data['pais'] ?? '';
    $tipoPago = $data['tipoPago'] ?? '';
    $precioPorPersona = (float)($data['precioPorPersona'] ?? 0);
    $precioTotal = (float)($data['precioTotal'] ?? 0);
    $precioComisionable = (float)($data['precioComisionable'] ?? 0);
    $totalComision = (float)($data['totalComision'] ?? 0);
    $agente = $data['agente'] ?? '';
    $countryCodewaAgente = $data['countryCodewaAgente'] ?? '';
    $waAgente = $data['waAgente'] ?? '';
    $observacion = $data['observacion'] ?? '';
    $driver = $data['driver'] ?? '';
    $countryCodewaDriver = $data['countryCodewaDriver'] ?? '';
    $waDriver = $data['waDriver'] ?? '';
    $guia = $data['guia'] ?? '';
    $countryCodewaGuia = $data['countryCodewaGuia'] ?? '';
    $waGuia = $data['waGuia'] ?? '';
    $id_calendar = ""; 
    $id_map = $data['id_map'] ?? '';
    $cantidadPasajero = $data['cantidadPasajero'] ?? '1';
    $tipoServicio = $data['tipoServicio'] ?? '';
    $observacionGeneral = $data['observacionGeneral'] ?? '';
    $status = "BORRADOR";
    $is_deleted = 0;
    $FlagActivo = 1;
    $estadoPago = $data['estadoPago'] ?? 'PENDIENTE PAGO';
    $estadoReserva = $data['estadoReserva'] ?? 'COMPLETADA';
    $ComprobantePago = $data['comprobantePago'] ?? '';

    // 2. GOOGLE CALENDAR (Sin bloquear)

    // 3. SQL (45 columnas, 45 signos ?)
    $sql = "INSERT INTO items (
        name, description, codigoReserva, nombreTour, tipoCliente, 
        fecha, horaInicio, turno, hotelDireccion, duracion, 
        nombrePrincipal, pasajerosAdicionales, pasaporteID, countryCodewhatsapp, whatsapp, 
        correo, habitacion, idioma, pais, tipoPago, 
        precioPorPersona, precioTotal, precioComisionable, totalComision, agente, 
        countryCodewaAgente, waAgente, observacion, driver, countryCodewaDriver, 
        waDriver, guia, countryCodewaGuia, waGuia, id_calendar, 
        id_map, cantidadPasajero, tipoServicio, observacionGeneral, status, 
        is_deleted, FlagActivo, estadoPago, estadoReserva, ComprobantePago
    ) VALUES (
        ?,?,?,?,?,  ?,?,?,?,?,  ?,?,?,?,?,  ?,?,?,?,?, 
        ?,?,?,?,?,  ?,?,?,?,?,  ?,?,?,?,?,  ?,?,?,?,?, 
        ?,?,?,?,?
    )";

    $success = false;
    $error_db = "";

    if ($stmt = $conn->prepare($sql)) {
        // TIPOS: 
        // s: strings (39)
        // d: doubles (4) -> precios
        // i: integers (2) -> is_deleted, FlagActivo
        // Total = 45
        $types = "ssssssssssssssssssssddddsssssssssssssssssiiss";
        
        $stmt->bind_param($types, 
            $name, $description, $codigoReserva, $nombreTour, $tipoCliente, // 5
            $fecha, $horaInicio, $turno, $hotelDireccion, $duracion,         // 10
            $nombrePrincipal, $pasajerosAdicionales, $pasaporteID, $countryCodewhatsapp, $whatsapp, // 15
            $correo, $habitacion, $idioma, $pais, $tipoPago,               // 20
            $precioPorPersona, $precioTotal, $precioComisionable, $totalComision, $agente, // 25
            $countryCodewaAgente, $waAgente, $observacion, $driver, $countryCodewaDriver, // 30
            $waDriver, $guia, $countryCodewaGuia, $waGuia, $id_calendar,   // 35
            $id_map, $cantidadPasajero, $tipoServicio, $observacionGeneral, $status, // 40
            $is_deleted, $FlagActivo, $estadoPago, $estadoReserva, $ComprobantePago // 45
        );

        $success = $stmt->execute();
        if (!$success) $error_db = $stmt->error;
        $stmt->close();
    } else {
        $error_db = $conn->error;
    }

    echo json_encode(["success" => $success, "id_calendar" => $id_calendar, "error" => $error_db]);
