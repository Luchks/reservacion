<?php
    header('Content-Type: application/json');
    include 'db.php';
    require 'google_calendar_service.php';

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
      echo json_encode(["success" => false, "message" => "ID inválido"]);
      exit;
    }

    $id_calendar = null;
    $stmtCal = $conn->prepare("SELECT id_calendar FROM items WHERE id = ?");
    $stmtCal->bind_param("i", $id);
    $stmtCal->execute();
    $stmtCal->bind_result($id_calendar_db);
    if ($stmtCal->fetch()) $id_calendar = $id_calendar_db;
    $stmtCal->close();

    $delete=true;
    // borrar calendar si existe
    if (!empty($id_calendar)) {
      $okCal = eliminarEventoCalendar($id_calendar);
      $delete=$okCal;
      if (!$okCal) error_log("No se pudo eliminar Calendar (hard delete) id=$id event=$id_calendar");
    }

    if($delete){

	    $stmt = $conn->prepare("UPDATE items SET id_calendar='',FlagActivo = 0 WHERE id = ?");
	    $stmt->bind_param("i", $id);

	    if ($stmt->execute()) {
	      echo json_encode(["success" => true, "message" => "Enviado a papelera"]);
	    } else {
	      echo json_encode(["success" => false, "message" => "Error", "error" => $stmt->error]);
	    }

	    $stmt->close();
	    $conn->close();
	}
?>
