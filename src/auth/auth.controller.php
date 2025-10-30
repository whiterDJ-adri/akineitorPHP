<?php

//Clases como java, con constructores y demás


class Auth_controller
{
    private $authServicio;



    public function __construct()
    {

        $this->authServicio = new AuthServicio();

    }

    public function handleRequest($ruta)
    {
        // Aquí se manejarán las diferentes rutas de autenticación
        if ($ruta === '/auth/login') {
            //$this->login();
        } elseif ($ruta === '/auth/logout') {
            // $this->logout();
        } elseif ($ruta === '/auth/register') {
            //$this->register();
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Ruta de autenticación no encontrada']);
        }
    }
}

?>