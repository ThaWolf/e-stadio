<?php

class Tournament_model extends CI_Model {

    public function get_last_winners() {
        $this->db->select('winner.*, tournament.team_require, tournament.name, tournament.game_id, tournament.image, tournament.image as user_image');
        $this->db->from('winner');
        $this->db->join('tournament', 'tournament.id = winner.tournament_id');
        $this->db->order_by('date', 'DESC');
        $this->db->limit(4);

        return $this->db->get()->result();
    }

    public function get_tournament($id){
        $this->db->select('tournament.*, game.name as game_name, game.min_team_size as game_team_size');
        $this->db->from('tournament');
        $this->db->join('game', 'tournament.game_id = game.id');
        $this->db->where('tournament.id', $id);
        $query = $this->db->get();
        if ($query->num_rows() > 0){
            $tournament = $query->first_row();
            $tournament->brackets = json_decode($tournament->brackets);
            $tournament->settings = json_decode($tournament->settings);            
            $tournament->time_to_confirm = get_date()->getTimestamp() >= $tournament->start_at - TIME_TO_CONFIRM;
            return $tournament;
        }
        else{
            return false;
        }
    }

    public function team_has_min($team_id){
        $this->db->select('team.id, game.min_team_size');
        $this->db->from('team');
        $this->db->join('membership', 'membership.team_id = team.id');
        $this->db->join('game', 'game.id = team.game_id');
        $this->db->where('team.id', $team_id);
        $this->db->having('COUNT(membership.user_id) >= game.min_team_size');
        $this->db->group_by('team.id');
        return $this->db->get()->num_rows() > 0;        
    }

    public function delete_tournament($id){
        $this->db->where('tournament_id', $id);
        $this->db->delete('team_inscription');
        $this->db->where('tournament_id', $id);
        $this->db->delete('user_inscription');
        $this->db->where('tournament_id', $id);
        $this->db->delete('match');
        $this->db->where('id', $id);
        $this->db->delete('tournament');
    }
    
    public function create_tournament($tournament){
        $this->db->insert('tournament', $tournament);
        return $this->db->insert_id();
    }
    
    public function get_all($limit = false, $offset = false, $where = array()){
        $this->db->select('tournament.*, game.name as game_name');
        $this->db->from('tournament');
        $this->db->join('game', 'tournament.game_id = game.id');
        foreach($where as $key => $value){
            $this->db->where($key, $value);
        }
        if ($limit && $offset){
            $this->db->limit($limit);
            $this->db->offset($offset);
        }
        return $this->db->get()->result();
    }
    
    public function count_all($where = array()){
        $this->db->select('COUNT(tournament.id) as count'); 
        $this->db->from('tournament');  
        $this->db->join('game', 'tournament.game_id = game.id');   
        foreach($where as $key => $value){
            $this->db->where($key, $value);
        }
        return $this->db->get()->first_row()->count;  
    }
    
    public function get_actives($limit = false, $offset = false, $where = array()){
        $this->db->select('tournament.*, game.name as game_name');
        $this->db->from('tournament');
        $this->db->join('game', 'tournament.game_id = game.id');
        $this->db->where('tournament.start_at <=', get_date()->getTimestamp());
        $this->db->where('tournament.ends_at IS NULL');
        foreach($where as $key => $value){
            $this->db->where($key, $value);
        }
        if ($limit && $offset){
            $this->db->limit($limit);
            $this->db->offset($offset);
        }
        $this->db->order_by('tournament.start_at', 'DESC');
        return $this->db->get()->result();
    }

    public function count_actives($where = array()){
        $this->db->select('COUNT(tournament.id) as count');
        $this->db->from('tournament');
        $this->db->join('game', 'tournament.game_id = game.id');
        $this->db->where('start_at <=', get_date()->getTimestamp());
        $this->db->where('ends_at IS NULL');  
        foreach($where as $key => $value){
            $this->db->where($key, $value);
        }
        return $this->db->get()->first_row()->count;
    }
    
    public function get_coming($limit = false, $offset = false, $where = array()){
        $this->db->select('tournament.*, game.name as game_name');
        $this->db->from('tournament');
        $this->db->join('game', 'tournament.game_id = game.id');
        $this->db->where('tournament.start_at >', get_date()->getTimestamp());
        $this->db->where('tournament.ends_at IS NULL'); 
        foreach($where as $key => $value){
            $this->db->where($key, $value);
        }
        if ($limit && $offset){
            $this->db->limit($limit);
            $this->db->offset($offset);
        }
        $this->db->order_by('tournament.start_at', 'DESC');
        return $this->db->get()->result();
    }
    
    public function count_coming($where = array()){
        $this->db->select('COUNT(tournament.id) as count');
        $this->db->from('tournament');
        $this->db->join('game', 'tournament.game_id = game.id');
        $this->db->where('start_at >', get_date()->getTimestamp());
        $this->db->where('ends_at IS NULL');
        foreach($where as $key => $value){
            $this->db->where($key, $value);
        }
        return $this->db->get()->first_row()->count;
    }

    public function get_finished($limit = false, $offset = false, $where = array()){
        $this->db->select('tournament.*, game.name as game_name');
        $this->db->from('tournament');
        $this->db->join('game', 'tournament.game_id = game.id');
        $this->db->where('tournament.ends_at IS NOT NULL');
        foreach($where as $key => $value){
            $this->db->where($key, $value);
        }
        if ($limit && $offset){
            $this->db->limit($limit);
            $this->db->offset($offset);
        }
        $this->db->order_by('tournament.start_at', 'DESC');
        return $this->db->get()->result();
    }

    public function count_finished($where = array()){
        $this->db->select('COUNT(tournament.id) as count');
        $this->db->from('tournament');
        $this->db->join('game', 'tournament.game_id = game.id');
        $this->db->where('ends_at IS NOT NULL'); 
        foreach($where as $key => $value){
            $this->db->where($key, $value);
        }
        return $this->db->get()->first_row()->count;
    }

    public function get_team_id_from_tournament_game_id($game_id){
        //Ver si es juego hijo. Si es hijo busco por el parent id
    /*    $this->db->select('parent_id');
        $this->db->from('game');
        $this->db->where('id', $game_id);
        $padre = $this->db->get()->first_row()->parent_id;
        if ($padre !== 0){ //Tiene padre
            $game_id = $padre;
        }*/
        $this->db->select('id');
        $this->db->from('team');
        $this->db->where('game_id', $game_id);
        return $this->db->get()->first_row()->id;
    }
    
    public function get_inscripted_teams($id){
        $this->db->select('t.id, t.name as show_name, ti.confirmed_at');
        $this->db->from('team_inscription ti');
        $this->db->join('team t', 't.id = ti.team_id');
        $this->db->where('tournament_id', $id);
        return $this->db->get()->result();
    }

    public function get_inscripted_users($id, $game_id){
        $this->db->select('user.id , game_account.username as show_name, user_inscription.confirmed_at');
        $this->db->from('user_inscription');
        $this->db->join('user', 'user.id = user_inscription.user_id');
        $this->db->join('game_account', 'game_account.user_id = user.id');
        $this->db->where('game_account.game_id', $game_id);
        $this->db->where('tournament_id', $id);
        return $this->db->get()->result();
    }
    
    public function create_match($match){        
        if (!isset($match['data'])){
            // CREO LA DATA
            $match['data'] = new stdClass();
            $match['data']->player1 = new stdClass();
            $match['data']->player2 = new stdClass();
            $match['data']->sub_matches = new stdClass();
            $match['data']->p1_wins = null;
            $match['data']->p2_wins = null;
            $match['data']->player1->winner = null;
            $match['data']->player1->p1_wins = null;
            $match['data']->player1->p2_wins = null;
            $match['data']->player1->sub_matches = new stdClass();
            $match['data']->player2->winner = null;
            $match['data']->player2->p1_wins = null;
            $match['data']->player2->p2_wins = null;
            $match['data']->player2->sub_matches = new stdClass();
            $match['data'] = json_encode($match['data']);
        }
        $this->db->insert('match', $match); 
        return $this->db->insert_id();
    }
    
    public function update_tournament($tournament_id, $tournament){
        $this->db->where('tournament.id', $tournament_id);
        $this->db->update('tournament', $tournament);
    }
    
    public function update_tournaments_brackets($id, $brackets){
        $this->db->set('brackets', json_encode($brackets));
        $this->db->where('id', $id);
        $this->db->update('tournament');
    }

    public function update_tournaments_settings($id, $settings){
        $this->db->set('settings', json_encode($settings));
        $this->db->where('id', $id);
        $this->db->update('tournament');
    }

    public function get_user_inscription($tournament_id, $user_id){
        $this->db->from('user_inscription');
        $this->db->where('tournament_id', $tournament_id);
        $this->db->where('user_id', $user_id);
        $query = $this->db->get();
        if ($query->num_rows() > 0){
            return $query->first_row();
        }
        else{
            return false;
        }
    }

    public function inscribe_user($inscription){
        $this->db->insert('user_inscription', $inscription);
        return $this->db->insert_id();
    }

    public function confirm_user($tournament_id, $user_id){
        $this->db->where('tournament_id', $tournament_id);
        $this->db->where('user_id', $user_id);
        $this->db->update('user_inscription', array(
            'confirmed_at' => get_date()->getTimestamp()
        ));
    }

    public function get_team_inscription($tournament_id, $user_id){
        $this->db->select('team_inscription.*, team.name AS team_name');
        $this->db->from('team_inscription');
        $this->db->join('team', 'team.id = team_inscription.team_id');
        $this->db->join('membership', 'team.id = membership.team_id');
        $this->db->where('tournament_id', $tournament_id);
        $this->db->where('membership.user_id', $user_id);
        $query = $this->db->get();
        if ($query->num_rows() > 0){
            return $query->first_row();
        }
        else{
            return false;
        }
    }

    public function inscribe_team($inscription){
        $this->db->insert('team_inscription', $inscription);
        return $this->db->insert_id();
    }

    public function confirm_team($tournament_id, $team_id){
        $this->db->where('tournament_id', $tournament_id);
        $this->db->where('user_id', $team_id);
        $this->db->update('team_inscription', array(
            'confirmed_at' => get_date()->getTimestamp()
        ));
    }

    public function get_tournaments_to_generate_brackets(){
        $this->db->from('tournament');
        $this->db->where('tournament.brackets IS NULL');
        $this->db->where('tournament.start_at <=', get_date()->getTimestamp());
        $results = $this->db->get()->result();
        foreach($results as $result){
            $result->brackets = ($result->brackets)?json_decode($result->brackets):array();
            $result->settings = ($result->settings)?json_decode($result->settings):array();
        }
        return $results;
    }

    public function get_tournaments_to_update_brackets(){
        $this->db->from('tournament');
        $this->db->where('tournament.brackets IS NOT NULL');
        $this->db->where('tournament.ends_at IS NULL');
        $this->db->where('tournament.problem IS NULL');
        $results = $this->db->get()->result();
        foreach($results as $result){
            $result->brackets = json_decode($result->brackets);
            $result->settings = json_decode($result->settings);
        }
        return $results;
    }
    
    public function get_users($tournament_id){
        return $this->db->query('
            SELECT user.id, game_account.username AS name 
            FROM (user) 
            JOIN user_inscription ON user_inscription.user_id = user.id
            JOIN tournament ON tournament.id = user_inscription.tournament_id 
            JOIN game_account ON game_account.user_id = user.id 
            WHERE game_account.game_id = tournament.game_id AND tournament.id = ? 
            GROUP BY user.id
        ', array($tournament_id))->result();
    }
    
    public function get_teams($tournament_id){
        $this->db->select('team.id, team.name');
        $this->db->from('team');
        $this->db->join('team_inscription', 'team_inscription.user_id = team.id');
        $this->db->join('tournament', 'tournament.id = team_inscription.tournament_id');
        $this->db->where('tournament.id', $tournament_id);
        $this->db->group_by('team.id');
        return $this->db->get()->result();
    }
    
    public function get_match($match_id){
        $this->db->from('match');
        $this->db->where('match.id', $match_id);
        $query = $this->db->get();
        if ($query->num_rows() > 0){
            $match = $query->first_row();
            $match->data = json_decode($match->data);
            
            if (get_user() && $match->player1_id == get_user()->id){
                $match->player = 1;
                $match->remaining = is_null($match->data->player1->winner);
            }
            else if(get_user() && $match->player2_id == get_user()->id){
                $match->player = 2;
                $match->remaining = is_null($match->data->player2->winner);
            }
            else{
                $match->player = false;
            }
            
            $match->max = intval(ceil($match->best_of / 2));
            
            return $match;
        }
        else{
            return false;
        }
    }
    
    public function update_match($match_id, $match){
        $this->db->where('match.id', $match_id);
        $this->db->update('match', $match);
    }
    
    public function update_match_data($match_id, $data){        
        $this->update_match($match_id, array(
            'data' => json_encode($data)
        ));
    }

    public function remove_inscription($id, $type, $tid){ //team or user
        if ($type === 'equipo'){
            $this->db->where('team_id', $id);
            $this->db->where('tournament_id', $tid);
            $this->db->delete('team_inscription'); 
        } else {
            $this->db->where('user_id', $id);
            $this->db->where('tournament_id', $tid);
            $this->db->delete('user_inscription'); 
        }      
    }
    
    public function delete_unconfirmed(){
        $time_to_compare = get_date()->getTimestamp() + DELETE_UNCONFIRMED;
        $this->db->query('
            DELETE user_inscription.* 
            FROM user_inscription
            INNER JOIN tournament ON tournament.id = user_inscription.tournament_id
            WHERE (user_inscription.confirmed_at IS NULL) AND (tournament.start_at <= '.$time_to_compare.');
            DELETE team_inscription.* 
            FROM team_inscription
            INNER JOIN tournament ON tournament.id = team_inscription.tournament_id
            WHERE (team_inscription.confirmed_at IS NULL) AND (tournament.start_at <= '.$time_to_compare.');
        ');
    }

}