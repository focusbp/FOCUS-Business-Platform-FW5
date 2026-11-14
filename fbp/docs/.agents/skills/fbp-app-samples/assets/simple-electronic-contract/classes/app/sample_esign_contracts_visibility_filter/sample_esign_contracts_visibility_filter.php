<?php
require_once dirname(__DIR__).'/sample_esign/Service.php';
/** Standard Screen is a read-only list; state/files cannot be edited or exported. */
class sample_esign_contracts_visibility_filter {
    function can_show_menu(Controller $c,string $table='',array $settings=[]): bool {return SampleESignGuard::allowed($c);}
    function can_access(Controller $c,string $function='',string $table='',array $settings=[]): bool {
        if(!SampleESignGuard::allowed($c)||!in_array($function,['page','rows','reload','search','search_exe','sort','sort_exe','search_reset','reset_search'],true))return false;
        SampleESign::refreshList($c);return true;
    }
}
