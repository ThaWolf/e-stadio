<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	http://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There area two reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router what URI segments to use if those provided
| in the URL cannot be matched to a valid route.
|
*/

/* USER */
$route['logout\.html$'] = 'user/logout';
$route['perfil/(.+)\.html$'] = 'user/profile/$1';
$route['user/([0-9]+)'] = 'user/profile/null/$1';
$route['ajax_login'] = 'user/ajax_login';
$route['ajax_register'] = 'user/ajax_register';
$route['ajax_update_profile'] = 'user/ajax_update_profile';
$route['ajax_add_account'] = 'user/ajax_add_account';
$route['ajax_remove_account/([0-9]+)'] = 'user/ajax_remove_account/$1';
$route['ajax_user_update_image'] = 'user/ajax_user_update_image';


/* TEAM */
$route['equipo/([0-9]+)/(.+)\.html$'] = 'team/view/$1/$2';
$route['equipo/([0-9]+)'] = 'team/view/$1';
$route['ajax_create_team'] = 'user/ajax_create_team';
$route['ajax_accept_team_invitation'] = 'team/ajax_accept_request';
$route['ajax_decline_team_invitation'] = 'team/ajax_decline_request';
$route['ajax_send_request'] = 'team/ajax_send_request';
$route['ajax_delete_team'] = 'team/ajax_delete_team';
$route['ajax_remove_membership'] = 'team/ajax_remove_membership';
$route['ajax_assign_captain'] = 'team/ajax_assign_captain';
$route['ajax_set_sup1'] = 'team/ajax_set_sup1';
$route['ajax_set_sup2'] = 'team/ajax_set_sup2';
$route['ajax_decline_request'] = 'team/ajax_decline_request';
$route['ajax_team_update_image/([0-9]+)'] = 'team/ajax_team_update_image/$1';


/* TOURNAMENT */
$route['torneo/([0-9]+)/(.*)\.html$'] = 'tournament/view_tournament/$1/$2';
$route['torneos'] = 'tournament/all_tournaments';
$route['ajax_tournament_inscript_user/([0-9]+)'] = 'tournament/ajax_tournament_inscript_user/$1'; 
$route['ajax_tournament_inscript_team/([0-9]+)'] = 'tournament/ajax_tournament_inscript_team/$1';
$route['ajax_tournament_subscribe/([0-9]+)'] = 'tournament/ajax_tournament_subscribe/$1'; 
$route['ajax_tournament_confirm/([0-9]+)'] = 'tournament/ajax_tournament_confirm/$1';
$route['ajax_leave_tournament'] = 'tournament/ajax_leave_tournament';

/* MATCH */
$route['partido/([0-9]+)\.html'] = 'tournament/view_match/$1';
$route['ajax_send_match_result/([0-9]+)'] = 'tournament/ajax_send_match_result/$1';

/* CONTACT & US*/ 

$route['contact'] = 'contact';
$route['about'] = 'about';

/* FAQS */

$route['faqs'] = 'faqs';

/* SEARCH */

$route['search'] = 'search';
/* ADMIN */
$route['admin/torneos'] = 'admin/tournaments';
$route['admin/torneos/nuevo'] = 'admin/new_tournament';
$route['admin/torneos/editar/([0-9]+)'] = 'admin/edit_tournament/$1';
$route['admin/ajax_new_tournament'] = 'admin/ajax_new_tournament';
$route['admin/ajax_edit_tournament/([0-9]+)'] = 'admin/ajax_edit_tournament/$1';
$route['admin/backup'] = 'admin/backup';

/* CRON */
$route['cron/(.+)'] = 'cron/$1';

$route['ajax_upload_image'] = 'front/ajax_upload_image';
$route['default_controller'] = 'front';

/* End of file routes.php */
/* Location: ./application/config/routes.php */