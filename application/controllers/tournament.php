<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Tournament extends CI_Controller {

    public function all_tournaments (){
        $this->load->model('user_model');
        $this->load->model('tournament_model');  


        $params = array(
            'page_name' => 'tournaments view',
        );

        $params['active_tournaments'] = $this->tournament_model->get_actives();
        $params['coming_tournaments'] = $this->tournament_model->get_coming();       



        if (is_logged()){ 
            $user =  $this->user_model->get_by_id(get_user()->id);
            $this->load->model('images_model');
            $params['userimg'] = '/uploads/' . $this->images_model->get_filename($user->image); 

            $params['username'] = strtoupper($user->username);
        }
        $this->twig->cache_display(strtolower(__CLASS__) . '/tournaments', $params);


    }

    public function view_tournament ($tournament_id, $title){
        $this->load->model('user_model');
        $this->load->model('tournament_model');
        $this->load->model('team_model');

        $tournament = $this->tournament_model->get_tournament($tournament_id);

        

        // COMPROBAR EXISTENCIA
        if (!$tournament) {
            show_404();
        }
        else if (text_normalize($tournament->name) !== $title) {
            redirect('/torneo/' . $tournament_id . '/' . text_normalize($tournament->name) . '.html');
        }
        
        $tournament->inscription = false;
        // TRAER INSCRIPCION
        if (get_user()){
            if ($tournament->team_require){    
                $tournament->inscription = $this->tournament_model->get_team_inscription($tournament->id, get_user()->id);
            }
            else{            
                $tournament->inscription = $this->tournament_model->get_user_inscription($tournament->id, get_user()->id);
            }
        }        
        
        if ($tournament->team_require){    
            $tournament->participants = $this->tournament_model->get_inscripted_teams($tournament->id);            
        }
        else{            
            $tournament->participants = $this->tournament_model->get_inscripted_users($tournament->id, $tournament->game_id);            
        }
        
        // ME FIJO LA ETAPA
        if ($tournament->start_at <= get_date()->getTimestamp()){
            $tournament->state = 'started';
            
            // GENERO LA VISTA DE BRACKETS
            if (!is_null($tournament->brackets)){            

                $tournament->view = new stdClass();        
                $couples = array();
                $results = array();
                $match_ids = array();
                
                foreach ($tournament->brackets as $round => $bracket){
                    $result = array();
                    
                    foreach($bracket as $match){
                        $match_result = array();
                        if (!$match){
                            continue;
                        }
                        
                        if ($round == $tournament->settings->quantities->brackets){
                            $couple = array();
                            if ($match->player1){
                                $couple[] = $match->player1->name;
                            }
                            else{                    
                                $couple[] = '';
                            }

                            if ($match->player2){
                                $couple[] = $match->player2->name;
                            }else{
                                $couple[] = '';
                            }
                            
                            $couples[] = $couple;
                        }
                                                
                        if (isset($match->match_id) && $match->match_id){
                            $match_ids[$match->player1->name.'|'.$match->player2->name] = $match->match_id;
                        }
                        
                        if (isset($match->match_id)){
                            $match_db = $this->tournament_model->get_match($match->match_id);
                            if (!is_null($match_db->winner)){
                                $match_result[0] = $match_db->data->p1_wins;
                                $match_result[1] = $match_db->data->p2_wins;
                            }
                        }
                        else if (is_null($match->player1) && is_null($match->player2)){                            
                            $match_result[0] = -5;
                            $match_result[1] = -6;
                        }
                        $result[] = $match_result;  
                        
                    }                    
                    if ($round === THIRD_PLACE){
                        $results[count($results) - 1] = array_merge($results[count($results) - 1], $result);
                    }
                    else{
                        $results[] = $result;
                    }
                }  
                $tournament->view->results = json_encode($results);
                $tournament->view->couples = json_encode($couples);
                $tournament->view->ids = json_encode($match_ids);
            }
        }
        else if ($tournament->inscription && !is_null($tournament->inscription->confirmed_at)){
            $tournament->state = 'already_confirmed';
        }
        else if($tournament->inscription && $tournament->time_to_confirm){
            $tournament->state = 'confirm';
        }
        else if($tournament->inscription){
            $tournament->state = 'waiting_to_confirm';
        }
        else if(count($tournament->participants) < $tournament->vacancies){
            if ($tournament->team_require){
                $tournament->state = 'team_inscription';
            }
            else{
                $tournament->state = 'user_inscription';                
            }
        }
        else if($tournament->time_to_confirm){
            $tournament->state = 'time_to_confirm';
        }
        else if(count($tournament->participants) >= $tournament->vacancies){
            $tournament->state = 'is_full';
        }
        
        if ($tournament->team_require){
            $tipo = 'equipo';
            $teamuser_id = $this->team_model->find_my_team_id(get_user()->id, $tournament->game_id);
        } else {
            $tipo = 'user';
            $teamuser_id = get_user()->id;
        }

        $params = array(
            'page_name' => 'tournament view',
            'tournament' => $tournament,
            'tipo' => $tipo,
            'teamuser_id' => $teamuser_id
        );

         $params['active_tournaments'] = $this->tournament_model->get_actives();
        $params['coming_tournaments'] = $this->tournament_model->get_coming();       



        if (is_logged()){ 
            $user =  $this->user_model->get_by_id(get_user()->id);
            $this->load->model('images_model');
            $params['userimg'] = '/uploads/' . $this->images_model->get_filename($user->image); 

            $params['username'] = strtoupper($user->username);
        }
        $this->twig->cache_display(strtolower(__CLASS__) . '/tournament', $params);
    }

    public function ajax_tournament_inscript_user($tournament_id){
        require_ajax_request();
        
        if (get_user()){
            $this->load->model('tournament_model');
            $this->load->model('user_model');
            $tournament = $this->tournament_model->get_tournament($tournament_id);
            $user_points = $this->user_model->get_points(get_user()->id);
            $tournament->inscription = false;
            // TRAER INSCRIPCION
            if (get_user()){
                if ($tournament->team_require){    
                    $tournament->inscription = $this->tournament_model->get_team_inscription($tournament->id, get_user()->id);
                    $tournament->participants = $this->tournament_model->get_inscripted_teams($tournament->id);            
                }
                else{            
                    $tournament->inscription = $this->tournament_model->get_user_inscription($tournament->id, get_user()->id);
                    $tournament->participants = $this->tournament_model->get_inscripted_users($tournament->id, $tournament->game_id);            
                }
            }
        }
        
        if (!get_user()){              
            $this->response->json(array(
               'mode' => 'reload'
            ));
        }
        else if(!$tournament){              
            $this->response->json(array(
               'mode' => 'home'
            ));            
        }
        else if (!confirmed()){
            $this->response->messages('warning', array(
                'Primero debes confirmar tu cuenta'
            ));            
        }
        else if (!has_complete_profile()){
            $this->response->messages('warning', array(
                'Primero debes completar tu perfil'
            ));            
        }
        else if (get_date()->getTimestamp() >= $tournament->start_at) {
            $this->response->messages('error', array(
                'El torneo ya comenzó'
            ));
        }
        else if (count($tournament->participants) >= $tournament->vacancies) {            
            $this->response->messages('error', array(
                'Los cupos están completos'
            ));
        }
        else if ($tournament->inscription) {            
            $this->response->messages('info', array(
                'Ya te encuentras inscripto'
            ));
        }
        else if (!$this->user_model->has_game_account(get_user()->id, $tournament->game_id)) {           
            $this->response->messages('warning', array(
                'Para inscribirte en este torneo primero debes vincular una cuenta de ' . $tournament->game_name
            ));
        }
        else if ($tournament->price > $user_points) {
            $this->response->messages('error', array(
                'No tienes puntos suficientes'
            ));
        }
        else{
            $confirmed = ($tournament->time_to_confirm) ? get_date()->getTimestamp() : null;
            $this->tournament_model->inscribe_user(array(
                'tournament_id' => $tournament->id,
                'user_id' => get_user()->id,
                'confirmed_at' => $confirmed,
                'created_at' => get_date()->getTimestamp()
            ));  
            $this->user_model->update_user(get_user()->id, array(
                'points' => $user_points - $tournament->price
            ));
            $this->response->json(array(
               'mode' => 'reload'
            ));
        }
    }

    public function ajax_tournament_inscript_team($tournament_id){
        require_ajax_request();
        if (get_user()){
            $this->load->model('tournament_model');
            $this->load->model('user_model');
            $this->load->model('team_model');
            $tournament = $this->tournament_model->get_tournament($tournament_id);
            $user_points = $this->user_model->get_points(get_user()->id);
            $tournament->inscription = false;
            // TRAER INSCRIPCION
            if (get_user()){
                if ($tournament->team_require){    
                    $tournament->inscription = $this->tournament_model->get_team_inscription($tournament->id, get_user()->id);
                    $tournament->participants = $this->tournament_model->get_inscripted_teams($tournament->id);            
                }
                else{            
                    $tournament->inscription = $this->tournament_model->get_user_inscription($tournament->id, get_user()->id);
                    $tournament->participants = $this->tournament_model->get_inscripted_users($tournament->id, $tournament->game_id);            
                }
            }
        }
        
        if (!get_user()){              
            $this->response->json(array(
               'mode' => 'reload'
            ));
        }
        else if(!$tournament){              
            $this->response->json(array(
               'mode' => 'home'
            ));            
        }
        else if (!confirmed()){
            $this->response->messages('warning', array(
                'Primero debes confirmar tu cuenta'
            ));            
        }
        else if (!has_complete_profile()){
            $this->response->messages('warning', array(
                'Primero debes completar tu perfil'
            ));            
        }
        else if (get_date()->getTimestamp() >= $tournament->start_at) {
            $this->response->messages('error', array(
                'El torneo ya comenzó'
            ));
        }
        else if (count($tournament->participants) >= $tournament->vacancies) {            
            $this->response->messages('error', array(
                'Los cupos están completos'
            ));
        }
        else if ($tournament->inscription) {            
            $this->response->messages('info', array(
                'Ya te encuentras inscripto'
            ));
        }
        else if(!$this->user_model->has_game_team(get_user()->id, $tournament->game_id)) {
             $this->response->messages('error', array(
                'Necesitas tener un equipo de ' . $tournament->game_name
            ));           
        }
        else if (!$this->user_model->has_game_account(get_user()->id, $tournament->game_id)) {           
            $this->response->messages('error', array(
                'Para inscribirte en este torneo primero debes vincular una cuenta de ' . $tournament->game_name
            ));
        }
        else if ($tournament->price > $user_points) {
            $this->response->messages('error', array(
                'No tienes puntos suficientes'
            ));
        }
        else{
            $confirmed = ($tournament->time_to_confirm) ? get_date()->getTimestamp() : null;
            
            $team_id = $this->team_model->find_my_team_id(get_user()->id, $tournament->game_id);

            if (!$this->tournament_model->team_has_min($team_id)){
                $this->response->messages('error', array(
                    'Tu equipo no alcanza el minimo de miembros requeridos'
                ));
            } else { 
            
                $this->tournament_model->inscribe_team(array(
                    'tournament_id' => $tournament->id,
                    'team_id' => $team_id,
                    'confirmed_at' => $confirmed,
                    'created_at' => get_date()->getTimestamp()
                ));  
                $this->user_model->update_user(get_user()->id, array(
                    'points' => $user_points - $tournament->price
                ));
                $this->response->json(array(
                   'mode' => 'reload'
                ));
            }
        }
    }
    
    public function ajax_tournament_subscribe($tournament_id){
        require_ajax_request();
    }
    
    public function ajax_tournament_confirm($tournament_id){
        require_ajax_request();
        
        if (get_user()){
            $this->load->model('tournament_model');
            $this->load->model('user_model');
            $tournament = $this->tournament_model->get_tournament($tournament_id);

            $tournament->inscription = false;
            // TRAER INSCRIPCION
            if (get_user()){
                if ($tournament->team_require === 1){  
                    $tournament->inscription = $this->tournament_model->get_team_inscription($tournament->id, get_user()->id);
                    $tournament->participants = $this->tournament_model->get_inscripted_teams($tournament->id);            
                }
                else{            
                    $tournament->inscription = $this->tournament_model->get_user_inscription($tournament->id, get_user()->id);
                    $tournament->participants = $this->tournament_model->get_inscripted_users($tournament->id, $tournament->game_id);            
                }
            }
        }
        
        if (!get_user()){              
            $this->response->json(array(
               'mode' => 'reload'
            ));
        }
        else if(!$tournament){              
            $this->response->json(array(
               'mode' => 'home'
            ));            
        }
        else if (!$tournament->inscription){        
            $this->response->json(array(
               'mode' => 'reload'
            ));
        }
        else if (!is_null($tournament->inscription->confirmed_at)){        
            $this->response->json(array(
               'mode' => 'reload'
            ));            
        }
        else if(get_date()->getTimestamp() >= $tournament->start_at){
            $this->response->messages('error', array(
                'El torneo ya comenzó'
            ));
        }
        else{
            if ($tournament->team_require){
                $this->tournament_model->confirm_team($tournament->id, $tournament->inscription->team_id);                
            }
            else{
                $this->tournament_model->confirm_user($tournament->id, get_user()->id);
            }     
            $this->response->json(array(
               'mode' => 'reload'
            ));   
        }
    }
    
    private function old_view_team_tournament($tournament) {
        $this->load->model('team_model');
        $now = new \DateTime();
        $inscription = $this->tournament_model->get_team_inscription($tournament->id, get_user()->id);
        $let_me_know = false;
        $tournament->participants = $this->tournament_model->get_inscripted_teams($tournament->id);
        if ($this->input->post('flag') === 'inscript') {
            $this->form_validation->set_rules('team', 'Equipo', 'xss_clean|required|is_natural_no_zero');
            $form_valid = $this->form_validation->run();
            $team = false;
            if ($form_valid) {
                $team = $this->team_model->get_by_id($this->input->post('team'));
            }

            if (!$team || !$form_valid) {
                $this->internal->push_message('warning', 'Elija un equipo de la lista');
            } else if ($now->getTimestamp() >= $tournament->start_at) {
                $this->internal->push_message('info', 'Lo siento :( el torneo ya comenzó');
            } else if (count($tournament->participants) >= $tournament->vacancies) {
                $this->internal->push_message('danger', 'Los cupos están completos');
            } else if ($inscription) {
                $this->internal->push_message('info', 'Ya te encuentras inscripto con el equipo ' . $inscription->team_name);
            } else {
                if ($tournament->price > $team->points) {
                    $this->internal->push_message('danger', 'El equipo no tiene puntos suficientes');
                } else {
                    $this->internal->push_message('success', 'Se ha inscripto al equipo');
                    $confirmed = ($tournament->time_to_confirm) ? $now->getTimestamp() : null;
                    $this->tournament_model->inscribe_team(array(
                        'tournament_id' => $tournament->id,
                        'team_id' => $team->id,
                        'confirmed' => $confirmed,
                        'date' => $now->getTimestamp()
                    ));
                    $this->team_model->update_team(get_user()->id, array(
                        'points' => $team->points - $tournament->price
                    ));
                    $participant = new stdClass();
                    $participant->id = $team->id;
                    $participant->name = $team->name;
                    $tournament->participants[] = $participant;

                    $inscription = new stdClass();
                    $inscription->tournament_id = $tournament->id;
                    $inscription->team_id = $team->id;
                    $inscription->confirmed = $confirmed;
                    $inscription->date = $now->getTimestamp();
                }
            }
        } else if ($this->input->post('flag') === 'confirm') {
            if ($now->getTimestamp() >= $tournament->start_at) {
                $this->internal->push_message('info', 'Lo siento :( el torneo ya comenzó');
            }
            if (!$inscription) {
                $this->internal->push_message('info', 'Ninguno de tus equipos está inscripto en este torneo');
            } else if ($inscription->confirmed) {
                $this->internal->push_message('info', 'El equipo ya confirmó');
            } else if (!$tournament->time_to_confirm) {
                $this->internal->push_message('warning', 'Todavía no comenzó la etapa de confirmación');
            } else {
                $this->internal->push_message('success', 'Tu confirmación se realizó con éxito');
                $this->tournament_model->confirm_team($tournament->id, $inscription->team_id);
                $inscription->confirmed = $now->getTimestamp();
            }
        } else if ($this->input->post('flag') === 'let_me_know') {
            if ($now->getTimestamp() >= $tournament->start_at) {
                $this->internal->push_message('info', 'Lo siento :( el torneo ya comenzó');
            }
            if ($inscription) {
                $this->internal->push_message('info', 'Te encuentras inscripto');
            } else {
                $this->internal->push_message('success', 'Te tendremos en cuenta :)');
                $let_me_know = true;
            }
        }

        $options = array(
            'page_name' => 'tournament view',
            'page_title' => $tournament->title,
            'tournament' => $tournament,
            'user_teams' => $this->user_model->get_teams(get_user()->id, $tournament->game_id, true, true),
            'inscription' => $inscription,
            'let_me_know' => $let_me_know
        );
        $this->twig->cache_display(strtolower(__CLASS__) . '/view_team_tournament', $options);
    }
    
    public function view_match($match_id){        
        $this->load->model('tournament_model');
        $this->load->model('user_model');
        
        $match = $this->tournament_model->get_match($match_id);
        
        if (!$match){
            show_404();
        }        
                
        $tournament = $this->tournament_model->get_tournament($match->tournament_id);
        
        if ($match->player !== 1){
            $match->player1 = $this->user_model->get_by_id($match->player1_id);
        }
        else{
            $match->player1 = get_user();
        }
        
        if ($match->player !== 2){
            $match->player2 = $this->user_model->get_by_id($match->player2_id);
        }
        else{
            $match->player2 = get_user();
        }
        
        $match->player1->game_account = $this->user_model->get_game_account($match->player1->id, $tournament->game_id);
        $match->player2->game_account = $this->user_model->get_game_account($match->player2->id, $tournament->game_id);
          
        $params = array(
            'page_name' => 'match-view',
            'page_title' => 'Match',
            'tournament' => $tournament,
            'match' => $match,
        );

        $params['active_tournaments'] = $this->tournament_model->get_actives();
        $params['coming_tournaments'] = $this->tournament_model->get_coming();



        if (is_logged()){ 
            $user =  $this->user_model->get_by_id(get_user()->id);
            $this->load->model('images_model');
            $params['userimg'] = '/uploads/' . $this->images_model->get_filename($user->image); 

            $params['username'] = strtoupper($user->username);
        }     
        
        $this->twig->cache_display(strtolower(__CLASS__) . '/match', $params);
    }

    public function ajax_leave_tournament(){ 
        require_ajax_request();
                
        $this->load->model('tournament_model');
        $this->load->model('user_model');
        $this->load->model('team_model');

        $tid = $this->input->post('tid');
        $the_id = $this->input->post('tuid');
        $type = $this->input->post('tipo');



        if ($this->tournament_model->get_tournament($tid)){
            if (isset($tid) && isset($the_id)) {
                if (ctype_digit($tid) && ctype_digit($the_id)) {
                    if ($type === 'equipo'){                        
                        if ($this->team_model->is_captain($the_id, get_user()->id)) { //Soy el capitan del equipo a remover del torneo
                            $this->tournament_model->remove_inscription($the_id, $type, $tid);
                            $this->response->json(array(
                               'mode' => 'reload'
                            ));
                        }
                    }
                    else if ($type === 'user'){
                        if ($the_id === get_user()->id) { //Soy el que quiere ser removido de un torneo
                            $this->tournament_model->remove_inscription($the_id, $type, $tid);
                            $this->response->json(array(
                               'mode' => 'reload'
                            ));
                        }
                    }
                    else {
                         $this->response->messages('error', array(
                             'Datos erroneos'
                         ));                         
                    }
                }
            }
        } 
        else {
            $this->response->messages('error', array(
                'Error al procesar la informacion del torneo'
            )); 
        }
    }
    
    public function ajax_send_match_result($match_id){
        require_ajax_request();
                
        $this->load->model('tournament_model');
        $this->load->model('user_model');
        
        if (get_user()){
            $match = $this->tournament_model->get_match($match_id);
            $this->form_validation->set_rules('sub_matches', 'Encuentros', 'xss_clean|required|is_array');
        }
        
        if (!get_user()){
            $this->response->json(array(
               'cause' => 'no user',
               'mode' => 'reload'
            ));              
        }
        else if (!$match){
            $this->response->json(array(
               'cause' => 'no match',
               'mode' => 'reload'
            ));   
        }
        else if(!$this->form_validation->run()){
            $this->response->messages('error', form_errors());
        }
        else if(!is_null($match->problem) && is_admin()){
            $sub_matches = $this->input->post('sub_matches');
            $p1_wins = 0;
            $p2_wins = 0;
            
            foreach ($sub_matches as $winner){
                if ($winner == '1'){
                    $p1_wins++;
                }
                else if ($winner == '2'){
                    $p2_wins++;
                }
                else{       
                    $this->response->json(array(
                       'mode' => 'reload'
                    ));
                    die();
                }
            }
            
            $match->data->sub_matches = $sub_matches;
            $match->data->p1_wins = $p1_wins;
            $match->data->p2_wins = $p2_wins; 
            
            if ($p1_wins !== $match->max && $p2_wins !== $match->max){                    
                $this->response->json(array(
                   'mode' => 'reload'
                ));
                die();
            }       
            else if ($p1_wins > $p2_wins){
                $winner = 1;
            }
            else if ($p1_wins < $p2_wins){
                $winner = 2;
            }
            else{      
                $this->response->json(array(
                   'mode' => 'reload'
                ));
                die();                    
            }
            
            $this->tournament_model->update_match($match->id, array(
                'winner' => $winner,
                'closed_at' => get_date()->getTimestamp(),
                'data' => json_encode($match->data),
                'problem' => null
            ));
        }
        else if(!$match->player){
            $this->response->json(array(
               'cause' => 'no player',
               'mode' => 'reload'
            ));             
        }
        else if(!$match->remaining){            
            $this->response->json(array(
               'cause' => 'no remainig',
               'mode' => 'reload'
            ));
        }
        else if (get_date()->getTimestamp() > $match->ends_at){
            $this->response->messages('error', array('El tiempo de votar ya terminó'));
        }
        else{
            $sub_matches = $this->input->post('sub_matches');
            $p1_wins = 0;
            $p2_wins = 0;
            
            foreach ($sub_matches as $winner){
                if ($winner == '1'){
                    $p1_wins++;
                }
                else if ($winner == '2'){
                    $p2_wins++;
                }
                else{       
                    $this->response->json(array(
                       'mode' => 'reload'
                    ));
                    die();
                }
            }
            
            if ($p1_wins !== $match->max && $p2_wins !== $match->max){                    
                $this->response->json(array(
                   'mode' => 'reload'
                ));
                die();
            }       
            else if ($p1_wins > $p2_wins){
                $winner = 1;
            }
            else if ($p1_wins < $p2_wins){
                $winner = 2;
            }
            else{      
                $this->response->json(array(
                   'mode' => 'reload'
                ));
                die();                    
            }
                        
            if ($match->player === 1){
                $match->data->player1->sub_matches = $sub_matches;                
                $match->data->player1->p1_wins = $p1_wins;
                $match->data->player1->p2_wins = $p2_wins;
                $match->data->player1->winner = $winner;
            }
            else if ($match->player === 2){
                $match->data->player2->sub_matches = $sub_matches;                
                $match->data->player2->p1_wins = $p1_wins;
                $match->data->player2->p2_wins = $p2_wins;                
                $match->data->player2->winner = $winner;
            }
                                    
            $players_ready = !is_null($match->data->player1->winner) && !is_null($match->data->player2->winner); 
            
            $equals = $match->data->player1->p1_wins === $match->data->player2->p1_wins && 
                      $match->data->player1->p2_wins === $match->data->player2->p2_wins;
            
            if ($players_ready){
                if ($equals){
                    if ($winner == 1){
                        $match->data->sub_matches = $match->data->player1->sub_matches;
                        $match->data->p1_wins = $match->data->player1->p1_wins;
                        $match->data->p2_wins = $match->data->player1->p2_wins;                        
                    }
                    else if ($winner == 2){
                        $match->data->sub_matches = $match->data->player2->sub_matches;
                        $match->data->p1_wins = $match->data->player2->p1_wins;
                        $match->data->p2_wins = $match->data->player2->p2_wins;                        
                    }
                    
                    $this->tournament_model->update_match($match->id, array(
                        'winner' => $winner,
                        'closed_at' => get_date()->getTimestamp()
                    ));
                }
                else{
                    $this->tournament_model->update_match($match->id, array(
                        'problem' => TOURNAMENT_PROBLEM_MATCH
                    ));
                }
            }           
            
            $this->tournament_model->update_match_data($match->id, $match->data);
            
            $this->response->json(array(
               'mode' => 'reload'
            )); 
        }
    }
    
}