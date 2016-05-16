<?php

class Team_model extends CI_Model {

    public function get_by_id($team_id, $options = array()) {
        $this->db->from('team');
        $this->db->where('team.id', $team_id);
        if (in_array('wgame',$options)){
            $this->db->select('team.*, game.name AS game_name');
            $this->db->join('game', 'team.game_id = game.id');            
        }
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $team = $query->first_row();
            if (is_logged()){ 
            $team->iamcaptain = $team->user_id === get_user()->id;  
            
            if (in_array('wmembers', $options)){
                $team->members = $this->team_model->get_members($team->id);
                if ($team->iamcaptain){
                    $team->iammember = true;
                }
                else{
                    $team->iammember = false;
                    foreach ($team->members as $member){
                        if ($member->id === get_user()->id){
                            $team->iammember = true;
                            break;
                        }
                    }
                }

                if ($team->iammember && in_array('wrequests', $options)) {
                    $team->requests = $this->get_team_requests($team->id);
                }
            }
            }
            return $team;
        } else {
            return false;
        }
    }

    private function get_game_id($team_id){
        $this->db->select('game_id');
        $this->db->from('team');
        $this->db->where('id', $team_id);
        return $this->db->get()->row()->game_id;
    }

    public function size_above_one($game_id){
        $this->db->from('game');
        $this->db->where('max_team_size > ', 1);
        $query = $this->db->get();
        return $query->num_rows() > 0;
    }

    public function update_team($team_id, $fields) {
        if ($this->size_above_one($this->get_game_id($team_id))){ 
            $this->db->where('id', $team_id);
            $this->db->update('team', $fields);
        }
    }

    public function get_team_request_by_id($team_request_id, $user_id = false, $with_data = false) {
        
        $this->db->from('team_request');

        if ($with_data) {
            $this->db->select('team_request.id AS id, team.id AS team_id, team.name AS team_name, game.id AS game_id, game.name AS game_name');
            $this->db->join('team', 'team.id = team_request.team_id');
            $this->db->join('game', 'game.id = team.game_id');
        }

        $this->db->where('team_request.id', $team_request_id);

        if ($user_id) {
            $this->db->where('(team_request.user_id = ' . $user_id . ' OR EXISTS(
                    SELECT user_id
                    FROM membership
                    WHERE (membership.user_id = ' . $user_id . ') AND (membership.team_id = team_request.team_id)
                )
            )');
        }
        
        
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return $query->first_row();
        } else {
            return false;
        }
    }

    public function get_team_request($team_id, $user_id){
        $this->db->select('team_request.id');
        $this->db->where('team_request.team_id', $team_id);
        $this->db->where('team_request.user_id', $user_id);
        $this->db->from('team_request');
        $query = $this->db->get();
        if ($query->num_rows() > 0){
            return $query->first_row();
        }
        else {
            return false;
        }
    }
    
    public function get_team_requests($team_id){
        $this->db->select('team_request.id, user.username, team_request.user_id , team_request.date');
        $this->db->where('team_request.team_id', $team_id);
        $this->db->from('team_request');
        $this->db->join('user', 'team_request.user_id = user.id');
        return $this->db->get()->result();
    }
    
    public function team_is_complete($team_id) {
        $this->db->select('team.id, game.max_team_size');
        $this->db->from('team');
        $this->db->join('membership', 'membership.team_id = team.id');
        $this->db->join('game', 'game.id = team.game_id');
        $this->db->where('team.id', $team_id);
        $this->db->having('COUNT(membership.user_id) < game.max_team_size');
        $this->db->group_by('team.id');
        return $this->db->get()->num_rows() == 0;
    }

    private function get_members_count($team_id){
        $this->db->select('COUNT(team.id) as cantidad, game.max_team_size');
        $this->db->from('team');
        $this->db->join('membership', 'membership.team_id = team.id');
        $this->db->join('game', 'game.id = team.game_id');
        $this->db->where('team.id', $team_id);    
        return $this->db->get()->first_row();    
    }

    public function team_in_tournament($team_id){
        $this->db->select('ti.team_id');
        $this->db->from('team_inscription ti');
        $this->db->where('ti.team_id', $team_id);
        return $this->db->get()->num_rows() > 0;
    }

 /*   public function team_is_complete2($team_id) {
        $this->db->select('team.id, game.max_team_size');
        $this->db->from('team');
        $this->db->join('membership', 'membership.team_id = team.id');
        $this->db->join('game', 'game.id = team.game_id');
        $this->db->where('team.id', $team_id);
        $this->db->having('COUNT(membership.user_id) < game.max_team_size + 2');
        $this->db->group_by('team.id');
        return $this->db->get()->num_rows() == 0;
    }*/

    public function is_captain($team_id, $user_id){
        $this->db->from('team');
        $this->db->where('id', $team_id);
        $this->db->where('user_id', $user_id);
        return $this->db->get()->num_rows() > 0;
    }

    public function is_sup1($team_id, $user_id){
        $this->db->from('team');
        $this->db->where('id', $team_id);
        $this->db->where('sup1_id', $user_id);
        return $this->db->get()->num_rows() > 0;
    }    

    public function is_sup2($team_id, $user_id){
        $this->db->from('team');
        $this->db->where('id', $team_id);
        $this->db->where('sup2_id', $user_id);
        return $this->db->get()->num_rows() > 0;
    }    

    public function get_sup1($team_id) {
        $this->db->from('team');
        $this->db->where('id', $team_id);
        return $this->db->get()->row()->sup1_id;
    }

    public function get_sup2($team_id) {
        $this->db->from('team');
        $this->db->where('id', $team_id);
        return $this->db->get()->row()->sup2_id;
    }

    private function update_sup1($team_id, $user_id) {
        $this->db->where('id', $team_id);
        $this->db->update('team', array('sup1_id' => $user_id));
    }

    private function update_sup2($team_id, $user_id) {
        $this->db->where('id', $team_id);
        $this->db->update('team', array('sup2_id' => $user_id));
    }

    public function set_sup1($team_id, $user_id){
        if ($this->is_captain($team_id, get_user()->id)){
            if (!$this->is_captain($team_id, $user_id)){
                if($this->is_sup1($team_id, $user_id)) {
                        //
                } else {
                    if($this->is_sup2($team_id, $user_id)){
                        $tmp = $this->get_sup1($team_id);
                        $this->update_sup2($team_id, $tmp);
                    }
                }
                $this->update_sup1($team_id, $user_id);
            }
        }
    }

    public function set_sup2($team_id, $user_id){
        if ($this->is_captain($team_id, get_user()->id)){
            if (!$this->is_captain($team_id, $user_id)){
                if($this->is_sup2($team_id, $user_id)) {
                        //
                } else {
                    if($this->is_sup1($team_id, $user_id)){
                        $tmp = $this->get_sup2($team_id);
                        $this->update_sup1($team_id, $tmp);
                    }
                }
                $this->update_sup2($team_id, $user_id);
            }
        }
    }

    private function nullify_sup($team_id, $sup) {
        $this->db->where('id', $team_id);
        switch ($sup) {
            case 1:
                $this->db->update('team', array('sup1_id' => NULL));
                break;
            
            case 2:
                $this->db->update('team', array('sup2_id' => NULL));
                break;
        }        
    }

    public function is_member($team_id, $user_id){
        $this->db->from('membership');
        $this->db->where('team_id', $team_id);
        $this->db->where('user_id', $user_id);
        return $this->db->get()->num_rows() > 0;
    }
    
    public function remove_team_request($team_request_id){
        $this->db->where('id', $team_request_id);
        $this->db->delete('team_request');
    }   


    public function delete_team($team_id, $user_id) {
        $team = $this->get_by_id($team_id);
        if ($team->user_id === $user_id) {


            $this->db->where('user_id', $user_id);
            $this->db->where('id', $team_id);
            $this->db->from('team');
            $this->db->delete();

            $this->remove_all_membership($team_id);
            $this->remove_all_invitations($team_id);
        }
    }
    
    public function create_first_membership($team_id, $user_id){
            $this->db->insert('membership', array(
                'user_id' => $user_id,
                'team_id' => $team_id
            ));
            return $this->db->insert_id();

    }

    public function create_membership($team_id, $user_id){
        if ($this->size_above_one($this->get_game_id($team_id))){ 
            if (!$this->team_is_complete($team_id)) { 
                $query = $this->get_members_count($team_id); 
                $cantidad = $query->cantidad;
                $total = $query->max_team_size;


                if (($total - $cantidad) <= 2){
                     if (($total - $cantidad) === 2 ){
                        if (!$this->get_sup1($team_id)){
                            $this->update_sup1($team_id, $user_id);  //Somos 5 y no hay sup1, entonces lo es
                        }
                     } else {
                         if (($total - $cantidad) === 1){
                            if (!$this->get_sup2($team_id)){
                                $this->update_sup2($team_id, $user_id);  //Somos 6 y no hay sup2, entonces lo es
                            } else {
                                $this->update_sup1($team_id, $user_id);                            
                            }
                         }
                     }
                }

           
                $this->db->insert('membership', array(
                    'user_id' => $user_id,
                    'team_id' => $team_id
                ));
                return $this->db->insert_id();
            }
        }
    }

    public function remove_all_invitations($team_id) {
        $this->db->where('team_id', $team_id);
        $this->db->from('team_inscription');
        $this->db->delete();        
    }

    public function remove_all_membership($team_id){
        $this->db->where('team_id', $team_id);
        $this->db->from('membership');
        $this->db->delete();
    }    
    
    public function remove_membership($team_id, $user_id){
        if (!$this->is_captain($team_id, $user_id)) { 
            $this->db->where('team_id', $team_id);
            $this->db->where('user_id', $user_id);
            $this->db->from('membership');
            $this->db->delete();
            if($this->get_sup2($team_id)){  //Eran 7, pongo a sup2 en null y sup1 es el que era sup2                                
                if ($this->is_sup1($team_id, $user_id)) { //Si el que quiero borrar es el sup1, pongo al 2 en 1
                    $tmp = $this->get_sup2($team_id);
                    $this->set_sup1($team_id, $tmp);
                }
                $this->nullify_sup($team_id, 2);
                
            } else {    //Eran 6 o menos, solo hago null al sup1
                $this->nullify_sup($team_id, 1);
            }
        }
    }
 
    public function assign_captain($team_id, $user_id){
        if ($this->size_above_one($this->get_game_id($team_id))){ 
            if ($this->is_captain($team_id, get_user()->id)) { //Si yo soy capitan
                if (!$this->is_captain($team_id, $user_id)) {  //Si el target no es capitan (osea que no sea yo)
                    if ($this->is_sup1($team_id, $user_id)) {  //Si es sup1 switcheo capitan con sup1                    
                        $this->update_sup1($team_id, get_user()->id);
                    }
                    else {
                        if ($this->is_sup2($team_id, $user_id)) {  //Sino, si es sup2 lo mismo
                        $this->update_sup2($team_id, get_user()->id);                 
                        } 
                    }
                    $this->db->where('id', $team_id);
                    $this->db->update('team', array('user_id' => $user_id));
                }
            }
        }
    }

    public function name_exist($team_name, $game_id){
        $this->db->from('team');
        $this->db->where('name', $team_name);
        $this->db->where('game_id', $game_id);
        return $this->db->get()->num_rows() > 0;
    }

    public function get_max($game_id){
        $this->db->select('game.max_team_size');
        $this->db->from('game');
        $this->db->where('id', $game_id);
        $query = $this->db->get();
        $max = $query->first_row();
        return intval($max->max_team_size);
    }
    
    public function create_team($team_name, $game_id, $user_id){
        if ($this->size_above_one($game_id)) { 
            $now = new \DateTime();

            $max = $this->get_max($game_id);
            $this->db->insert('team', array(
                'name' => $team_name,
                'game_id' => $game_id,
                'user_id' => $user_id,
                'created_at' => $now->getTimestamp(),
                'points' => 0,
                'image' => 2,
                'limit' => $max
            ));
            return $this->db->insert_id();    
        }    
    }

    public function get_members($team_id){
        $this->db->select('user.id, user.username');
        $this->db->from('membership');
        $this->db->join('user', 'membership.user_id = user.id');
        $this->db->where('membership.team_id', $team_id);
        return $this->db->get()->result();
    }
    
    public function team_request_exists($team_id, $user_id){
        $this->db->from('team_request');
        $this->db->where('team_id', $team_id);
        $this->db->where('user_id', $user_id);
        return $this->db->get()->num_rows() > 0;
    }
    
    public function send_team_request($team_id, $user_id){
        $now = new \DateTime();
        $this->db->insert('team_request', array(
           'team_id' => $team_id,
            'user_id' => $user_id,
            'date' => $now->getTimestamp()
        ));
    }
    public function find_my_team_id($user_id, $game_id){
        $this->db->select('id');
        $this->db->from('team');
        $this->db->where('game_id', $game_id);
        $this->db->where('user_id', $user_id);
        return $this->db->get()->first_row()->id;        
    }
}
