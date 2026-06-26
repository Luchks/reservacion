<?php
    header('Content-Type: application/json');
    include 'db.php';
    require 'google_calendar_service.php';

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(["success" => false, "message" => "ID inválido"]);
        exit;
    }

    // ── PARCHE: ampliar SELECT para leer también FlagActivo ──────────────────
    $id_calendar = null;
    $flagActivo   = null;

    $stmtCal = $conn->prepare("SELECT id_calendar, FlagActivo FROM items WHERE id = ? LIMIT 1");
    $stmtCal->bind_param("i", $id);
    $stmtCal->execute();
    $stmtCal->bind_result($id_calendar_db, $flag_db);
    $rowFound = $stmtCal->fetch();
    if ($rowFound) {
        $id_calendar = $id_calendar_db;
        $flagActivo  = $flag_db;
    }
    $stmtCal->close();

    // ── PARCHE: idempotencia ─────────────────────────────────────────────────
    // Caso 1 — la fila no existe en MySQL (ya fue eliminada definitivamente)
    if (!$rowFound) {
        echo json_encode(["success" => true, "message" => "Ya eliminado"]);
        $conn->close();
        exit;
    }

    // Caso 2 — la fila existe pero ya está en papelera (FlagActivo = 0)
    if ($flagActivo == 0) {
        echo json_encode(["success" => true, "message" => "Ya en papelera"]);
        $conn->close();
        exit;
    }

    // ── Intentar borrar el evento de Calendar si existe ─────────────────────
    if (!empty($id_calendar)) {
        $okCal = eliminarEventoCalendar($id_calendar);
        if (!$okCal) {
            error_log("No se pudo eliminar Calendar (soft delete) id=$id event=$id_calendar");
            // ── PARCHE: body explícito en lugar de silencio ──────────────────
            echo json_encode([
                "success"        => false,
                "calendar_error" => true,
                "message"        => "No se pudo eliminar el evento de Calendar. Se reintentará."
            ]);
            $conn->close();
            exit;
        }
    }

    // ── Calendar OK (o no había evento): actualizar MySQL ───────────────────
    $stmt = $conn->prepare("UPDATE items SET id_calendar = '', FlagActivo = 0 WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Enviado a papelera"]);
    } else {
        echo json_encode(["success" => false, "message" => "Error", "error" => $stmt->error]);
    }

    $stmt->close();
    $conn->close();
?>
