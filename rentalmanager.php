<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class RentalManager extends Module
{
    protected $debug = true;
    protected $log_file = 'rentalmanager.log';
    protected $admin_controller_link;
    protected $front_controller_link;

    public function __construct()
    {
        $this->name = 'rentalmanager';
        $this->tab = 'front_office_features';
        $this->version = '1.0.1';
        $this->author = 'Jorge Toro (Steward 2025)';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '8.0.0', 
            'max' => '8.99.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Gestor de Arriendos');
        $this->description = $this->l('Sistema de gestión de arriendos para menaje de eventos');

        // Configuración inicial
        $this->debug = (bool)Configuration::get('RENTAL_DEBUG_MODE', true);
        $this->admin_controller_link = $this->context->link->getAdminLink('AdminModules', true, [], [
            'configure' => $this->name,
            'tab_module' => $this->tab,
            'module_name' => $this->name
        ]);
        $this->front_controller_link = $this->context->link->getModuleLink(
            $this->name,
            'rental',
            [],
            true
        );

        // Inicializar sistema de logs
        $this->initLogSystem();
        $this->log('Módulo instanciado', [
            'debug_mode' => $this->debug,
            'version' => $this->version,
            'controller_link' => $this->front_controller_link
        ]);
    }

    public function install()
    {
        $this->log('Iniciando instalación');
        $result = parent::install() 
            && $this->registerHook('displayHeader')
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->registerHook('displayProductAdditionalInfo')
            && $this->registerHook('actionCartUpdateQuantityBefore')
            && $this->registerHook('actionBeforeCartUpdateQty')
            && $this->installDB()
            && Configuration::updateValue('RENTAL_DEBUG_MODE', true);
        
        $this->log('Instalación ' . ($result ? 'exitosa' : 'fallida'));
        return $result;
    }

    public function uninstall()
    {
        $this->log('Iniciando desinstalación');
        $result = parent::uninstall() 
            && $this->uninstallDB()
            && Configuration::deleteByName('RENTAL_DEBUG_MODE');
        
        $this->log('Desinstalación ' . ($result ? 'exitosa' : 'fallida'));
        return $result;
    }

    protected function installDB()
    {
        $this->log('Instalando base de datos');
        return Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'rent_dates` (
                `id_rent` INT(11) NOT NULL AUTO_INCREMENT,
                `id_cart` INT(11) NOT NULL,
                `id_order` INT(11) DEFAULT NULL,
                `sku` VARCHAR(64) NOT NULL,
                `rental_date` DATETIME NOT NULL,
                `end_date` DATETIME NOT NULL,
                `reserved_qty` INT(11) NOT NULL,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_rent`),
                INDEX (`id_cart`),
                INDEX (`id_order`),
                INDEX (`sku`),
                INDEX (`rental_date`),
                INDEX (`end_date`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;
        ');
    }

    protected function uninstallDB()
    {
        $this->log('Desinstalando base de datos');
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'rent_dates`');
    }

    protected function initLogSystem()
    {
        $logPath = $this->getLocalPath().$this->log_file;
        
        if (!file_exists($logPath)) {
            $this->log('Creando archivo de log', ['path' => $logPath]);
            @file_put_contents($logPath, '['.date('Y-m-d H:i:s').'] Sistema de logs inicializado'.PHP_EOL);
            @chmod($logPath, 0666);
        }
    }

    public function getProductBySku($sku)
    {
        $sql = new DbQuery();
        $sql->select('p.id_product, pl.name, p.price')
            ->from('product', 'p')
            ->leftJoin('product_lang', 'pl', 'p.id_product = pl.id_product AND pl.id_lang = '.(int)$this->context->language->id)
            ->where('p.reference = "'.pSQL($sku).'" OR p.id_product IN (
                SELECT id_product FROM '._DB_PREFIX_.'product_attribute 
                WHERE reference = "'.pSQL($sku).'"
            )');
        
        $result = Db::getInstance()->getRow($sql);
        
        if ($result) {
            $result['price_formatted'] = Tools::displayPrice($result['price']);
            return $result;
        }
        
        return null;
    }


   
    
    public function log($message, $data = null)
    {
        if (!$this->debug) return;
        
        $logPath = $this->getLocalPath().$this->log_file;
        $logMessage = '['.date('Y-m-d H:i:s').'] '.$message.PHP_EOL;
        
        if ($data) {
            $logMessage .= 'Data: '.print_r($data, true).PHP_EOL;
        }
        
        // Contexto adicional para diagnóstico
        $logMessage .= 'Context: ';
        $logMessage .= 'Cart: '.(isset($this->context->cart->id) ? $this->context->cart->id : 'NULL').' | ';
        $logMessage .= 'Customer: '.(isset($this->context->customer->id) ? $this->context->customer->id : 'NULL').' | ';
        $logMessage .= 'URL: '.($_SERVER['REQUEST_URI'] ?? 'NULL').PHP_EOL;
        $logMessage .= str_repeat('-', 80).PHP_EOL;
        
        @file_put_contents($logPath, $logMessage, FILE_APPEND | LOCK_EX);
    }

    public function getContent()
    {
        $this->postProcess();

        $this->context->smarty->assign([
            'module_dir' => $this->_path,
            'module_name' => $this->name,
            'rental_settings' => [
                'debug_mode' => Configuration::get('RENTAL_DEBUG_MODE'),
                'log_file_exists' => file_exists($this->getLocalPath().$this->log_file),
                'log_file_path' => realpath($this->getLocalPath().$this->log_file),
                'log_file_size' => $this->getLogFileSize(),
                'module_version' => $this->version,
                'front_controller_url' => $this->front_controller_link
            ],
            'form_action' => $this->admin_controller_link
        ]);

        $this->context->controller->addCSS($this->_path.'views/css/admin.css');
        $this->context->controller->addJS($this->_path.'views/js/admin.js');

        return $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }

    protected function postProcess()
    {
        if (Tools::isSubmit('submitRentalSettings')) {
            $debugMode = (bool)Tools::getValue('RENTAL_DEBUG_MODE');
            Configuration::updateValue('RENTAL_DEBUG_MODE', $debugMode);
            $this->debug = $debugMode;
            $this->context->controller->confirmations[] = $this->l('Configuración actualizada correctamente');
            $this->log('Configuración actualizada', ['debug_mode' => $debugMode]);
        }

        if (Tools::getValue('downloadLog')) {
            $this->downloadLogFile();
        }

        if (Tools::getValue('clearLog')) {
            $this->clearLogFile();
        }
    }

    protected function downloadLogFile()
    {
        $logPath = $this->getLocalPath().$this->log_file;
        if (file_exists($logPath)) {
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="rental_log_'.date('Y-m-d').'.txt"');
            readfile($logPath);
            exit;
        }
        $this->context->controller->errors[] = $this->l('Archivo log no encontrado');
    }

    protected function clearLogFile()
    {
        $logPath = $this->getLocalPath().$this->log_file;
        if (file_exists($logPath)) {
            file_put_contents($logPath, '');
            $this->context->controller->confirmations[] = $this->l('Log limpiado correctamente');
        }
    }

    public function hookDisplayHeader()
    {
        $this->context->controller->registerStylesheet(
            'rentalmanager-css',
            'modules/'.$this->name.'/views/css/rental.css',
            ['media' => 'all', 'priority' => 150]
        );

        $this->context->controller->registerJavascript(
            'rentalmanager-js',
            'modules/'.$this->name.'/views/js/rental.js',
            ['position' => 'bottom', 'priority' => 150, 'attribute' => 'async']
        );

        Media::addJsDef([
            'rentalModuleUrl' => $this->context->link->getModuleLink('rentalmanager', 'rental'),
            'rentalDebug' => $this->debug
        ]);
    }


    public function hookActionFrontControllerSetMedia($params)
    {
        // Registrar CSS
        $this->context->controller->registerStylesheet(
            'rentalmanager-css',
            'modules/'.$this->name.'/views/css/rental.css',
            ['media' => 'all', 'priority' => 150]
        );
        
        // Registrar JavaScript
        $this->context->controller->registerJavascript(
            'rentalmanager-js',
            'modules/'.$this->name.'/views/js/rental.js',
            ['position' => 'bottom', 'priority' => 150, 'attribute' => 'async']
        );
    
        // Inyectar variables JS necesarias para el módulo
        Media::addJsDef([
            'rentalModuleUrl' => $this->context->link->getModuleLink($this->name, 'rental'),
            'rentalDebug' => $this->debug
        ]);
    }


    public function hookDisplayProductAdditionalInfo($params)
    {
        if (!isset($params['product']->reference) || empty($params['product']->reference)) {
            return '';
        }

        $product = $params['product'];
        $availableStock = $this->getAvailableStock($product->reference);
        
        $this->context->smarty->assign([
            'product_sku' => $product->reference,
            'product_id' => $product->id,
            'product_attribute_id' => isset($product->id_product_attribute) ? $product->id_product_attribute : 0,
            'available_stock' => $availableStock,
            'minimal_quantity' => 1,
            'max_quantity' => $availableStock > 0 ? $availableStock : 0,
            'rental_modal_content' => $this->generateModalHtml()
        ]);
        
        return $this->display(__FILE__, 'views/templates/hook/product-rental.tpl');
    }

    protected function generateModalHtml()
    {
        return $this->fetch('module:rentalmanager/views/templates/front/modal.tpl');
    }




    
    public function hookActionCartUpdateQuantityBefore($params)
    {
        try {
            // Verificación completa de parámetros
            if (!isset($params['id_product']) || !isset($params['quantity'])) {
                $this->log('Parámetros incompletos en hookActionCartUpdateQuantityBefore', [
                    'params_received' => $params,
                    'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
                ]);
                return false;
            }
    
            // Validar valores numéricos
            $id_product = (int)$params['id_product'];
            $quantity = (int)$params['quantity'];
            $operator = isset($params['operator']) ? $params['operator'] : 'up';
            $id_product_attribute = isset($params['id_product_attribute']) ? (int)$params['id_product_attribute'] : 0;
    
            if ($id_product <= 0 || $quantity <= 0) {
                $this->log('Valores inválidos en hook', [
                    'id_product' => $id_product,
                    'quantity' => $quantity
                ]);
                return true; // No bloquear pero registrar
            }
    
            // Obtener referencia (SKU) del producto
            $product = new Product($id_product, false, $this->context->language->id);
            if (!Validate::isLoadedObject($product) || empty($product->reference)) {
                return true;
            }
    
            return $this->validateRentalQuantity(
                $id_product,
                $id_product_attribute,
                $quantity,
                $operator
            );
    
        } catch (Exception $e) {
            $this->log('Excepción en hookActionCartUpdateQuantityBefore: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    
    
    

    public function hookActionBeforeCartUpdateQty($params)
    {
        if (!isset($params['id_product'])) {
            $this->log('Error: Parámetro id_product faltante en hookActionBeforeCartUpdateQty', $params);
            return false;
        }

        return $this->validateRentalQuantity(
            $params['id_product'],
            isset($params['id_product_attribute']) ? $params['id_product_attribute'] : 0,
            $params['quantity'],
            'up'
        );
    }
    
    

    protected function validateRentalQuantity($productId, $productAttributeId, $quantity, $operator)
    {
        $product = new Product($productId);
        if (!Validate::isLoadedObject($product) || empty($product->reference)) {
            return true;
        }

        $sku = $product->reference;
        $currentQuantity = $this->getRentalQuantityInCart($productId, $productAttributeId);
        
        // Calcular nueva cantidad
        if ($operator == 'up') {
            $newQuantity = $currentQuantity + $quantity;
        } elseif ($operator == 'down') {
            $newQuantity = max(0, $currentQuantity - $quantity);
        } else {
            $newQuantity = $quantity;
        }

        // Solo validamos si la cantidad está aumentando
        if ($newQuantity <= $currentQuantity) {
            return true;
        }

        $availableStock = $this->getAvailableStock($sku);
        $requestedIncrease = $newQuantity - $currentQuantity;

        if ($requestedIncrease > $availableStock) {
            $errorMessage = sprintf(
                $this->l('No hay suficiente stock disponible para "%s". Stock disponible: %d'),
                $product->name,
                $availableStock
            );
            $this->context->controller->errors[] = $errorMessage;
            return false;
        }

        return true;
    }
    
    
   
    public function getAvailableStock($sku)
    {
        try {
            // Stock total desde AX
            $totalStock = (int)Db::getInstance()->getValue('
                SELECT COALESCE(cantidad, 0)
                FROM pst_stock_ax 
                WHERE sku = "' . pSQL($sku) . '"
            ');
    
            // Stock reservado (excluyendo el carrito actual)
            $reservedStock = (int)Db::getInstance()->getValue('
                SELECT COALESCE(SUM(reserved_qty), 0)
                FROM ' . _DB_PREFIX_ . 'rent_dates 
                WHERE sku = "' . pSQL($sku) . '"
                AND end_date > NOW()
                AND (id_cart != ' . (int)$this->context->cart->id . ' OR id_cart IS NULL)
                AND id_order IS NULL
            ');
    
            $available = max(0, $totalStock - $reservedStock);
            
            $this->log('Stock calculado', [
                'sku' => $sku,
                'total' => $totalStock,
                'reserved' => $reservedStock,
                'available' => $available
            ]);
    
            return $available;
    
        } catch (Exception $e) {
            $this->log('Error en getAvailableStock: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return 0; // Retornar 0 como fallback seguro
        }
    }

    protected function getProductIdBySku($sku)
    {
        // Buscar en productos simples
        $result = Db::getInstance()->getRow('
            SELECT id_product, 0 as id_product_attribute
            FROM '._DB_PREFIX_.'product
            WHERE reference = "'.pSQL($sku).'"
        ');
        
        // Si no se encuentra, buscar en combinaciones
        if (!$result) {
            $result = Db::getInstance()->getRow('
                SELECT pa.id_product, pa.id_product_attribute
                FROM '._DB_PREFIX_.'product_attribute pa
                WHERE pa.reference = "'.pSQL($sku).'"
            ');
        }
        
        return $result ?: false;
    }

    protected function getRentalQuantityInCart($productId, $productAttributeId = 0)
    {
        if (!Validate::isLoadedObject($this->context->cart)) {
            return 0;
        }

        return (int)Db::getInstance()->getValue('
            SELECT quantity
            FROM '._DB_PREFIX_.'cart_product
            WHERE id_cart = '.(int)$this->context->cart->id.'
            AND id_product = '.(int)$productId.'
            AND id_product_attribute = '.(int)$productAttributeId
        );
    }

}