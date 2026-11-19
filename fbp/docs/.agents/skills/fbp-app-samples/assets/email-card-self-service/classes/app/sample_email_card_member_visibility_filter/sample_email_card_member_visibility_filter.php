<?php
/** The starter uses one shared administrator list. Adapt organization scope in both places. */
class sample_email_card_member_visibility_filter {
    function can_show_menu(Controller $ctl,string $table='',array $settings=[]): bool {return $ctl->is_app_admin();}
    function can_access(Controller $ctl,string $function='',string $table='',array $settings=[]): bool {return $ctl->is_app_admin();}
}
