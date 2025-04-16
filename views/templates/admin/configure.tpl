<div class="panel rental-manager-panel">
    <div class="panel-heading">
        <i class="icon-cogs"></i> {l s='Configuración del Gestor de Arriendos' mod='rentalmanager'}
    </div>
    
    <div class="module-info-box">
        <h4><i class="icon-info-circle"></i> {l s='Información del Módulo' mod='rentalmanager'}</h4>
        <ul>
            <li><strong>{l s='Versión:' mod='rentalmanager'}</strong> {$module_version|escape:'html':'UTF-8'}</li>
            <li><strong>{l s='Compatibilidad:' mod='rentalmanager'}</strong> PrestaShop 8.x</li>
            <li><strong>{l s='Autor:' mod='rentalmanager'}</strong> Tu Nombre</li>
        </ul>
    </div>
    
    <div class="form-wrapper">
        <form action="{$form_action|escape:'html':'UTF-8'}" method="post" name="rental_settings">
            <div class="form-group">
                <label class="control-label col-lg-3">
                    {l s='Modo Debug' mod='rentalmanager'}
                </label>
                <div class="col-lg-9">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="RENTAL_DEBUG_MODE" id="RENTAL_DEBUG_MODE_on" value="1" {if $rental_settings.debug_mode}checked{/if}>
                        <label for="RENTAL_DEBUG_MODE_on">{l s='Activado' mod='rentalmanager'}</label>
                        <input type="radio" name="RENTAL_DEBUG_MODE" id="RENTAL_DEBUG_MODE_off" value="0" {if !$rental_settings.debug_mode}checked{/if}>
                        <label for="RENTAL_DEBUG_MODE_off">{l s='Desactivado' mod='rentalmanager'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                    <p class="help-block">
                        {l s='Activa esta opción para registrar información detallada de depuración en el archivo log' mod='rentalmanager'}
                    </p>
                </div>
            </div>
            
            <div class="form-group">
                <label class="control-label col-lg-3">
                    {l s='Archivo Log' mod='rentalmanager'}
                </label>
                <div class="col-lg-9">
                    {if $rental_settings.log_file_exists}
                        <p class="form-control-static">
                            <i class="icon-file-text"></i> 
                            <strong>{$rental_settings.log_file_path|escape:'html':'UTF-8'}</strong>
                            <br>
                            <strong>{$rental_settings.log_file_size|escape:'html':'UTF-8'}</strong>
                        </p>
                        <div class="btn-group">
                            <a href="{$form_action|escape:'html':'UTF-8'}&downloadLog=1" class="btn btn-default btn-download-log">
                                <i class="icon-download"></i> {l s='Descargar Log' mod='rentalmanager'}
                            </a>
                            <a href="{$form_action|escape:'html':'UTF-8'}&clearLog=1" class="btn btn-danger">
                                <i class="icon-trash"></i> {l s='Limpiar Log' mod='rentalmanager'}
                            </a>
                        </div>
                    {else}
                        <p class="form-control-static text-warning">
                            <i class="icon-warning"></i> 
                            {l s='No se encontró archivo de log' mod='rentalmanager'}
                        </p>
                    {/if}
                </div>
            </div>
            
            <div class="panel-footer">
                <button type="submit" name="submitRentalSettings" class="btn btn-default pull-right">
                    <i class="process-icon-save"></i> {l s='Guardar Configuración' mod='rentalmanager'}
                </button>
            </div>
        </form>
    </div>
    
    <div class="panel-footer">
        <div class="text-center">
            <small class="text-muted">
                {l s='Módulo Rental Manager v%version% - %year%' mod='rentalmanager' sprintf=['%version%' => $module_version|escape:'html':'UTF-8', '%year%' => '2023']}
            </small>
        </div>
    </div>
</div>