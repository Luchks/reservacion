<?php

    ob_start(); // captura cualquier output no deseado (warnings de PHP, etc.)

    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Allow-Headers: Content-Type');

    ini_set('display_errors', 0);
    ini_set('log_errors',     1);

    include 'db.php';
    include 'utilitarios/fechaCalendar.php';
    require 'google_calendar_service.php';

    // ── 0. Limpiar output buffer antes de responder ───────────────────────────
    function responder(array $payload): void {
        ob_end_clean();
        echo json_encode($payload);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['id']) || !isset($data['estadoPago'])) {
        responder(["success" => false, "message" => "Datos incompletos"]);
    }

    $id              = (int)$data['id'];
    $estadoPago      = strtoupper(trim($data['estadoPago']));
    $clientUpdatedAt = $data['updatedAt'] ?? '';

    // ── 1. Validación de dominio ──────────────────────────────────────────────
    $estadosValidos = ['PAGADO', 'PENDIENTE PAGO', 'CREDITO AGENCIA', 'SERVICIO GRATUITO'];
    if (!in_array($estadoPago, $estadosValidos, true)) {
        responder(["success" => false, "message" => "Estado de pago inválido: $estadoPago"]);
    }

    // ── 2. Leer fila actual y verificar conflicto ─────────────────────────────
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
    $result      = $stmtRead->get_result();
    $currentItem = $result->fetch_assoc();
    $stmtRead->close();

    if (!$currentItem) {
        responder(["success" => false, "message" => "Reserva no encontrada"]);
    }

    // ── 3. Validación de concurrencia optimista ───────────────────────────────
    if (!empty($clientUpdatedAt) && $currentItem['updated_at'] !== $clientUpdatedAt) {
        responder([
            "success"         => false,
            "conflict"        => true,
            "message"         => "Conflicto: el estado de pago fue modificado en otro dispositivo.",
            "serverUpdatedAt" => $currentItem['updated_at']
        ]);
    }

    // ── 4. Actualizar en MySQL ────────────────────────────────────────────────
    // Columna: `estadoPago` (camelCase) — confirmado en DDL y en ItemEntity.kt
    $stmtUpdate = $conn->prepare("UPDATE items SET estadoPago = ? WHERE id = ?");
    $stmtUpdate->bind_param("si", $estadoPago, $id);
    $ok = $stmtUpdate->execute();
    $stmtUpdate->close();

    if (!$ok) {
        responder(["success" => false, "message" => "Error al actualizar en la base de datos"]);
    }

    // ── 5. Obtener el nuevo updated_at ────────────────────────────────────────
    $stmtTs = $conn->prepare("SELECT updated_at FROM items WHERE id = ?");
    $stmtTs->bind_param("i", $id);
    $stmtTs->execute();
    $stmtTs->bind_result($newUpdatedAt);
    $stmtTs->fetch();
    $stmtTs->close();
    $conn->close();

    // ── 6. Google Calendar — aislado: si falla, NO aborta la respuesta ────────
    $calendarError = false;
    $id_calendar   = $currentItem['id_calendar'] ?? '';

    if (!empty($id_calendar)) {
        try {
            $colorId = "1";
            if ($estadoPago === 'PAGADO')             $colorId = "10";
            if ($estadoPago === 'PENDIENTE PAGO')     $colorId = "11";
            if ($estadoPago === 'CREDITO AGENCIA')    $colorId = "5";
            if ($estadoPago === 'SERVICIO GRATUITO')  $colorId = "3";

            // ✅ BLINDAJE CONTRA NULOS: fallback a fecha/hora de inicio
            $fechaFinalSafe = !empty($currentItem['fechaFinal']) ? $currentItem['fechaFinal'] : $currentItem['fecha'];
            $horaFinalSafe  = !empty($currentItem['horaFinal'])  ? $currentItem['horaFinal']  : $currentItem['horaInicio'];

            $fechaStart = convertirAGoogleCalendar($currentItem['fecha'], $currentItem['horaInicio'], 0);
            $fechaEnd   = convertirAGoogleCalendar($fechaFinalSafe,       $horaFinalSafe,             0);

            if ($fechaStart && $fechaEnd) {
                $summary  = $currentItem['nombreTour'] . " X" . $currentItem['cantidadPasajero'] .
                            " (" . $currentItem['tipoServicio'] . ") - 👤 " . $currentItem['agente'];
                $location = $currentItem['hotelDireccion'];

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
                    "<b>🏁 Fecha final:</b> " . $fechaFinalSafe . "<br>" .
                    "<b>⌛ Hora de final:</b> " . $horaFinalSafe . "<br>" .
                    "<br><b>=======================</b><br>" .
                    "<b>👤 DATOS DEL PASAJERO</b><br>" .
                    "<b>🔢 Cantidad pasajeros:</b> " . $currentItem['cantidadPasajero'] . "<br>" .
                    "<b>🥇 Pasajero principal:</b> " . $currentItem['nombrePrincipal'] . "<br>" .
                    "<b>📱 WhatsApp:</b> <a href='https://wa.me/" . $currentItem['whatsapp'] . "'>📲 " . $currentItem['countryCodewhatsapp'] . $currentItem['whatsapp'] . "</a><br>" .
                    "<b>🛂 Pasaporte/ID:</b> " . $currentItem['pasaporteID'] . "<br>" .
                    "<b>📧 Correo:</b> " . $currentItem['correo'] . "<br>" .
                    "<b>🌎 País:</b> " . $currentItem['pais'] . "<br>" .
                    "<b>🗣️ Idioma:</b> " . $currentItem['idioma'] . "<br>" .
                    "<b>👫 Pasajeros adicionales:</b><br>" . $textoPasajeros . "<br>" .
                    "<br><b>=======================</b><br>" .
                    "<b>💰 PAGOS</b><br>" .
                    "<b>💳 Tipo de pago:</b> " . $currentItem['tipoPago'] . "<br>" .
                    "<b>💵 Precio por persona:</b> " . $currentItem['precioPorPersona'] . "<br>" .
                    "<b>💎 Precio total:</b> " . $currentItem['precioTotal'] . "<br>" .
                    "<b>✅ Estado de pago:</b> " . $estadoPago . "<br>" .
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
                error_log("update_estado_pago: fecha/hora inválida id=$id — Calendar no actualizado.");
                $calendarError = true;
            }
        } catch (Throwable $e) {
            // ✅ CLAVE: atrapar CUALQUIER error de Calendar (incluye Error de PHP)
            error_log("update_estado_pago: Calendar exception id=$id: " . $e->getMessage());
            $calendarError = true;
            // NO re-lanzamos: el pago ya está guardado en MySQL
        }
    }

    // ── 7. Respuesta al cliente ───────────────────────────────────────────────
    responder([
        "success"         => true,
        "message"         => "Estado de pago actualizado",
        "serverUpdatedAt" => $newUpdatedAt,
        "calendar_error"  => $calendarError  // informativo; Android ya lo ignora o lo loggea
    ]);
?>
