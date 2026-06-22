<?php
    header('Content-Type: application/json');
    include 'db.php';

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
      echo json_encode(["success" => false, "message" => "ID inválido"]);
      exit;
    }

    $stmt = $conn->prepare("UPDATE items SET FlagActivo = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
      echo json_encode(["success" => true, "message" => "Restaurado"]);
    } else {
      echo json_encode(["success" => false, "message" => "Error", "error" => $stmt->error]);
    }

    $stmt->close();
    $conn->close();
?>
