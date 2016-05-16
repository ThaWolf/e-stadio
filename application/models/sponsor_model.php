<?php

class Sponsor_model extends CI_Model {

   public function get_all() {
    $this->db->from('sponsor');
    return $this->db->get()->result_array();
   }
}