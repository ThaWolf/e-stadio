<?php

class Game_model extends CI_Model {
    public function get_all($perfil = false, $torneo = false){ 
        if (!$perfil || $torneo){ 
            //return $this->db->get('game')->result();
            $sql = "SELECT * FROM game WHERE id NOT IN (SELECT DISTINCT g.parent_id FROM game g)";
            if (!$torneo){
                $sql .=  " AND max_team_size > 1";
            }
            $query = $this->db->query($sql);
            return $query->result();      
        }
        else {
            $this->db->where('parent_id', 0); 
            //$this->db->where('max_team_size >', 1);
            return $this->db->get('game')->result();
        }
    }
    public function get_by_id($id){
        $this->db->from('game');
        $this->db->where('id', $id);
        $query = $this->db->get();
        if ($query->num_rows() > 0){
            return $query->first_row();
        }
        else{
            return false;
        }
    }
}