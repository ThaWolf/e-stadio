<?php

class Images_model extends CI_Model {
    public function insert_image($image){
        $this->db->insert('images', $image);
        return $this->db->insert_id();
    }
    public function get_filename($image_id){
        $this->db->from('images');
        $this->db->where('id', $image_id);
        $query = $this->db->get();
        if ($query->num_rows() > 0){
            return $query->first_row()->file_name;
        }
        else{
            return false;
        }
    }
    public function get_id($md5){
        $this->db->from('images');
        $this->db->where('md5', $md5);
        $query = $this->db->get();
        if ($query->num_rows() > 0){
            return $query->first_row()->id;
        }
        else{
            return false;
        }
    }
}