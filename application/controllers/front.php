<?php


if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Front extends CI_Controller {

    public function index() {
        $params = array(
            'page_name' => 'home'
        );
        
        $this->load->model('tournament_model');
        $this->load->model('sponsor_model');
        $this->load->model('user_model');
        $this->load->model('team_model');
        $this->load->model('images_model');

        if (is_logged()){ 
            $user =  $this->user_model->get_by_id(get_user()->id);
            $params['userimg'] = '/uploads/' . $this->images_model->get_filename($user->image); 

            $params['username'] = strtoupper($user->username);
        }

        $params['active_tournaments'] = $this->tournament_model->get_actives();
        $params['coming_tournaments'] = $this->tournament_model->get_coming();
        $params['sponsors'] = $this->sponsor_model->get_all();

        $last_winners = $this->tournament_model->get_last_winners();

        foreach ($last_winners as $key => $last_winner) {
            
            $last_winners[$key]->image = $this->images_model->get_filename($last_winner->image);

          ///////  


            
           // var_dump($last_winner); die();

        	if (intval($last_winner->team_require) === 0) {
        		$last_winners[$key]->first_place = $this->user_model->get_by_id($last_winner->first_id);
        		$last_winners[$key]->second_place = $this->user_model->get_by_id($last_winner->second_id);
        		$last_winners[$key]->third_place = $this->user_model->get_by_id($last_winner->third_id);


                $last_winners[$key]->user_image = $this->images_model->get_filename(intval($last_winners[$key]->first_place->image));

        	} 
        	else {

        		$last_winners[$key]->first_place = $this->team_model->get_by_id($last_winner->first_id);
        		$last_winners[$key]->second_place = $this->team_model->get_by_id($last_winner->second_id);
        		$last_winners[$key]->third_place = $this->team_model->get_by_id($last_winner->third_id);        		

                $last_winners[$key]->user_image = $this->images_model->get_filename(intval($last_winners[$key]->first_place->image));

        	}
        }

        $params['winners'] = ($last_winners);
        

        $this->twig->cache_display(strtolower(__CLASS__) . '/index', $params);
    }
    
    public function ajax_upload_image(){       

        $this->load->library('upload', image_upload_config());
        $this->load->model('images_model');
        
        if (!$this->upload->do_upload(IMAGE_UPLOAD_USERFILE)) {                                    
            $this->response->messages('error', array(
               $this->upload->display_errors('', '')
            ));
        } else {
            $data = $this->upload->data();
            
            $file_md5 = md5_file($data['full_path']);
            
            $image_id = $this->images_model->get_id($file_md5);
            
            if ($image_id){
                unlink($data['full_path']);
                $file_name = get_image($image_id);
            }
            else{
                $file_name = get_date()->getTimestamp() . '_' . $data['file_name']/* . $data['file_ext']*/;
                rename($data['full_path'], FCPATH . IMAGE_UPLOAD_PATH . $file_name);
                
                $image_id = $this->images_model->insert_image(array(
                    'file_name' => $file_name,
                    'md5' => $file_md5
                ));
                
                $file_name = '/' . IMAGE_UPLOAD_PATH . $file_name;
            }          
                        
            if ($image_id) { 
                $this->response->json(array(
                    'mode' => 'image_uploaded',
                    'image_id' => $image_id,
                    'path' => $file_name
                ));
            } else {                 
                $this->response->messages('error', array(
                   'Algo salió mal mientras se intentaba guardar la imagen' 
                ));
            }
        }
        @unlink($_FILES[IMAGE_UPLOAD_USERFILE]);
    }

}
