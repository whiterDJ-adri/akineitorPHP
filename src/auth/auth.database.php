<?php
require_once __DIR__ . 'models/MySQLConnection.php';
class AuthDataBase
{


    public function __construct()
    {
        // Aquí se podría inicializar la conexión a la base de datos
    }


    public function verificarUsuario($nombreUsuario, $contrasenyaIngresada)
    {
        // Crear conexión a la base de datos
        $conexionBD = new MySQLConnection();
        $conexionMySQL = $conexionBD->getConnection();

        if (!$conexionMySQL) {
            throw new Exception("No hay conexión a la base de datos.");
        }

        // Consulta SQL para buscar al usuario
        $consultaSQL = "SELECT * FROM usuarios WHERE usuario = ? AND password = ?";

        // Preparamos la consulta
        $sentenciaPreparada = $conexionMySQL->prepare($consultaSQL);

        // Asociamos los valores (ambos strings: "ss")
        $sentenciaPreparada->bind_param("ss", $nombreUsuario, $contrasenyaIngresada);

        // Ejecutamos la consulta
        $sentenciaPreparada->execute();

        // Obtenemos el resultado de la ejecución
        $resultadoConsulta = $sentenciaPreparada->get_result();

        // Si hay una fila, el usuario existe
        if ($resultadoConsulta->num_rows > 0) {
            return true; // Usuario encontrado
        } else {
            return false; // Usuario no encontrado
        }
    }


    // Evalúa la respuesta del usuario a una pregunta ç
    // específica sobre un personaje

    // @param string $pregunta La pregunta realizada al usuario
    // @param string $respuestaUsuario La respuesta del usuario
    // @return bool true si la respuesta es afirmativa, false si es negativa

    public function evaluarRespuestaUsuario($pregunta, $respuestaUsuario)
    {
        // Normalizamos la respuesta del usuario (convertir a minúsculas y quitar espacios)
        $respuestaNormalizada = strtolower(trim($respuestaUsuario));

        // Respuestas afirmativas
        $respuestasAfirmativas = ['si', 'sí', 's', 'yes', 'y', '1', 'true', 'verdadero', 'cierto'];

        // Respuestas negativas
        $respuestasNegativas = ['no', 'n', '0', 'false', 'falso', 'incorrecto'];

        // Verificar si es una respuesta afirmativa
        if (in_array($respuestaNormalizada, $respuestasAfirmativas)) {
            return true;
        }

        // Verificar si es una respuesta negativa
        if (in_array($respuestaNormalizada, $respuestasNegativas)) {
            return false;
        }

        // Si no es una respuesta reconocida, podríamos lanzar una excepción o asumir false
        throw new Exception("Respuesta no válida. Por favor responde con 'sí' o 'no'.");
    }






    //Select rasgos de personajes

    public function obtenerRasgosPersonajes()
    {
        // Crear conexión a la base de datos
        $conexionBD = new MySQLConnection();
        $conexionMySQL = $conexionBD->getConnection();

        if (!$conexionMySQL) {
            throw new Exception("No hay conexión a la base de datos.");
        }

        // Consulta SQL para obtener los rasgos de personajes
        $consultaSQL = "SELECT * FROM rasgos";

        // Ejecutamos la consulta
        $resultadoConsulta = $conexionMySQL->query($consultaSQL);

        $rasgos = [];
        while ($fila = $resultadoConsulta->fetch_assoc()) {
            $rasgos[] = $fila;
        }
        return $rasgos;
    }

    //Select para filtrar personajes según rasgos
    public function filtrarPersonajesPorRasgo($rasgoId)
    {
        // Crear conexión a la base de datos
        $conexionBD = new MySQLConnection();
        $conexionMySQL = $conexionBD->getConnection();

        if (!$conexionMySQL) {
            throw new Exception("No hay conexión a la base de datos.");
        }

        // Consulta SQL para filtrar personajes según rasgo
        $consultaSQL = "SELECT * FROM personajes_rasgo WHERE $rasgoId = ? AND valor = 1";

        // Preparamos la consulta
        $sentenciaPreparada = $conexionMySQL->prepare($consultaSQL);

        // Asociamos el valor del rasgo (asumiendo que es un entero: "i")
        $sentenciaPreparada->bind_param("i", $rasgoId);

        if (!$sentenciaPreparada) {
            throw new Exception("Error al preparar la consulta: " . $conexionMySQL->error);
        }



        if (!$sentenciaPreparada->bind_param("i", $rasgoId)) {
            throw new Exception("Error al enlazar los parámetros: " . $sentenciaPreparada->error);
        }


        // Ejecutamos la consulta
        $sentenciaPreparada->execute();

        // Obtenemos el resultado de la ejecución
        $resultadoConsulta = $sentenciaPreparada->get_result();
        $personajesFiltrados = [];
        while ($fila = $resultadoConsulta->fetch_assoc()) {
            $personajesFiltrados[] = $fila;

        }
        return $personajesFiltrados;
    }

}













?>