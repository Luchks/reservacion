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
        SELECT updated_at, id_calendar, codigoReserva, nombreTour, tipoCliente,
               nombrePrincipal, hotelDireccion, fecha, fechaFinal, horaInicio, horaFinal,
               countryCodewhatsapp, whatsapp, pasajerosAdicionales, pasaporteID,
               correo, pais, idioma, cantidadPasajero, tipoServicio,
               agente, countryCodewaAgente, waAgente,
               tipoPago, precioPorPersona, precioTotal, ComprobantePago,
               observacion, observacionGeneral,
               driver, waDriver, guia, waGuia
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
        // 3) ACTUALIZAR GOOGLE CALENDAR (Cambio de color según estadoPago)
        // ==================================================================
        $id_calendar = $currentItem['id_calendar'];

        if (!empty($id_calendar)) {
            // Color según el NUEVO estadoPago (el que se acaba de guardar)
            $colorId = "1";
            if ($estadoPago === 'PAGADO')             $colorId = "10"; // Verde
            if ($estadoPago === 'PENDIENTE PAGO')     $colorId = "11"; // Rojo
            if ($estadoPago === 'CREDITO AGENCIA')    $colorId = "5";  // Amarillo
            if ($estadoPago === 'SERVICIO GRATUITO')  $colorId = "3";  // Morado

            $fechaStart = convertirAGoogleCalendar($currentItem['fecha'],      $currentItem['horaInicio'], 0);
            $fechaEnd   = convertirAGoogleCalendar($currentItem['fechaFinal'], $currentItem['horaFinal'],  0);

            if ($fechaStart && $fechaEnd) {
                // ── Summary: mismo formato que create_item.php ────────────
                $summary  = $currentItem['nombreTour'] . " X" . $currentItem['cantidadPasajero'] .
                            " (" . $currentItem['tipoServicio'] . ") - 👤 " . $currentItem['agente'];
                $location = $currentItem['hotelDireccion'];

                // ── Pasajeros adicionales ─────────────────────────────────
                $listaPasajeros = json_decode($currentItem['pasajerosAdicionales'], true);
                $textoPasajeros = "";
                if (is_array($listaPasajeros) && !empty($listaPasajeros)) {
                    foreach ($listaPasajeros as $p) {
                        $nombre = is_array($p) ? ($p['nombre'] ?? 'Sin nombre') : $p;
                        $textoPasajeros .= "- " . mb_strtoupper($nombre) . "<br>";
                    }
                } else {
                    $textoPasajeros = "Ninguno";
                }

                // ── Descripción completa idéntica a create_item.php ───────
                $description_cal =
                    "<b>🆔 Código: " . $currentItem['codigoReserva'] . "</b> <br>" .
                    "<br><b>=======================</b><br>" .
                    "<b>📅 DATOS DE RESERVA</b><br>" .
                    "<b>🗺️ Servicio:</b> " . $currentItem['nombreTour'] . "<br>" .
                    "<b>🛠️ Tipo de servicio:</b> " . $currentItem['tipoServicio'] . "<br>" .
                    "<b>👥 Tipo de cliente:</b> " . $currentItem['tipoCliente'] . "<br>" .
                    "<b>🏢 Contacto o Agente:</b> " . $currentItem['agente'] . "<br>" .
                    "<b>📞 Teléfono contacto:</b> " . $currentItem['waAgente'] . "<br>" .
                    "<b>📆 Fecha:</b> " . $currentItem['fecha'] . "<br>" .
                    "<b>⏰ Hora de Inicio:</b> " . $currentItem['horaInicio'] . "<br>" .
                    "<b>🏁 Fecha final:</b> " . $currentItem['fechaFinal'] . "<br>" .
                    "<b>⌛ Hora de final:</b> " . $currentItem['horaFinal'] . "<br>" .
                    "<br><b>=======================</b><br>" .
                    "<b>👤 DATOS DEL PASAJERO</b><br>" .
                    "<b>🔢 Cantidad pasajeros:</b> " . $currentItem['cantidadPasajero'] . "<br>" .
                    "<b>🥇 Pasajero principal:</b> " . $currentItem['nombrePrincipal'] . "<br>" .
                    "<b>📱 WhatsApp:</b> <a href='https://wa.me/" . $currentItem['whatsapp'] . "'>📲 " . $currentItem['countryCodewhatsapp'] . $currentItem['whatsapp'] . "</a><br>" .
                    "<b>🛂 pasaporteID:</b> " . $currentItem['pasaporteID'] . "<br>" .
                    "<b>📧 Correo:</b> " . $currentItem['correo'] . "<br>" .
                    "<b>🌎 País:</b> " . $currentItem['pais'] . "<br>" .
                    "<b>🗣️ Idioma:</b> " . $currentItem['idioma'] . "<br>" .
                    "<b>👫 Pasajeros adicionales:</b><br>" . $textoPasajeros . "<br>" .
                    "<br><b>=======================</b><br>" .
                    "<b>💰 PAGOS</b><br>" .
                    "<b>💳 Tipo de pago:</b> " . $currentItem['tipoPago'] . "<br>" .
                    "<b>💵 Precio por persona:</b> " . $currentItem['precioPorPersona'] . "<br>" .
                    "<b>💎 Precio total:</b> " . $currentItem['precioTotal'] . "<br>" .
                    "<b>✅ Estado de pago:</b> " . $estadoPago . "<br>" .  // nuevo valor
                    "<b>📄 Comprobante:</b> " . $currentItem['ComprobantePago'] . "<br>" .
                    "<br><b>=======================</b><br>" .
                    "<b>📝 OBSERVACIONES</b><br>" .
                    "<b>🔒 Interna:</b> " . $currentItem['observacion'] . "<br>" .
                    "<b>📢 General:</b> " . $currentItem['observacionGeneral'] . "<br>" .
                    "<br><b>=======================</b><br>" .
                    "<b>🚐 PERSONAL ASIGNADO</b><br>" .
                    "<b>👨‍✈️ Driver:</b> " . $currentItem['driver'] . "<br>" .
                    "<b>📞 Teléfono driver:</b> " . $currentItem['waDriver'] . "<br>" .
                    "<b>🚩 Guía:</b> " . $currentItem['guia'] . "<br>" .
                    "<b>📞 Teléfono guía:</b> " . $currentItem['waGuia'] . "<br>";

                actualizarEventoCalendar($id_calendar, $summary, $description_cal, $location, $fechaStart, $fechaEnd, $colorId);

            } else {
                error_log("Google Calendar (update_estado_pago): fecha/hora inválida — inicio: fecha=" . $currentItem['fecha'] . " hora=" . $currentItem['horaInicio'] . " (válida=" . ($fechaStart ? 'sí' : 'no') . ") | fin: fecha=" . $currentItem['fechaFinal'] . " hora=" . $currentItem['horaFinal'] . " (válida=" . ($fechaEnd ? 'sí' : 'no') . "). No se actualizó el evento id=$id_calendar.");
            }
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
