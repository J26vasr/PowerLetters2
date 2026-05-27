-- Reemplaza claves semilla por hash bcrypt de "Test1234" (password_verify() compatible).
USE powerletters;

UPDATE tb_usuarios   SET clave_usuario       = '$2y$10$dEwBIFLOjUvG//GduM6VVuSaTBYA95bF7ECvMODrv8yNWwfA8H4/i';
UPDATE administrador SET clave_administrador = '$2y$10$dEwBIFLOjUvG//GduM6VVuSaTBYA95bF7ECvMODrv8yNWwfA8H4/i';

UPDATE tb_usuarios SET estado_cliente = 1
 WHERE estado_cliente IS NULL OR estado_cliente = 0;
