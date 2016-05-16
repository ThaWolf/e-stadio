<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Contact extends CI_Controller {

    public function index() {
        $params = array(
            'page_name' => 'contact'
        );
        
        $this->load->model('contact_model');

        $this->form_validation->set_rules('name', 'Nombre', 'xss_clean|required|max_length[50]');
        $this->form_validation->set_rules('email', 'Email', 'xss_clean|required|max_length[100]|valid_email');
        $this->form_validation->set_rules('msg', 'Mensaje', 'xss_clean|required|max_length[255]');


        $nombre = $this->input->post('name');
        $email = $this->input->post('email');
        $msg = $this->input->post('msg');

        $params['return_msg'] = array();

        if ($this->input->post('contacto')){ 
            if (!$this->form_validation->run()){
                if (!$nombre || count($nombre) > 50 || $nombre == ''){
                    $params['return_type'] = 'danger';
                    array_push($params['return_msg'], 'Ingrese un nombre con un máximo de 50 caracteres');
                }
                if (!$msg || count($msg) > 255 || $msg == ''){
                    $params['return_type'] = 'danger';
                    array_push($params['return_msg'], 'Ingrese un mensaje con un máximo de 255 caracteres');
                }     

                if ($msg && count($msg)<50 && $msg !== '' && $nombre && count($nombre)<50 && $nombre !== '' && $email && count($email)<101 && $email == '' )       {

                } else {
                    $params['return_type'] = 'danger';
                    array_push($params['return_msg'], 'Ingrese un correo válido');                
                }
            }
            else {

                $this->contact_model->send(array(
                    'name' => $nombre,
                    'email' => $email,
                    'msg' => $msg));
                $params['return_type'] = 'success';
                array_push($params['return_msg'], 'Se envio el mensaje con exito');
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
