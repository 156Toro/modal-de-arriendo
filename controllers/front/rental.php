<?php
class RentalManagerRentalModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    
    public function init()
    {
        if (ob_get_length()) {
            ob_clean();
        }
                
        
        parent::init();
        $this->logRequest();
    }
    
    protected function logRequest()
    {
        $this->module->log('Solicitud recibida en controlador front', [
            'action' => Tools::getValue('action'),
            'GET' => $_GET,
            'POST' => $_POST,
            'headers' => getallheaders(),
            'client_ip' => Tools::getRemoteAddr(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido'
        ]);
    }
    
    
    public function initContent()
    {
        parent::initContent();
        
         // ✅ Asegurar que exista un carrito válido
        if (!$this->context->cart || !$this->context->cart->id) {
            $this->context->cart = new Cart();
            $this->context->cart->id_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
            $this->context->cart->id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
            $this->context->cart->add(); 
            $this->context->cookie->id_cart = (int) $this->context->cart->id;
        }
        
        
        try {
            $action = Tools::getValue('action');
            
            if (empty($action)) {
                throw new Exception('Acción no especificada');
            }
            
            $this->module->log('Procesando acción: '.$action);
            
            switch ($action) {
                case 'addRental':
                    $this->processAddRental();
                    break;
                    
                case 'checkAvailability':
                    $this->processCheckAvailability();
                    break;
                    
                case 'getModal': // Nueva acción para obtener el modal
                    $this->processGetModal();
                    break;
                    
                case 'log':
                    $this->processLog();
                    break;
                    
                case 'getLastRentalDates':
                    $this->processGetLastRentalDates();
                    break;    
                
                    
                default:
                    throw new Exception('Acción no válida: '.$action);
            }
        } catch (Exception $e) {
            $this->module->log('Error en controlador: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'code' => $e->getCode()
            ]);
            
            $this->ajaxResponse(false, $e->getMessage(), null, $e->getCode());
        }
    }
    
    protected function processGetModal()
    {
        // Configurar datos para el modal
        $productData = [
            'available_stock' => 0,
            'min_quantity' => 1,
            'product_name' => '',
            'product_price' => '',
            'product_image_url' => '' 
        ];
        
        // Si se proporciona un SKU, cargar datos del producto
        $sku = Tools::getValue('sku');
        if ($sku) {
            $product = $this->module->getProductBySku($sku);
            if ($product) {
                $productData['available_stock'] = $this->module->getAvailableStock($sku);
                $productData['product_name'] = $product['name'];
                $productData['product_price'] = $product['price'];
                
                
            // Agregar estas líneas para obtener la imagen
                $cover = Product::getCover($product['id_product']);
                if ($cover && isset($cover['id_image'])) {
                    $link = new Link();
                    $image_url = $this->context->link->getImageLink(
                        $product['id_product'], 
                        $cover['id_image'], 
                        'home_default'
                    );
                    $productData['product_image_url'] = $image_url;
                }    
                
            }
        }
        
        // Asignar variables a la plantilla
        $this->context->smarty->assign([
            'product' => $productData,
            'module_dir' => $this->module->getPathUri()
        ]);
        
        // Renderizar la plantilla del modal
        $modalContent = $this->context->smarty->fetch(
            'module:rentalmanager/views/templates/front/modal.tpl'
        );
        
        $this->ajaxResponse(true, '', [
            'modal' => $modalContent,
            'product_image_url' => $productData['product_image_url'],
            'product_name' => $productData['product_name']
        ]);
    }
    
    
    protected function processAddRental()
    {
        // Limpiar cualquier salida anterior
        if (ob_get_length()) {
            ob_clean();
        }
    
        try {
            // ✅ [0] Asegurar que exista un carrito activo
            if (!$this->context->cart || !$this->context->cart->id) {
                $this->context->cart = new Cart();
                $this->context->cart->id_currency = (int) Configuration::get('PS_CURRENCY_DEFAULT');
                $this->context->cart->id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
                $this->context->cart->add();
                $this->context->cookie->id_cart = (int) $this->context->cart->id;
            }
    
            // ✅ [1] Validación básica
            $required = ['sku', 'quantity', 'start_date', 'end_date', 'product_id'];
            foreach ($required as $field) {
                if (empty(Tools::getValue($field))) {
                    throw new Exception("Falta el campo requerido: $field");
                }
            }
    
            $productId = (int) Tools::getValue('product_id');
            $quantity = (int) Tools::getValue('quantity');
            $attributeId = (int) Tools::getValue('attribute_id', 0);
            $sku = Tools::getValue('sku');
    
            // ✅ [2] Validación de fechas corregida
            $dateFormat = 'd-m-Y';
            $start = DateTime::createFromFormat($dateFormat, Tools::getValue('start_date'));
            $end = DateTime::createFromFormat($dateFormat, Tools::getValue('end_date'));
    
            if (!$start || !$end) {
                throw new Exception('Formato de fecha inválido. Use dd-mm-yyyy');
            }
    
            if ($end <= $start) {
                throw new Exception('Fecha final debe ser posterior');
            }
    
            // ✅ [3] Verificar disponibilidad real
            $available = $this->getAvailableStockForDates($sku, $start->format('Y-m-d'), $end->format('Y-m-d'));
            if ($quantity > $available) {
                throw new Exception("Solo hay $available unidades disponibles");
            }
    
            // ✅ [4] Registrar la reserva en BD
            $rentalId = Db::getInstance()->insert('rent_dates', [
                'id_cart' => (int) $this->context->cart->id,
                'sku' => pSQL($sku),
                'rental_date' => $start->format('Y-m-d H:i:s'),
                'end_date' => $end->format('Y-m-d H:i:s'),
                'reserved_qty' => (int) $quantity,
                'date_add' => date('Y-m-d H:i:s'),
                'date_upd' => date('Y-m-d H:i:s')
            ]);
    
            if (!$rentalId) {
                throw new Exception('Error al registrar la reserva');
            }
    
            // ✅ [5] Agregar producto al carrito
            $currentQty = $this->getRentalQuantityInCart($productId, $attributeId);
            $product = new Product($productId);
    
            if ($currentQty > 0) {
                $toAdd = $quantity - $currentQty;
                if ($toAdd > 0) {
                    $result = $this->context->cart->updateQty($toAdd, $productId, $attributeId, false, 'up');
                } else {
                    $result = true;
                }
            } else {
                $result = $this->context->cart->updateQty(
                $quantity,
                $productId,
                $attributeId,
                false,
                'up',
                0,
                null,
                true,
                true
            );
            }
    
            if (!$result) {
                throw new Exception('Error al actualizar carrito');
            }
    
            $this->context->cart->update();
    
            // ✅ [6] Respuesta exitosa
            $response = [
                'success' => true,
                'message' => 'Producto agregado correctamente',
                'data' => [
                    'available_stock' => $available - $quantity,
                    'product_name' => $product->name,
                    'new_quantity' => $quantity,
                    'cart_rentals' => $this->getCartRentals()
                ]
            ];
    
        } catch (Throwable $e) { // ✅ ATRAPAR TODO
            $response = [
                'success' => false,
                'message' => 'Error capturado: ' . $e->getMessage(),
                'data' => []
            ];
        }
    
        // ✅ [7] Siempre devolver JSON limpio
        if (ob_get_length()) {
            ob_clean();
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }


        
    
    protected function ajaxResponse($success, $message, $data = null, $code = 200)
    {
        // Limpiar cualquier salida previa
        if (ob_get_length()) {
            ob_clean();
        }
        
        // Forzar cabeceras JSON
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        
        // Estructura de respuesta estandarizada
        $response = [
            'success' => (bool)$success,
            'message' => (string)$message,
            'data' => $data ?: new stdClass(), // Evitar arrays vacíos
        ];
        
        // Conversión segura a JSON
        $jsonResponse = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Validar conversión
        if ($jsonResponse === false) {
            $response = [
                'success' => false,
                'message' => 'Error al generar respuesta JSON: ' . json_last_error_msg(),
                'data' => new stdClass()
            ];
            $jsonResponse = json_encode($response);
        }
        
        // Registrar solo en debug
        if (Configuration::get('RENTAL_DEBUG_MODE')) {
            $this->module->log('Respuesta preparada', [
                'response' => $response,
                'json' => $jsonResponse
            ]);
        }
        
        // Salida controlada
        die($jsonResponse);
    }

        
    protected function getCartSummary()
    {
        $cart = $this->context->cart;
        $summary = $cart->getSummaryDetails();
        
        return [
            'items_count' => $cart->nbProducts(),
            'subtotal' => Tools::displayPrice($summary['total_products']),
            'shipping' => Tools::displayPrice($summary['total_shipping']),
            'total' => Tools::displayPrice($summary['total_price_without_tax'])
        ];
    }   
        
        
        
    
    protected function processCheckAvailability()
    {
        try {
            // Validación mínima
            if (!Tools::getValue('sku') || !Tools::getValue('start_date') || !Tools::getValue('end_date')) {
                throw new Exception('Faltan parámetros requeridos');
            }
    
            $availableStock = $this->getAvailableStockForDates(
                Tools::getValue('sku'),
                Tools::getValue('start_date'),
                Tools::getValue('end_date')
            );
    
            // Respuesta consistente
            $this->ajaxResponse(true, '', [
                'available_stock' => $availableStock,
                'max_quantity' => $availableStock
            ]);
    
        } catch (Exception $e) {
            $this->ajaxResponse(false, $e->getMessage());
        }
    }

    
    protected function processLog()
    {
        if (Configuration::get('RENTAL_DEBUG_MODE')) {
            $this->module->log(
                Tools::getValue('message'), 
                json_decode(Tools::getValue('data'), true)
            );
        }
        die(json_encode(['success' => true]));
    }
    
    protected function registerRental($sku, $quantity, $startDate, $endDate)
    {
        return Db::getInstance()->insert('rent_dates', [
            'id_cart' => $this->context->cart->id,
            'sku' => pSQL($sku),
            'rental_date' => pSQL($startDate),
            'end_date' => pSQL($endDate),
            'reserved_qty' => (int)$quantity,
            'date_add' => date('Y-m-d H:i:s'),
            'date_upd' => date('Y-m-d H:i:s')
        ]);
    }
    
    
    protected function addToCart($productId, $productAttributeId, $quantity)
    {
        // Verificar si el producto ya est芍 en el carrito
        $inCart = $this->context->cart->containsProduct(
            $productId,
            $productAttributeId,
            0,
            0,
            Cart::ONLY_PRODUCTS
        );
    
        if ($inCart) {
            // Actualizar cantidad si ya existe
            return $this->context->cart->updateQty(
                $quantity,
                $productId,
                $productAttributeId,
                false,
                'up',
                0,
                null,
                true
            );
        } else {
            // Agregar nuevo producto al carrito
            return $this->context->cart->addProduct(
                $productId,
                $quantity,
                $productAttributeId,
                0,
                [],
                null,
                null,
                true,
                true
            );
        }
    }
    
    protected function getAvailableStockForDates($sku, $startDate, $endDate)
    {
        $totalStock = (int)Db::getInstance()->getValue('
            SELECT cantidad FROM pst_stock_ax 
            WHERE sku = "'.pSQL($sku).'"
        ');
        
        $reservedStock = (int)Db::getInstance()->getValue('
            SELECT SUM(reserved_qty) FROM '._DB_PREFIX_.'rent_dates 
            WHERE sku = "'.pSQL($sku).'"
            AND (
                (rental_date <= "'.pSQL($endDate).'" AND end_date >= "'.pSQL($startDate).'")
                OR (rental_date BETWEEN "'.pSQL($startDate).'" AND "'.pSQL($endDate).'")
                OR (end_date BETWEEN "'.pSQL($startDate).'" AND "'.pSQL($endDate).'")
            )
            AND (id_cart != '.(int)$this->context->cart->id.' OR id_cart IS NULL)
        ');
        
        return max(0, $totalStock - $reservedStock);
    }
    
    
    
    protected function getCartRentals()
    {
        $rentals = Db::getInstance()->executeS('
            SELECT sku, rental_date, end_date, reserved_qty
            FROM '._DB_PREFIX_.'rent_dates
            WHERE id_cart = '.(int)$this->context->cart->id.'
            ORDER BY rental_date ASC
        ');
        
        if (!$rentals) {
            return [];
        }
    
        // Asegurarse que las fechas sean strings legibles
        foreach ($rentals as &$rental) {
            $rental['rental_date'] = date('Y-m-d', strtotime($rental['rental_date']));
            $rental['end_date'] = date('Y-m-d', strtotime($rental['end_date']));
        }
        unset($rental);
    
        return $rentals;
    }


    protected function processGetLastRentalDates()
    {
        try {
            $dates = Db::getInstance()->getRow('
                SELECT rental_date, end_date 
                FROM '._DB_PREFIX_.'rent_dates 
                WHERE id_cart = '.(int)$this->context->cart->id.'
                ORDER BY id_rent DESC
            ');
    
            if (!$dates) {
                throw new Exception('No hay fechas anteriores.');
            }
    
            $this->ajaxResponse(true, '', [
                'start_date' => date('d-m-Y', strtotime($dates['rental_date'])),
                'end_date' => date('d-m-Y', strtotime($dates['end_date']))
            ]);
        } catch (Exception $e) {
            $this->ajaxResponse(false, $e->getMessage());
        }
    }

    
    
    protected function getRentalQuantityInCart($productId, $productAttributeId = 0)
    {
        return (int)Db::getInstance()->getValue('
            SELECT quantity FROM '._DB_PREFIX_.'cart_product
            WHERE id_cart = '.(int)$this->context->cart->id.'
            AND id_product = '.(int)$productId.'
            AND id_product_attribute = '.(int)$productAttributeId
        );
    }
}