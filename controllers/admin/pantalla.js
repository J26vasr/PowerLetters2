// Constante para completar la ruta de la API.
const LIBRO_API =  'services/admin/libros.php';
// Se establece la ruta de la API para interactuar con los usuarios.
const USUARIO_API = 'services/public/usuario.php';

// Método del evento para cuando el documento ha cargado.
document.addEventListener('DOMContentLoaded', () => {
   
    graficoPastelEditoriales();
    

    GraficaUsuariosEstados();
});

/*
*   Función asíncrona para mostrar un gráfico de pastel con el porcentaje de productos por categoría.
*   Parámetros: ninguno.
*   Retorno: ninguno.
*/

/*const graficoBarrasCategorias = async () => {
    // Petición para obtener los datos del gráfico.
    const DATA = await fetchData(LIBRO_API, 'cantidadProductosEditorial');
    // Se comprueba si la respuesta es satisfactoria, de lo contrario se remueve la etiqueta canvas.
    if (DATA.status) {
        // Se declaran los arreglos para guardar los datos a graficar.
        let editoriales = [];
        let cantidades = [];
        // Se recorre el conjunto de registros fila por fila a través del objeto row.
        DATA.dataset.forEach(row => {
            // Se agregan los datos a los arreglos.
            editoriales.push(row.nombre);
            cantidades.push(row.cantidad);
        });
        // Llamada a la función para generar y mostrar un gráfico de barras. Se encuentra en el archivo components.js
        barGraph('chart1', editoriales, cantidades);
    } else {
        document.getElementById('chart1').remove();
        console.log(DATA.error)
        
        ;
    }
}
*/const GraficaUsuariosEstados = async () => {
    // Petición para obtener los datos del gráfico.
    const DATA = await fetchData(USUARIO_API, 'GraficaUsuariosEstados');
    // Se comprueba si la respuesta es satisfactoria, de lo contrario se remueve la etiqueta canvas.
    if (DATA.status) {
        // Se declaran los arreglos para guardar los datos a graficar.
        let estado = [];
        let cantidad = [];
        // Se recorre el conjunto de registros fila por fila a través del objeto row.
        DATA.dataset.forEach(row => {
            // Se agregan los datos a los arreglos.
            estado.push(row.estado);
            cantidad.push(row.cantidad);
        });
        // Llamada a la función para generar y mostrar un gráfico de líneas. Se encuentra en el archivo components.js
        barGraph('chart10', estado, cantidad, 'Cantidad usuarios ', '');
    } else {
        document.getElementById('chart10').remove();
        console.log(DATA.error);
    }
}

const graficoPastelEditoriales = async () => {
    // Petición para obtener los datos del gráfico.
    const DATA = await fetchData(LIBRO_API, 'porcentajeProductosEditorial');
    // Se comprueba si la respuesta es satisfactoria, de lo contrario se remueve la etiqueta canvas.
    if (DATA.status) {
        // Se declaran los arreglos para guardar los datos a gráficar.
        let editoriales = [];
        let porcentajes = [];
        // Se recorre el conjunto de registros fila por fila a través del objeto row.
        DATA.dataset.forEach(row => {
            // Se agregan los datos a los arreglos.
            editoriales.push(row.nombre);
            porcentajes.push(row.porcentaje);
        });
        // Llamada a la función para generar y mostrar un gráfico de pastel. Se encuentra en el archivo components.js
        pieGraph('chart2', editoriales, porcentajes);
    } else {
        document.getElementById('chart2').remove();
        console.log(DATA.error);
    }
}
    
