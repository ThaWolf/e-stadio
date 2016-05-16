<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class User extends CI_Controller {
    
    public function ajax_login() {
        require_ajax_request();        
        require_not_logged();
        
        $this->load->model('user_model');
        $this->form_validation->set_rules('username', 'Usuario', 'xss_clean|required');
        $this->form_validation->set_rules('password', 'Contraseña', 'xss_clean|required');
        
        if (!$this->form_validation->run()){
            $this->response->messages('error', array(
                form_error('username'),
                form_error('password')
            ));
        }
        else if (!$this->user_model->login($this->input->post('username'), $this->input->post('password'))){
            $this->response->messages('error', array(
                'El usuario o contraseña no coinciden'
            ));
        }
        else{
            $this->response->json(array(
               'mode' => 'reload'
            ));
        }
    }

    public function ajax_register() {        
        require_ajax_request();        
        require_not_logged();
        
        $this->load->model('user_model');
        $this->form_validation->set_rules('username', 'Usuario', 'xss_clean|required|min_length[5]|max_length[20]|alpha_dash');
        $this->form_validation->set_rules('email', 'Email', 'xss_clean|required|valid_email');
        $this->form_validation->set_rules('password', 'Contraseña', 'xss_clean|required|matches[repassword]|min_length[6]|max_length[20]');
        $this->form_validation->set_rules('repassword', 'Repetir Contraseña', 'xss_clean|required');
        
        
        if (!$this->form_validation->run()){
            $this->response->messages('error', array(
                form_error('username'),
                form_error('email'),
                form_error('password'),
                form_error('repassword')                
            ));
        }
        else if($this->user_model->email_exist($this->input->post('email'))){                    
            $this->response->messages('error', array(
               'El email ingresado ya se encuentra registrado'
            ));            
        }
        else if($this->user_model->username_exist($this->input->post('username'))){                    
            $this->response->messages('error', array(
               'El nombre de usuario ingresado ya se encuentra registrado'
            ));            
        }
        else{
            $this->user_model->register(array(
                'username' => $this->input->post('username'),
                'email' => $this->input->post('email'),
                'password' => $this->input->post('password')
            ));
            // send email
            $this->response->json(array(
               'mode' => 'reload'
            ));
        }
    }
    
    public function logout() {
        require_logged_in_dt();
        $this->session->unset_userdata('user');
        $this->session->sess_destroy();
        redirect('/');
    }
    
    public function profile($username, $id = false) {
        $this->load->model('user_model');
        $this->load->model('game_model');
        $this->load->model('team_model');
        
        if (!$id){
            $user = $this->user_model->get_by_username($username);
        } else {
            $user = $this->user_model->get_by_id($id);
        }
        
        if (!$user){
            show_404();
        }        
        
        if ($id){
            redirect('/perfil/'.text_normalize($user->username).'.html');
        }
        
        $params = array(
            'page_name' => 'profile',
            'page_title' => 'Perfil de '.$user->username
        );
        
        $user->iam = get_user() && $user->id === get_user()->id; 
        $user->accounts = $this->user_model->get_accounts($user->id);  
        $user->teams = $this->user_model->get_teams($user->id);
        $user->requests = $this->user_model->get_team_request(get_user()->id);
        
        if ($user->iam){            
            $params['games'] = $this->game_model->get_all(true, false);
            $params['games_children'] = $this->game_model->get_all(false, false);           
            $user->team_requests = $this->user_model->get_team_request($user->id);
        }
        
        $params['user'] = $user;
        $this->load->model('tournament_model');


        if (is_logged()){ 
            $user =  $this->user_model->get_by_id(get_user()->id);
            $this->load->model('images_model');
            $params['userimg'] = '/uploads/' . $this->images_model->get_filename($user->image); 

            $params['username'] = strtoupper($user->username);
        }

        $params['active_tournaments'] = $this->tournament_model->get_actives();
        $params['coming_tournaments'] = $this->tournament_model->get_coming();        
        $this->twig->cache_display(strtolower(__CLASS__) . '/profile', $params);
    }
    
    public function ajax_update_profile(){        
        require_ajax_request();
        require_logged_in_dt();
        
        $this->load->model('user_model');
        
        $this->form_validation->set_rules('first_name', 'Nombre', 'xss_clean|required|min_length[4]|max_length[30]');
        $this->form_validation->set_rules('last_name', 'Apellido', 'xss_clean|required|min_length[4]|max_length[30]');
        $this->form_validation->set_rules('dni', 'Documento', 'xss_clean|required|is_natural_no_zero|exact_length[8]');
        $this->form_validation->set_rules('url', 'Página Web', 'xss_clean|valid_url');
        
        $user = $this->user_model->get_by_id(get_user()->id);
        
        if (!$this->form_validation->run()){
            $this->response->messages('error', array(
                form_error('first_name'),
                form_error('last_name'),
                form_error('dni'),
                form_error('url')                
            ));
        }
        else if (!valid_password($this->input->post('password'))){
            $this->response->messages('info', array(
                'Contraseña Incorrecta'
            )); 
        }
        else if($user->dni != $this->input->post('dni') && $this->user_model->dni_exist($this->input->post('dni'))){                    
            $this->response->messages('error', array(
               'El DNI ingresado ya se encuentra registrado'
            ));            
        }
        else{            
            $this->user_model->update_user(get_user()->id, array(
                'first_name' => $this->input->post('first_name'),
                'last_name' => $this->input->post('last_name'),
                'dni' => $this->input->post('dni'),
                'url' => $this->input->post('url'),                    
             ));
            $this->response->json(array(
               'mode' => 'reload'
            ));
        }
    }
    
    public function ajax_add_account(){     
        require_ajax_request();
        require_logged_in();
        
        $this->load->model('user_model');
        $this->load->model('game_model');
        
        $this->form_validation->set_rules('username', 'Usuario', 'xss_clean|required|regex_match[/^[a-z0-9 \#\.\-\_\/]+$/i]');
        $this->form_validation->set_rules('game', 'Juego', 'xss_clean|required|is_natural_no_zero');

        $gamerule = true;


        $game = $this->game_model->get_by_id($this->input->post('game'));


        if (intval($game->id) === 2){

            $nickname = explode('#', $this->input->post('username'));

            if (count($nickname) !== 2){   
                $this->response->messages('info', array(
                    'El usuario de Battle.net debe tener el siguiente formato: usuario#12345'
                ));        
                $gamerule = false;
            } else {
                if ((!preg_match('/^[0-9]*$/', $nickname[1])) || (strlen($nickname[0]) < 4)){
                    $this->response->messages('info', array(
                        'El usuario de Battle.net debe tener el siguiente formato: usuario#12345'
                    ));  
                    $gamerule = false;
                } else {
                    if (!((intval($nickname[1]) > 99) && (intval($nickname[1] < 99999)))){
                        $this->response->messages('info', array(
                            'El usuario de Battle.net debe tener entre 3 y 5 dígitos numéricos luego del símbolo #'
                        ));         
                        $gamerule = false;                
                    }
                }
                
            }
        }
        
        if ($gamerule){ 
            if ($this->form_validation->run()){
                $game = $this->game_model->get_by_id($this->input->post('game'));
            }
            
            if (!$this->form_validation->run() || !$gamerule){
                $this->response->messages('error', array(
                    form_error('username'),
                    form_error('game')               
                ));
            }
            else if (!valid_password($this->input->post('password'))){
                $this->response->messages('info', array(
                    'Contraseña Incorrecta'
                )); 
            }
            else if(!$game){
                $this->response->messages('error', array(
                    'El juego seleccionado no existe'
                ));    
            }
            else if($this->user_model->has_game_account(get_user()->id, $game->id)){
                $this->response->messages('warning', array(
                    'Ya tienes una cuenta vinculada a este juego'
                )); 
            }
            else if($this->user_model->account_username_exists($this->input->post('username'), $game->id)){
                $this->response->messages('info', array(
                    'El nombre de usuario  ya está ocupado. Si eres el propietario comunícate con algún administrador'
                ));             
            }
            else{
                $this->user_model->vinculate_account(get_user()->id, $game->id, $this->input->post('username'));
                $this->response->json(array(
                   'mode' => 'reload'
                ));
            }
        }
    }
    
    public function ajax_remove_account($account_id){
        require_ajax_request();
        require_logged_in();
        
        $this->load->model('user_model');
        
        $account = $this->user_model->get_account($account_id, get_user()->id);
        
        if (!$account){
            $this->response->json(array(
               'mode' => 'reload'
            ));
        }
        else if (!valid_password($this->input->post('password'))){
            $this->response->messages('info', array(
                'Contraseña Incorrecta'
            )); 
        }
        else if ($this->user_model->has_game_team(get_user()->id ,$account->game_id )) {
            $this->response->messages('info', array(
                'No puedes borrar una cuenta que pertenece a un equipo'
            ));             
        }
        else if (count($this->user_model->actives_individual_tournaments(get_user()->id, $account->game_id)) > 0){
            $this->response->messages('info', array(
                'Primero finaliza los torneos que estés jugando con esta cuenta'
            )); 
        }
        else{
            $this->user_model->remove_account($account->id);
            $this->response->json(array(
               'mode' => 'reload'
            ));
        }
    }
    
    public function ajax_user_update_image(){
        require_ajax_request();
        require_logged_in();
        
        $this->load->model('images_model');
        $this->load->model('user_model');
        
        $this->form_validation->set_rules('image_id', 'Imagen', 'xss_clean|required|is_natural_no_zero');
        
        if(!$this->form_validation->run()){
            $this->response->messages('error', form_errors());
        }
        else if (!$this->images_model->get_filename($this->input->post('image_id'))){
            $this->response->json(array(
               'mode' => 'reload'
            ));
        }
        else{
            $this->user_model->update_user(get_user()->id, array(
                'image' => $this->input->post('image_id')
            ));
            set_user_attr('image', $this->input->post('image_id'));
            $this->response->json(array(
               'mode' => 'reload'
            ));
        }
    }
    
    public function ajax_create_team() {         
        require_ajax_request();
        require_logged_in();
        
        $this->load->model('user_model');
        $this->load->model('game_model');
        $this->load->model('team_model');
        
        $this->form_validation->set_rules('team_name', 'Nombre de Equipo', 'xss_clean|required|min_length[3]|max_length[20]|alphabetic');
        $game = $this->game_model->get_by_id($this->input->post('game'));

        if (!$this->form_validation->run()){
            $this->response->messages('error', form_errors());               
        }
        else if (!valid_password($this->input->post('password'))){
            $this->response->messages('info', array(
                'Contraseña Incorrecta'
            )); 
        }
        else if (!$game){            
            $this->response->messages('error', array(
                'El juego seleccionado no existe'
            ));                 
        }
        else if ($this->user_model->has_game_team(get_user()->id, $game->id)){         
            $this->response->messages('error', array(
                'Ya tienes un equipo de '.$game->name
            ));               
        }
        else if (!$this->user_model->has_game_account(get_user()->id, $game->id)) {
            $this->response->messages('error', array(
                'Necesitas tener una cuenta asociada al juego '.$game->name . ' para crear un equipo del mismo'
            ));   
        }
        else if ($this->team_model->name_exist($this->input->post('team_name'), $game->id)){         
            $this->response->messages('error', array(
                'El equipo '.$this->input->post('team_name').' de '.$game->name. ' ya existe, prueba con otro'  
            ));                 
        }
        else{

            $team_id = $this->team_model->create_team($this->input->post('team_name'), $game->id, get_user()->id);
            $this->team_model->create_first_membership($team_id, get_user()->id);            
            $this->response->json(array(
               'mode' => 'redirect',
               'page' => '/equipo/'.$team_id
            ));
        }
    }
    
}