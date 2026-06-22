<?php
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Allow-Headers: Content-Type');

    include 'db.php';
    include 'utilitarios/fechaCalendar.php';
    require 'google_calendar_service.php';

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['id']) || !isset($data['tipoPago'])) {
        echo json_encode([
            "success" => false,
            "message" => "Datos incompletos (id o tipoPago faltantes)"
        ]);
        exit;
    }

    $id       = (int)$data['id'];
    $tipoPago = strtoupper(trim($data['tipoPago']));
    
    // Recibimos el updatedAt enviado desde la App para verificar concurrencia
    $clientUpdatedAt = $data['updatedAt'] ?? '';

    // Validar tipos de pago permitidos
    $tiposValidos = ['POS', 'EFECTIVO', 'PAGO LINK', 'TRANSFERENCIA',
                     'POS CULQI', 'POS NIUVIZ', 'POS EXTERNO', 'VIATOR'];
                     
    if (!in_array($tipoPago, $tiposValidos)) {
        echo json_encode([
            "success" => false,
            "message" => "Tipo de pago inválido: $tipoPago"
        ]);
        exit;
    }

    // ==================================================================
    // 1) LEER DATOS ACTUALES Y VERIFICAR CONFLICTO
    // ==================================================================
    $stmtRead = $conn->prepare("
        SELECT updated_at, id_calendar, codigoReserva, nombreTour, nombrePrincipal, 
               hotelDireccion, fecha, fechaFinal, horaInicio, horaFinal, whatsapp,
               estadoPago, precioPorPersona, precioTotal, comprobantePago, 
               observacion, observacionGeneral, driver, waDriver, guia, waGuia
        FROM items WHERE id = ?
    ");
    $stmtRead->bind_param("i", $id);
    $stmtRead->execute();
    $result = $stmtRead->get_result();
    $currentItem = $result->fetch_assoc();
    $stmtRead->close();

    if (!$currentItem) {
        echo json_encode(["success" => false, "message" => "La reserva no existe."]);
        exit;
    }

    // --- VALIDACIÓN DE CONCURRENCIA ---
    // Si el servidor tiene una fecha distinta a la que la App tiene guardada
    if (!empty($clientUpdatedAt) && $currentItem['updated_at'] !== $clientUpdatedAt) {
        echo json_encode([
            "success" => false,
            "conflict" => true,
            "message" => "Conflicto: El tipo de pago fue modificado recientemente por otro usuario.",
            "serverUpdatedAt" => $currentItem['updated_at']
        ]);
        exit;
    }

    // ==================================================================
    // 2) ACTUALIZAR EN MYSQL
    // ==================================================================
    $stmtUpdate = $conn->prepare("UPDATE items SET tipoPago = ? WHERE id = ?");
    $stmtUpdate->bind_param("si", $tipoPago, $id);
    $ok = $stmtUpdate->execute();
    $stmtUpdate->close();

    if ($ok) {
        // Obtener el nuevo timestamp generado por MySQL
        $stmtNewDate = $conn->prepare("SELECT updated_at FROM items WHERE id = ?");
        $stmtNewDate->bind_param("i", $id);
        $stmtNewDate->execute();
        $stmtNewDate->bind_result($newUpdatedAt);
        $stmtNewDate->fetch();
        $stmtNewDate->close();

        // ==================================================================
        // 3) ACTUALIZAR GOOGLE CALENDAR (Actualizar descripción)
        // ==================================================================
        $id_calendar = $currentItem['id_calendar'];

        if (!empty($id_calendar)) {
            // Determinar color según el estado de pago actual
            $estadoPago = $currentItem['estadoPago'];
            $colorId = "1"; 
            if ($estadoPago === 'PAGADO')            $colorId = "10";
            if ($estadoPago === 'PENDIENTE PAGO')   $colorId = "11";
            if ($estadoPago === 'CREDITO AGENCIA')  $colorId = "5";
            if ($estadoPago === 'SERVICIO GRATUITO') $colorId = "3";

            $fechaStart = convertirAGoogleCalendar($currentItem['fecha'], $currentItem['horaInicio']);
            $fechaEnd   = convertirAGoogleCalendar($currentItem['fechaFinal'], $currentItem['horaFinal']);
            
            $summary = "[" . $currentItem['codigoReserva'] . "] " . $currentItem['nombreTour'] . " - " . $currentItem['nombrePrincipal'];
            $location = $currentItem['hotelDireccion'];

            // Reconstruir la descripción incluyendo el NUEVO tipoPago
            $description_cal = "<b>Tour:</b> " . $currentItem['nombreTour'] . "<br>" .
                "<b>Cliente:</b> " . $currentItem['nombrePrincipal'] . "<br>" .
                "<b>WhatsApp:</b> " . $currentItem['whatsapp'] . "<br>" .
                "<b>=======================</b><br>" .
                "<b>💰 PAGOS</b><br>" .
                "<b>💳 Tipo de pago:</b> " . $tipoPago . "<br>" . // Nuevo valor
                "<b>💵 Precio x Pers:</b> " . $currentItem['precioPorPersona'] . "<br>" .
                "<b>💎 Precio Total:</b> " . $currentItem['precioTotal'] . "<br>" .
                "<b>✅ Estado Pago:</b> " . $estadoPago . "<br>" .
                "<b>📄 Comprobante:</b> " . $currentItem['comprobantePago'] . "<br>" .
                "<br><b>=======================</b><br>" .
                "<b>PERSONAL ASIGNADO</b><br>" .
                "<b>👨‍✈️ Driver:</b> " . $currentItem['driver'] . " (" . $currentItem['waDriver'] . ")<br>" .
                "<b>🚩 Guía:</b> " . $currentItem['guia'] . " (" . $currentItem['waGuia'] . ")<br>";

            actualizarEventoCalendar($id_calendar, $summary, $description_cal, $location, $fechaStart, $fechaEnd, $colorId);
        }

        echo json_encode([
            "success" => true,
            "message" => "Tipo de pago actualizado correctamente",
            "serverUpdatedAt" => $newUpdatedAt
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Error al intentar actualizar la base de datos"
        ]);
    }

    $conn->close();
?>
