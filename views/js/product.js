document.addEventListener('DOMContentLoaded', function() {
    // Sobrescribir el comportamiento del botón "Añadir al carrito" en página de producto
    $(document).on('click', '.rental-btn', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        
        const $btn = $(this);
        const productData = {
            sku: $btn.data('sku'),
            productId: $btn.data('product-id'),
            attributeId: $btn.data('product-attribute-id') || 0,
            available: $btn.data('available'),
            min: $btn.data('min') || 1,
            max: $btn.data('max') || $btn.data('available'),
            productName: $btn.closest('.product-container').find('h1').text() || $btn.data('product-name'),
            productPrice: $btn.closest('.product-prices').find('.current-price').text() || $btn.data('product-price')
        };
        
        // Mostrar nuestro modal en lugar del comportamiento por defecto
        $('#rentalModal').modal('show');
        updateModalContent(productData);
    });
    
    function updateModalContent(productData) {
        $('#rentalModal').data('product-data', productData)
            .find('.product-name').text(productData.productName || 'Producto').end()
            .find('.product-price').text(productData.productPrice || '').end()
            .find('.available-stock').text(productData.available || 0).end()
            .find('#rentalQuantity').attr({
                'min': productData.min,
                'max': productData.max,
                'value': productData.min
            });
        
        const today = new Date().toISOString().split('T')[0];
        $('#rentalModal').find('.rental-date').attr('min', today).val('');
    }
});