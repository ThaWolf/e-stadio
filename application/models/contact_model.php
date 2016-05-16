<?php

class Contact_model extends CI_Model {
    public function send($msg) {
        $this->db->insert('message', $msg);
    }

}
