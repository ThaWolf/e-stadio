<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Search extends CI_Controller {

    public function index() {
        $params = array(
            'page_name' => 'search'
        );
        
       $this->load->model('search_model');

        $_POST['key'] = $this->input->get('key');

        $this->form_validation->set_rules('key', 'Busqueda', 'xss_clean|required|max_length[50]');
      

        if ($this->input->get('key')){ 
            if (!$this->form_validation->run()){
                //Error                             
            }
            else {
                //Hacer la busqueda y devolver los resultados
                $query = $this->search_model->search($this->input->get('key'));
                $params['result'] = $query;
            }
        }
        $this->load->model('user_model');

        if (is_logged()){ 
            $user =  $this->user_model->get_by_id(get_user()->id);
            $this->load->model('images_model');
            $params['userimg'] = '/uploads/' . $this->images_model->get_filename($user->image); 

            $params['username'] = strtoupper($user->username);
        }
        
        $this->load->model('tournament_model');

        $params['active_tournaments'] = $this->tournament_model->get_actives();
        $params['coming_tournaments'] = $this->tournament_model->get_coming();        
        $this->twig->cache_display(strtolower(__CLASS__) . '/index', $params);
    }
    
}
