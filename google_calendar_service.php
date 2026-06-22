<?php
    // google_calendar_service.php

    require __DIR__ . '/vendor/autoload.php';

    use Google\Client;
    use Google\Service\Calendar;
    use Google\Service\Calendar\Event;
    use Google\Service\Calendar\EventDateTime;

    /**
     * Crea un evento en Google Calendar y devuelve el ID creado.
     *
     * @param string $summary     Título del evento.
     * @param string $description Descripción.
     * @param string $location    Ubicación.
     * @param string $startDate   Fecha/hora inicio: "2026-02-10T08:00:00-05:00"
     * @param string $endDate     Fecha/hora fin:    "2026-02-10T12:00:00-05:00"
     *
     * @return string|false Devuelve el ID del evento o false si hubo error.
     */
    function crearEventoCalendar($summary, $description, $location, $startDate, $endDate,$colorId)
    {
        $client = new Client();
        $client->setApplicationName('MiApp PHP Calendar');

        // JSON de tus credenciales
        $client->setAuthConfig(__DIR__ . '/credentials.json');

        $client->setScopes(Calendar::CALENDAR);

        $service = new Calendar($client);

        // Calendar ID (tu correo)
        $calendarId = 'limaviptravel2@gmail.com';

        // Fechas
        $start = new EventDateTime();
        $start->setDateTime($startDate);
        $start->setTimeZone('America/Lima');

        $end = new EventDateTime();
        $end->setDateTime($endDate);
        $end->setTimeZone('America/Lima');

        // Evento
        $event = new Event([
            'summary'     => $summary,
            'location'    => $location,
            'description' => $description,
            'start'       => $start,
            'end'         => $end,
            'colorId'     => $colorId, // ✅ Debe ser 'colorId', no 'idColor'
        ]);

        try {
            $createdEvent = $service->events->insert($calendarId, $event);
            return $createdEvent->getId();  // 🔥 Aquí devuelves el ID
        } catch (Exception $e) {
            error_log("Error creando evento: " . $e->getMessage());
            return false; // falló
        }
    }

    function actualizarEventoCalendar($eventId, $summary, $description, $location, $startDate, $endDate,$colorId)
    {
        $client = new Client();
        $client->setApplicationName('MiApp PHP Calendar');
        $client->setAuthConfig(__DIR__ . '/credentials.json');
        $client->setScopes(Calendar::CALENDAR);

        $service = new Calendar($client);

        // El mismo calendarId que usas en crearEventoCalendar
        $calendarId = 'limaviptravel2@gmail.com';

        try {
            // 1) Traer el evento actual
            $event = $service->events->get($calendarId, $eventId);

            // 2) Actualizar campos
            $event->setSummary($summary);
            $event->setLocation($location);
            $event->setDescription($description);
            $event->setColorId($colorId);

            $start = new EventDateTime();
            $start->setDateTime($startDate);
            $start->setTimeZone('America/Lima');

            $end = new EventDateTime();
            $end->setDateTime($endDate);
            $end->setTimeZone('America/Lima');

            $event->setStart($start);
            $event->setEnd($end);

            // 3) Guardar cambios
            $updated = $service->events->update($calendarId, $eventId, $event);

            return $updated->getId(); // normalmente es el mismo, pero por si acaso
        } catch (Exception $e) {
            error_log("Error actualizando evento: " . $e->getMessage());
            return false;
        }
    }

    function eliminarEventoCalendar($eventId)
    {
        $client = new Client();
        $client->setApplicationName('MiApp PHP Calendar');
        $client->setAuthConfig(__DIR__ . '/credentials.json');
        $client->setScopes(Calendar::CALENDAR);

        $service = new Calendar($client);

        // Mismo calendarId que usas en crear/actualizar
        $calendarId = 'limaviptravel2@gmail.com';

        try {
            $service->events->delete($calendarId, $eventId);
            return true;
        } catch (Exception $e) {
            error_log("Error eliminando evento: " . $e->getMessage());
            return false;
        }
    }
