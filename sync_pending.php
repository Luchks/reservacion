<?php
// ═══════════════════════════════════════════════════════════════════════════════
//  sync_pending.php  —  Endpoint de sincronización masiva (offline → MySQL)
//
//  Recibe un array JSON de operaciones pendientes desde Android y las ejecuta
//  en MySQL en orden. Devuelve un resultado por operación.
//
//  MÉTODO: POST
//  BODY (JSON):
//  {
//    "operations": [
//      {
//        "tipo":      "INSERT" | "UPDATE" | "DELETE" | "UPDATE_ESTADO_PAGO",
//        "roomId":    123,          ← ID local de Room (para que Android actualice)
//        "serverId":  0,            ← 0 si es INSERT nuevo, > 0 si ya existe en MySQL
//        "data":      { ... }       ← campos del item (igual que create_item.php)
//      }
//    ]
//  }
//
//  RESPONSE (JSON):
//  {
//    "success": true,
//    "results": [
//      { "roomId": 123, "success": true,  "serverId": 88, "tipo": "INSERT" },
//      { "roomId": 124, "success": true,  "serverId": 77, "tipo": "UPDATE" },
//      { "roomId": 125, "success": false, "error": "...", "tipo": "DELETE" }
//    ]
//  }
// ═══════════════════════════════════════════════════════════════════════════════

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');
include 'db.php';

// ─────────────────────────────────────────────────────────────────────────────
// LEER BODY
// ─────────────────────────────────────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['operations']) || !is_array($input['operations'])) {
    echo json_encode(["success" => false, "message" => "Body inválido o vacío"]);
    exit;
}

$operations = $input['operations'];
$results    = [];

// ─────────────────────────────────────────────────────────────────────────────
// PROCESAR CADA OPERACIÓN
// ─────────────────────────────────────────────────────────────────────────────
foreach ($operations as $op) {

    $tipo     = strtoupper($op['tipo']     ?? '');
    $roomId   = intval($op['roomId']       ?? 0);
    $serverId = intval($op['serverId']     ?? 0);
    $data     = $op['data']                ?? [];

    switch ($tipo) {

        // ─────────────────────────────────────────────────────────────────────
        // INSERT — nueva reserva creada offline
        // ─────────────────────────────────────────────────────────────────────
        case 'INSERT':
            $r = handleInsert($conn, $data);
            $results[] = [
                "roomId"   => $roomId,
                "tipo"     => $tipo,
                "success"  => $r['success'],
                "serverId" => $r['serverId'] ?? 0,
                "error"    => $r['error']    ?? null,
            ];
            break;

        // ─────────────────────────────────────────────────────────────────────
        // UPDATE — reserva editada offline
        // ─────────────────────────────────────────────────────────────────────
        case 'UPDATE':
            if ($serverId <= 0) {
                // Nunca llegó al servidor → tratar como INSERT
                $r = handleInsert($conn, $data);
                $results[] = [
                    "roomId"   => $roomId,
                    "tipo"     => "INSERT_FROM_UPDATE",
                    "success"  => $r['success'],
                    "serverId" => $r['serverId'] ?? 0,
                    "error"    => $r['error']    ?? null,
                ];
            } else {
                $r = handleUpdate($conn, $serverId, $data);
                $results[] = [
                    "roomId"   => $roomId,
                    "tipo"     => $tipo,
                    "success"  => $r['success'],
                    "serverId" => $serverId,
                    "error"    => $r['error'] ?? null,
                ];
            }
            break;

        // ─────────────────────────────────────────────────────────────────────
        // DELETE — soft delete hecho offline (envío a papelera)
        // ─────────────────────────────────────────────────────────────────────
        case 'DELETE':
            if ($serverId <= 0) {
                // Nunca existió en el servidor, solo borrar de Room
                $results[] = [
                    "roomId"   => $roomId,
                    "tipo"     => $tipo,
                    "success"  => true,
                    "serverId" => 0,
                    "note"     => "Solo existía en Room, no requiere acción en servidor",
                ];
                break;
            }
            $r = handleSoftDelete($conn, $serverId);
            $results[] = [
                "roomId"   => $roomId,
                "tipo"     => $tipo,
                "success"  => $r['success'],
                "serverId" => $serverId,
                "error"    => $r['error'] ?? null,
            ];
            break;

        // ─────────────────────────────────────────────────────────────────────
        // UPDATE_ESTADO_PAGO — cambio de estado de pago hecho offline
        // ─────────────────────────────────────────────────────────────────────
        case 'UPDATE_ESTADO_PAGO':
            if ($serverId <= 0) {
                $results[] = [
                    "roomId"  => $roomId,
                    "tipo"    => $tipo,
                    "success" => false,
                    "error"   => "serverId inválido para UPDATE_ESTADO_PAGO",
                ];
                break;
            }
            $estadoPago    = $data['estadoPago'] ?? '';
            $estadosValidos = ['PAGADO', 'PENDIENTE PAGO', 'CREDITO AGENCIA', 'SERVICIO GRATUITO'];
            if (!in_array($estadoPago, $estadosValidos)) {
                $results[] = [
                    "roomId"  => $roomId,
                    "tipo"    => $tipo,
                    "success" => false,
                    "error"   => "estadoPago inválido: $estadoPago",
                ];
                break;
            }
            $r = handleUpdateEstadoPago($conn, $serverId, $estadoPago);
            $results[] = [
                "roomId"   => $roomId,
                "tipo"     => $tipo,
                "success"  => $r['success'],
                "serverId" => $serverId,
                "error"    => $r['error'] ?? null,
            ];
            break;

        // ─────────────────────────────────────────────────────────────────────
        // TIPO DESCONOCIDO
        // ─────────────────────────────────────────────────────────────────────
        default:
            $results[] = [
                "roomId"  => $roomId,
                "tipo"    => $tipo,
                "success" => false,
                "error"   => "Tipo de operación desconocido: $tipo",
            ];
            break;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// RESPUESTA FINAL
// ─────────────────────────────────────────────────────────────────────────────
echo json_encode([
    "success" => true,
    "total"   => count($results),
    "results" => $results,
], JSON_UNESCAPED_UNICODE);

$conn->close();


// =============================================================================
// FUNCIONES AUXILIARES
// =============================================================================

/**
 * Inserta un nuevo item en MySQL.
 * Igual a create_item.php pero como función reutilizable.
 */
function handleInsert(mysqli $conn, array $data): array {
    $nombreTour          = $data['nombreTour']          ?? '';
    $name                = !empty($nombreTour) ? $nombreTour : ($data['name'] ?? 'Tour');
    $description         = $data['description']         ?? '';
    $codigoReserva       = $data['codigoReserva']       ?? '';
    $tipoCliente         = $data['tipoCliente']         ?? '';
    $fecha               = $data['fecha']               ?? date('Y-m-d');
    $horaInicio          = $data['horaInicio']          ?? '00:00 AM';
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
    $id_calendar         = $data['id_calendar']         ?? '';
    $id_map              = $data['id_map']              ?? '';
    $cantidadPasajero    = $data['cantidadPasajero']    ?? '1';
    $tipoServicio        = $data['tipoServicio']        ?? '';
    $observacionGeneral  = $data['observacionGeneral']  ?? '';
    $status              = $data['status']              ?? 'BORRADOR';
    $is_deleted          = 0;
    $FlagActivo          = 1;
    $estadoPago          = $data['estadoPago']          ?? 'PENDIENTE PAGO';
    $estadoReserva       = $data['estadoReserva']       ?? 'BORRADOR';
    $comprobantePago     = $data['comprobantePago']     ?? 'NO APLICA';

    // -------------------------------------------------------------------------
    // INSERT IDEMPOTENTE: INSERT IGNORE + SELECT posterior
    //
    // MOTIVO: sync_pending.php puede recibir el mismo batch más de una vez si
    // la respuesta HTTP se pierde antes de llegar al dispositivo Android (red
    // inestable, timeout, reinicio de la app). Sin IGNORE, el segundo envío del
    // batch produciría una fila duplicada en la tabla items.
    //
    // Se usa INSERT IGNORE porque:
    //   - El índice UNIQUE(codigoReserva) en MySQL rechaza el duplicado sin
    //     lanzar error (errno 1062 queda silenciado).
    //   - INSERT...ON DUPLICATE KEY UPDATE dispararía updated_at y corrompería
    //     el control de concurrencia optimista de update_item.php y similares.
    //
    // FLUJO:
    //   1. INSERT IGNORE → inserta si no existe; no hace nada si ya existe.
    //   2. insert_id > 0  → fila nueva  → devolver ese id como serverId.
    //   3. insert_id == 0 → duplicado detectado → SELECT id WHERE codigoReserva
    //      para recuperar el serverId real y que Android llame markSynced().
    // -------------------------------------------------------------------------
    $sql = "INSERT IGNORE INTO items (
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

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ["success" => false, "serverId" => 0, "error" => $conn->error];
    }

    $types = "ssssssssssssssssssssddddsssssssssssssssssiiss";
    $stmt->bind_param($types,
        $name, $description, $codigoReserva, $nombreTour, $tipoCliente,
        $fecha, $horaInicio, $turno, $hotelDireccion, $duracion,
        $nombrePrincipal, $pasajerosAdicionales, $pasaporteID, $countryCodewhatsapp, $whatsapp,
        $correo, $habitacion, $idioma, $pais, $tipoPago,
        $precioPorPersona, $precioTotal, $precioComisionable, $totalComision, $agente,
        $countryCodewaAgente, $waAgente, $observacion, $driver, $countryCodewaDriver,
        $waDriver, $guia, $countryCodewaGuia, $waGuia, $id_calendar,
        $id_map, $cantidadPasajero, $tipoServicio, $observacionGeneral, $status,
        $is_deleted, $FlagActivo, $estadoPago, $estadoReserva, $comprobantePago
    );

    $ok    = $stmt->execute();
    $newId = $conn->insert_id;
    $error = $stmt->error;
    $stmt->close();

    if (!$ok) {
        return ["success" => false, "serverId" => 0, "error" => $error];
    }

    // insert_id == 0 → INSERT IGNORE silenció un duplicado por UNIQUE(codigoReserva).
    // Recuperamos el id real para que Android pueda llamar markSynced(roomId, serverId).
    if ($newId == 0 && !empty($codigoReserva)) {
        $stmtSel = $conn->prepare("SELECT id FROM items WHERE codigoReserva = ? LIMIT 1");
        if ($stmtSel) {
            $stmtSel->bind_param("s", $codigoReserva);
            $stmtSel->execute();
            $stmtSel->bind_result($existingId);
            $stmtSel->fetch();
            $stmtSel->close();
            $newId = (int)$existingId;
            error_log("sync_pending handleInsert: duplicado detectado para codigoReserva=$codigoReserva, id existente=$newId");
        }
    }

    return ["success" => true, "serverId" => $newId, "error" => null];
}

/**
 * Actualiza un item existente en MySQL por su id (serverId).
 */
function handleUpdate(mysqli $conn, int $serverId, array $data): array {
    $name                = $data['name']                ?? '';
    $description         = $data['description']         ?? '';
    $codigoReserva       = $data['codigoReserva']       ?? '';
    $nombreTour          = $data['nombreTour']          ?? '';
    $tipoCliente         = $data['tipoCliente']         ?? '';
    $fecha               = $data['fecha']               ?? '';
    $horaInicio          = $data['horaInicio']          ?? '';
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
    $id_calendar         = $data['id_calendar']         ?? '';
    $cantidadPasajero    = $data['cantidadPasajero']    ?? '1';
    $tipoServicio        = $data['tipoServicio']        ?? '';
    $observacionGeneral  = $data['observacionGeneral']  ?? '';
    $estadoPago          = $data['estadoPago']          ?? '';
    $estadoReserva       = $data['estadoReserva']       ?? 'BORRADOR';
    $comprobantePago     = $data['comprobantePago']     ?? '';

    $sql = "UPDATE items SET
        name=?, description=?, codigoReserva=?, nombreTour=?, tipoCliente=?,
        fecha=?, horaInicio=?, turno=?, hotelDireccion=?, duracion=?,
        nombrePrincipal=?, pasajerosAdicionales=?, pasaporteID=?, countryCodewhatsapp=?, whatsapp=?,
        correo=?, habitacion=?, idioma=?, pais=?, tipoPago=?,
        precioPorPersona=?, precioTotal=?, precioComisionable=?, totalComision=?,
        agente=?, countryCodewaAgente=?, waAgente=?, observacion=?,
        driver=?, countryCodewaDriver=?, waDriver=?,
        guia=?, countryCodewaGuia=?, waGuia=?,
        id_calendar=?, cantidadPasajero=?, tipoServicio=?, observacionGeneral=?,
        estadoPago=?, estadoReserva=?, ComprobantePago=?
        WHERE id=?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ["success" => false, "error" => $conn->error];
    }

    $stmt->bind_param(
        "ssssssssssssssssssssddddsssssssssssssssssi",
        $name, $description, $codigoReserva, $nombreTour, $tipoCliente,
        $fecha, $horaInicio, $turno, $hotelDireccion, $duracion,
        $nombrePrincipal, $pasajerosAdicionales, $pasaporteID, $countryCodewhatsapp, $whatsapp,
        $correo, $habitacion, $idioma, $pais, $tipoPago,
        $precioPorPersona, $precioTotal, $precioComisionable, $totalComision,
        $agente, $countryCodewaAgente, $waAgente, $observacion,
        $driver, $countryCodewaDriver, $waDriver,
        $guia, $countryCodewaGuia, $waGuia,
        $id_calendar, $cantidadPasajero, $tipoServicio, $observacionGeneral,
        $estadoPago, $estadoReserva, $comprobantePago,
        $serverId
    );

    $ok    = $stmt->execute();
    $error = $stmt->error;
    $stmt->close();

    return ["success" => $ok, "error" => $ok ? null : $error];
}

/**
 * Soft delete: mueve a papelera seteando FlagActivo = 0.
 */
function handleSoftDelete(mysqli $conn, int $serverId): array {
    $stmt = $conn->prepare("UPDATE items SET FlagActivo = 0 WHERE id = ?");
    if (!$stmt) return ["success" => false, "error" => $conn->error];

    $stmt->bind_param("i", $serverId);
    $ok    = $stmt->execute();
    $error = $stmt->error;
    $stmt->close();

    return ["success" => $ok, "error" => $ok ? null : $error];
}

/**
 * Actualiza solo el estadoPago de un item.
 */
function handleUpdateEstadoPago(mysqli $conn, int $serverId, string $estadoPago): array {
    $stmt = $conn->prepare("UPDATE items SET estadoPago = ? WHERE id = ?");
    if (!$stmt) return ["success" => false, "error" => $conn->error];

    $stmt->bind_param("si", $estadoPago, $serverId);
    $ok    = $stmt->execute();
    $error = $stmt->error;
    $stmt->close();

    return ["success" => $ok, "error" => $ok ? null : $error];
}
