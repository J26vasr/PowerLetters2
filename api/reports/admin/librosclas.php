<?php
// Se incluye la clase con las plantillas para generar reportes.
require_once('../../helpers/report.php');

// Se instancia la clase para crear el reporte.
$pdf = new Report;
// Se verifica si existe un valor para la categoría, de lo contrario se muestra un mensaje.
if (isset($_GET['idClas'])) {
    // Se incluyen las clases para la transferencia y acceso a datos.
    require_once('../../models/data/clasificacion_data.php');
    require_once('../../models/data/libros_data.php');
    // Se instancian las entidades correspondientes.
    $clasificacion = new ClasificacionData;
    $libros = new LibroData;
    // Se establece el valor de la categoría, de lo contrario se muestra un mensaje.
    if ($clasificacion->setId($_GET['idClas']) && $libros->setClasificación($_GET['idClas'])) {
        // Se verifica si la categoría existe, de lo contrario se muestra un mensaje.
        if ($rowClasi = $clasificacion->readOne()) {
            // Se inicia el reporte con el encabezado del documento.
            $pdf->startReport('Libros con clasificación : ' . $rowClasi['nombre']);
            // Se verifica si existen registros para mostrar, de lo contrario se imprime un mensaje.
            if ($dataLibrosC = $libros->librosClasificacion()) {
                // Se establece un color de relleno para los encabezados.
                $pdf->setFillColor(0, 21, 26);
                // Se establece la fuente para los encabezados.
                $pdf->setFont('Times', 'B', 15);
                $pdf->SetTextColor(237, 237, 237);
                // Se imprimen las celdas con los encabezados.
                $pdf->cell(120, 10, 'Titulo', 1, 0, 'C', 1);
                $pdf->cell(40, 10, 'Precio (US$)', 1, 0, 'C', 1);
                $pdf->cell(30, 10, 'Existencias', 1, 1, 'C', 1);
                // Se establece la fuente para los datos de los productos.
                $pdf->setFont('Times', '', 11);
                // Se recorren los registros fila por fila.
                foreach ($dataLibrosC as $rowLibros) {
                    // ($rowProducto['estado_producto']) ? $estado = 'Activo' : $estado = 'Inactivo';
                    // Se imprimen las celdas con los datos de los productos.
                    $pdf->setFont('Times', '', 15);
                    $pdf->cell(120, 10, $pdf->encodeString($rowLibros['titulo']), 1, 0);
                    $pdf->cell(40, 10, $rowLibros['precio'], 1, 0);
                    $pdf->cell(30, 10, $rowLibros['existencias'], 1, 1);
                }
            } else {
                $pdf->cell(0, 10, $pdf->encodeString('No hay productos para la marca'), 1, 1);
            }
            // Se llama implícitamente al método footer() y se envía el documento al navegador web.
            $pdf->output('I', 'marca.pdf');
        } else {
            print('Marca inexistente');
        }
    } else {
        print('Marca incorrecta');
    }
} else {
    print('Debe seleccionar una marca');
}
