var modal = document.getElementById("myModal");
var modal_ = document.getElementById("myModalView");
var MODAL_TITLE = document.getElementById("modalTitle");
var btn = document.querySelector(".add-button");

// Ocultar los modales al cargar la página (solo si existen en esta pagina).
if (modal) modal.style.display = "none";
if (modal_) modal_.style.display = "none";

// Abrir el modal al hacer click en el botón de añadir
function  AbrirModal() {
    if (modal) modal.style.display = "block";
};

function  AbrirModalVista() {
    if (modal_) modal_.style.display = "block";
};



// Cerrar el modal de añadir al hacer click en el botón de cierre
function closeModal() {
    if (modal) modal.style.display = "none";
}
function closeModalDetalles() {
    if (modal_) modal_.style.display = "none";
}




