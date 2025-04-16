/**
 * Rental Manager - Frontend Controller
 * @version 1.0.4
 * @description Manejo completo de reservas con robusto sistema de errores
 */
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  // 1. Configuración inicial con validación robusta
  const config = {
    debug: typeof rentalDebug !== 'undefined' ? rentalDebug : true,
    moduleUrl: typeof rentalModuleUrl !== 'undefined' ? rentalModuleUrl : typeof prestashop !== 'undefined' ? prestashop.urls.base_url + 'module/rentalmanager/rental' : '',
    staticToken: typeof prestashop !== 'undefined' && prestashop.static_token ? prestashop.static_token : '',
    cartId: typeof prestashop !== 'undefined' && prestashop.cart && prestashop.cart.id ? prestashop.cart.id : null
  };

  // 2. Sistema de logging mejorado
  function debugLog(message, data = {}, force = false) {
    if (!config.debug && !force) return;
    const logEntry = {
      timestamp: new Date().toISOString(),
      message: message,
      data: data,
      cartId: config.cartId
    };
    console.groupCollapsed(`[RentalManager] ${message}`);
    console.table(logEntry);
    console.groupEnd();

    // Solo enviar logs críticos al servidor
    if (force || message.includes('ERROR')) {
      safeAjax({
        url: config.moduleUrl,
        type: 'POST',
        data: {
          action: 'log',
          message: message.substring(0, 500),
          data: JSON.stringify(data),
          _token: config.staticToken
        },
        timeout: 2000
      });
    }
  }

  // 3. Función AJAX segura con manejo de errores
 
   
   
   
   function safeAjax(options) {
        // Configuración mejorada
        const ajaxConfig = {
            url: options.url || config.moduleUrl,
            type: options.type || 'POST',
            data: typeof options.data === 'string' ? options.data : $.param(options.data),
            dataType: 'json',
            timeout: options.timeout || 8000, // Aumentado a 8 segundos
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-Rental-Token': config.staticToken
            },
            cache: false
        };
    
        // Implementación robusta
        return $.ajax(ajaxConfig)
            .then(function(response) {
                // Validación estricta de la respuesta
                if (!response || typeof response.success === 'undefined') {
                    throw new Error('Respuesta inválida del servidor');
                }
                return response;
            }, function(jqXHR, textStatus, errorThrown) {
                // Manejo profesional de errores
                let errorMsg = 'Error en la conexión';
                let errorData = {};
                
                try {
                    if (jqXHR.responseJSON) {
                        errorMsg = jqXHR.responseJSON.message || errorMsg;
                        errorData = jqXHR.responseJSON.data || {};
                    } else if (jqXHR.responseText) {
                        const parsed = JSON.parse(jqXHR.responseText);
                        errorMsg = parsed.message || errorMsg;
                        errorData = parsed.data || {};
                    }
                } catch (e) {
                    errorMsg = textStatus || 'Error desconocido';
                }
    
                // Log detallado
                debugLog('Error en AJAX', {
                    status: jqXHR.status,
                    message: errorMsg,
                    errorData: errorData,
                    request: ajaxConfig
                }, true);
    
                // Mantener compatibilidad
                return $.Deferred().reject({
                    message: errorMsg,
                    data: errorData,
                    jqXHR: jqXHR,
                    textStatus: textStatus
                });
            });
    }

  // 4. Mostrar mensajes de error/éxito
  function showAlert(type, message, duration = 5000) {
    const $alert = $(`#rental${type.charAt(0).toUpperCase() + type.slice(1)}`);
    const icon = type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle';
    if ($alert.length) {
      $alert.html(`<i class="fas ${icon}"></i> ${message}`).stop(true, true).slideDown();
      if (duration > 0) {
        setTimeout(() => $alert.slideUp(), duration);
      }
      if (type === 'error') {
        $('html, body').animate({
          scrollTop: $alert.offset().top - 100
        }, 300);
      }
    } else {
      console.error(`No se encontró contenedor para alerta ${type}`);
      alert(message);
    }
  }

  // 5. Obtener datos del producto desde el botón
  function getProductData($btn) {
    if (!$btn || !$btn.length) {
      debugLog('ERROR: Elemento botón no válido', {}, true);
      return null;
    }
    const productData = {
      sku: $btn.data('sku'),
      productId: $btn.data('product-id'),
      attributeId: $btn.data('product-attribute-id') || 0,
      available: $btn.data('available'),
      min: $btn.data('min'),
      max: $btn.data('max'),
      productName: $btn.data('product-name'),
      productPrice: $btn.data('product-price')
    };
    if (!productData.sku || !productData.productId) {
      debugLog('ERROR: Datos del producto incompletos', {
        buttonData: $btn.data(),
        productData: productData
      }, true);
      return null;
    }
    return productData;
  }

  // 6. Inicialización del modal con manejo seguro
  function initModal(productData) {
    if (!(productData !== null && productData !== void 0 && productData.sku)) {
      debugLog('ERROR: SKU no definido al inicializar modal', {
        productData: productData,
        stack: new Error().stack
      }, true);
      showAlert('error', 'Error: Producto no válido');
      return false;
    }
    debugLog('Inicializando modal', {
      productData
    });
    if ($('#rentalModal').length) {
        $('#rentalModal').remove();
    
        
        
      updateModalContent(productData);
      setupModalEvents();
      $('#rentalModal').modal('show');
      return true;
    }
    safeAjax({
      url: config.moduleUrl,
      type: 'GET',
      data: {
        action: 'getModal',
        sku: productData.sku
      },
      timeout: 3000
    }).done(function (response) {
      var _response$data;
      if (response !== null && response !== void 0 && response.success && (_response$data = response.data) !== null && _response$data !== void 0 && _response$data.modal) {
        $('body').append(response.data.modal);
        
        
        productData.productImageUrl = response.data.product_image_url;
        console.log('Imagen recibida desde AJAX:', productData.productImageUrl);
        
        productData.productName = response.data.product_name;

        
        updateModalContent(productData);
        setupModalEvents();
        $('#rentalModal').modal('show');
      } else {
        throw new Error('Respuesta inválida del servidor');
      }
    }).fail(function () {
      createFallbackModal();
      showAlert('error', 'Error al cargar el formulario');
    });
    return true;
  }

  // 7. Actualizar contenido del modal
  function updateModalContent(productData) {
    try {
      const $modal = $('#rentalModal');
      if (!$modal.length) {
        throw new Error('Modal no encontrado en el DOM');
      }
      
      // Limpiar datos antiguos antes de actualizar
        $modal.data('product-data', null);
      
      
      // Actualizar nuevamente con nuevos datos
        $modal.data('product-data', productData)
              .find('.product-name').text(productData.productName || 'Producto').end()
              .find('.product-price').text(productData.productPrice || '').end()
              .find('.available-stock').text(productData.available || 0).end()
              .find('#rentalQuantity').attr({
                  'min': productData.min || 1,
                  'max': productData.max || productData.available || 1,
                  'value': productData.min || 1
              });
      
      
       // Actualizar imagen correctamente
        if (productData.productImageUrl) {
            $modal.find('#rentalProductImage').attr('src', productData.productImageUrl);
        } else {
            $modal.find('#rentalProductImage').attr('src', ''); // Limpiar si no hay imagen
        }
        
        
        // Nombre del producto 
        if (productData.productName) {
            $modal.find('.product-name').text(productData.productName);
        }
        
      
      
      
      const today = new Date().toISOString().split('T')[0];
      $modal.find('.rental-date').attr('min', today).val('');
      debugLog('Modal actualizado', {
        productData
      });
    } catch (error) {
      debugLog('ERROR al actualizar modal', {
        error,
        productData
      }, true);
    }
  }

  // 8. Modal de emergencia
  function createFallbackModal() {
    debugLog('Creando modal de emergencia', {}, true);
    $('body').append(`
            <div class="modal fade" id="rentalModal">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">Error en el sistema</h5>
                            <button type="button" class="close text-white" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p>No se pudo cargar el formulario de reserva.</p>
                            <p>Por favor, intente recargar la página o contacte al soporte técnico.</p>
                        </div>
                    </div>
                </div>
            </div>
        `);
    $('#rentalModal').modal('show');
  }

  // 9. Configurar eventos del modal
  function setupModalEvents() {
    $('#rentalModal').on('hidden.bs.modal', function () {
      if ($('#blockcart-modal').length && !$('#blockcart-modal').hasClass('show')) {
        setTimeout(() => {
          $('#blockcart-modal').modal('show');
        }, 300);
      }
    });
    $(document).on('click', '#rentalModal .close, #rentalModal .btn-secondary', function (e) {
      e.preventDefault();
      $('#rentalModal').modal('hide');
      if ($('#blockcart-modal').length) {
        setTimeout(() => {
          $('#blockcart-modal').modal('show');
        }, 300);
      }
    });
    $(document).on('change', '#rentalEndDate', function () {
      const startDate = $('#rentalStartDate').val();
      const endDate = $(this).val();
      if (!startDate) {
        showAlert('error', 'Primero seleccione la fecha de inicio');
        $(this).val('');
        return;
      }
      if (endDate <= startDate) {
        showAlert('error', 'La fecha de término debe ser posterior');
        $(this).val('');
        return;
      }
      const productData = $('#rentalModal').data('product-data');
      const quantity = parseInt($('#rentalQuantity').val()) || 0;
      if (quantity > 0) {
        checkAvailability(productData.sku, quantity, startDate, endDate);
      }
    });
    $(document).on('change', '#rentalQuantity', function () {
      const max = parseInt($(this).attr('max')) || 0;
      const value = parseInt($(this).val()) || 0;
      if (value > max) {
        showAlert('error', `No puede reservar más de ${max} unidades`);
        $(this).val(max);
      }
    });
  }

  // 10. Verificar disponibilidad
  function checkAvailability(sku, quantity, startDate, endDate) {
    safeAjax({
      url: config.moduleUrl,
      type: 'POST',
      data: {
        action: 'checkAvailability',
        sku: sku,
        quantity: quantity,
        start_date: startDate,
        end_date: endDate,
        _token: config.staticToken
      }
    }).done(function (response) {
      if (response.success) {
        $('#rentalModal .available-stock').text(response.available_stock);
        $('#rentalQuantity').attr('max', response.available_stock);
      } else {
        showAlert('error', response.message);
        $('#rentalEndDate').val('');
      }
    });
  }

  // 11. Manejador del botón de reserva
  $(document).on('click', '.rental-btn', function (e) {
    e.preventDefault();
    e.stopPropagation();
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

  // 12. Procesamiento del formulario
  $(document).on('click', '#rentalModal .add-to-cart', function () {
    const $btn = $(this);
    const productData = $('#rentalModal').data('product-data');
    if (!(productData !== null && productData !== void 0 && productData.sku)) {
      showAlert('error', 'Error: Datos del producto perdidos');
      return;
    }
    const quantity = parseInt($('#rentalQuantity').val()) || 0;
    const startDate = $('#rentalStartDate').val();
    const endDate = $('#rentalEndDate').val();
    if (quantity <= 0) {
      showAlert('error', 'La cantidad debe ser mayor a cero');
      return;
    }
    if (!startDate || !endDate) {
      showAlert('error', 'Debe seleccionar ambas fechas');
      return;
    }
    $btn.prop('disabled', true).find('.btn-text').hide().end().find('.btn-loading').show();
    safeAjax({
      url: config.moduleUrl,
      type: 'POST',
      data: {
        action: 'addRental',
        sku: productData.sku,
        product_id: productData.productId,
        attribute_id: productData.attributeId,
        quantity: quantity,
        start_date: startDate,
        end_date: endDate,
        _token: config.staticToken
      }
    }).done(function (response) {
      if (response !== null && response !== void 0 && response.success) {
        var _response$data2;
        showAlert('success', response.message || 'Reserva exitosa');
        if (((_response$data2 = response.data) === null || _response$data2 === void 0 ? void 0 : _response$data2.available_stock) !== undefined) {
          $('.available-stock').text(response.data.available_stock);
          $('#rentalQuantity').attr('max', response.data.available_stock);
        }
        setTimeout(() => {
          var _response$data3;
          $('#rentalModal').modal('hide');
          if ((_response$data3 = response.data) !== null && _response$data3 !== void 0 && _response$data3.cart_url) {
            window.location.href = response.data.cart_url;
          }
        }, 1500);
      } else {
        throw new Error((response === null || response === void 0 ? void 0 : response.message) || 'Error en la reserva');
      }
    }).fail(function (jqXHR) {
      let errorMsg = 'Error de conexión';
      try {
        const json = jqXHR.responseJSON;
        if (json !== null && json !== void 0 && json.message) errorMsg = json.message;
      } catch (e) {
        debugLog('ERROR al parsear respuesta', {
          error: e
        }, true);
      }
      showAlert('error', errorMsg);
    }).always(function () {
      $btn.prop('disabled', false).find('.btn-text').show().end().find('.btn-loading').hide();
    });
  });

  // Controlador de cantidad con botones +/-
  $(document).on('click', '.quantity-plus', function () {
    const $input = $(this).siblings('input');
    const max = parseInt($input.attr('max'));
    let value = parseInt($input.val()) || 0;
    if (value < max) {
      $input.val(value + 1).trigger('change');
    }
  });
  $(document).on('click', '.quantity-minus', function () {
    const $input = $(this).siblings('input');
    const min = parseInt($input.attr('min'));
    let value = parseInt($input.val()) || 0;
    if (value > min) {
      $input.val(value - 1).trigger('change');
    }
  });


  // Animación al mostrar el modal
  $(document).on('shown.bs.modal', '#rentalModal', function () {
      const $modal = $(this);
    
      // 🔒 Ocultar stock, cantidad y mostrar instrucciones
      $modal.find('.available-stock').closest('.form-group').hide();
      $modal.find('.quantity-selector').hide();
      $modal.find('.add-to-cart').prop('disabled', true);
      $modal.find('#rentalInstruction').show(); // Mostrar mensaje de instrucciones
    
      // 🧼 Destruir instancias anteriores de Flatpickr si existen
      const startInput = $modal.find('#rentalStartDate')[0];
      const endInput = $modal.find('#rentalEndDate')[0];
      if (startInput && startInput._flatpickr) {
        startInput._flatpickr.destroy();
      }
      if (endInput && endInput._flatpickr) {
        endInput._flatpickr.destroy();
      }
    
      // ✅ Inicializar Flatpickr solo si los elementos existen
      if (typeof flatpickr !== 'undefined') {
        if (startInput) {
          flatpickr(startInput, {
            dateFormat: "d-m-Y",
            altInput: true,
            altFormat: "d-m-Y",
            minDate: "today",
            allowInput: true,
            disableMobile: true,
            locale: {
              firstDayOfWeek: 1,
              weekdays: {
                shorthand: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa'],
                longhand: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
              },
              months: {
                shorthand: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
                longhand: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
              },
            },
          });
        }
    
        if (endInput) {
          flatpickr(endInput, {
            dateFormat: "d-m-Y",
            altInput: true,
            altFormat: "d-m-Y",
            minDate: "today",
            allowInput: true,
            disableMobile: true,
            locale: {
              firstDayOfWeek: 1,
              weekdays: {
                shorthand: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa'],
                longhand: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
              },
              months: {
                shorthand: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
                longhand: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
              },
            },
          });
        }
      }
    
      // 🧠 Escuchar cambios en las fechas
      function checkDates() {
        const startDate = $modal.find('#rentalStartDate').val();
        const endDate = $modal.find('#rentalEndDate').val();
    
        if (startDate && endDate) {
          // 🔓 Mostrar stock, cantidad y ocultar instrucciones
          $modal.find('.available-stock').closest('.form-group').show();
          $modal.find('.quantity-selector').show();
          $modal.find('.add-to-cart').prop('disabled', false);
          $modal.find('#rentalInstruction').hide(); // Ocultar mensaje
        }
      }
    
      $modal.find('#rentalStartDate, #rentalEndDate').on('change', checkDates);
    });


  // Estilo para el botón principal
  function styleRentalButton() {
    $('.rental-btn').html('<i class="fas fa-calendar-alt"></i> Reservar para evento');
  }

  // Inicialización completada
  $(document).ready(function () {
    styleRentalButton();
    debugLog('Módulo inicializado correctamente', {
      config: config,
      jQueryVersion: $.fn.jquery,
      prestashop: typeof prestashop !== 'undefined'
    });
  });
});          
