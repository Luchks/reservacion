<?php
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Allow-Headers: Content-Type');

    include 'db.php';
    include 'utilitarios/fechaCalendar.php';
    require 'google_calendar_service.php';

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['id']) || !isset($data['estadoPago'])) {
        echo json_encode([
            "success" => false,
            "message" => "Datos incompletos"
        ]);
        exit;
    }

    $id         = (int)$data['id'];
    $estadoPago = strtoupper(trim($data['estadoPago']));
    
    // Recibimos el updatedAt del celular para validar concurrencia
    $clientUpdatedAt = $data['updatedAt'] ?? '';

    // Validar que el estado sea válido
    $estadosValidos = ['PAGADO', 'PENDIENTE PAGO', 'CREDITO AGENCIA', 'SERVICIO GRATUITO'];
    if (!in_array($estadoPago, $estadosValidos)) {
        echo json_encode([
            "success" => false,
            "message" => "Estado de pago inválido: $estadoPago"
        ]);
        exit;
    }

    // ==================================================================
    // 1) LEER DATOS ACTUALES Y VERIFICAR CONFLICTO
    // ==================================================================
    $stmtRead = $conn->prepare("
        SELECT updated_at, id_calendar, codigoReserva, nombreTour, nombrePrincipal, 
               hotelDireccion, fecha, fechaFinal, horaInicio, horaFinal, whatsapp,
               tipoPago, precioPorPersona, precioTotal, comprobantePago, 
               observacion, observacionGeneral, driver, waDriver, guia, waGuia
        FROM items WHERE id = ?
    ");
    $stmtRead->bind_param("i", $id);
    $stmtRead->execute();
    $result = $stmtRead->get_result();
    $currentItem = $result->fetch_assoc();
    $stmtRead->close();

    if (!$currentItem) {
        echo json_encode(["success" => false, "message" => "Reserva no encontrada"]);
        exit;
    }

    // --- VALIDACIÓN DE CONFLICTO ---
    if (!empty($clientUpdatedAt) && $currentItem['updated_at'] !== $clientUpdatedAt) {
        echo json_encode([
            "success" => false,
            "conflict" => true,
            "message" => "Conflicto: El estado de pago fue modificado en otro dispositivo.",
            "serverUpdatedAt" => $currentItem['updated_at']
        ]);
        exit;
    }

    // ==================================================================
    // 2) ACTUALIZAR EN MYSQL
    // ==================================================================
    $stmtUpdate = $conn->prepare("UPDATE items SET estadoPago = ? WHERE id = ?");
    $stmtUpdate->bind_param("si", $estadoPago, $id);
    $ok = $stmtUpdate->execute();
    $stmtUpdate->close();

    if ($ok) {
        // Obtener el nuevo updated_at generado tras el update
        $stmtNewDate = $conn->prepare("SELECT updated_at FROM items WHERE id = ?");
        $stmtNewDate->bind_param("i", $id);
        $stmtNewDate->execute();
        $stmtNewDate->bind_result($newUpdatedAt);
        $stmtNewDate->fetch();
        $stmtNewDate->close();

        // ==================================================================
        // 3) ACTUALIZAR GOOGLE CALENDAR (Cambio de color según estado)
        // ==================================================================
        $id_calendar = $currentItem['id_calendar'];

        if (!empty($id_calendar)) {
            // Lógica de colores que ya manejas
            $colorId = "1"; // Default (Azul/Lavanda)
            if ($estadoPago === 'PAGADO')            $colorId = "10"; // Verde
            if ($estadoPago === 'PENDIENTE PAGO')   $colorId = "11"; // Rojo
            if ($estadoPago === 'CREDITO AGENCIA')  $colorId = "5";  // Amarillo
            if ($estadoPago === 'SERVICIO GRATUITO') $colorId = "3";  // Morado

            $fechaStart = convertirAGoogleCalendar($currentItem['fecha'], $currentItem['horaInicio']);
            $fechaEnd   = convertirAGoogleCalendar($currentItem['fechaFinal'], $currentItem['horaFinal']);
            
            $summary = "[" . $currentItem['codigoReserva'] . "] " . $currentItem['nombreTour'] . " - " . $currentItem['nombrePrincipal'];
            $location = $currentItem['hotelDireccion'];

            // Construir descripción (usando los datos leídos de la DB)
            $description_cal = "<b>Tour:</b> " . $currentItem['nombreTour'] . "<br>" .
                "<b>Cliente:</b> " . $currentItem['nombrePrincipal'] . "<br>" .
                "<b>WhatsApp:</b> " . $currentItem['whatsapp'] . "<br>" .
                "<b>=======================</b><br>" .
                "<b>💰 PAGOS</b><br>" .
                "<b>💳 Tipo de pago:</b> " . $currentItem['tipoPago'] . "<br>" .
                "<b>💵 Precio x Pers:</b> " . $currentItem['precioPorPersona'] . "<br>" .
                "<b>💎 Precio Total:</b> " . $currentItem['precioTotal'] . "<br>" .
                "<b>✅ Estado Pago:</b> " . $estadoPago . "<br>" . // El nuevo estado
                "<b>📄 Comprobante:</b> " . $currentItem['comprobantePago'] . "<br>" .
                "<br><b>=======================</b><br>" .
                "<b>PERSONAL ASIGNADO</b><br>" .
                "<b>👨‍✈️ Driver:</b> " . $currentItem['driver'] . " (" . $currentItem['waDriver'] . ")<br>" .
                "<b>🚩 Guía:</b> " . $currentItem['guia'] . " (" . $currentItem['waGuia'] . ")<br>";

            actualizarEventoCalendar($id_calendar, $summary, $description_cal, $location, $fechaStart, $fechaEnd, $colorId);
        }

        echo json_encode([
            "success" => true,
            "message" => "Estado de pago actualizado",
            "serverUpdatedAt" => $newUpdatedAt
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Error al actualizar en la base de datos"
        ]);
    }

    $conn->close();
?>
