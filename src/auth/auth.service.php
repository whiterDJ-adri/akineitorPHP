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


        if ($this->conexionBaseDeDatos->verificarUsuario($usuario, $password)) {
            return true;
        }

        return false;
    }


    /*
    seleccionarPersonajesParaPregunta

    Flujo resumido:
        1. Validar entrada (personajes, personaje objetivo, cantidad >= 2).

        2. Excluir objetivo de candidatos y aplicar filtros opcionales.

        3. Mezclar candidatos y tomar (cantidad-1) distracciones; relajar filtros si faltan.
        4. Combinar objetivo + distracciones, mezclar y marcar 'esCorrecto'.

        5. Devolver array de opciones (id, nombre, imagen, esCorrecto) sin duplicados.

        En caso de datos insuficientes, devolver el máximo posible (o array vacío).
     */
    // 
    public function seleccionarPersonajesParaPregunta($respuesta, $pregunta)
    {
        // Evaluar la respuesta del usuario usando la conexión a la base de datos
        $resultadoTrueOrFalse = $this->conexionBaseDeDatos->evaluarRespuestaUsuario($pregunta, $respuesta);

        // Lógica para seleccionar personajes basados en la respuesta del usuario
        if ($resultadoTrueOrFalse === false) {

            return [];
        }

        // Si la evaluación es verdadera, filtrar personajes por rasgo y devolver el resultado
        return $this->conexionBaseDeDatos->filtrarPersonajesPorRasgo($pregunta);

    }


}

?>