<?php
require_once dirname(__FILE__).'/../../config/config.inc.php';
require_once dirname(__FILE__).'/../../init.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== Rental Manager System Check ===\n\n";

// 1. Verificar instalación del módulo
$module = Module::getInstanceByName('rentalmanager');
if (!Validate::isLoadedObject($module)) {
    die("✖ ERROR: El módulo no está instalado o no se pudo cargar\n");
}

echo "✓ Módulo instalado correctamente (v{$module->version})\n";

// 2. Verificar tabla de la base de datos
$tableExists = Db::getInstance()->executeS('SHOW TABLES LIKE "'._DB_PREFIX_.'rent_dates"');
if (empty($tableExists)) {
    die("✖ ERROR: La tabla de arriendos no existe. Ejecute la instalación nuevamente\n");
}

echo "✓ Tabla de base de datos existe\n";

// 3. Verificar archivo de log
$logPath = $module->getLocalPath().'rentalmanager.log';
if (!file_exists($logPath)) {
    @file_put_contents($logPath, '['.date('Y-m-d H:i:s').'] Archivo de log creado por verificación'.PHP_EOL);
    @chmod($logPath, 0666);
}

if (!is_writable($logPath)) {
    die("✖ ERROR: No se puede escribir en el archivo de log ($logPath). Verifique permisos (necesario 0666)\n");
}

echo "✓ Archivo de log accesible ($logPath)\n";

// 4. Verificar controlador front
try {
    $url = $module->context->link->getModuleLink('rentalmanager', 'rental', [], true);
    echo "✓ URL del controlador: $url\n";
    
    // Probar acceso al controlador
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['action' => 'log', 'message' => 'Test', 'data' => '{}'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ['X-Requested-With: XMLHttpRequest']
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "  Código HTTP: $httpCode\n";
    
    if ($httpCode !== 200) {
        die("✖ ERROR: El controlador no responde correctamente (código $httpCode)\n");
    }
    
    $json = json_decode($response, true);
    if (!$json || !isset($json['success'])) {
        die("✖ ERROR: Respuesta del controlador no es JSON válido\n");
    }
    
    echo "✓ Controlador responde correctamente\n";
} catch (Exception $e) {
    die("✖ ERROR: Excepción al verificar controlador: ".$e->getMessage()."\n");
}

// 5. Verificar hooks
$requiredHooks = ['displayHeader', 'displayProductAdditionalInfo', 'actionCartUpdateQuantityBefore', 'actionBeforeCartUpdateQty'];
$missingHooks = [];

foreach ($requiredHooks as $hook) {
    if (!$module->isRegisteredInHook($hook)) {
        $missingHooks[] = $hook;
    }
}

if (!empty($missingHooks)) {
    die("✖ ERROR: El módulo no está registrado en los hooks: ".implode(', ', $missingHooks)."\n");
}

echo "✓ Registrado en todos los hooks requeridos\n";

// 6. Verificar configuración
$debugMode = Configuration::get('RENTAL_DEBUG_MODE');
echo "✓ Modo debug: ".($debugMode ? 'ACTIVADO' : 'DESACTIVADO')."\n";

// 7. Verificar archivos importantes
$requiredFiles = [
    'rentalmanager.php',
    'controllers/front/rental.php',
    'views/js/rental.js',
    'views/css/rental.css'
];

foreach ($requiredFiles as $file) {
    $fullPath = $module->getLocalPath().$file;
    if (!file_exists($fullPath)) {
        die("✖ ERROR: Archivo faltante: $file\n");
    }
    echo "✓ Archivo presente: $file\n";
}

// 8. Verificar plantillas
$requiredTemplates = [
    'views/templates/hook/product-rental.tpl',
    'views/templates/front/modal.tpl',
    'views/templates/admin/configure.tpl'
];

foreach ($requiredTemplates as $template) {
    $fullPath = $module->getLocalPath().$template;
    if (!file_exists($fullPath)) {
        die("✖ ERROR: Plantilla faltante: $template\n");
    }
    echo "✓ Plantilla presente: $template\n";
}

echo "\n=== Todas las verificaciones pasaron correctamente ===\n";
echo "Por favor revise el archivo de log para más detalles: $logPath\n";