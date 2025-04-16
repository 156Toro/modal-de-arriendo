<div class="cart-rental-buttons">
    {foreach $products as $product}
        <div class="rental-product-item mb-3" data-product-id="{$product.id_product}">
            <button class="btn btn-primary rental-btn btn-block"
                    data-sku="{$product.reference}"
                    data-product-id="{$product.id_product}"
                    data-product-attribute-id="{$product.id_product_attribute}"
                    data-available="{$product.stock_quantity}"
                    data-quantity="{$product.cart_quantity}"
                    data-product-name="{$product.name}"
                    data-product-price="{$product.price}">
                <i class="fas fa-calendar-alt mr-2"></i> 
                {l s='Reservar' mod='rentalmanager'} "{$product.name}"
            </button>
        </div>
    {/foreach}
</div>