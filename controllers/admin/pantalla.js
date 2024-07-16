// Constante para completar la ruta de la API.
const LIBRO_API =  'services/admin/libros.php';

// Método del evento para cuando el documento ha cargado.
document.addEventListener('DOMContentLoaded', () => {
   
    graficoPastelEditoriales();
});

/*
*   Función asíncrona para mostrar un gráfico de pastel con el porcentaje de productos por categoría.
*   Parámetros: ninguno.
*   Retorno: ninguno.
*/
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
        pieGraph('chart2', editoriales, porcentajes, 'Porcentaje de productos por categoría');
    } else {
        document.getElementById('chart2').remove();
        console.log(DATA.error);
    }
}
