<?php

//Clases como java, con constructores y demás


class Auth_controller
{
    private $authServicio;



    public function __construct()
    {

        $this->authServicio = new AuthServicio();

    }

    public function manejarPeticion($ruta)
    {
        $ruta = $_SERVER['REQUEST_URI'];
        $metodo = $_SERVER['REQUEST_METHOD'];

        if ($ruta === '/auth/login' && $metodo === 'POST') {
            //$this->login();
        } elseif ($ruta === '/auth/register' && $metodo === 'POST') {
            //$this->registrar();
        } else {
            http_response_code(404);
            echo json_encode(["error" => "Ruta no encontrada"]);
        }

    }
}

?>