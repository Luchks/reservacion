<?php
    header('Content-Type: application/json');
    include 'db.php';

    function nstr($v) { return $v === null ? "" : (string)$v; }
    function nint($v) { return $v === null ? 0 : (int)$v; }

    function normalizeEstadoPago($v) {
        $s = strtoupper(trim((string)$v));
        if ($s === "" || $s === "0") return "PENDIENTE PAGO";

        $valid = ["PAGADO","PENDIENTE PAGO","CREDITO AGENCIA","SERVICIO GRATUITO"];
        return in_array($s, $valid, true) ? $s : "PENDIENTE PAGO";
    }

    $result = $conn->query("SELECT * FROM items WHERE FlagActivo = 0 ORDER BY fecha ASC");

    $items = [];

    while ($row = $result->fetch_assoc()) {

        // pasajeros siempre array
        $decoded = json_decode($row['pasajerosAdicionales'] ?? '[]', true);
        $row['pasajerosAdicionales'] = is_array($decoded) ? $decoded : [];

        // ints
        $row['id']                 = nint($row['id']);
        $row['precioPorPersona']   = nint($row['precioPorPersona']);
        $row['precioTotal']        = nint($row['precioTotal']);
        $row['precioComisionable'] = nint($row['precioComisionable']);
        $row['totalComision']      = nint($row['totalComision']);
        $row['FlagActivo']         = nint($row['FlagActivo']);
        $row['is_deleted']         = nint($row['is_deleted']);   // 🔥 clave


        $stringFields = [
            'name','description','codigoReserva','nombreTour','tipoCliente',
            'fecha','horaInicio','turno','hotelDireccion','duracion',
            'nombrePrincipal','pasaporteID','countryCodewhatsapp','whatsapp',
            'correo','habitacion','idioma','pais','tipoPago','agente',
            'countryCodewaAgente','waAgente','observacion','driver',
            'countryCodewaDriver','waDriver','guia','countryCodewaGuia',
            'waGuia','id_calendar','id_map','cantidadPasajero','tipoServicio',
            'observacionGeneral','status','estadoReserva','comprobantePago','fechaFinal','horaFinal'  // ← agregado
        ];
     

        foreach ($stringFields as $f) {
            if (array_key_exists($f, $row)) $row[$f] = nstr($row[$f]);
        }

        // 🔥 normalizar estadoPago SIEMPRE a valores válidos
        $row['estadoPago'] = normalizeEstadoPago($row['estadoPago'] ?? "");

        $row['comprobantePago'] = nstr($row['comprobantePago'] ?? "NO APLICA");

        $items[] = $row;
    }

    echo json_encode($items, JSON_UNESCAPED_UNICODE);
    $conn->close();
