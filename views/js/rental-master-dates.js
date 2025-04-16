$(document).ready(function() { 
  // Inicializar Flatpickr en inputs
  if (typeof flatpickr !== 'undefined') {
    flatpickr("#masterStartDate", {
      dateFormat: "Y-m-d",
      minDate: "today",
      locale: "es"
    });
    flatpickr("#masterEndDate", {
      dateFormat: "Y-m-d",
      minDate: "today",
      locale: "es"
    });
  }

  // Mostrar automáticamente el modal si no hay fechas guardadas solo si estamos en el catálogo
  const start = localStorage.getItem('rentalMasterStartDate');
  const end = localStorage.getItem('rentalMasterEndDate');
  
  // Verificar si estamos en una página de catálogo o producto
  const isCatalogPage = window.location.href.includes('category') || window.location.href.includes('product');
  
  if (!start || !end) {
    // Mostrar el modal de fechas maestras solo si estamos en el catálogo
    if (isCatalogPage) {
      $('#rentalMasterDatesModal').modal({
        backdrop: 'static', // No se puede cerrar haciendo click afuera
        keyboard: false     // No se puede cerrar con ESC
      });

      $('body').addClass('rental-blur');
    }
  }

  // Botón flotante para abrir el modal de fechas manualmente en todas las páginas
  $('#openRentalMasterDatesModal').click(function() {
    $('#rentalMasterDatesModal').modal('show');
    $('body').addClass('rental-blur'); // Desenfocar fondo
  });

  // Guardar fechas en LocalStorage
  $('#saveRentalMasterDates').click(function() {
    const startDate = $('#masterStartDate').val();
    const endDate = $('#masterEndDate').val();
    
    if (!startDate || !endDate) {
      alert('Por favor, completa ambas fechas.');
      return;
    }
    if (endDate <= startDate) {
      alert('La fecha de término debe ser posterior a la fecha de inicio.');
      return;
    }

    localStorage.setItem('rentalMasterStartDate', startDate);
    localStorage.setItem('rentalMasterEndDate', endDate);

    $('#rentalMasterDatesModal').modal('hide');
    $('body').removeClass('rental-blur');
    alert('Fechas guardadas correctamente.');
  });
  
  // Eliminar desenfoque cuando el modal se cierre
  $('#rentalMasterDatesModal').on('hidden.bs.modal', function () {
    $('body').removeClass('rental-blur'); // Elimina el desenfoque
  });
  
  // Antes de permitir agregar un producto de arriendo, verificar fechas
  $(document).on('click', '.rental-btn', function (e) {
    e.preventDefault();
    e.stopPropagation();
    
    const start = localStorage.getItem('rentalMasterStartDate');
    const end = localStorage.getItem('rentalMasterEndDate');
    
    if (!start || !end) {
      alert('Debes seleccionar primero las fechas de tu evento.');
      $('#openRentalMasterDatesModal').click(); // Abrir modal automáticamente
      return;
    }

    const $btn = $(this);
    const productData = getProductData($btn);
    if (!productData) {
      showAlert('error', 'Error: Datos del producto incompletos');
      return;
    }
    setTimeout(() => {
      if (!initModal(productData)) {
        showAlert('error', 'Error al cargar el formulario');
      }
    }, 100);
  });
});
