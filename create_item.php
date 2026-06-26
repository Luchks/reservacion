<?php
    // create_item.php
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

    // ─────────────────────────────────────────────────────────────────────
    // 1. MAPEADO
    // ─────────────────────────────────────────────────────────────────────
    $nombreTour          = $data['nombreTour']          ?? '';
    $name                = !empty($nombreTour) ? $nombreTour : ($data['name'] ?? 'Tour');
    $description         = $data['description']         ?? '';
    $codigoReserva       = $data['codigoReserva']       ?? '';
    $tipoCliente         = $data['tipoCliente']         ?? '';
    $fecha               = $data['fecha']               ?? date('Y-m-d');
    $fechaFinal          = $data['fechaFinal']          ?? date('Y-m-d');
    $horaInicio          = $data['horaInicio']          ?? '00:00 AM';
    $horaFinal           = $data['horaFinal']           ?? '00:00 AM';
    $turno               = $data['turno']               ?? '';
    $hotelDireccion      = $data['hotelDireccion']      ?? '';
    $duracion            = $data['duracion']            ?? '';
    $nombrePrincipal     = $data['nombrePrincipal']     ?? '';
    $pasajerosAdicionales = is_array($data['pasajerosAdicionales'] ?? null)
        ? json_encode($data['pasajerosAdicionales'])
        : ($data['pasajerosAdicionales'] ?? '[]');
    $pasaporteID         = $data['pasaporteID']         ?? '';
    $countryCodewhatsapp = $data['countryCodewhatsapp'] ?? '';
    $whatsapp            = $data['whatsapp']            ?? '';
    $correo              = $data['correo']              ?? '';
    $habitacion          = $data['habitacion']          ?? '';
    $idioma              = $data['idioma']              ?? '';
    $pais                = $data['pais']                ?? '';
    $tipoPago            = $data['tipoPago']            ?? '';
    $precioPorPersona    = (float)($data['precioPorPersona']  ?? 0);
    $precioTotal         = (float)($data['precioTotal']        ?? 0);
    $precioComisionable  = (float)($data['precioComisionable'] ?? 0);
    $totalComision       = (float)($data['totalComision']      ?? 0);
    $agente              = $data['agente']              ?? '';
    $countryCodewaAgente = $data['countryCodewaAgente'] ?? '';
    $waAgente            = $data['waAgente']            ?? '';
    $observacion         = $data['observacion']         ?? '';
    $driver              = $data['driver']              ?? '';
    $countryCodewaDriver = $data['countryCodewaDriver'] ?? '';
    $waDriver            = $data['waDriver']            ?? '';
    $guia                = $data['guia']                ?? '';
    $countryCodewaGuia   = $data['countryCodewaGuia']   ?? '';
    $waGuia              = $data['waGuia']              ?? '';
    $id_map              = $data['id_map']              ?? '';
    $cantidadPasajero    = $data['cantidadPasajero']    ?? '1';
    $tipoServicio        = $data['tipoServicio']        ?? '';
    $observacionGeneral  = $data['observacionGeneral']  ?? '';
    $status              = "BORRADOR";
    $is_deleted          = 0;
    $FlagActivo          = 1;
    $estadoPago          = $data['estadoPago']          ?? 'PENDIENTE PAGO';
    $estadoReserva       = $data['estadoReserva']       ?? 'COMPLETADO';
    $comprobantePago     = $data['comprobantePago']     ?? '';
    $clientId            = trim($data['clientId']       ?? '');

    // ─────────────────────────────────────────────────────────────────────
    // 2. IDEMPOTENCIA POR clientId
    //
    // Si Android ya mandó este clientId antes (reintento de sync),
    // devolvemos el id existente SIN insertar nada ni generar nuevo código.
    // Esto evita duplicados en MySQL cuando syncPendingOperations() se
    // ejecuta múltiples veces sobre el mismo item PENDING_CREATE.
    // ─────────────────────────────────────────────────────────────────────
    if (!empty($clientId)) {
        $stmtCheck = $conn->prepare("SELECT id, codigoReserva FROM items WHERE clientId = ? and FlagActivo = 1 LIMIT 1");
        $stmtCheck->bind_param("s", $clientId);
        $stmtCheck->execute();
        $stmtCheck->bind_result($existingId, $existingCodigo);
        $found = $stmtCheck->fetch();
        $stmtCheck->close();

        if ($found) {
            error_log("create_item: reintento detectado por clientId=$clientId, id existente=$existingId, codigo=$existingCodigo");
            echo json_encode([
                "success"       => true,
                "id"            => (int)$existingId,
                "codigoReserva" => $existingCodigo,
                "duplicado"     => true,
                "message"       => "OK (idempotente por clientId)"
            ]);
            $conn->close();
            exit;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // 3. GENERAR codigoReserva (stored procedure)
    //
    // Solo llegamos aquí si el clientId NO existe todavía en MySQL,
    // es decir, es una inserción genuinamente nueva.
    // ─────────────────────────────────────────────────────────────────────
    $codigoReserva = '';

    if ($conn->query("CALL obtener_siguiente_codigo('RESERVA', @codigo)") === false) {
        echo json_encode(["success" => false, "message" => "Error al llamar stored procedure: " . $conn->error]);
        exit;
    }

    // Limpiar resultados pendientes del CALL antes de hacer el SELECT
    while ($conn->more_results() && $conn->next_result()) {
        $extra = $conn->use_result();
        if ($extra) $extra->close();
    }

    $res = $conn->query("SELECT @codigo AS nuevo_codigo");
    if ($res && $row = $res->fetch_assoc()) {
        $codigoReserva = $row['nuevo_codigo'];
        $res->free();
    }

    // ─────────────────────────────────────────────────────────────────────
    // 4. INSERT IDEMPOTENTE EN MYSQL
    //
    // Se mantiene INSERT IGNORE como segunda línea de defensa (por si
    // dos requests llegan simultáneamente antes de que el clientId esté
    // guardado). El clientId se almacena en la fila para futuras consultas.
    // ─────────────────────────────────────────────────────────────────────
    $id_calendar = "";

    $sql = "INSERT IGNORE INTO items (
        name, description, codigoReserva, nombreTour, tipoCliente,
        fecha, horaInicio, turno, hotelDireccion, duracion,
        nombrePrincipal, pasajerosAdicionales, pasaporteID, countryCodewhatsapp, whatsapp,
        correo, habitacion, idioma, pais, tipoPago,
        precioPorPersona, precioTotal, precioComisionable, totalComision, agente,
        countryCodewaAgente, waAgente, observacion, driver, countryCodewaDriver,
        waDriver, guia, countryCodewaGuia, waGuia, id_calendar,
        id_map, cantidadPasajero, tipoServicio, observacionGeneral, status,
        is_deleted, FlagActivo, estadoPago, estadoReserva, comprobantePago, fechaFinal, horaFinal,
        clientId
    ) VALUES (
        ?,?,?,?,?,  ?,?,?,?,?,  ?,?,?,?,?,  ?,?,?,?,?,
        ?,?,?,?,?,  ?,?,?,?,?,  ?,?,?,?,?,  ?,?,?,?,?,
        ?,?,?,?,?,  ?,?,?
    )";

    $success    = false;
    $error_db   = "";
    $insertedId = 0;
    $esDuplicado = false;

    if ($stmt = $conn->prepare($sql)) {
        // 47 params: 39s + 4d + 2i + 2s (estadoPago,estadoReserva) = original 45 + fechaFinal,horaFinal,clientId = 48
        // Conteo: s×41 + d×4 + i×2 + s×1 = ssssssssssssssssssssddddsssssssssssssssssiisssss


    $types = "ssssssssssssssssssssddddssssssssssssssssiissssss";

    $params = [
        $name,
        $description,
        $codigoReserva,
        $nombreTour,
        $tipoCliente,
        $fecha,
        $horaInicio,
        $turno,
        $hotelDireccion,
        $duracion,
        $nombrePrincipal,
        $pasajerosAdicionales,
        $pasaporteID,
        $countryCodewhatsapp,
        $whatsapp,
        $correo,
        $habitacion,
        $idioma,
        $pais,
        $tipoPago,
        $precioPorPersona,
        $precioTotal,
        $precioComisionable,
        $totalComision,
        $agente,
        $countryCodewaAgente,
        $waAgente,
        $observacion,
        $driver,
        $countryCodewaDriver,
        $waDriver,
        $guia,
        $countryCodewaGuia,
        $waGuia,
        $id_calendar,
        $id_map,
        $cantidadPasajero,
        $tipoServicio,
        $observacionGeneral,
        $status,
        $is_deleted,
        $FlagActivo,
        $estadoPago,
        $estadoReserva,
        $comprobantePago,
        $fechaFinal,
        $horaFinal,
        $clientId
    ];

    error_log("TIPOS=" . strlen($types));
    error_log("PARAMS=" . count($params));

    $stmt->bind_param(
        $types,
        $name,
        $description,
        $codigoReserva,
        $nombreTour,
        $tipoCliente,
        $fecha,
        $horaInicio,
        $turno,
        $hotelDireccion,
        $duracion,
        $nombrePrincipal,
        $pasajerosAdicionales,
        $pasaporteID,
        $countryCodewhatsapp,
        $whatsapp,
        $correo,
        $habitacion,
        $idioma,
        $pais,
        $tipoPago,
        $precioPorPersona,
        $precioTotal,
        $precioComisionable,
        $totalComision,
        $agente,
        $countryCodewaAgente,
        $waAgente,
        $observacion,
        $driver,
        $countryCodewaDriver,
        $waDriver,
        $guia,
        $countryCodewaGuia,
        $waGuia,
        $id_calendar,
        $id_map,
        $cantidadPasajero,
        $tipoServicio,
        $observacionGeneral,
        $status,
        $is_deleted,
        $FlagActivo,
        $estadoPago,
        $estadoReserva,
        $comprobantePago,
        $fechaFinal,
        $horaFinal,
        $clientId
    );




        $success = $stmt->execute();
        if (!$success) {
            $error_db = $stmt->error;
            $stmt->close();
        } else {
            $insertedId = $conn->insert_id;
            $stmt->close();

            // insert_id == 0 → INSERT IGNORE silenció un duplicado (UNIQUE codigoReserva o clientId).
            // Recuperamos el id real de la fila existente para devolverlo al cliente Android.
            if ($insertedId == 0 && !empty($codigoReserva)) {
                $esDuplicado = true;
                $stmtSel = $conn->prepare("SELECT id FROM items WHERE codigoReserva = ? and FlagActivo = 1 LIMIT 1");
                if ($stmtSel) {
                    $stmtSel->bind_param("s", $codigoReserva);
                    $stmtSel->execute();
                    $stmtSel->bind_result($existingId);
                    $stmtSel->fetch();
                    $stmtSel->close();
                    $insertedId = (int)$existingId;
                    error_log("create_item: duplicado detectado por UNIQUE(codigoReserva), id existente=$insertedId, codigo=$codigoReserva");
                }
            }
        }
    } else {
        $error_db = $conn->error;
    }

    // ─────────────────────────────────────────────────────────────────────
    // 5. GOOGLE CALENDAR
    //
    // SOLO se crea el evento si:
    //   a) El INSERT fue exitoso ($success == true)
    //   b) Era una fila NUEVA ($esDuplicado == false) → insert_id > 0
    //   c) El estadoReserva es 'COMPLETADO'
    //
    // Si era duplicado, el evento de Calendar ya fue creado en la primera
    // inserción exitosa. No se crea un segundo evento huérfano.
    // ─────────────────────────────────────────────────────────────────────
    

    if ($success && !$esDuplicado && $estadoReserva === 'COMPLETADO') {

        function parsearHorasDuracion(string $duracion): int {
            preg_match('/(\d+)\s*[Hh]ora/i', $duracion, $matches);
            return isset($matches[1]) ? (int)$matches[1] : 4;
        }

        // FIX: offset 0 explícito — nada de adición automática de horas en el servidor.
        $startDate = convertirAGoogleCalendar($fecha, $horaInicio, 0);
        $endDate   = convertirAGoogleCalendar($fechaFinal, $horaFinal, 0);

        // Esta validación YA EXISTÍA en tu código — se conserva sin cambios.
        // Es la que garantiza que crearEventoCalendar() nunca se ejecute con fechas inválidas/null.
        if ($startDate && $endDate) {
            $summary  = "$nombreTour X$cantidadPasajero ($tipoServicio) - 👤 $agente";
            $location = $hotelDireccion;

            $listaPasajeros = json_decode($pasajerosAdicionales, true);
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
                "<b>🆔 Código: $codigoReserva</b> <br>" .
                "<br><b>=======================</b><br>" .
                "<b>📅 DATOS DE RESERVA</b><br>" .
                "<b>🗺️ Servicio:</b> $nombreTour<br>" .
                "<b>🛠️ Tipo de servicio:</b> $tipoServicio<br>" .
                "<b>👥 Tipo de cliente:</b> $tipoCliente<br>" .
                "<b>🏢 Contacto o Agente:</b> $agente<br>" .
                "<b>📞 Teléfono contacto:</b> $waAgente<br>" .
                "<b>📆 Fecha:</b> $fecha<br>" .
                "<b>⏰ Hora de Inicio:</b> $horaInicio<br>" .
                "<b>🏁 Fecha final:</b> $fechaFinal<br>" .
                "<b>⌛ Hora de final:</b> $horaFinal<br>" .
                "<br><b>=======================</b><br>" .
                "<b>👤 DATOS DEL PASAJERO</b><br>" .
                "<b>🔢 Cantidad pasajeros:</b> $cantidadPasajero<br>" .
                "<b>🥇 Pasajero principal:</b> $nombrePrincipal<br>" .
                "<b>📱 WhatsApp:</b> <a href='https://wa.me/$whatsapp'>📲 $countryCodewhatsapp$whatsapp</a><br>" .
                "<b>🛂 pasaporteID:</b> $pasaporteID<br>" .
                "<b>📧 Correo:</b> $correo<br>" .
                "<b>🌎 País:</b> $pais<br>" .
                "<b>🗣️ Idioma:</b> $idioma<br>" .
                "<b>👫 Pasajeros adicionales:</b><br>$textoPasajeros<br>" .
                "<br><b>=======================</b><br>" .
                "<b>💰 PAGOS</b><br>" .
                "<b>💳 Tipo de pago:</b> $tipoPago<br>" .
                "<b>💵 Precio por persona:</b> $precioPorPersona<br>" .
                "<b>💎 Precio total:</b> $precioTotal<br>" .
                "<b>✅ Estado de pago:</b> $estadoPago<br>" .
                "<b>📄 Comprobante:</b> $comprobantePago<br>" .
                "<br><b>=======================</b><br>" .
                "<b>📝 OBSERVACIONES</b><br>" .
                "<b>🔒 Interna:</b> $observacion<br>" .
                "<b>📢 General:</b> $observacionGeneral<br>" .
                "<br><b>=======================</b><br>" .
                "<b>🚐 PERSONAL ASIGNADO</b><br>" .
                "<b>👨‍✈️ Driver:</b> $driver<br>" .
                "<b>📞 Teléfono driver:</b> $waDriver<br>" .
                "<b>🚩 Guía:</b> $guia<br>" .
                "<b>📞 Teléfono guía:</b> $waGuia<br>";

            $colorId = "5";
            if ($tipoServicio === "Privado") $colorId = "5";
            if ($tipoServicio === "Grupal")  $colorId = "10";
            if ($tipoServicio === "Anulado") $colorId = "11";

            $calendarId = crearEventoCalendar(
                $summary, $description_cal, $location, $startDate, $endDate, $colorId
            );

            if ($calendarId !== false) {
                $id_calendar = $calendarId;
                error_log("Google Calendar: evento creado con ID = $id_calendar para $codigoReserva");

                // Guardar el id_calendar en la fila recién insertada
                $stmtCal = $conn->prepare("UPDATE items SET id_calendar = ? WHERE id = ?");
                if ($stmtCal) {
                    $stmtCal->bind_param("si", $id_calendar, $insertedId);
                    $stmtCal->execute();
                    $stmtCal->close();
                }
            } else {
                error_log("Google Calendar: falló la creación del evento para $codigoReserva (id=$insertedId). La reserva fue guardada en MySQL.");
            }
        } else {
            // FIX: log ya no engañoso — muestra inicio Y fin, con su validez individual.
            error_log("Google Calendar: fecha/hora inválida — inicio: fecha=$fecha hora=$horaInicio (válida=" . ($startDate ? 'sí' : 'no') . ") | fin: fecha=$fechaFinal hora=$horaFinal (válida=" . ($endDate ? 'sí' : 'no') . ")");
        }
    } elseif ($esDuplicado) {
        error_log("create_item: reintento detectado para $codigoReserva — Calendar NO se crea de nuevo (id existente=$insertedId)");
    }




    // ─────────────────────────────────────────────────────────────────────
    // 6. RESPUESTA
    // ─────────────────────────────────────────────────────────────────────
    echo json_encode([
        "success"       => $success,
        "id"            => $insertedId,
        "id_calendar"   => $id_calendar,
        "codigoReserva" => $codigoReserva,
        "duplicado"     => $esDuplicado,
        "message"       => $success ? "OK" : $error_db
    ]);

    $conn->close();
?>
