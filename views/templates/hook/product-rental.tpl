{if $available_stock > 0}
    <div class="rental-manager-container">
        <button class="btn btn-primary rental-btn" 
                data-sku="{$product_sku}" 
                data-product-id="{$product_id}"
                data-product-attribute-id="{$product_attribute_id}"
                data-available="{$available_stock}"
                data-min="{$minimal_quantity}"
                data-max="{$max_quantity}">
            <i class="fas fa-calendar-alt mr-2"></i> {l s='Reservar para Evento' mod='rentalmanager'}
        </button>
        
        <div id="rentalModalContainer" style="display:none;"></div>
        
        <script type="text/javascript">
            var rentalModalContent = {$rental_modal_content|json_encode nofilter};
        </script>
    </div>
{else}
    <button class="btn btn-secondary" disabled>
        <i class="fas fa-times-circle mr-2"></i> {l s='Sin Stock Disponible' mod='rentalmanager'}
    </button>
{/if}