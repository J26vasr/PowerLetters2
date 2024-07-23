<?php
// Se incluye la clase para trabajar con la base de datos.
require_once('../../helpers/database.php');
/*
 *	Clase para manejar el comportamiento de los datos de la tabla usuario.
 */
class UsuarioHandler
{
    /*
     *   Declaración de atributos para el manejo de datos.
     */
    protected $id = null;
    protected $nombre = null;
    protected $apellido = null;
    protected $correo = null;
    protected $telefono = null;
    protected $dui = null;
    protected $nacimiento = null;
    protected $direccion = null;
    protected $clave = null;
    protected $estado = null;
    protected $imagen = null;



    const RUTA_IMAGEN = '../../images/usuarios/';

    /*
     *   Métodos para gestionar la cuenta del usuario.
     */
    public function checkUser($mail, $password)
    {
        $sql = 'SELECT id_usuario, correo_usuario, clave_usuario, estado_cliente, imagen
                FROM tb_usuarios
                WHERE correo_usuario = ?';
        $params = array($mail);
        $data = Database::getRow($sql, $params);
        if (password_verify($password, $data['clave_usuario'])) {
            $this->id = $data['id_usuario'];
            $this->correo = $data['correo_usuario'];
            $this->estado = $data['estado_cliente'];
            $this->imagen = $data['imagen'];
            return true;
        } else {
            return false;
        }
    }

    public function checkPassword($password)
    {
        $sql = 'SELECT clave_usuario
                FROM tb_usuarios
                WHERE id_usuario = ?';
        $params = array($_SESSION['idUsuario']);
        $data = Database::getRow($sql, $params);
        // Se verifica si la contraseña coincide con el hash almacenado en la base de datos.
        if (password_verify($password, $data['clave_usuario'])) {
            return true;
        } else {
            return false;
        }
    }

    public function checkStatus()
    {
        if ($this->estado) {
            $_SESSION['idUsuario'] = $this->id;
            $_SESSION['correoUsuario'] = $this->correo;
            $_SESSION['imagen'] = $this->imagen;
            return true;
        } else {
            return false;
        }
    }
    /*
     *   Métodos para cambiar la contraseña
     */
    public function changePassword()
    {
        $sql = 'UPDATE tb_usuarios
                SET clave_usuario = ?
                WHERE id_usuario = ?';
        $params = array($this->clave, $_SESSION['idUsuario']);
        return Database::executeRow($sql, $params);
    }
    /*
     *   Métodos para leer el perfil
     */
    public function readProfile()
    {
        $sql = 'SELECT id_usuario, nombre_usuario, apellido_usuario, correo_usuario, dui_usuario, telefono_usuario, nacimiento_usuario, direccion_usuario, estado_cliente, imagen
                FROM tb_usuarios
                WHERE id_usuario = ?';
        $params = array($_SESSION['idUsuario']);
        return Database::getRow($sql, $params);
    }

    /*
     *   Métodos para editar el perfil
     */
    public function editProfile()
    {
        $sql = 'UPDATE tb_usuarios
                SET nombre_usuario = ?, apellido_usuario = ?, correo_usuario = ?, dui_usuario = ?, telefono_usuario = ?, nacimiento_usuario = ?, direccion_usuario = ?
                WHERE id_usuario = ?';
        $params = array($this->nombre, $this->apellido, $this->correo, $this->dui, $this->telefono, $this->nacimiento, $this->direccion, $_SESSION['idUsuario']);
        return Database::executeRow($sql, $params);
    }

    /*
     *   Métodos para cambiar el estado
     */

    public function changeStatus()
    {
        $sql = 'UPDATE tb_usuarios
                SET estado_cliente = ?
                WHERE id_usuario = ?';
        $params = array($this->estado, $this->id);
        return Database::executeRow($sql, $params);
    }

    /*
     *   Métodos para realizar las operaciones SCRUD (search, create, read, update, and delete).
     */
    public function searchRows()
    {
        $value = '%' . Validator::getSearchValue() . '%';
        $sql = 'SELECT id_usuario, nombre_usuario, apellido_usuario, correo_usuario, dui_usuario, telefono_usuario, nacimiento_usuario, direccion_usuario
                FROM tb_usuarios
                WHERE apellido_usuario LIKE ? OR nombre_usuario LIKE ? OR correo_usuario LIKE ?
                ORDER BY apellido_usuario';
        $params = array($value, $value, $value);
        return Database::getRows($sql, $params);
    }

    /*
     *   Métodos para crear usuarios
     */
    public function createRow()
    {
        $sql = 'INSERT INTO tb_usuarios(nombre_usuario, apellido_usuario, correo_usuario, dui_usuario, telefono_usuario, nacimiento_usuario, direccion_usuario, clave_usuario, imagen)
                VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $params = array($this->nombre, $this->apellido, $this->correo, $this->dui, $this->telefono, $this->nacimiento, $this->direccion, $this->clave, $this->imagen);
        return Database::executeRow($sql, $params);
    }


    /*
     *   Métodos para leer todo
     */
    public function readAll()
    {
        $sql = 'SELECT id_usuario, nombre_usuario, apellido_usuario, correo_usuario, dui_usuario, estado_cliente, imagen,nacimiento_usuario,telefono_usuario,direccion_usuario
                FROM tb_usuarios
                ORDER BY apellido_usuario';
        return Database::getRows($sql);
    }

    /*
     *   Métodos para leer solo uno
     */
    public function readOne()
    {
        $sql = 'SELECT id_usuario, nombre_usuario, apellido_usuario, correo_usuario, dui_usuario, telefono_usuario, nacimiento_usuario, direccion_usuario, estado_cliente, imagen
                FROM tb_usuarios
                WHERE id_usuario = ?';
        $params = array($this->id);
        return Database::getRow($sql, $params);
    }

    /*
     *   Métodos para actualizar
     */
    public function updateRow()
    {
        $sql = 'UPDATE tb_usuarios
                SET nombre_usuario = ?, apellido_usuario = ?, dui_usuario = ?, estado_cliente = ?, telefono_usuario = ?, nacimiento_usuario = ?, direccion_usuario = ?, imagen = ?
                WHERE id_usuario = ?';
        $params = array($this->nombre, $this->apellido, $this->dui, $this->estado, $this->telefono, $this->nacimiento, $this->direccion, $this->id, $this->imagen);
        return Database::executeRow($sql, $params);
    }

    /*
     *   Métodos para eliminar
     */
    public function deleteRow()
    {
        $sql = 'DELETE FROM tb_usuarios
                WHERE id_usuario = ?';
        $params = array($this->id);
        return Database::executeRow($sql, $params);
    }

    public function checkDuplicate($value)
    {
        $sql = 'SELECT id_usuario
                FROM tb_usuarios
                WHERE dui_usuario = ? OR correo_usuario = ?';
        $params = array($value, $value);
        return Database::getRow($sql, $params);
    }

    public function readFilename()
    {
        $sql = 'SELECT imagen FROM tb_usuarios WHERE id_usuario = ?';
        $params = array($this->id);
        return Database::getRow($sql, $params);
    }

    public function clienteMasCompras()
    {
        $sql = 'SELECT c.nombre_usuario, SUM(p.id_pedido) AS totalCompras
        FROM tb_usuarios c
        INNER JOIN tb_pedidos p ON c.id_usuario = p.id_usuario
        GROUP BY c.nombre_usuario
        ORDER BY totalCompras DESC
        LIMIT 5';
        return Database::getRows($sql);
    }

    // Función para obtener la cantidad de usuarios conectados y desconectados
    public function GraficaUsuariosEstados()
    {
        $sql = 'SELECT
    CASE
        WHEN estado_cliente = 1 THEN "Activos"
        ELSE "Inactivos"
    END AS estado,
    COUNT(*) AS cantidad
FROM tb_usuarios
GROUP BY estado_cliente;';
        return Database::getRows($sql);
    }

    public function reporteClientesA()
    {
        $sql = 'SELECT nombre_usuario, correo_usuario,
                CASE
                    WHEN estado_cliente = 1 THEN "Activos"
                    ELSE "Inactivos"
                END AS estado
                FROM tb_usuarios';
    
        return Database::getRows($sql);
    }

}

