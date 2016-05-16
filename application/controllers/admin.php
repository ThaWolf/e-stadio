<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Admin extends CI_Controller {
    
    public function backup(){
        require_logged_in(false, array(ROLE_ADMIN));
        
        $this->load->dbutil();
        $this->load->helper('download');
        
        $filename = 'estadio-' . get_date()->format('d-m-Y-H-i') . '.sql';
        
        $prefs = array(
            //'tables'      => array('table1', 'table2'),  // Array of tables to backup.
            //'ignore'      => array(),           // List of tables to omit from the backup
            'format'      => 'txt',             // gzip, zip, txt
            'filename'    => $filename,    // File name - NEEDED ONLY WITH ZIP FILES
            'add_drop'    => TRUE,              // Whether to add DROP TABLE statements to backup file
            'add_insert'  => TRUE,              // Whether to add INSERT data to backup file
            'newline'     => PHP_EOL            // Newline character used in backup file
        );

        $backup = $this->dbutil->backup();
        
        
        force_download($filename, $backup);
    }
    
    public function tournaments(){
        require_logged_in(false, array(ROLE_ADMIN));
        
        $this->load->model('tournament_model');
        $this->load->model('game_model');
        
        $page = ($this->input->get('pag'))?$this->input->get('pag'):1;
        $limit = ($this->input->get('limit'))?$this->input->get('limit'):LIMIT;
        
        if ($limit > 100){
            $limit = 100;
        }
        
        $status = $this->input->get('status');
        
        $where = array();
        
        if ($this->input->get('game')){
            $where['game.id'] = $this->input->get('game');
        }
        if ($this->input->get('mode')){
            $where['tournament.team_require'] = (($this->input->get('mode') == 'team')?1:0);
        }
        if ($this->input->get('problem')){
            if ($this->input->get('problem') == 'yes'){
                $where['tournament.problem IS NOT NULL'] = null;
                $where['tournament.match_problem IS NOT NULL'] = null;
            }
            else if ($this->input->get('problem') == 'no'){
                $where['tournament.problem IS NULL'] = null;  
                $where['tournament.match_problem IS NULL'] = null;              
            }
        }
        
        switch ($status){
            case 'actives':
                $tournaments = $this->tournament_model->get_actives($limit, $limit * ($page - 1), $where);
                $count = $this->tournament_model->count_actives($where);
                break;
            case 'coming':
                $tournaments = $this->tournament_model->get_coming($limit, $limit * ($page - 1), $where);
                $count = $this->tournament_model->count_coming($where);
                break;
            case 'finished':
                $tournaments = $this->tournament_model->get_finished($limit, $limit * ($page - 1), $where);
                $count = $this->tournament_model->count_finished($where);
                break;
            default:                
                $tournaments = $this->tournament_model->get_all($limit, $limit * ($page - 1), $where);
                $count = $this->tournament_model->count_all($where);
                break;
        }
        
        $params = array(
            'page_name' => 'admin tournaments',
            'page_title' => 'Torneos',
            'box_title' => 'NUEVO TORNEO',
            'tournaments' => $tournaments,
            'games' => $this->game_model->get_all(false, true),
            'current_page' => $page,
            'total_pages' => ceil($count / $limit)
        );
    

        $params['active_tournaments'] = $this->tournament_model->get_actives();
        $params['coming_tournaments'] = $this->tournament_model->get_coming();        
        $this->twig->cache_display(strtolower(__CLASS__) . '/tournaments', $params);
    }
    
    public function new_tournament(){
        require_logged_in(false, array(ROLE_ADMIN));
        
        $this->load->model('game_model');
        $this->load->model('tournament_model');
        
        $params = array(
            'page_name' => 'admin new tournament',
            'page_title' => 'Nuevo Torneo',
            'games' => $this->game_model->get_all(false, true),
            'box_title' => 'NUEVO TORNEO'
        );


        $params['active_tournaments'] = $this->tournament_model->get_actives();
        $params['coming_tournaments'] = $this->tournament_model->get_coming();
        $this->twig->cache_display(strtolower(__CLASS__) . '/tournament', $params);
    }
    
    public function edit_tournament($tournament_id){
        require_logged_in(false, array(ROLE_ADMIN));
        
        $this->load->model('tournament_model');
        $this->load->model('game_model');
        
        $tournament = $this->tournament_model->get_tournament($tournament_id);
        
        if (!$tournament){
            show_404();
        }
        
        $params = array(
            'page_name' => 'admin edit tournament',
            'page_title' => 'Editar Torneo',
            'box_title' => 'EDITAR "'.mb_strtoupper($tournament->name).'"',
            'games' => $this->game_model->get_all(false, true),
            'tournament' => $tournament
        );
        
        $params['active_tournaments'] = $this->tournament_model->get_actives();
        $params['coming_tournaments'] = $this->tournament_model->get_coming();        
        $this->twig->cache_display(strtolower(__CLASS__) . '/tournament', $params);
    }
    
    public function ajax_new_tournament(){        
        require_ajax_request();
        require_logged_in(false, array(ROLE_ADMIN));
            
        $this->load->model('tournament_model');
        $this->load->model('game_model');    
        $this->load->model('images_model');               
        
        $this->form_validation->set_rules('image_id', 'Imagen', 'xss_clean|required|is_natural_no_zero');
        $this->form_validation->set_rules('name', 'Nombre', 'xss_clean|required|max_length[50]');
        $this->form_validation->set_rules('game', 'Juego', 'xss_clean|required|is_natural_no_zero');
        $this->form_validation->set_rules('date', 'Fecha', 'xss_clean|required|valid_date');
        $this->form_validation->set_rules('hour', 'Hora', 'xss_clean|required|greater_or_equal_than[0]|less_or_equal_than[23]|is_natural');
        $this->form_validation->set_rules('minute', 'Minuto', 'xss_clean|required|in_array[00|30]');
        $this->form_validation->set_rules('mode', 'Modo', 'xss_clean|required|in_array[alone|team]'); 
        $this->form_validation->set_rules('vacancies', 'Capacidad', 'xss_clean|required|in_array[8|16|32|64]');
        
        $rounds = $this->input->post('rounds');
        
        foreach($rounds as $number => $round){
            $prefix = 'round_'.$number.'_';
            $_POST[$prefix.'days'] = $round['days'];
            $_POST[$prefix.'hours'] = $round['hours'];
            $_POST[$prefix.'best_of'] = $round['best_of'];
            $this->form_validation->set_rules($prefix.'days', 'Días - Ronda '+$number, 'xss_clean|required|is_natural');
            $this->form_validation->set_rules($prefix.'hours', 'Horas - Ronda '+$number, 'xss_clean|required|greater_or_equal_than[0]|less_or_equal_than[84600]|is_natural');
            $this->form_validation->set_rules($prefix.'best_of', 'Mejor de # - Ronda '+$number, 'xss_clean|required|in_array[1|3|5|7]');
        }
        
        $juego = $this->game_model->get_by_id($this->input->post('game'));

        if (!$this->form_validation->run()){
            $this->response->messages('error', form_errors());
        }
        else if(!$this->game_model->get_by_id($this->input->post('game'))){
            $this->response->messages('error', array(
               'Seleccione un juego de la lista' 
            ));
        }
        else if ($juego->max_team_size === '1' && $this->input->post('mode') === 'team') { 
                $this->response->messages('error', array(
                   'Este juego no admite sistema de equipos' 
                ));
        }        
        else if(!$this->images_model->get_filename($this->input->post('image_id'))){
            $this->response->messages('error', array(
               'La imagen no existe' 
            ));
        }
        else{
            $start_at = DateTime::createFromFormat('!d/m/Y', $this->input->post('date'))->getTimestamp() +
                        (intval($this->input->post('hour')) * 3600) +
                        (intval($this->input->post('minute')) * 60);
            
            
            $std_rounds = new stdClass();
            
            foreach($rounds as $number => $round){
                $key = KEY_DATES_PREFIX.$number;
                $std_rounds->$key = new stdClass();
                $std_rounds->$key->expiration_time = (intval($round['days']) * 24 * 3600) + intval($round['hours']);
                $std_rounds->$key->best_of = intval($round['best_of']);               
            }
                        
            $tournament_id = $this->tournament_model->create_tournament(array(
                'game_id' => $this->input->post('game'),
                'name' => $this->input->post('name'),
                'description' => $this->input->post('description'),
                'image' => $this->input->post('image_id'),
                'start_at' => $start_at,
                'team_require' => $this->input->post('mode') === 'team',
                'created_at' => get_date()->getTimestamp(),
                'vacancies' => $this->input->post('vacancies'),
                'settings' => json_encode(array('rounds' => $std_rounds))
            ));
            
            $this->response->json(array(
               'mode' => 'redirect',
               'page' => '/admin/torneos/editar/'.$tournament_id
            ));
        }        
    }
    
    public function ajax_edit_tournament($tournament_id){  
        require_ajax_request();
        require_logged_in(false, array(ROLE_ADMIN));
            
        $this->load->model('tournament_model');
        $this->load->model('game_model');    
        $this->load->model('images_model');               
        
        $tournament = $this->tournament_model->get_tournament($tournament_id);      
                
        $this->form_validation->set_rules('image_id', 'Imagen', 'xss_clean|required|is_natural_no_zero');
        $this->form_validation->set_rules('name', 'Nombre', 'xss_clean|required|max_length[50]');
        
        if (!$tournament){            
            $this->response->json(array(
               'mode' => 'reload'
            ));
        }
        else if (!$this->form_validation->run()){
            $this->response->messages('error', form_errors());
        }
        else if(!$this->images_model->get_filename($this->input->post('image_id'))){
            $this->response->messages('error', array(
               'La imagen no existe' 
            ));
        }
        else{            
            $this->tournament_model->update_tournament($tournament->id, array(
                'name' => $this->input->post('name'),
                'description' => $this->input->post('description'),
                'image' => $this->input->post('image_id')
            ));
            
            $this->response->json(array(
               'mode' => 'reload'
            ));
        } 
    }
    
    public function delete_tournament($id){
        require_logged_in(false, array(ROLE_ADMIN));
        $this->load->model('tournament_model');
        $this->tournament_model->delete_tournament($id);
        redirect('/admin');
    }
}