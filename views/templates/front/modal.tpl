<div class="modal fade rental-modal" id="rentalModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content fade-in">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="fas fa-calendar-alt"></i>
          Reservar para evento
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <!-- Mensajes -->
        <div id="rentalErrors" class="alert alert-danger"></div>
        <div id="rentalSuccess" class="alert alert-success"></div>

        <div id="rentalInstruction" class="alert alert-info" style="margin-bottom: 15px;">
          Por favor, selecciona una fecha de inicio y de término para ver el stock disponible.
        </div>

        <!-- Tarjeta de producto -->
        <div class="product-card text-center">
          <img id="rentalProductImage" src="" alt="Imagen del producto" style="max-width:120px; margin-bottom:10px;">
          <h4 class="product-name">{$product.product_name}</h4>
          <div class="product-price">{$product.product_price}</div>
        </div>

        <!-- Stock y cantidad -->
        <div class="form-group">
          <label>Stock disponible: <span class="stock-badge available-stock">{$product.available_stock}</span></label>

          <div class="quantity-selector">
            <button type="button" class="quantity-minus">–</button>
            <input type="number" id="rentalQuantity" name="rentalQuantity" min="1" value="1" />
            <button type="button" class="quantity-plus">+</button>
          </div>
        </div>




        <!-- Fechas -->
        <div class="form-group">
            <label>Fecha de inicio</label>
            <div class="input-group">
                <input type="text" id="rentalStartDate" class="form-control rental-date" placeholder="Selecciona fecha de inicio" required>
                <div class="input-group-append">
                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                </div>
            </div>
        </div>
        
        <div class="form-group">
            <label>Fecha de término</label>
            <div class="input-group">
                <input type="text" id="rentalEndDate" class="form-control rental-date" placeholder="Selecciona fecha de término" required>
                <div class="input-group-append">
                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                </div>
            </div>
        </div>






      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">
          Cancelar
        </button>
        <button type="button" class="btn btn-primary add-to-cart">
          <span class="btn-text">Reservar</span>
          <span class="btn-loading" style="display: none;">
            Procesando...
          </span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css">
<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
