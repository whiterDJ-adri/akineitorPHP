<?php

//llamamos controller para manejar autenticacion
require_once __DIR__ . '/auth/auth.controller.php';

//pasamos el tipo de ruta a una variable
$ruta = $_SERVER['REQUEST_URI'];

if (str_starts_with($ruta, '/auth')) {

    //si la ruta empieza con /auth llamamos al controlador de autenticacion
    // Creamos una instancia del controlador de autenticación
    //$authController = new AuthController();

    // Delegamos el manejo de la petición (ruta) al controlador de auth.
    // El controlador analizará $ruta y ejecutará la acción correspondiente (login, logout, registro, etc.).
    $authController->handleRequest($ruta);
} else {
    //si no, mostramos un mensaje de ruta no encontrada
    http_response_code(404);
    echo json_encode(['error' => 'Ruta no encontrada']);
}












?>