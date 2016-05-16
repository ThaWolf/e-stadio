<?php


if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Team extends CI_Controller {



    public function view($team_id, $team_name) {
        require_logged_in();

        $this->load->model('team_model');
        $this->load->model('user_model');

       
        $team = $this->team_model->get_by_id($team_id, array(
            'wgame',
            'wmembers',
            'wrequests'
        ));

        if (!$team) {
            redirect('/');
        } else if (text_normalize($team->name) !== $team_name) {
            redirect('/equipo/' . $team_id . '/' . text_normalize($team->name) . '.html');
        }

        /*if ($this->input->post('flag') === 'ascend_as_captain' && $is_captain) {
            $user = $this->user_model->get_by_id($this->input->post('user_id'));
            if (!$user) {
                $this->internal->push_message('warning', 'El usuario no existe');
            } else if (!$this->team_model->is_member($team->id, $user->id)){
                $this->internal->push_message('info', 'El usuario '.$user->username.' no es parte de este equipo');                
            } else if ($user->id === get_user()->id) {
                $this->internal->push_message('info', 'Ya eres el capitán');
            } else {
                $this->team_model->update_team($team->id, array(
                    'user_id' => $user->id
                ));
                $this->internal->push_message('success', 'Se ha asignado a ' . $user->username . ' como capitán');
                $team->user_id = $user->id;
                $is_captain = false;
            }
        } 
        else if ($this->input->post('flag') === 'leave' && !$is_captain) {
            $this->team_model->remove_membership($team->id, get_user()->id);
        }
        else if ($this->input->post('flag') === 'remove_request' && $is_captain){
            $team_request = $this->team_model->get_team_request($team->id, $this->input->post('user_id'));
            if (!$team_request){
                $this->internal->push_message('warning', 'No se pudo encontrar la solicitud'); 
            }
            else{
                $this->team_model->remove_team_request($team_request->id);
            }
        }
        else if ($this->input->post('flag') === 'remove_member' && $is_captain){
            if ($this->input->post('user_id') === $team->user_id){                
                $this->internal->push_message('info', 'No se puede expulsar al capitán');
            }
            else{
                $this->team_model->remove_membership($team->id, $this->input->post('user_id'));
            }
        }*/
        $members_count = count($team->members); 


        $team_limit = $team->limit;



        $newTeamMembers = array();
        foreach ($team->members as $m) {
            if ($this->team_model->is_captain($team->id, $m->id)){
                array_push($newTeamMembers, $m);
            }
        }
        foreach ($team->members as $m) {
            if ((!$this->team_model->is_sup1($team_id, $m->id)) && (!$this->team_model->is_sup2($team_id, $m->id)) && (!$this->team_model->is_captain($team->id, $m->id)) ) {
                array_push($newTeamMembers, $m);
            }
        }
        foreach ($team->members as $m) {
            if ($this->team_model->is_sup1($team_id, $m->id)) {
                array_push($newTeamMembers, $m);
            }
        }  
        foreach ($team->members as $m) {
            if ($this->team_model->is_sup2($team_id, $m->id)) {
                array_push($newTeamMembers, $m);
            }
        } 
        

        
        $team->members = $newTeamMembers;



        $options = array(
            'page_name' => 'team',
            'page_title' => $team->name,
            'team' => $team,
            'members_count' => $members_count,
            'team_limit' => $team_limit,
            'sup1_id' => $team->sup1_id,
            'sup2_id' => $team->sup2_id
        );
        
        if (is_logged()){ 
            $user =  $this->user_model->get_by_id(get_user()->id);
            $this->load->model('images_model');
            $options['userimg'] = '/uploads/' . $this->images_model->get_filename($user->image); 

            $options['username'] = strtoupper($user->username);
        } 

        
        $this->load->model('tournament_model');

        $options['active_tournaments'] = $this->tournament_model->get_actives();
        $options['coming_tournaments'] = $this->tournament_model->get_coming();
        $this->twig->cache_display(strtolower(__CLASS__) . '/view', $options);
    }

    public function ajax_send_request(){
        require_ajax_request();
        require_logged_in();
        
        $this->load->model('team_model');
        $this->load->model('user_model');
        
        $team = $this->team_model->get_by_id(post_input('team_id'));
        $user = $this->user_model->get_by_username(post_input('username'));
        
        
        if (!valid_password(post_input('password'))){
            $this->response->messages('error', array(
               'La contraseña es incorrecta'
            ));              
        }
        else if (!$team){            
            $this->response->json(array(
               'mode' => 'reload'
            ));
        }
        else if (!$this->team_model->is_member($team->id, get_user()->id)){                  
            $this->response->json(array(
               'mode' => 'reload'
            ));
        }
        else if (!$user) {
            $this->response->messages('error', array(
               'El usuario no existe'
            ));     
        }
        else if ($this->team_model->team_request_exists($team->id, $user->id)) {
            $this->response->messages('error', array(
               'Ya hay una solicitud pendiente'
            ));
        } else if ($this->team_model->is_member($team->id, $user->id)) {
            $this->response->messages('error', array(
               'El usuario ya es miembro del equipo'
            ));
        } else if ($this->team_model->team_is_complete($team->id)) {
            $this->response->messages('error', array(
               'El equipo está lleno'
            ));
        } else if (!$this->user_model->has_game_account($user->id, $team->game_id)) {
            $this->response->messages('error', array(
               'El usuario no tiene una cuenta asociada para el juego '
            ));
        } else if ($this->user_model->has_game_team($user->id, $team->id)) {
            $this->response->messages('error', array(
               'El usuario ya tiene un equipo para el juego ' . $team->game_name
            ));
        } else {
            $this->team_model->send_team_request($team->id, $user->id);       
            $this->response->json(array(
               'mode' => 'reload'
            ));
        }
    }
    
    public function ajax_team_update_image($team_id){
        require_ajax_request();
        require_logged_in();
        
        $this->load->model('images_model');
        $this->load->model('team_model');
        
        $team = $this->team_model->get_by_id($team_id);
        
        $this->form_validation->set_rules('image_id', 'Imagen', 'xss_clean|required|is_natural_no_zero');
        
        if (!$team){
            $this->response->json(array(
               'mode' => 'reload'
            ));
        }
        else if (!$team->iamcaptain){            
            $this->response->json(array(
               'mode' => 'reload'
            ));
        }
        else if(!$this->form_validation->run()){
            $this->response->messages('error', form_errors());
        }
        else if (!$this->images_model->get_filename($this->input->post('image_id'))){
            $this->response->json(array(
               'mode' => 'reload'
            ));
        }
        else{
            $this->team_model->update_team($team->id, array(
                'image' => $this->input->post('image_id')
            ));
            $this->response->json(array(
               'mode' => 'reload'
            ));
        }
    }

    public function ajax_decline_request(){       
        require_ajax_request();
        require_logged_in();
        
        $this->load->model('team_model');
        
        $team_request = $this->team_model->get_team_request_by_id($this->input->post('team_request'), get_user()->id);
        
        if (!$team_request){
            $this->response->messages('error', array(
                'No existe la solicitud'
            ));  
        }
        else if (!valid_password($this->input->post('password'))){
            $this->response->messages('info', array(
                'Contraseña Incorrecta'
            )); 
        }
        else{
            $this->team_model->remove_team_request($team_request->id);         
            $this->response->json(array(
               'mode' => 'reload'
            ));
        }
        
    }
 
    public function ajax_set_sup1(){
        require_ajax_request();
        require_logged_in();

        $this->load->model('team_model');

        $tid = $this->input->post('team_id');
        $mid = $this->input->post('user_id');        

        $this->team_model->set_sup1($tid, $mid);
        $this->response->json(array(
           'mode' => 'reload'
        ));     
    }

    public function ajax_set_sup2(){
        require_ajax_request();
        require_logged_in();

        $this->load->model('team_model');

        $tid = $this->input->post('team_id');
        $mid = $this->input->post('user_id');        

        $this->team_model->set_sup2($tid, $mid);
        $this->response->json(array(
           'mode' => 'reload'
        ));     
    }

    public function ajax_remove_membership(){
        require_ajax_request();
        require_logged_in();
        
        $this->load->model('team_model');

        $tid = $this->input->post('team_id');
        $mid = $this->input->post('user_id');
        $this->team_model->remove_membership($tid, $mid);
        $this->response->json(array(
           'mode' => 'reload'
        ));
    }

    public function ajax_assign_captain(){
        require_ajax_request();
        require_logged_in();
        
        $this->load->model('team_model');

        $tid = $this->input->post('team_id');
        $mid = $this->input->post('user_id');
        $this->team_model->assign_captain($tid, $mid);
        $this->response->json(array(
           'mode' => 'reload'
        ));
    }


    public function ajax_delete_team(){
        require_ajax_request();
        require_logged_in();
        
        $this->load->model('team_model');
        $this->load->model('tournament_model');

        if ($this->team_model->team_in_tournament($this->input->post('team_id'))){
            $this->response->messages('error', array(
                'No puedes borrar un equipo que esta inscripto a un torneo'
            ));  
        }
        else { 
            $this->team_model->delete_team($this->input->post('team_id'), get_user()->id);
            $this->response->json(array(
               'mode' => 'reload'
            ));
        }
    }

    public function ajax_accept_request(){
        require_ajax_request();
        require_logged_in();
        
        $this->load->model('team_model');
        
        $team_request = $this->team_model->get_team_request_by_id($this->input->post('team_request'), get_user()->id, true);
        

        if (!$team_request){
            $this->response->messages('error', array(
                'No existe la solicitud'
            )); 
        }
        else if (!valid_password($this->input->post('password'))){
            $this->response->messages('info', array(
                'Contraseña Incorrecta'
            )); 
        }
        else if ($this->user_model->has_game_team(get_user()->id, $team_request->game_id)){
            $this->response->messages('error', array(
                'Ya perteneces a un equipo del juego '.$team_request->game_name
            ));              
        }
        else if ($this->team_model->team_is_complete($team_request->team_id)){
            $this->response->messages('error', array(
                'El equipo '.$team_request->team_name.' está completo'
            ));  
        }
        else{
            $this->team_model->remove_team_request($team_request->id);
            $this->team_model->create_membership($team_request->team_id, get_user()->id);         
              $this->response->json(array(
               'mode' => 'reload'
            ));
        }
    }
 
}
