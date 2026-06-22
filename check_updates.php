<?php
    // check_updates.php
    header('Content-Type: application/json');
    include 'db.php';


	$mes  = intval($_GET['mes']  ?? date('n'));
	$anio = intval($_GET['anio'] ?? date('Y'));

	$sql = "SELECT UNIX_TIMESTAMP(MAX(updated_at)) as ultima_mod 
		FROM items 
		WHERE FlagActivo = 1 
		  AND estadoReserva = 'COMPLETADO'
		  AND YEAR(fecha) = $anio
		  AND MONTH(fecha) = $mes";



    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    // La firma será el timestamp (ej: 1715432001)
    $signature = $row['ultima_mod'] ?? "0";

    echo json_encode([
        "success" => true,
        "signature" => $signature
    ]);

    $conn->close();
?>
