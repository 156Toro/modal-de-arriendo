/**
 * Rental Manager - Admin Controller
 * @version 1.0.0
 */
$(document).ready(function() {
    // Confirmación para limpiar log
    $('.btn-danger').on('click', function(e) {
        if (!confirm('¿Está seguro que desea limpiar el archivo log? Esta acción no se puede deshacer.')) {
            e.preventDefault();
        }
    });

    // Tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Mostrar/ocultar secciones avanzadas
    $('.toggle-advanced').on('click', function() {
        $('.advanced-settings').toggleClass('hidden');
        $(this).find('i').toggleClass('fa-plus-square fa-minus-square');
    });

    // Validación del formulario
    $('form[name="rental_settings"]').on('submit', function(e) {
        var isValid = true;
        
        $(this).find('.required-field').each(function() {
            if ($(this).val() === '') {
                $(this).addClass('is-invalid');
                isValid = false;
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Por favor complete todos los campos requeridos');
        }
    });
});