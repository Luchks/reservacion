<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include 'db.php';

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

$stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param('s', $username);
$stmt->execute();

// ✅ Reemplazo: en lugar de get_result()
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($id, $user_name, $hashed_password);
    $stmt->fetch();

    if (password_verify($password, $hashed_password)) {
        echo json_encode(["success" => true, "message" => "Login exitoso", "userId" => (int)$id]);
    } else {
        echo json_encode(["success" => false, "message" => "Contraseña incorrecta"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Usuario no existe"]);
}

$stmt->close();
$conn->close();
?>
