<?php
require_once __DIR__ . '/auth.database.php';
class authServicio
{
    private $conexionBaseDeDatos;

    public function __construct()
    {
        // Aquí se podría inicializar la conexión a la base de datos
        // $this->conexionBaseDeDatos = new DatabaseConnection();

        $autenticacionBasedeDatos = new AuthDataBase();
        $this->conexionBaseDeDatos = $autenticacionBasedeDatos;
    }



    public function login($usuario, $password)
    {
        // Lógica para autenticar al usuario
        if (empty($usuario) || empty($password)) {
            return false;
        }

        if ($usuario === 'admin' && $password === 'password') {
            return true;
        }

        //no acabado
    }
}

?>